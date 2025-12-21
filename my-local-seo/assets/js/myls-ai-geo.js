(function($){
  'use strict';

  if (!window.MYLS_AI_GEO) return;
  const CFG = window.MYLS_AI_GEO;

  $(function(){

    let stopping = false;

    function log(line){
      const $res = $('#myls_ai_geo_results');
      if (!$res.length) return;
      $res.text(String(line) + "\n" + $res.text());
    }

    function setCount(n){
      const $count = $('#myls_ai_geo_count');
      if ($count.length) $count.text(String(n));
    }

    function setBusy(on){
      $('#myls_ai_geo_analyze').prop('disabled', !!on);
      $('#myls_ai_geo_convert').prop('disabled', !!on);
      $('#myls_ai_geo_duplicate').prop('disabled', !!on);
      $('#myls_ai_geo_stop').prop('disabled', !on);

      $('#myls_ai_geo_pt').prop('disabled', !!on);
      $('#myls_ai_geo_posts').prop('disabled', !!on);
      $('#myls_ai_geo_select_all').prop('disabled', !!on);
      $('#myls_ai_geo_clear').prop('disabled', !!on);

      const $status = $('#myls_ai_geo_status');
      if ($status.length) $status.text(on ? 'Working…' : '');

      // spinner
      const $sp = $('#myls_ai_geo_spinner');
      if ($sp.length) $sp.css('display', on ? 'inline-flex' : 'none');
    }

    function loadPosts(){
      const $pt    = $('#myls_ai_geo_pt');
      const $posts = $('#myls_ai_geo_posts');

      if (!$pt.length) { log('ERROR: #myls_ai_geo_pt not found in DOM.'); return; }
      if (!$posts.length) { log('ERROR: #myls_ai_geo_posts not found in DOM.'); return; }

      $posts.empty();

      $.post(CFG.ajaxurl, {
        action: CFG.action_get_posts || 'myls_ai_geo_get_posts_v1',
        nonce:  CFG.nonce,
        post_type: $pt.val()
      }).done(function(resp){
        console.log('[geo_get_posts_v1] resp:', resp);

        if (!resp || !resp.success) {
          log('Failed to load posts (resp.success not true).');
          return;
        }

        const posts = (resp.data && Array.isArray(resp.data.posts)) ? resp.data.posts : null;
        if (!posts) {
          log('Failed to load posts: resp.data.posts missing or not array.');
          console.log('[geo_get_posts_v1] resp.data:', resp && resp.data);
          return;
        }

        // Build options as a single HTML string (fast + less chance of other scripts interfering mid-loop)
        let html = '';
        for (let i=0; i<posts.length; i++){
          const p = posts[i] || {};
          const id = parseInt(p.id || 0, 10);
          const title = (p.title || '(no title)').replace(/</g,'&lt;').replace(/>/g,'&gt;');
          if (!id) continue;
          html += '<option value="' + String(id) + '">' + title + ' (ID ' + String(id) + ')</option>';
        }

        $posts.html(html);

        // Force selection box repaint on some admin themes
        $posts.trigger('change');

        const countNow = $posts.find('option').length;
        console.log('[geo_get_posts_v1] options count NOW:', countNow);
        log('Loaded ' + countNow + ' posts for ' + $pt.val() + '.');

        // Detect if some other code is clearing it AFTER we fill it
        setTimeout(function(){
          const countLater = $posts.find('option').length;
          console.log('[geo_get_posts_v1] options count 500ms LATER:', countLater);
          if (countLater !== countNow) {
            log('WARNING: Posts list changed after load (now ' + countLater + '). Another script may be clearing it.');
          }
        }, 500);

      }).fail(function(xhr){
        log('AJAX error: get_posts (' + (xhr && xhr.status) + ')');
      });
    }

    function selectAll(){
      $('#myls_ai_geo_posts option').prop('selected', true);
      $('#myls_ai_geo_posts').trigger('change');
    }

    function clearSelection(){
      $('#myls_ai_geo_posts option').prop('selected', false);
      $('#myls_ai_geo_posts').trigger('change');
    }

    function analyzeOne(){
      const ids = ($('#myls_ai_geo_posts').val() || []).map(v => parseInt(v, 10)).filter(Boolean);

      if (!ids.length) { log('Select one post to analyze.'); return; }
      if (ids.length > 1) { log('Select only ONE post for preview right now.'); return; }

      const id = ids[0];

      stopping = false;
      setBusy(true);
      setCount(0);

      $('#myls_ai_geo_preview').empty();
      $('#myls_ai_geo_output').text('');
      $('#myls_ai_geo_diff').empty();

      const template = ($('textarea[name="myls_ai_geo_prompt_template"]').val() || '');
      const tokens = parseInt(($('input[name="myls_ai_geo_tokens"]').val() || '1200'), 10);
      const temperature = parseFloat(($('input[name="myls_ai_geo_temperature"]').val() || '0.4'));

      log('Analyze: sending request for ID ' + id + '…');

      $.post(CFG.ajaxurl, {
        action: CFG.action_analyze || 'myls_ai_geo_analyze_v1',
        nonce: CFG.nonce,
        post_id: id,
        template: template,
        tokens: tokens,
        temperature: temperature,
        mode: $('input[name="myls_ai_geo_mode"]:checked').val() || 'partial',
        with_anchors: $('#myls_ai_geo_with_anchors').is(':checked') ? 1 : 0
      })
      .done(function(resp){
        console.log('[geo_analyze_v1] resp:', resp);

        if (!resp || typeof resp !== 'object') {
          log('ID ' + id + ' — Unexpected server response.');
          return;
        }

        if (resp.success) {
          const d = resp.data || {};
          const html = (d.html || '').trim();
          const raw  = (d.raw || '').trim();
          const diff = (d.diff_html || '').trim();
          const title = d.title || '(no title)';

          log('ID ' + id + ' — Analyzed "' + title + '" (preview ready).');

          if (html) {
            $('#myls_ai_geo_preview').html(html);
            $('#myls_ai_geo_output').text(html);
            if (diff) $('#myls_ai_geo_diff').html(diff);
          } else if (raw) {
            $('#myls_ai_geo_preview').html('<em>Cleaned HTML was empty (likely stripped tags). Showing RAW output below.</em>');
            $('#myls_ai_geo_output').text(raw);
            log('Note: cleaned HTML was empty; showing RAW output for debugging.');
          } else {
            $('#myls_ai_geo_preview').html('<em>No content returned.</em>');
            $('#myls_ai_geo_output').text('');
            log('ERROR: No html or raw returned.');
          }
        } else {
          const d = resp.data || {};
          const msg = d.message || 'server_error';
          log('ID ' + id + ' — ERROR: ' + msg);
        }
      })
      .fail(function(xhr){
        log('ID ' + id + ' — AJAX error: ' + (xhr && xhr.status));
      })
      .always(function(){
        setCount(1);
        setBusy(false);
      });
    }

    function convertQueue(){
      const ids = ($('#myls_ai_geo_posts').val() || []).map(v => parseInt(v, 10)).filter(Boolean);
      if (!ids.length) { log('Select at least one post to convert.'); return; }

      stopping = false;
      setCount(0);
      setBusy(true);

      const template = ($('textarea[name="myls_ai_geo_prompt_template"]').val() || '');
      const tokens = parseInt(($('input[name="myls_ai_geo_tokens"]').val() || '1200'), 10);
      const temperature = parseFloat(($('input[name="myls_ai_geo_temperature"]').val() || '0.4'));
      const mode = $('input[name="myls_ai_geo_mode"]:checked').val() || 'partial';
      const withAnchors = $('#myls_ai_geo_with_anchors').is(':checked') ? 1 : 0;

      let done = 0;

      (function next(){
        if (stopping || !ids.length) {
          setBusy(false);
          log('Done.');
          return;
        }

        const id = ids.shift();
        log('Convert: sending request for ID ' + id + '…');

        $.post(CFG.ajaxurl, {
          action: CFG.action_convert || 'myls_ai_geo_convert_v1',
          nonce: CFG.nonce,
          post_id: id,
          template: template,
          tokens: tokens,
          temperature: temperature,
          mode: mode,
          with_anchors: withAnchors
        })
        .done(function(resp){
          console.log('[geo_convert_v1] resp:', resp);

          if (!resp || typeof resp !== 'object') {
            log('ID ' + id + ' — Unexpected server response.');
            return;
          }

          if (resp.success) {
            const d = resp.data || {};
            const newId = d.new_post_id || 0;
            const srcTitle = d.source_title || '(no title)';
            const newTitle = d.new_post_title || '(no title)';
            const previewUrl = d.preview_url || '';
            const editUrl = d.edit_url || '';

            log('ID ' + id + ' — GEO Draft created: ' + newId + ' (“' + newTitle + '”) from “' + srcTitle + '”.');

            // Show the most recent conversion in the preview panels
            if (d.html) {
              $('#myls_ai_geo_preview').html(d.html);
              $('#myls_ai_geo_output').text(d.html);
            }
            if (d.diff_html) {
              $('#myls_ai_geo_diff').html(d.diff_html);
            }

            if (previewUrl || editUrl) {
              const links = [];
              if (previewUrl) links.push('<a href="' + String(previewUrl).replace(/"/g,'&quot;') + '" target="_blank" rel="noopener">Preview Draft</a>');
              if (editUrl) links.push('<a href="' + String(editUrl).replace(/"/g,'&quot;') + '" target="_blank" rel="noopener">Edit Draft</a>');
              $('#myls_ai_geo_status').html(links.join(' • '));
            }
          } else {
            const d = resp.data || {};
            const msg = d.message || 'server_error';
            log('ID ' + id + ' — ERROR: ' + msg);
          }
        })
        .fail(function(xhr){
          log('ID ' + id + ' — AJAX error: ' + (xhr && xhr.status));
        })
        .always(function(){
          done++;
          setCount(done);
          next();
        });
      })();
    }

    function duplicateQueue(){
      const ids = ($('#myls_ai_geo_posts').val() || []).map(v => parseInt(v, 10)).filter(Boolean);
      if (!ids.length) { log('Select at least one post to duplicate.'); return; }

      stopping = false;
      setCount(0);
      setBusy(true);

      let done = 0;

      (function next(){
        if (stopping || !ids.length) {
          setBusy(false);
          log('Done.');
          return;
        }

        const id = ids.shift();

        $.post(CFG.ajaxurl, {
          action:  CFG.action_duplicate || 'myls_ai_geo_duplicate_v1',
          nonce:   CFG.nonce,
          post_id: id
        })
        .done(function(resp){
          console.log('[geo_duplicate_v1] resp:', resp);

          if (!resp || typeof resp !== 'object') {
            log('ID ' + id + ' — Unexpected server response.');
            return;
          }

          if (resp.success) {
            const d  = resp.data || {};
            const src_title = d.source_title || '(no title)';
            const new_id = d.new_post_id || 0;
            const new_title = d.new_post_title || '(no title)';
            log('ID ' + id + ' — Duplicated "' + src_title + '" → Draft ID ' + new_id + ' (“' + new_title + '”).');
          } else {
            const d  = resp.data || {};
            const msg = d.message || 'server_error';
            log('ID ' + id + ' — ERROR: ' + msg);
          }
        })
        .fail(function(xhr){
          log('ID ' + id + ' — AJAX error: ' + (xhr && xhr.status));
        })
        .always(function(){
          done++;
          setCount(done);
          next();
        });

      })();
    }

    // Delegated events (robust in modular admin tabs)
    $(document)
      .on('change', '#myls_ai_geo_pt', function(){ loadPosts(); })
      .on('click',  '#myls_ai_geo_select_all', function(e){ e.preventDefault(); selectAll(); })
      .on('click',  '#myls_ai_geo_clear', function(e){ e.preventDefault(); clearSelection(); })
      .on('click',  '#myls_ai_geo_analyze', function(e){ e.preventDefault(); analyzeOne(); })
      .on('click',  '#myls_ai_geo_convert', function(e){ e.preventDefault(); convertQueue(); })
      .on('click',  '#myls_ai_geo_duplicate', function(e){ e.preventDefault(); duplicateQueue(); })
      .on('click',  '#myls_ai_geo_stop', function(e){
        e.preventDefault();
        stopping = true;
        setBusy(false);
        log('Stopped.');
      });

    // Init
    $('#myls_ai_geo_loaded_hint').text('JS loaded');
    console.log('[MYLS GEO] file loaded');

    const $pt = $('#myls_ai_geo_pt');
    if ($pt.length && CFG.defaultType && $pt.val() !== CFG.defaultType) $pt.val(CFG.defaultType);

    loadPosts();
  });

})(jQuery);
