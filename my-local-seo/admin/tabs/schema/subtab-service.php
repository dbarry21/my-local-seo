<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Schema > Service (Two-column; Local Business styling)
 * - Options:
 *    myls_service_enabled ("0"/"1")
 *    myls_service_default_type (string)
 *    myls_service_pages (int[])
 */

$spec = [
  'id'    => 'serviceschema',
  'label' => 'Service',
  'render'=> function () {

    // Match ssseo-tools list
    $service_types = [
      '',
      'LocalBusiness','Plumber','Electrician','HVACBusiness','RoofingContractor','PestControl',
      'LegalService','CleaningService','AutoRepair','MedicalBusiness','Locksmith','MovingCompany',
      'RealEstateAgent','ITService',
    ];

    $enabled      = get_option('myls_service_enabled','0');
    $default_type = get_option('myls_service_default_type','');

    // Detect optional CPTs
    $has_service_cpt       = post_type_exists('service');
    $has_service_area_cpt  = post_type_exists('service_area');

    // Selected IDs (saved)
    $selected_ids = array_map('absint', (array) get_option('myls_service_pages', []));

    // --- Build hierarchical "Pages"
    $pages = get_pages([
      'sort_order'  => 'asc',
      'sort_column' => 'menu_order,post_title',
      'post_status' => ['publish'],
    ]);

    // Children map for pages
    $page_children = [];
    foreach ( $pages as $pg ) {
      $page_children[ (int)$pg->post_parent ][] = $pg;
    }

    $render_page_options = function($parent_id, $depth) use (&$render_page_options, $page_children, $selected_ids) {
      if ( empty($page_children[$parent_id]) ) return;
      foreach ( $page_children[$parent_id] as $pg ) {
        $indent = str_repeat('— ', max(0, (int)$depth));
        $sel    = in_array($pg->ID, $selected_ids, true) ? 'selected' : '';
        printf(
          '<option data-ptype="page" value="%d" %s>%s%s</option>',
          (int)$pg->ID,
          $sel,
          esc_html($indent),
          esc_html($pg->post_title)
        );
        $render_page_options($pg->ID, $depth+1);
      }
    };

    // Flat Posts (non-hierarchical)
    $posts = get_posts([
      'post_type'      => 'post',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'orderby'        => 'title',
      'order'          => 'asc',
    ]);

    // Services (if CPT exists)
    $services = [];
    if ( $has_service_cpt ) {
      $services = get_posts([
        'post_type'      => 'service',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'asc',
      ]);
    }

    // Service Areas (if CPT exists)
    $service_areas = [];
    if ( $has_service_area_cpt ) {
      $service_areas = get_posts([
        'post_type'      => 'service_area',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'asc',
      ]);
    }
    ?>

    <style>
      /* Same look as Local Business */
      .myls-svc-wrap { width: 100%; }
      .myls-svc-grid { display:flex; flex-wrap:wrap; gap:8px; align-items:stretch; }
      .myls-svc-left  { flex:3 1 520px; min-width:320px; }
      .myls-svc-right { flex:1 1 280px; min-width:260px; }

      .myls-block { background:#fff; border:1px solid #000; border-radius:1em; padding:12px; }
      .myls-block-title { font-weight:800; margin:0 0 8px; }

      .myls-svc-wrap input[type="text"], .myls-svc-wrap input[type="email"], .myls-svc-wrap input[type="url"],
      .myls-svc-wrap input[type="time"], .myls-svc-wrap input[type="tel"], .myls-svc-wrap textarea, .myls-svc-wrap select {
        border:1px solid #000 !important; border-radius:1em !important; padding:.6rem .9rem; width:100%;
      }
      .form-label { font-weight:600; margin-bottom:.35rem; display:block; }
      .myls-hr { height:1px; background:#000; opacity:.15; border:0; margin:8px 0 10px; }
      .myls-actions { margin-top:10px; display:flex; gap:.5rem; flex-wrap: wrap; }
      .myls-btn { display:inline-block; font-weight:600; border:1px solid #000; padding:.45rem .9rem; border-radius:1em; background:#f8f9fa; color:#111; cursor:pointer; }
      .myls-btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
      .myls-btn-outline { background:transparent; }
      .myls-btn:hover { filter:brightness(.97); }

      .myls-row { display:flex; flex-wrap:wrap; margin-left:-.5rem; margin-right:-.5rem; }
      .myls-col { padding-left:.5rem; padding-right:.5rem; margin-bottom:.75rem; }
      .col-12 { flex:0 0 100%; max-width:100%; }
      .col-6  { flex:0 0 50%;  max-width:50%; }

      /* Filter toolbar in right column */
      .myls-filter { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
      .myls-filter .chip { display:inline-flex; gap:.35rem; align-items:center; border:1px solid #000; border-radius:999px; padding:.3rem .6rem; background:#fff; }
      .myls-filter input { margin:0; }
      .myls-select { width:100%; min-height:420px; }
      optgroup { font-weight:700; }
    </style>

    <form method="post" class="myls-svc-wrap">
      <?php wp_nonce_field('myls_schema_save','myls_schema_nonce'); ?>

      <div class="myls-svc-grid">
        <!-- LEFT (75%) -->
        <div class="myls-svc-left">
          <div class="myls-block">
            <div class="myls-block-title">Service Schema</div>

            <div class="myls-row">
              <div class="myls-col col-6">
                <label class="form-label">Enable Service Schema</label>
                <select name="myls_service_enabled">
                  <option value="0" <?php selected('0', $enabled); ?>>Disabled</option>
                  <option value="1" <?php selected('1', $enabled); ?>>Enabled</option>
                </select>
              </div>

              <div class="myls-col col-6">
                <label class="form-label">Default Service Type</label>
                <select name="myls_service_default_type">
                  <?php foreach ( $service_types as $opt ): ?>
                    <option value="<?php echo esc_attr($opt); ?>" <?php selected($default_type, $opt); ?>>
                      <?php echo $opt === '' ? '— Select —' : esc_html($opt); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">Used when a Service doesn’t specify a specific type.</div>
              </div>
            </div>

            <div class="myls-actions">
              <button class="myls-btn myls-btn-primary" type="submit">Save Settings</button>
              <details>
                <summary style="cursor:pointer">Debug</summary>
                <pre style="white-space:pre-wrap"><?php
                  echo esc_html( sprintf(
                    "enabled=%s\ndefault_type=%s\nselected_count=%d",
                    $enabled,
                    $default_type,
                    count($selected_ids)
                  ) );
                ?></pre>
              </details>
            </div>
          </div>
        </div>

        <!-- RIGHT (25%) -->
        <div class="myls-svc-right">
          <div class="myls-block">
            <div class="myls-block-title">
              Apply on Services / Pages / Posts<?php
                if ( ! $has_service_cpt ) echo ' <span class="form-text" style="display:block;margin-top:.25rem">Note: <code>service</code> CPT not detected.</span>';
                if ( ! $has_service_area_cpt ) echo ' <span class="form-text" style="display:block;margin-top:.25rem">Note: <code>service_area</code> CPT not detected.</span>';
              ?>
            </div>

            <!-- Filters -->
            <div class="myls-actions myls-filter" style="margin-bottom:.5rem">
              <label class="chip">
                <input type="checkbox" class="myls-ptype" value="page" checked> Pages
              </label>
              <label class="chip">
                <input type="checkbox" class="myls-ptype" value="post" checked> Posts
              </label>
              <?php if ( $has_service_cpt ): ?>
                <label class="chip">
                  <input type="checkbox" class="myls-ptype" value="service" checked> Service
                </label>
              <?php endif; ?>
              <?php if ( $has_service_area_cpt ): ?>
                <label class="chip">
                  <input type="checkbox" class="myls-ptype" value="service_area" checked> Service Area
                </label>
              <?php endif; ?>
              <button type="button" class="myls-btn myls-btn-outline" id="myls-service-select-all">Select All</button>
              <button type="button" class="myls-btn myls-btn-outline" id="myls-service-clear">Clear</button>
            </div>

            <!-- Hierarchical, grouped select -->
            <select name="myls_service_pages[]" id="myls-service-pages" class="myls-select" multiple size="18">
              <optgroup label="Pages">
                <?php $render_page_options(0, 0); // options carry data-ptype="page" in renderer ?>
              </optgroup>

              <optgroup label="Posts">
                <?php foreach ( $posts as $p ):
                  $sel = in_array($p->ID, $selected_ids, true) ? 'selected' : '';
                  printf('<option data-ptype="post" value="%d" %s>%s</option>',
                    (int)$p->ID, $sel, esc_html($p->post_title));
                endforeach; ?>
              </optgroup>

              <?php if ( $has_service_cpt ): ?>
                <optgroup label="Service">
                  <?php foreach ( $services as $p ):
                    $sel = in_array($p->ID, $selected_ids, true) ? 'selected' : '';
                    printf('<option data-ptype="service" value="%d" %s>%s</option>',
                      (int)$p->ID, $sel, esc_html($p->post_title));
                  endforeach; ?>
                </optgroup>
              <?php endif; ?>

              <?php if ( $has_service_area_cpt ): ?>
                <optgroup label="Service Area">
                  <?php foreach ( $service_areas as $p ):
                    $sel = in_array($p->ID, $selected_ids, true) ? 'selected' : '';
                    printf('<option data-ptype="service_area" value="%d" %s>%s</option>',
                      (int)$p->ID, $sel, esc_html($p->post_title));
                  endforeach; ?>
                </optgroup>
              <?php endif; ?>
            </select>

            <div class="form-text" style="margin-top:.5rem">
              Hold <strong>Ctrl/Cmd</strong> to select multiple. Use the chips to filter by post type.
            </div>
          </div>
        </div>
      </div>
    </form>

    <script>
(function(){
  const sel   = document.getElementById('myls-service-pages');
  const chips = document.querySelectorAll('.myls-ptype');

  // Filter by post type via option.hidden (but NEVER change selection state here)
  function applyTypeFilter(){
    const allowed = new Set(Array.from(chips).filter(c => c.checked).map(c => c.value));
    for (const opt of sel.querySelectorAll('option')) {
      const t = opt.getAttribute('data-ptype') || 'page';
      opt.hidden = !allowed.has(t);
      // IMPORTANT: do NOT auto-deselect hidden options (preserves associations)
    }
  }
  chips.forEach(ch => ch.addEventListener('change', applyTypeFilter));
  applyTypeFilter();

  // Select All (visible only)
  document.getElementById('myls-service-select-all')?.addEventListener('click', function(){
    for (const o of sel.options) if (!o.hidden) o.selected = true;
  });

  // Clear (visible only) -> hidden selections remain, preserving associations
  document.getElementById('myls-service-clear')?.addEventListener('click', function(){
    for (const o of sel.options) if (!o.hidden) o.selected = false;
  });
})();
</script>

    <?php
  },

  'on_save'=> function () {
    if ( ! isset($_POST['myls_schema_nonce']) || ! wp_verify_nonce($_POST['myls_schema_nonce'], 'myls_schema_save') ) {
      return;
    }
    update_option('myls_service_enabled',       sanitize_text_field($_POST['myls_service_enabled'] ?? '0'));
    update_option('myls_service_default_type',  sanitize_text_field($_POST['myls_service_default_type'] ?? ''));

    $pages = isset($_POST['myls_service_pages']) && is_array($_POST['myls_service_pages'])
      ? array_map('absint', $_POST['myls_service_pages'])
      : [];
    update_option('myls_service_pages', $pages);
  }
];

if ( defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY ) return $spec;
return null;
