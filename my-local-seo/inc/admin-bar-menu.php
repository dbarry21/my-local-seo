<?php
/**
 * SEO Stuff – Admin Bar Menu + Google Index Check + Open GSC Request
 *
 * - Keeps your existing links (Rich Results, PSI Mobile/Desktop, GTmetrix)
 * - Adds:
 *    • "Check Google Index" with live green/red dot (API status ONLY)
 *    • "Request Indexing in GSC" link → opens GSC UI with URL prefilled
 *
 * Uses existing OAuth token via myls_gsc_get_access_token() from your API Integration.
 */

if ( ! defined('ABSPATH') ) exit;

/** Helper: current page URL to test (admin or front) */
if ( ! function_exists('ssseo_current_test_url') ) {
    function ssseo_current_test_url(): string {
        if ( is_admin() && ! empty($_GET['post']) && ($post_id = absint($_GET['post'])) ) {
            $u = get_permalink($post_id);
        } elseif ( is_singular() ) {
            $u = get_permalink();
        } else {
            $u = home_url('/');
        }
        $u = esc_url_raw( untrailingslashit($u) );
        return $u ?: esc_url_raw( home_url('/') );
    }
}

/** Safe accessor: reuse your existing token getter (no redeclare) */
if ( ! function_exists('myls_gsc_access_token_current') ) {
    function myls_gsc_access_token_current(): string {
        if ( function_exists('myls_gsc_get_access_token') ) {
            $t = call_user_func('myls_gsc_get_access_token'); // your OAuth module handles refresh
            return is_string($t) ? $t : '';
        }
        $t = get_option('myls_gsc_access_token'); // fallback (no refresh)
        return is_string($t) ? $t : '';
    }
}

/** Admin Bar menu (parent + children) */
add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
    if ( ! is_admin_bar_showing() || ! current_user_can('edit_posts') ) return;

    $test_url         = ssseo_current_test_url();
    $rich_results_url = add_query_arg( ['url'=>$test_url], 'https://search.google.com/test/rich-results' );
    $psi_mobile       = add_query_arg( ['url'=>$test_url,'form_factor'=>'mobile' ],  'https://pagespeed.web.dev/analysis' );
    $psi_desktop      = add_query_arg( ['url'=>$test_url,'form_factor'=>'desktop'],  'https://pagespeed.web.dev/analysis' );
    $gtmetrix_url     = add_query_arg( ['url'=>$test_url], 'https://gtmetrix.com/analyze.html' );

    // Parent
    $wp_admin_bar->add_node([
        'id'    => 'ssseo-seo-stuff',
        'title' => __('SEO Stuff', 'ssseo'),
        'href'  => false,
    ]);

    // Children: existing tools
    $wp_admin_bar->add_node([
        'id'     => 'ssseo-test-schema',
        'parent' => 'ssseo-seo-stuff',
        'title'  => __('Test Schema', 'ssseo'),
        'href'   => esc_url($rich_results_url),
        'meta'   => ['target'=>'_blank','title'=>__('Run Google Rich Results Test','ssseo')],
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'ssseo-page-speed-mobile',
        'parent' => 'ssseo-seo-stuff',
        'title'  => __('Page Speed (Mobile)', 'ssseo'),
        'href'   => esc_url($psi_mobile),
        'meta'   => ['target'=>'_blank','title'=>__('Run PSI (Mobile)','ssseo')],
    ]);
    $wp_admin_bar->add_node([
        'id'     => 'ssseo-page-speed-desktop',
        'parent' => 'ssseo-seo-stuff',
        'title'  => __('Page Speed (Desktop)', 'ssseo'),
        'href'   => esc_url($psi_desktop),
        'meta'   => ['target'=>'_blank','title'=>__('Run PSI (Desktop)','ssseo')],
    ]);

    $wp_admin_bar->add_node([
        'id'     => 'ssseo-page-speed-gtmetrix',
        'parent' => 'ssseo-seo-stuff',
        'title'  => __('GTmetrix Test', 'ssseo'),
        'href'   => esc_url($gtmetrix_url),
        'meta'   => ['target'=>'_blank','title'=>__('Run GTmetrix Performance Test','ssseo')],
    ]);

    // GSC: Check Google Index (status-only, no submit)
    $title_html = '<span class="ssseo-gsc-dot" aria-hidden="true"></span> ' . esc_html__('Check Google Index','ssseo');
    $wp_admin_bar->add_node([
        'id'     => 'ssseo-gsc-index',
        'parent' => 'ssseo-seo-stuff',
        'title'  => $title_html,
        'href'   => '#',
        'meta'   => ['title'=>__('Check current index status via URL Inspection API','ssseo')],
    ]);

    // GSC: Request Indexing in GSC (opens UI so user can click "Request indexing")
    $site_prop = get_option('myls_gsc_site_property') ?: home_url('/');
    $inspect_ui = add_query_arg(
        [
            'resource_id' => rawurlencode( trailingslashit( $site_prop ) ),
            'url'         => rawurlencode( $test_url ),
        ],
        'https://search.google.com/search-console/inspect'
    );
    $wp_admin_bar->add_node([
        'id'     => 'ssseo-gsc-open-ui',
        'parent' => 'ssseo-seo-stuff',
        'title'  => __('Request Indexing in GSC','ssseo'),
        'href'   => esc_url($inspect_ui),
        'meta'   => ['target'=>'_blank','title'=>__('Open URL Inspection in Search Console (manual Request indexing)','ssseo')],
    ]);
}, 100 );

/** Inline assets (admin + front when admin bar is visible) */
function ssseo_print_adminbar_gsc_assets() {
    if ( ! is_user_logged_in() || ! is_admin_bar_showing() ) return;

    $nonce     = wp_create_nonce('myls_gsc_nonce');
    $ajax      = admin_url('admin-ajax.php');
    $url       = ssseo_current_test_url();
    $site_prop = get_option('myls_gsc_site_property') ?: home_url('/');
    $has_token = (bool) myls_gsc_access_token_current();

    ?>
    <style id="ssseo-adminbar-gsc-css">
      #wp-admin-bar-ssseo-gsc-index .ab-item .ssseo-gsc-dot{
        display:inline-block;width:10px;height:10px;border-radius:50%;
        background:#c4c4c4;margin-right:6px;vertical-align:middle;
      }
      #wp-admin-bar-ssseo-gsc-index .ab-item .ssseo-gsc-dot.is-checking{background:#aaa}
      #wp-admin-bar-ssseo-gsc-index .ab-item .ssseo-gsc-dot.is-ok{background:#2bbf6a}
      #wp-admin-bar-ssseo-gsc-index .ab-item .ssseo-gsc-dot.is-bad{background:#e55353}
      #wp-admin-bar-ssseo-gsc-index .ab-item .ssseo-gsc-dot.is-unknown{background:#999}
    </style>
    <script id="ssseo-adminbar-gsc-config">
      window.SSSEO_GSC = {
        ajax: "<?php echo esc_js($ajax); ?>",
        nonce: "<?php echo esc_js($nonce); ?>",
        url: "<?php echo esc_js($url); ?>",
        siteUrl: "<?php echo esc_js( trailingslashit($site_prop) ); ?>",
        hasToken: <?php echo $has_token ? 'true' : 'false'; ?>
      };
    </script>
    <script id="ssseo-adminbar-gsc-js">
      (function(){
        function $(sel,ctx){return (ctx||document).querySelector(sel);}
        function paint(dot, cls){
          if(!dot) return;
          dot.classList.remove('is-checking','is-ok','is-bad','is-unknown');
          dot.classList.add(cls);
        }
        function post(data, cb){
          var xhr = new XMLHttpRequest();
          xhr.open('POST', SSSEO_GSC.ajax, true);
          xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');
          xhr.onreadystatechange = function(){ if (xhr.readyState===4 && cb) cb(xhr); };
          xhr.send(Object.keys(data).map(function(k){
            return encodeURIComponent(k)+'='+encodeURIComponent(data[k]);
          }).join('&'));
        }
        function checkIndex(){
          var dot = $('#wp-admin-bar-ssseo-gsc-index .ssseo-gsc-dot');
          paint(dot,'is-checking');
          post({
            action : 'myls_gsc_check_index',
            nonce  : SSSEO_GSC.nonce,
            url    : SSSEO_GSC.url,
            siteUrl: SSSEO_GSC.siteUrl
          }, function(xhr){
            try{
              var res = JSON.parse(xhr.responseText||'{}');
              if (res && res.success && res.data) {
                paint(dot, res.data.indexed ? 'is-ok' : 'is-bad');
              } else {
                paint(dot,'is-unknown');
              }
            }catch(e){ paint(dot,'is-unknown'); }
          });
        }
        // Click handler: status check ONLY (no submit)
        document.addEventListener('click', function(e){
          var a = e.target.closest('#wp-admin-bar-ssseo-gsc-index a.ab-item'); if (!a) return;
          e.preventDefault();
          if (!SSSEO_GSC.hasToken) { alert('Connect Google in My Local SEO → API Integration → Search Console.'); return; }
          checkIndex();
        }, false);
        // Auto-check on load if token exists
        window.addEventListener('load', function(){
          if (SSSEO_GSC.hasToken) checkIndex();
        });
      })();
    </script>
    <?php
}
add_action('admin_head', 'ssseo_print_adminbar_gsc_assets');
add_action('wp_head',    'ssseo_print_adminbar_gsc_assets'); // front-end admin bar

/** AJAX: status check (no submit) */
if ( ! function_exists('myls_gsc_ajax_check_index') ) {
    function myls_gsc_ajax_check_index(){
        check_ajax_referer('myls_gsc_nonce','nonce');
        $url     = esc_url_raw($_POST['url'] ?? '');
        $siteUrl = esc_url_raw($_POST['siteUrl'] ?? '');
        if ( ! $url || ! $siteUrl ) wp_send_json_error(['message'=>'Missing URL parameters.']);
        $token = myls_gsc_access_token_current();
        if ( ! $token ) wp_send_json_error(['message'=>'Google not connected.']);
        $resp = wp_remote_post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
            'headers' => ['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'],
            'body'    => wp_json_encode(['inspectionUrl'=>$url,'siteUrl'=>trailingslashit($siteUrl)]),
            'timeout' => 20,
        ]);
        if ( is_wp_error($resp) ) wp_send_json_error(['message'=>$resp->get_error_message()]);
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code === 401 || $code === 403) wp_send_json_error(['message'=>'Unauthorized. Reconnect Google.']);
        $verdict = $body['inspectionResult']['indexStatusResult']['verdict'] ?? '';
        $indexed = (strtoupper($verdict) === 'PASS');
        wp_send_json_success([
            'indexed' => $indexed,
            'verdict' => $verdict,
            'coverageState'   => $body['inspectionResult']['indexStatusResult']['coverageState']   ?? '',
            'lastCrawlTime'   => $body['inspectionResult']['indexStatusResult']['lastCrawlTime']   ?? '',
            'userCanonical'   => $body['inspectionResult']['indexStatusResult']['userCanonical']   ?? '',
            'googleCanonical' => $body['inspectionResult']['indexStatusResult']['googleCanonical'] ?? '',
        ]);
    }
    add_action('wp_ajax_myls_gsc_check_index', 'myls_gsc_ajax_check_index');
}
