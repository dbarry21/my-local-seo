/* My Local SEO – About the Area JS
 * Path: assets/js/myls-ai-about.js
 */
(function($){
  'use strict';
  if (!window.MYLS_AI_ABOUT) return;

  const A   = window.MYLS_AI_ABOUT;
  const $pt = $('#myls_ai_about_pt');
  const $posts   = $('#myls_ai_about_posts');
  const $skip    = $('#myls_ai_about_skip_filled');

  const $go      = $('#myls_ai_about_generate');
  const $stop    = $('#myls_ai_about_stop');
  const $status  = $('#myls_ai_about_status');
  const $results = $('#myls_ai_about_results');
  const $count   = $('#myls_ai_about_count');

  let stopFlag = false;

  function setStatus(txt){ $status.text(txt || ''); }
  function logOnce(txt){ $results.html(txt); }
  function incCount(){ $count.text(String( (parseInt($count.text()||'0',10))+1 )); }
  function resetCount(){ $count.text('0'); }

  function optionRow(id, title){
    return $('<option>').val(String(id)).text(title || '(no title)');
  }
  function fillPosts(items){
    $posts.empty(); (items||[]).forEach(it => $posts.append(optionRow(it.id, it.title)));
  }

  function loadPosts(pt){
  setStatus('Loading posts…');
  $posts.empty();
  return $.post(A.ajaxurl, {
    action: 'myls_ai_posts_by_type',
    pt: pt,
    nonce: A.nonce
  }).done(function(res){
    // Log full response to console for deep inspection
    try { console.log('[MYLS AI ABOUT] posts_by_type response:', res); } catch(e){}

    var ok = (res && (res.success === true || res.ok === true));
    var payload = res && (res.data || res);
    var list = ok && payload ? (payload.posts || (payload.data && payload.data.posts) || null) : null;

    if (Array.isArray(list)) {
      if (payload && typeof payload.nonce_ok !== 'undefined' && !payload.nonce_ok) {
        setStatus('Loaded ' + list.length + ' item(s) — ⚠️ nonce mismatch (still returned for diagnostics)');
      } else {
        setStatus('Loaded ' + list.length + ' item(s).');
      }
      fillPosts(list);
      return;
    }

    // Fallback: localized bootstrap (from tab-ai.php)
    if (window.MYLS_AI && window.MYLS_AI.posts_by_type && Array.isArray(window.MYLS_AI.posts_by_type[pt])) {
      fillPosts(window.MYLS_AI.posts_by_type[pt]);
      setStatus('Loaded (fallback) ' + window.MYLS_AI.posts_by_type[pt].length + ' item(s).');
      return;
    }

    // Show raw body so we immediately see what's wrong
    try { logOnce('Empty/invalid response:\n' + JSON.stringify(res, null, 2)); } catch(e){}
    setStatus('Could not load posts.');
  }).fail(function(xhr){
    // Fallback on failure
    if (window.MYLS_AI && window.MYLS_AI.posts_by_type && Array.isArray(window.MYLS_AI.posts_by_type[pt])) {
      fillPosts(window.MYLS_AI.posts_by_type[pt]);
      setStatus('Loaded (fallback) ' + window.MYLS_AI.posts_by_type[pt].length + ' item(s).');
      return;
    }
    try { logOnce('AJAX error:\n' + (xhr && xhr.responseText ? xhr.responseText : '(no body)')); } catch(e){}
    setStatus('Error loading posts.');
  });
}



  function selectedIds(){
    const ids = [];
    $posts.find('option:selected').each(function(){
      const v = parseInt($(this).val(),10);
      if (v) ids.push(v);
    });
    return ids;
  }

  // init
  loadPosts(A.defaultType || $pt.val());
  $pt.on('change', function(){ loadPosts($pt.val()); });

  $stop.on('click', function(e){ e.preventDefault(); stopFlag = true; setStatus('Stopping after current item…'); });

  $go.on('click', async function(e){
    e.preventDefault();
    stopFlag = false; resetCount();
    const ids = selectedIds();
    if (!ids.length) { setStatus('Select at least one post.'); return; }

    setStatus('Starting…');
    for (let i=0; i<ids.length; i++){
      if (stopFlag) { setStatus('Stopped.'); break; }
      const id = ids[i];
      logOnce('Processing ID ' + id + '…');

      try{
        const res = await $.post(A.ajaxurl, {
          action: 'myls_ai_about_generate',
          nonce:  A.nonce,
          post_id: id,
          skip_filled: $skip.is(':checked') ? '1' : '0'
        });

        if (!res || !res.success){
          logOnce('ID ' + id + ': ERROR\n' + (res && res.data ? JSON.stringify(res.data, null, 2) : 'No response'));
        } else {
          const d = res.data || {};
          let out = 'ID ' + id + ' — ' + (d.title || '') + '\n';
          out += d.skipped ? ('Skipped: ' + (d.reason || 'already filled') + '\n') : (d.saved ? 'Saved new content.\n' : 'Generated (not saved?).\n');
          if (d.city_state) out += 'Area: ' + d.city_state + '\n';
          if (d.debug) out += '\nDEBUG:\n' + d.debug + '\n';
          logOnce(out);
          incCount();
        }
      } catch (err){
        logOnce('ID ' + id + ': ERROR ' + err);
      }
    }
    if (!stopFlag) setStatus('Done.');
  });

})(jQuery);
