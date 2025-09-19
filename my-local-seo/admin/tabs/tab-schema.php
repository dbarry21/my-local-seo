<?php
/**
 * Admin Tab: Schema
 * - Auto-discovers subtabs from admin/tabs/schema/subtab-*.php
 * - Each subtab returns a spec when MYLS_SCHEMA_DISCOVERY is defined:
 *   [
 *     'id'       => 'organization',
 *     'label'    => 'Organization',
 *     'render'   => callable, // outputs form
 *     'on_save'  => callable, // handles POST (optional)
 *   ]
 */
if ( ! defined('ABSPATH') ) exit;

myls_register_admin_tab('schema', 'Schema', function () {

    $subtabs = [];
    $debug   = [];

    // 1) Discover subtabs
    $dir = trailingslashit( MYLS_PATH ) . 'admin/tabs/schema';
    if ( ! is_dir($dir) ) {
        echo '<div class="alert alert-warning mt-3">Create folder: <code>admin/tabs/schema</code> and add <code>subtab-*.php</code> files.</div>';
        return;
    }

    if ( ! defined('MYLS_SCHEMA_DISCOVERY') ) {
        define('MYLS_SCHEMA_DISCOVERY', true);
    }

    $files = glob( $dir . '/subtab-*.php' );
    natsort($files);

    foreach ( $files as $file ) {
        $rel = str_replace( trailingslashit(MYLS_PATH), '', $file );
        $spec = include $file;
        if ( is_array($spec) && ! empty($spec['id']) && ! empty($spec['label']) && is_callable($spec['render']) ) {
            $subtabs[$spec['id']] = $spec;
            $debug[] = "OK: {$rel}";
        } else {
            $debug[] = "No spec returned (check MYLS_SCHEMA_DISCOVERY handling): {$rel}";
        }
    }

    if ( empty($subtabs) ) {
        echo '<div class="alert alert-warning mt-3"><strong>No schema subtabs discovered.</strong> Add files like <code>admin/tabs/schema/subtab-organization.php</code> that return a spec when <code>MYLS_SCHEMA_DISCOVERY</code> is defined.</div>';
        if ( $debug ) {
            echo '<pre class="mt-2 p-2 bg-light border rounded small">'.esc_html(implode("\n",$debug)).'</pre>';
        }
        return;
    }

    // 2) Active subtab from query (fallback to first)
    $active = isset($_GET['schema_subtab']) ? sanitize_key($_GET['schema_subtab']) : key($subtabs);
    if ( ! isset($subtabs[$active]) ) $active = key($subtabs);

    // 3) Handle subtab save (if provided)
    if (
        isset($_POST['myls_schema_nonce'])
        && wp_verify_nonce($_POST['myls_schema_nonce'], 'myls_schema_save')
        && current_user_can('manage_options')
    ) {
        if ( isset($subtabs[$active]['on_save']) && is_callable($subtabs[$active]['on_save']) ) {
            call_user_func($subtabs[$active]['on_save']);
            echo '<div class="alert alert-success mt-3">Schema settings saved.</div>';
        }
        do_action('myls_schema_settings_updated', $active);
    }

    // 4) Render nav + active subtab
    ?>
    <div class="container-fluid mt-3">
        <?php if ( $debug ) : ?>
            <details class="mb-3">
                <summary class="small text-muted">Discovery debug</summary>
                <pre class="mt-2 p-2 bg-light border rounded small"><?php echo esc_html(implode("\n",$debug)); ?></pre>
            </details>
        <?php endif; ?>

        <ul class="nav nav-pills mb-3">
            <?php foreach ($subtabs as $id => $spec): 
                $label = $spec['label'];
                $url = add_query_arg(['page' => 'my-local-seo', 'tab' => 'schema', 'schema_subtab' => $id], admin_url('admin.php'));
                $is_active = ($id === $active);
            ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo esc_url($url); ?>">
                    <?php echo esc_html($label); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php call_user_func($subtabs[$active]['render']); ?>
            </div>
        </div>
    </div>
    <?php
});
