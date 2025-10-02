(function($){
  'use strict';
  if (!window.MYLS_AI_ABOUT) return;
  const CFG = window.MYLS_AI_ABOUT;

  const $pt     = $('#myls_ai_about_pt');
  const $posts  = $('#myls_ai_about_posts');
  const $skip   = $('#myls_ai_about_skip_filled');
  const $gen    = $('#myls_ai_about_generate');
  const $stop   = $('#myls_ai_about_stop');
  const $res    = $('#myls_ai_about_results');
  const $count  = $('#myls_ai_about_count');
  const $status = $('#myls_ai_about_status');

  let stopping = false;

  function log(line){ $res.text(line + '\n' + $res.text()); }
  function setCount(n){ $count.text(String(n)); }
  function setBusy(on){
    $gen.prop('disabled', !!on);
    $stop.prop('disabled', !on);
    $pt.prop('disabled', !!on);
    $posts.prop('disabled', !!on);
    $skip.prop('disabled', !!on);
    $status.text(on ? 'Working…' : '');
  }

  function loadPosts(){
    $posts.empty();
    $.post(CFG.ajaxurl, {
      action: CFG.action_get_posts || 'myls_ai_about_get_posts_v2',
      nonce:  CFG.nonce,
      post_type: $pt.val()
    }).done(function(resp){
      try { console.log('[about_get_posts_v2]', resp); } catch(e){}
      if (!resp || !resp.success || !resp.data || !Array.isArray(resp.data.posts)) {
        log('Failed to load posts (bad response).');
        return;
      }
      const posts = resp.data.posts;
      for (let i=0;i<posts.length;i++){
        const p = posts[i];
        $('<option>').val(String(p.id)).text((p.title||'(no title)')+' (ID '+p.id+')').appendTo($posts);
      }
      log('Loaded '+posts.length+' posts for '+$pt.val()+'.');
    }).fail(function(xhr){
      log('AJAX error: get_posts ('+(xhr && xhr.status)+')');
    });
  }

  function readTemplateParams(){
    const tpl = $('textarea[name="myls_ai_about_prompt_template"]').val() || '';
    const tok = parseInt($('input[name="myls_ai_about_tokens"]').val() || '600', 10);
    const tmp = parseFloat($('input[name="myls_ai_about_temperature"]').val() || '0.7');
    return { template: tpl, tokens: tok, temperature: tmp };
  }

  function run(){
    const ids = ($posts.val() || []).map(v => parseInt(v, 10)).filter(Boolean);
    if (!ids.length) { log('Select at least one post.'); return; }

    stopping = false;
    setCount(0);
    setBusy(true);

    const { template, tokens, temperature } = readTemplateParams();
    const skip = $skip.is(':checked');
    let done = 0;

    (function next(){
      if (stopping || !ids.length) {
        setBusy(false);
        log('Done.');
        return;
      }
      const id = ids.shift();

      $.post(CFG.ajaxurl, {
        action:      CFG.action_generate || 'myls_ai_about_generate_v2',
        nonce:       CFG.nonce,
        post_id:     id,
        skip_filled: skip ? 1 : 0,
        template:    template,
        tokens:      tokens,
        temperature: temperature
      })
      .done(function(resp){
        try { console.log('[about_generate_v2]', resp); } catch(e){}
        if (!resp || typeof resp !== 'object') { log('ID '+id+' — Unexpected server response.'); return; }

        if (resp.success) {
          const d = resp.data || {};
          const st = d.status || '(no status)';
          const mk = d.marker || '(no marker)';

          if (st === 'skipped') {
            log('ID '+id+' — Skipped (already filled). ['+mk+']');
          } else if (st === 'saved') {
            const dbg = d.debug || {};
            const method = dbg.saved_method || 'unknown_method';
            const key = dbg.acf_key_used ? (', key='+dbg.acf_key_used) : '';
            const area = d.city_state || '(n/a)';
            const prev = d.preview ? (' Preview: '+d.preview) : '';
            log('ID '+id+' — Saved. Area: '+area+' ['+method+key+']'+prev+' ['+mk+']');
          } else {
            log('ID '+id+' — Success but unexpected status='+st+' ['+mk+']');
          }
        } else {
          const d = resp.data || {};
          const mk = d.marker || '(no marker)';
          const msg = d.message || 'server_error';
          const dbg = d.debug ? (' ['+JSON.stringify(d.debug)+']') : '';
          log('ID '+id+' — ERROR: '+msg+dbg+' ['+mk+']');
        }
      })
      .fail(function(xhr){
        log('ID '+id+' — AJAX error: '+(xhr && xhr.status));
      })
      .always(function(){
        done++; setCount(done); next();
      });

    })();
  }

  $pt.on('change', loadPosts);
  $gen.on('click', function(e){ e.preventDefault(); run(); });
  $stop.on('click', function(e){ e.preventDefault(); stopping = true; });

  if ($pt.val() !== CFG.defaultType) $pt.val(CFG.defaultType);
  loadPosts();

})(jQuery);
