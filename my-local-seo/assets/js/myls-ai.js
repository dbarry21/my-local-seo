/* My Local SEO – AI Tab JS
 * Path: assets/js/myls-ai.js
 *
 * Handles:
 *  - Meta Titles & Descriptions subtab:
 *      - Populate posts list by post type (bootstrap JSON / localized / AJAX)
 *      - Filter
 *      - Select All / Clear
 *      - Reset prompt
 *      - Generate titles/descriptions with dry-run + overwrite
 *
 *  - Excerpts subtab:
 *      - Populate posts list by post type (bootstrap JSON / localized / AJAX)
 *      - Filter
 *      - Select All / Clear
 *      - Reset + Save excerpt prompt template
 *      - Generate excerpts with dry-run + overwrite
 */
(function($){
  'use strict';

  /* -------------------------------------------------------------
   * Shared utilities
   * ------------------------------------------------------------- */

  function hasLocalized(){
    return (typeof window.MYLS_AI === 'object' && window.MYLS_AI !== null);
  }

  function getAjaxUrl(){
    if (hasLocalized() && window.MYLS_AI.ajaxurl) return window.MYLS_AI.ajaxurl;
    if (typeof window.ajaxurl === 'string') return window.ajaxurl;
    return '';
  }

  function getNonce($fallbackInput){
    // Prefer per-tab hidden nonce input if provided; fallback to localized nonce.
    var v = ($fallbackInput && $fallbackInput.length) ? ($fallbackInput.val() || '') : '';
    if (v) return v;
    if (hasLocalized() && window.MYLS_AI.nonce) return window.MYLS_AI.nonce;
    return '';
  }

  function optionRow(id, title){
    return $('<option>').val(String(id)).text(title || '(no title)');
  }

  function safeParseJSONFromEl(elId){
    var el = document.getElementById(elId);
    if (!el) return null;
    try {
      var txt = el.textContent || el.innerText || '';
      var data = JSON.parse(txt || '{}');
      return data || null;
    } catch(e){
      return null;
    }
  }

  function applyOptionFilter($select, query){
    var q = (query || '').toLowerCase().trim();
    $select.find('option').each(function(){
      var txt = (this.textContent || '').toLowerCase();
      // Use "hidden" attribute (works reliably for <option> filtering)
      this.hidden = !!(q && txt.indexOf(q) === -1);
    });
  }

  function selectAllVisibleOptions($select){
    $select.find('option').each(function(){
      if (!this.hidden) this.selected = true;
    });
    $select.trigger('change');
  }

  function clearAllOptions($select){
    $select.find('option').prop('selected', false);
    $select.trigger('change');
  }

  function ajaxPostJSON(ajaxurl, data){
    return $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: data
    });
  }

  /* -------------------------------------------------------------
   * Meta subtab
   * ------------------------------------------------------------- */
  function initMetaSubtab(){
    const $pt        = $('#myls_ai_pt');
    const $filter    = $('#myls_ai_filter');
    const $posts     = $('#myls_ai_posts');
    const $selectAll = $('#myls_ai_select_all');
    const $clear     = $('#myls_ai_clear');
    const $overwrite = $('#myls_ai_overwrite');
    const $dryrun    = $('#myls_ai_dryrun');

    const $titlePrompt = $('#myls_ai_title_prompt');
    const $descPrompt  = $('#myls_ai_desc_prompt');
    const $resetTitle  = $('#myls_ai_reset_title_prompt');
    const $resetDesc   = $('#myls_ai_reset_desc_prompt');

    const $btnGenTitle = $('#myls_ai_gen_title');
    const $btnGenDesc  = $('#myls_ai_gen_desc');
    const $results     = $('#myls_ai_results');

    // Not on meta subtab
    if (!$pt.length || !$posts.length || !$results.length) return;

    const ajaxurl = getAjaxUrl();
    const nonce   = getNonce($('#myls_ai_nonce'));

    if (!ajaxurl) console.warn('[MYLS AI] ajaxurl not set; check localization/admin context.');

    // Cache posts per postType: { pt: [{id,title}] }
    const cache = Object.create(null);

    function log(msg){
      try {
        const now = new Date().toLocaleTimeString();
        $results.append(`[${now}] ${msg}\n`);
        $results.scrollTop($results[0].scrollHeight);
      } catch(e){}
    }

    function fillPosts(items){
      $posts.empty();
      (items || []).forEach(it => $posts.append(optionRow(it.id, it.title)));
    }

    function preloadFromLocalized(pt){
      if (!hasLocalized() || !window.MYLS_AI.posts_by_type) return null;
      const arr = window.MYLS_AI.posts_by_type[pt];
      if (Array.isArray(arr) && arr.length) return arr;
      return null;
    }

    function fromBootstrapJSON(){
      const data = safeParseJSONFromEl('myls_ai_bootstrap_posts');
      if (!data || !Array.isArray(data.posts)) return null;
      return data;
    }

    function fetchPosts(pt){
      if (!ajaxurl) { log('AJAX URL missing; cannot load posts.'); return $.Deferred().reject(); }

      if (cache[pt]) {
        fillPosts(cache[pt]);
        return $.Deferred().resolve(cache[pt]);
      }

      const loc = preloadFromLocalized(pt);
      if (loc) {
        cache[pt] = loc;
        fillPosts(loc);
        return $.Deferred().resolve(loc);
      }

      log(`Loading posts for post type "${pt}"...`);

      return ajaxPostJSON(ajaxurl, { action: 'myls_ai_posts_by_type', pt: pt, nonce: nonce })
        .done(function(res){
          if (res && res.ok && Array.isArray(res.posts)) {
            cache[pt] = res.posts;
            fillPosts(res.posts);
            log(`Loaded ${res.posts.length} post(s).`);
          } else {
            log('Failed to load posts (invalid response).');
          }
        })
        .fail(function(xhr){
          log('Failed to load posts (AJAX error). Check ajaxurl/nonce.');
          try {
            if (xhr && xhr.responseText) log(String(xhr.responseText).slice(0, 300));
          } catch(e){}
        });
    }

    function collectSelectedIds(){
      const ids = [];
      $posts.find('option:selected').each(function(){
        const v = parseInt($(this).val(), 10);
        if (v) ids.push(v);
      });
      return ids;
    }

    function setBusy(busy){
      $btnGenTitle.prop('disabled', busy);
      $btnGenDesc.prop('disabled', busy);
      $pt.prop('disabled', busy);
      $posts.prop('disabled', busy);
    }

    function runGenerate(kind){
      const ids = collectSelectedIds();
      if (!ids.length) { log('Select at least one post.'); return; }
      if (!ajaxurl) { log('AJAX URL missing; cannot generate.'); return; }

      const pt        = $pt.val();
      const overwrite = $overwrite.is(':checked') ? 1 : 0;
      const dryrun    = $dryrun.is(':checked') ? 1 : 0;
      const prompt    = (kind === 'title') ? ($titlePrompt.val() || '') : ($descPrompt.val() || '');

      setBusy(true);
      log(`Starting ${kind === 'title' ? 'Title' : 'Description'} generation for ${ids.length} post(s)...`);

      ajaxPostJSON(ajaxurl, {
        action: 'myls_ai_generate_meta',
        kind: kind,
        pt: pt,
        ids: ids,
        prompt: prompt,
        overwrite: overwrite,
        dryrun: dryrun,
        nonce: nonce
      }).done(function(res){
        if (!res || !res.ok) {
          log('Generation failed or returned invalid response.');
          return;
        }
        (res.items || []).forEach(function(row){
          const id = row.id, title = row.post_title || '(no title)';
          if (row.error) {
            log(`#${id} ${title} — ERROR: ${row.error}`);
            return;
          }
          const savedTxt = row.saved ? 'SAVED' : (row.dryrun ? 'PREVIEW' : 'SKIPPED');
          log(`#${id} ${title} — ${savedTxt}\n  old: ${row.old || '(empty)'}\n  new: ${row.new || '(empty)'}\n`);
        });
        if (res.summary) log(res.summary);
      }).fail(function(){
        log('AJAX error while generating. Check ajaxurl/nonce.');
      }).always(function(){
        setBusy(false);
      });
    }

    // ---------- Init (first paint) ----------
    (function initFirstPaint(){
      const boot = fromBootstrapJSON();
      const initialPT = $pt.val();

      if (boot && boot.post_type === initialPT) {
        cache[initialPT] = boot.posts || [];
        fillPosts(cache[initialPT]);
        return;
      }
      fetchPosts(initialPT);
    })();

    // ---------- Events ----------
    $pt.on('change', function(){
      $filter.val('');
      fetchPosts($(this).val());
    });

    $filter.on('input', function(){
      applyOptionFilter($posts, $(this).val());
    });

    $selectAll.on('click', function(){ selectAllVisibleOptions($posts); });
    $clear.on('click', function(){ clearAllOptions($posts); });

    $resetTitle.on('click', function(){ $titlePrompt.val($titlePrompt[0].defaultValue || $titlePrompt.val()); });
    $resetDesc.on('click', function(){ $descPrompt.val($descPrompt[0].defaultValue  || $descPrompt.val()); });

    $btnGenTitle.on('click', function(){ runGenerate('title'); });
    $btnGenDesc.on('click',  function(){ runGenerate('desc'); });
  }

  /* -------------------------------------------------------------
   * Excerpts subtab
   * ------------------------------------------------------------- */
  function initExcerptsSubtab(){

    const $exPt       = $('#myls_ai_ex_pt');
    const $exFilter   = $('#myls_ai_ex_filter');
    const $exPosts    = $('#myls_ai_ex_posts');
    const $exSelectAll= $('#myls_ai_ex_select_all');
    const $exClear    = $('#myls_ai_ex_clear');

    const $exOverwrite= $('#myls_ai_ex_overwrite');
    const $exDryrun   = $('#myls_ai_ex_dryrun');
    const $exPrompt   = $('#myls_ai_ex_prompt');
    const $exReset    = $('#myls_ai_ex_reset_prompt');
    const $exSave     = $('#myls_ai_ex_save_prompt');
    const $exGen      = $('#myls_ai_ex_gen');

    const $exResults  = $('#myls_ai_ex_results');
    const $exNonceInp = $('#myls_ai_ex_nonce');

    // Not on Excerpts subtab
    if (!$exPt.length || !$exPosts.length || !$exResults.length) return;

    const ajaxurl = getAjaxUrl();
    const nonce   = getNonce($exNonceInp);

    function exLog(msg){
      // Always log to console as well (helps when the results box is collapsed/hidden by CSS).
      try { console.log('[MYLS Excerpts]', msg); } catch(e){}
      try {
        const now = new Date().toLocaleTimeString();
        $exResults.append(`[${now}] ${msg}\n`);
        $exResults.scrollTop($exResults[0].scrollHeight);
      } catch(e){}
    }

    function exFillPosts(items){
      $exPosts.empty();
      (items || []).forEach(function(row){
        $exPosts.append(optionRow(row.id, row.title));
      });
    }

    function exSelectedIds(){
      // Keep as integers for server-side comfort
      const ids = [];
      $exPosts.find('option:selected').each(function(){
        const v = parseInt($(this).val(), 10);
        if (v) ids.push(v);
      });
      return ids;
    }

    function exFetchPosts(pt){
      if (!ajaxurl) { $exResults.text(''); exLog('ERROR: ajaxurl missing.'); return; }
      if (!nonce)   { $exResults.text(''); exLog('ERROR: nonce missing.'); return; }

      $exResults.text('');
      exLog(`Loading posts for post type "${pt}"...`);

      ajaxPostJSON(ajaxurl, {
        action: 'myls_ai_posts_by_type',
        pt: pt,
        nonce: nonce
      }).done(function(res){
        if (!res || !res.ok) {
          exLog('Failed to load posts (invalid response).');
          if (res && res.error) exLog(String(res.error));
          return;
        }
        exFillPosts(res.posts || []);
        exLog('Loaded ' + ((res.posts || []).length) + ' posts.');
      }).fail(function(xhr){
        exLog('AJAX error loading posts.');
        try {
          if (xhr && xhr.responseText) exLog(String(xhr.responseText).slice(0, 300));
        } catch(e){}
      });
    }

    // ---------- Init (first paint) ----------
    (function initExFirstPaint(){
      const boot = safeParseJSONFromEl('myls_ai_ex_bootstrap_posts');
      if (boot && Array.isArray(boot.posts)) {
        exFillPosts(boot.posts);
        // Do NOT auto-fetch here; user dropdown change should also work now.
      }
    })();

    // ---------- Events ----------
    $exPt.on('change', function(){
      $exFilter.val('');
      exFetchPosts($exPt.val());
    });

    $exFilter.on('input', function(){
      applyOptionFilter($exPosts, $(this).val());
    });

    $exSelectAll.on('click', function(){ selectAllVisibleOptions($exPosts); });
    $exClear.on('click', function(){ clearAllOptions($exPosts); });

    $exReset.on('click', function(){
      const def = $(this).data('default') || '';
      $exPrompt.val(def);
    });

    $exSave.on('click', function(){
      if (!ajaxurl) { $exResults.text(''); exLog('ERROR: ajaxurl missing.'); return; }
      if (!nonce)   { $exResults.text(''); exLog('ERROR: nonce missing.'); return; }

      $exResults.text('');
      exLog('Saving prompt template...');

      ajaxPostJSON(ajaxurl, {
        action: 'myls_ai_excerpt_save_prompt_v1',
        nonce: nonce,
        prompt: ($exPrompt.val() || '')
      }).done(function(res){
        // wp_send_json_success => { success:true, data:{...} }
        if (!res || !res.success) {
          exLog('Save failed.');
          if (res && res.data && res.data.message) exLog(String(res.data.message));
          return;
        }
        exLog('Saved.');
      }).fail(function(){
        exLog('AJAX error saving prompt.');
      });
    });

    $exGen.on('click', function(e){
      e.preventDefault();
      if (!ajaxurl) { $exResults.text(''); exLog('ERROR: ajaxurl missing.'); return; }
      if (!nonce)   { $exResults.text(''); exLog('ERROR: nonce missing.'); return; }

      const ids = exSelectedIds();
      if (!ids.length) { $exResults.text(''); exLog('Select at least one post.'); return; }

      const overwrite = $exOverwrite.is(':checked') ? 1 : 0;
      const dryrun    = $exDryrun.is(':checked') ? 1 : 0;

      $exResults.text('');
      const oldTxt = $exGen.text();
      $exGen.prop('disabled', true).text('Processing...');
      exLog('Processing... generating excerpts for ' + ids.length + ' posts...');

      ajaxPostJSON(ajaxurl, {
        action: 'myls_ai_excerpt_generate_v1',
        nonce: nonce,
        post_ids: ids,
        overwrite: overwrite,
        dryrun: dryrun
      }).done(function(res){
        if (!res || !res.success) {
          exLog('Generation failed.');
          if (res && res.data && res.data.message) exLog(String(res.data.message));
          return;
        }

        const rows = (res.data && Array.isArray(res.data.results)) ? res.data.results : [];
        exLog('Done. Results: ' + rows.length + '. Dry-run: ' + (dryrun ? 'YES' : 'NO'));

        rows.forEach(function(r){
          if (r.skipped) {
            exLog('#' + r.id + ' SKIPPED — ' + (r.reason || ''));
            return;
          }
          if (!r.ok) {
            exLog('#' + r.id + ' ERROR — ' + (r.error || 'Unknown'));
            return;
          }
          const savedTxt = r.saved ? 'SAVED' : (r.dryrun ? 'PREVIEW' : 'OK');
          exLog('#' + r.id + ' ' + (r.title || '') + ' — ' + savedTxt);
          exLog('  ' + (r.excerpt || ''));
        });
      }).fail(function(xhr){
        exLog('AJAX error generating excerpts.');
        try {
          if (xhr && xhr.responseText) exLog(String(xhr.responseText).slice(0, 300));
        } catch(e){}
      }).always(function(){
        $exGen.prop('disabled', false).text(oldTxt);
      });
    });
  }

  /* -------------------------------------------------------------
   * Boot once DOM is ready (critical for subtabs)
   * ------------------------------------------------------------- */
  $(document).ready(function(){
    initMetaSubtab();
    initExcerptsSubtab();
  });

})(jQuery);
