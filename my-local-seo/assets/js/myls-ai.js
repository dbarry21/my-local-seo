/* My Local SEO – AI Tab JS
 * Path: assets/js/myls-ai.js
 * Handles:
 *  - Populate multi-select list by post type (bootstrap from JSON, localized data, or AJAX)
 *  - Client-side filter
 *  - Select All / Clear
 *  - Prompt reset
 *  - Generate (title/description) with dry-run + overwrite flags
 */
(function($){
  'use strict';

  // Robust bootstrap: never silently bail if localization is missing.
  var hasMYLS = (typeof window.MYLS_AI === 'object' && window.MYLS_AI !== null);
  var ajaxurl = hasMYLS && window.MYLS_AI.ajaxurl
    ? window.MYLS_AI.ajaxurl
    : (typeof window.ajaxurl === 'string' ? window.ajaxurl : '');

  if (!ajaxurl) {
    console.warn('[MYLS AI] ajaxurl not set; check wp_localize_script or admin context.');
  }

  // Prefer hidden input; fallback to localized nonce.
  var nonce = $('#myls_ai_nonce').val() || (hasMYLS && window.MYLS_AI.nonce) || '';

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

  // Cache posts per postType: { pt: [{id,title}] }
  const cache = Object.create(null);

  function log(msg){
    try {
      const now = new Date().toLocaleTimeString();
      $results.append(`[${now}] ${msg}\n`);
      $results.scrollTop($results[0].scrollHeight);
    } catch(e){}
  }

  function optionRow(id, title){
    return $('<option>').val(String(id)).text(title || '(no title)');
  }

  function fillPosts(pt, items){
    $posts.empty();
    (items || []).forEach(it => $posts.append(optionRow(it.id, it.title)));
  }

  function fromBootstrapJSON(){
    const el = document.getElementById('myls_ai_bootstrap_posts');
    if (!el) return null;
    try {
      const data = JSON.parse(el.textContent || el.innerText || '{}');
      return (data && data.posts && Array.isArray(data.posts)) ? data : null;
    } catch (e) { return null; }
  }

  function preloadFromLocalized(pt){
    if (!hasMYLS || !window.MYLS_AI.posts_by_type) return null;
    const arr = window.MYLS_AI.posts_by_type[pt];
    if (Array.isArray(arr) && arr.length) return arr;
    return null;
  }

  function fetchPosts(pt){
    if (!ajaxurl) {
      log('AJAX URL missing; cannot load posts.');
      return $.Deferred().reject();
    }

    if (cache[pt]) {
      fillPosts(pt, cache[pt]);
      return $.Deferred().resolve(cache[pt]);
    }

    const loc = preloadFromLocalized(pt);
    if (loc) {
      cache[pt] = loc;
      fillPosts(pt, loc);
      return $.Deferred().resolve(loc);
    }

    log(`Loading posts for post type "${pt}"...`);
    return $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: { action: 'myls_ai_posts_by_type', pt: pt, nonce: nonce }
    }).done(function(res){
      if (res && res.ok && Array.isArray(res.posts)) {
        cache[pt] = res.posts;
        fillPosts(pt, res.posts);
        log(`Loaded ${res.posts.length} post(s).`);
      } else {
        log('Failed to load posts (invalid response).');
      }
    }).fail(function(){
      log('Failed to load posts (AJAX error). Check ajaxurl/nonce).');
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

  // ---------- Init population ----------
  (function initFirstPaint(){
    const boot = fromBootstrapJSON();
    const initialPT = $pt.val();
    if (boot && boot.post_type === initialPT) {
      cache[initialPT] = boot.posts || [];
      fillPosts(initialPT, cache[initialPT]);
      return;
    }
    fetchPosts(initialPT);
  })();

  // ---------- Events ----------
  $pt.on('change', function(){
    fetchPosts($(this).val());
    $filter.val('');
  });

  $filter.on('input', function(){
    const needle = $(this).val().toLowerCase();
    $posts.find('option').each(function(){
      const show = $(this).text().toLowerCase().indexOf(needle) !== -1;
      $(this).toggle(show);
    });
  });

  $selectAll.on('click', function(){
    $posts.find('option:visible').prop('selected', true);
  });
  $clear.on('click', function(){
    $posts.find('option').prop('selected', false);
  });

  $resetTitle.on('click', function(){
    $titlePrompt.val($titlePrompt[0].defaultValue || $titlePrompt.val());
  });
  $resetDesc.on('click', function(){
    $descPrompt.val($descPrompt[0].defaultValue || $descPrompt.val());
  });

  function setBusy(busy){
    $btnGenTitle.prop('disabled', busy);
    $btnGenDesc.prop('disabled', busy);
    $pt.prop('disabled', busy);
    $posts.prop('disabled', busy);
  }

  function runGenerate(kind){
    const ids = collectSelectedIds();
    if (!ids.length) {
      log('Select at least one post.');
      return;
    }
    if (!ajaxurl) {
      log('AJAX URL missing; cannot generate.');
      return;
    }

    const pt        = $pt.val();
    const overwrite = $overwrite.is(':checked') ? 1 : 0;
    const dryrun    = $dryrun.is(':checked') ? 1 : 0;
    const prompt    = (kind === 'title') ? ($titlePrompt.val() || '') : ($descPrompt.val() || '');

    setBusy(true);
    log(`Starting ${kind === 'title' ? 'Title' : 'Description'} generation for ${ids.length} post(s)...`);

    $.ajax({
      url: ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'myls_ai_generate_meta',
        kind: kind,
        pt: pt,
        ids: ids,
        prompt: prompt,
        overwrite: overwrite,
        dryrun: dryrun,
        nonce: nonce
      }
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

  $btnGenTitle.on('click', function(){ runGenerate('title'); });
  $btnGenDesc.on('click',  function(){ runGenerate('desc'); });

})(jQuery);
