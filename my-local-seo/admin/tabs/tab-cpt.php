<?php
/**
 * Admin Tab: Custom Post Types (Modular CPT Manager)
 * - Discovers CPT modules in modules/cpt/*.php using MYLS_CPT_DISCOVERY
 * - Each module returns a spec when MYLS_CPT_DISCOVERY is true:
 *     [
 *       'id'       => 'video',
 *       'label'    => 'Videos',
 *       'defaults' => [
 *           'default_slug'    => 'video',
 *           'default_archive' => 'videos'
 *       ]
 *     ]
 * - Runtime registration is handled by your modules via inc/load-cpt-modules.php
 */
if ( ! defined('ABSPATH') ) exit;

myls_register_admin_tab('cpt', 'Custom Post Types', function () {

    // -------------------------------------------------
    // Discover CPT modules
    // -------------------------------------------------
    $cpt_specs = [];
    $debug     = [];

    $cpt_dir = trailingslashit( MYLS_PATH ) . 'modules/cpt';
    if ( ! is_dir($cpt_dir) ) {
        echo '<div class="alert alert-warning mt-3"><strong>Missing folder:</strong> <code>modules/cpt</code> under plugin root.</div>';
        return;
    }

    if ( ! defined('MYLS_CPT_DISCOVERY') ) {
        define('MYLS_CPT_DISCOVERY', true);
    }

    $files = glob( $cpt_dir . '/*.php' );
    if ( ! empty($files) ) {
        natsort($files);
        // Skip known non-module helpers if present
        $skip = ['register.php','_bootstrap.php','_loader.php'];
        foreach ( $files as $f ) {
            $base = basename($f);
            if ( in_array($base, $skip, true) ) {
                $debug[] = "Skipped (non-module): modules/cpt/{$base}";
                continue;
            }
            $spec = include $f;
            if ( is_array($spec) && ! empty($spec['id']) ) {
                $cpt_specs[$spec['id']] = $spec;
                $debug[] = "OK: modules/cpt/{$base}";
            } else {
                $debug[] = "No spec returned: modules/cpt/{$base}";
            }
        }
    }

    if ( empty($cpt_specs) ) {
        echo '<div class="alert alert-warning mt-3"><strong>No CPT modules discovered.</strong> Add files like <code>modules/cpt/video.php</code> that return a spec when <code>MYLS_CPT_DISCOVERY</code> is defined.</div>';
        if ( $debug ) {
            echo '<pre class="mt-2 p-2 bg-light border rounded small">'. esc_html(implode("\n", $debug)) .'</pre>';
        }
        return;
    }

    // -------------------------------------------------
    // Handle POST save
    // -------------------------------------------------
    if (
        isset($_POST['myls_cpt_nonce']) &&
        wp_verify_nonce( $_POST['myls_cpt_nonce'], 'myls_cpt_save' ) &&
        current_user_can('manage_options')
    ) {
        foreach ( $cpt_specs as $id => $spec ) {
            $opt_key = "myls_enable_{$id}_cpt";

            $enabled = isset($_POST[$opt_key]) ? '1' : '0';
            $slug    = isset($_POST["{$opt_key}_slug"]) ? sanitize_title( wp_unslash($_POST["{$opt_key}_slug"]) ) : '';
            $arch    = isset($_POST["{$opt_key}_hasarchive"]) ? sanitize_text_field( wp_unslash($_POST["{$opt_key}_hasarchive"]) ) : '';

            update_option($opt_key, $enabled);
            update_option("{$opt_key}_slug", $slug);
            update_option("{$opt_key}_hasarchive", $arch);
        }

        flush_rewrite_rules();
        do_action('myls_cpt_settings_updated');

        echo '<div class="alert alert-success mt-3">Content type settings saved.</div>';
    }

    // -------------------------------------------------
    // Load settings
    // -------------------------------------------------
    $settings = [];
    foreach ( $cpt_specs as $id => $spec ) {
        $opt_key = "myls_enable_{$id}_cpt";
        $settings[$id] = [
            'enabled'     => get_option($opt_key, '0'),
            'slug'        => get_option("{$opt_key}_slug", ''),
            'has_archive' => get_option("{$opt_key}_hasarchive", ''),
        ];
    }

    // Helpers
    $human = function($s){ $s = str_replace(['-','_'],' ',$s); return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8'); };

    ?>
    <div class="container-fluid mt-3">
        <?php if ( ! empty($debug) ) : ?>
            <details class="mb-3">
                <summary class="small text-muted">Discovery debug</summary>
                <pre class="mt-2 p-2 bg-light border rounded small"><?php echo esc_html(implode("\n", $debug)); ?></pre>
            </details>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('myls_cpt_save', 'myls_cpt_nonce'); ?>

            <div class="d-flex gap-2 mb-3">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="button" class="btn btn-outline-secondary" id="myls-flush-rewrites">Flush Rewrites (AJAX)</button>
            </div>

            <div class="row">
                <?php foreach ( $cpt_specs as $id => $spec ) :
                    $s = $settings[$id];
                    $label    = !empty($spec['label']) ? $spec['label'] : $human($id);
                    $defaults = $spec['defaults'] ?? [];
                    $ph_slug  = $defaults['default_slug']    ?? $id;
                    $ph_arch  = $defaults['default_archive'] ?? "{$id}s";
                    $toggle   = "myls_enable_{$id}_cpt";
                ?>
                <div class="col-lg-4">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <strong><?php echo esc_html($label); ?></strong>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    id="<?php echo esc_attr($toggle); ?>"
                                    name="<?php echo esc_attr($toggle); ?>"
                                    value="1"
                                    <?php checked('1', $s['enabled']); ?>
                                >
                                <label class="form-check-label" for="<?php echo esc_attr($toggle); ?>">
                                    Enable <strong><?php echo esc_html($label); ?></strong>
                                </label>
                            </div>

                            <div class="mb-3">
                                <label for="<?php echo esc_attr("{$toggle}_hasarchive"); ?>" class="form-label">Has Archive</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="<?php echo esc_attr("{$toggle}_hasarchive"); ?>"
                                    name="<?php echo esc_attr("{$toggle}_hasarchive"); ?>"
                                    value="<?php echo esc_attr($s['has_archive']); ?>"
                                    placeholder="<?php echo esc_attr($ph_arch); ?>"
                                >
                                <div class="form-text">Leave blank to disable archive.</div>
                            </div>

                            <div class="mb-3">
                                <label for="<?php echo esc_attr("{$toggle}_slug"); ?>" class="form-label">Slug</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="<?php echo esc_attr("{$toggle}_slug"); ?>"
                                    name="<?php echo esc_attr("{$toggle}_slug"); ?>"
                                    value="<?php echo esc_attr($s['slug']); ?>"
                                    placeholder="<?php echo esc_attr($ph_slug); ?>"
                                >
                                <div class="form-text">URL base (no slashes). Blank uses default.</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-info btn-sm" data-cpt="<?php echo esc_attr($id); ?>">
                                    Check Registered (AJAX)
                                </button>
                                <span class="align-self-center small text-muted" id="myls-cpt-status-<?php echo esc_attr($id); ?>"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>

    <script>
    (function(){
        const ajaxurl = "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>";
        const nonce   = "<?php echo esc_js( wp_create_nonce('myls_cpt_ajax') ); ?>";

        // Flush rewrites
        const flushBtn = document.getElementById('myls-flush-rewrites');
        if (flushBtn) {
            flushBtn.addEventListener('click', function(){
                flushBtn.disabled = true;
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams({ action: 'myls_flush_rewrites', _wpnonce: nonce })
                }).then(r=>r.json()).then(data=>{
                    alert((data && data.message) ? data.message : 'Flushed.');
                }).catch(()=>alert('Request failed')).finally(()=>flushBtn.disabled=false);
            });
        }

        // Check CPT registered
        document.querySelectorAll('button[data-cpt]').forEach(btn=>{
            btn.addEventListener('click', function(){
                const id  = btn.getAttribute('data-cpt');
                const out = document.getElementById('myls-cpt-status-'+id);
                if (out) out.textContent = 'Checking...';
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams({ action: 'myls_check_cpt', cpt: id, _wpnonce: nonce })
                }).then(r=>r.json()).then(data=>{
                    if (!out) return;
                    out.textContent = (data && data.registered) ? 'Registered ✅' : 'Not registered ❌';
                }).catch(()=>{
                    if (out) out.textContent = 'Error';
                });
            });
        });
    })();
    </script>
    <?php
});
