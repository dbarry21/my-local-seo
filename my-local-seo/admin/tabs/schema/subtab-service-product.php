<?php if (!defined('ABSPATH')) exit;

/**
 * Schema > Service as Product
 * Adds per-URL selection (pages/posts/CPTs) and all Product options.
 *
 * Options:
 *  - myls_sp_enable ("0"/"1")
 *  - myls_sp_post_types (string[])
 *  - myls_sp_currency (string)
 *  - myls_sp_availability (URL)
 *  - myls_sp_condition (URL)
 *  - myls_sp_price_meta (string)
 *  - myls_sp_sale_meta  (string)
 *  - myls_sp_price_fallback (string)
 *  - myls_sp_brand_meta (string)
 *  - myls_sp_sku_meta   (string)
 *  - myls_sp_sku_prefix (string)
 *  - myls_sp_image_meta (string)
 *  - myls_sp_require_price ("0"/"1")
 *  - myls_sp_call_for_price_text (string)
 *  - myls_service_debug ("0"/"1")
 *  - myls_sp_object_ids (int[])      // per-URL selection list
 *
 * NOTE: Do NOT open a <form> here — the main Schema tab wraps this content in a single form.
 */

$spec = [
  'id'    => 'serviceproduct',
  'label' => 'Service as Product',
  'order' => 30,

  'render'=> function () {

    // ---- Load existing values
    $enabled      = get_option('myls_sp_enable','0');
    $ptypes       = (array) get_option('myls_sp_post_types', ['service']);
    $currency     = get_option('myls_sp_currency','USD');
    $availability = get_option('myls_sp_availability','https://schema.org/InStock');
    $condition    = get_option('myls_sp_condition','https://schema.org/NewCondition');
    $price_meta   = get_option('myls_sp_price_meta','_myls_price');
    $sale_meta    = get_option('myls_sp_sale_meta','');
    $price_fb     = get_option('myls_sp_price_fallback','');
    $brand_meta   = get_option('myls_sp_brand_meta','');
    $sku_meta     = get_option('myls_sp_sku_meta','');
    $sku_prefix   = get_option('myls_sp_sku_prefix','');
    $image_meta   = get_option('myls_sp_image_meta','');
    $require_price= get_option('myls_sp_require_price','0');
    $call_text    = get_option('myls_sp_call_for_price_text','Call for Price');
    $debug_vis    = get_option('myls_service_debug','0');

    // NEW: selected object IDs (pages/posts/CPT items)
    $selected_ids = array_map('absint', (array) get_option('myls_sp_object_ids', []));

    // Discover CPTs
    $has_service_cpt      = post_type_exists('service');
    $has_service_area_cpt = post_type_exists('service_area');

    // Public post types list
    $public_pts = get_post_types(['public'=>true], 'objects');

    // -------- Build hierarchical Pages tree
    $pages = get_pages([
      'sort_order'  => 'asc',
      'sort_column' => 'menu_order,post_title',
      'post_status' => ['publish'],
    ]);
    $page_children = [];
    foreach ($pages as $pg) $page_children[(int) $pg->post_parent][] = $pg;

    $render_page_options = function($parent_id, $depth) use (&$render_page_options, $page_children, $selected_ids) {
      if (empty($page_children[$parent_id])) return;
      foreach ($page_children[$parent_id] as $pg) {
        $indent = str_repeat('— ', max(0, (int)$depth));
        $sel    = in_array($pg->ID, $selected_ids, true) ? 'selected' : '';
        printf(
          '<option data-ptype="page" value="%d" %s>%s%s</option>',
          (int)$pg->ID, $sel, esc_html($indent), esc_html($pg->post_title)
        );
        $render_page_options($pg->ID, $depth+1);
      }
    };

    // Flat posts/CPTs
    $posts = get_posts([
      'post_type'      => 'post',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'orderby'        => 'title',
      'order'          => 'asc',
    ]);
    $services = $has_service_cpt ? get_posts([
      'post_type'=>'service','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'asc'
    ]) : [];
    $service_areas = $has_service_area_cpt ? get_posts([
      'post_type'=>'service_area','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'asc'
    ]) : [];
    ?>

    <style>
      .myls-block { background:#fff;border:1px solid #000;border-radius:1em;padding:12px; }
      .form-label { font-weight:600;margin-bottom:.35rem;display:block; }
      .myls-row { display:flex;flex-wrap:wrap;gap:12px; }
      .myls-col { flex:1 1 260px;min-width:260px; }
      .myls-btn { display:inline-block;font-weight:600;border:1px solid #000;padding:.45rem .9rem;border-radius:1em;background:#0d6efd;color:#fff; cursor:pointer; }
      select,input[type="text"],input[type="url"] { border:1px solid #000;border-radius:1em;padding:.55rem .8rem;width:100%; }

      /* Right column filter + list */
      .myls-filter { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
      .myls-filter .chip { display:inline-flex; gap:.35rem; align-items:center; border:1px solid #000; border-radius:999px; padding:.3rem .6rem; background:#fff; }
      .myls-select { width:100%; min-height:420px; }
      optgroup { font-weight:700; }
    </style>

    <!-- IMPORTANT: No <form> here. This lives inside the main Schema tab's form. -->
    <div class="myls-row">
      <!-- LEFT: core Product settings -->
      <div class="myls-col">
        <div class="myls-block">
          <label class="form-label">Enable</label>
          <select name="myls_sp_enable">
            <option value="0" <?php selected($enabled,'0');?>>Disabled</option>
            <option value="1" <?php selected($enabled,'1');?>>Enabled</option>
          </select>

          <label class="form-label" style="margin-top:.6rem">Post Types</label>
          <select name="myls_sp_post_types[]" multiple size="6">
            <?php foreach ($public_pts as $pt): ?>
              <option value="<?php echo esc_attr($pt->name);?>" <?php selected(in_array($pt->name,$ptypes,true)); ?>>
                <?php echo esc_html($pt->labels->singular_name ?: $pt->name); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label class="form-label" style="margin-top:.6rem">Currency / Availability / Condition</label>
          <input type="text" name="myls_sp_currency" value="<?php echo esc_attr($currency);?>" placeholder="USD" style="margin-bottom:.4rem">
          <input type="url"  name="myls_sp_availability" value="<?php echo esc_url($availability);?>" placeholder="https://schema.org/InStock" style="margin-bottom:.4rem">
          <input type="url"  name="myls_sp_condition" value="<?php echo esc_url($condition);?>" placeholder="https://schema.org/NewCondition">
        </div>

        <div class="myls-block" style="margin-top:10px">
          <label class="form-label">Price Meta / Sale / Fallback</label>
          <input type="text" name="myls_sp_price_meta" value="<?php echo esc_attr($price_meta);?>" placeholder="_myls_price" style="margin-bottom:.4rem">
          <input type="text" name="myls_sp_sale_meta" value="<?php echo esc_attr($sale_meta);?>" placeholder="_myls_sale_price" style="margin-bottom:.4rem">
          <input type="text" name="myls_sp_price_fallback" value="<?php echo esc_attr($price_fb);?>" placeholder="e.g. 199.00">

          <label class="form-label" style="margin-top:.6rem">Brand / SKU / Prefix</label>
          <input type="text" name="myls_sp_brand_meta" value="<?php echo esc_attr($brand_meta);?>" placeholder="_myls_brand" style="margin-bottom:.4rem">
          <input type="text" name="myls_sp_sku_meta" value="<?php echo esc_attr($sku_meta);?>" placeholder="_myls_sku" style="margin-bottom:.4rem">
          <input type="text" name="myls_sp_sku_prefix" value="<?php echo esc_attr($sku_prefix);?>" placeholder="SRV-">

          <label class="form-label" style="margin-top:.6rem">Image Meta Key</label>
          <input type="text" name="myls_sp_image_meta" value="<?php echo esc_attr($image_meta);?>" placeholder="_myls_product_image_id or URL">

          <label class="form-label" style="margin-top:.6rem">Require Numeric Price?</label>
          <select name="myls_sp_require_price">
            <option value="1" <?php selected($require_price,'1');?>>Yes (legacy behavior)</option>
            <option value="0" <?php selected($require_price,'0');?>>No (emit with “Call for Price”)</option>
          </select>

          <label class="form-label" style="margin-top:.6rem">“Call for Price” Text</label>
          <input type="text" name="myls_sp_call_for_price_text" value="<?php echo esc_attr($call_text);?>">
        </div>

        <div class="myls-block" style="margin-top:10px">
          <label class="form-label">Visible HTML Debug Panels</label>
          <select name="myls_service_debug">
            <option value="0" <?php selected($debug_vis,'0');?>>Off</option>
            <option value="1" <?php selected($debug_vis,'1');?>>On (admins only; ?myls_debug=1 also)</option>
          </select>
        </div>

        <div style="margin-top:12px">
          <!-- This submit triggers the MAIN form (no nested form here) -->
          <button class="myls-btn" type="submit">Save Settings</button>
        </div>
      </div>

      <!-- RIGHT: Apply on specific URLs -->
      <div class="myls-col" style="flex:2 1 520px;min-width:420px">
        <div class="myls-block">
          <div class="form-label" style="font-weight:800">Apply on Specific URLs</div>
          <div class="form-text" style="margin-bottom:.35rem">
            If you select items below, Product schema will apply <strong>only</strong> to these URLs.
            Leave empty to apply to all posts of the chosen post types.
          </div>

          <div class="myls-filter" style="margin-bottom:.5rem">
            <label class="chip"><input type="checkbox" class="myls-ptype" value="page" checked> Pages</label>
            <label class="chip"><input type="checkbox" class="myls-ptype" value="post" checked> Posts</label>
            <?php if ($has_service_cpt): ?>
              <label class="chip"><input type="checkbox" class="myls-ptype" value="service" checked> Service</label>
            <?php endif; ?>
            <?php if ($has_service_area_cpt): ?>
              <label class="chip"><input type="checkbox" class="myls-ptype" value="service_area" checked> Service Area</label>
            <?php endif; ?>
            <button type="button" class="myls-btn" id="myls-sp-select-all" style="background:#fff;color:#111;border-color:#000">Select All</button>
            <button type="button" class="myls-btn" id="myls-sp-clear" style="background:#fff;color:#111;border-color:#000">Clear</button>
          </div>

          <select name="myls_sp_object_ids[]" id="myls-sp-objects" class="myls-select" multiple size="18">
            <optgroup label="Pages">
              <?php $render_page_options(0, 0); ?>
            </optgroup>

            <optgroup label="Posts">
              <?php foreach ($posts as $p):
                $sel = in_array($p->ID, $selected_ids, true) ? 'selected' : '';
                printf('<option data-ptype="post" value="%d" %s>%s</option>',
                  (int)$p->ID, $sel, esc_html($p->post_title));
              endforeach; ?>
            </optgroup>

            <?php if ($has_service_cpt): ?>
              <optgroup label="Service">
                <?php foreach ($services as $p):
                  $sel = in_array($p->ID, $selected_ids, true) ? 'selected' : '';
                  printf('<option data-ptype="service" value="%d" %s>%s</option>',
                    (int)$p->ID, $sel, esc_html($p->post_title));
                endforeach; ?>
              </optgroup>
            <?php endif; ?>

            <?php if ($has_service_area_cpt): ?>
              <optgroup label="Service Area">
                <?php foreach ($service_areas as $p):
                  $sel = in_array($p->ID, $selected_ids, true) ? 'selected' : '';
                  printf('<option data-ptype="service_area" value="%d" %s>%s</option>',
                    (int)$p->ID, $sel, esc_html($p->post_title));
                endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>

          <div class="form-text" style="margin-top:.5rem">
            Hold <strong>Ctrl/Cmd</strong> to select multiple. Filters hide options without removing selections.
          </div>
        </div>
      </div>
    </div>

    <script>
    (function(){
      const sel   = document.getElementById('myls-sp-objects');
      const chips = document.querySelectorAll('.myls-ptype');

      function applyTypeFilter(){
        const allowed = new Set(Array.from(chips).filter(c => c.checked).map(c => c.value));
        for (const opt of sel.querySelectorAll('option')) {
          const t = opt.getAttribute('data-ptype') || 'page';
          opt.hidden = !allowed.has(t);
        }
      }
      chips.forEach(ch => ch.addEventListener('change', applyTypeFilter));
      applyTypeFilter();

      document.getElementById('myls-sp-select-all')?.addEventListener('click', function(){
        for (const o of sel.options) if (!o.hidden) o.selected = true;
      });
      document.getElementById('myls-sp-clear')?.addEventListener('click', function(){
        for (const o of sel.options) if (!o.hidden) o.selected = false;
      });
    })();
    </script>
    <?php
  },

  'on_save'=> function(){
    // Main tab verifies nonce & capability before calling this; keep a light guard anyway.
    if ( empty($_POST['myls_schema_nonce']) || !wp_verify_nonce($_POST['myls_schema_nonce'],'myls_schema_save') || ! current_user_can('manage_options') ) return;

    update_option('myls_sp_enable',               sanitize_text_field($_POST['myls_sp_enable'] ?? '0'));
    update_option('myls_sp_post_types',           array_map('sanitize_text_field', $_POST['myls_sp_post_types'] ?? ['service']));
    update_option('myls_sp_currency',             sanitize_text_field($_POST['myls_sp_currency'] ?? 'USD'));
    update_option('myls_sp_availability',         esc_url_raw($_POST['myls_sp_availability'] ?? 'https://schema.org/InStock'));
    update_option('myls_sp_condition',            esc_url_raw($_POST['myls_sp_condition'] ?? 'https://schema.org/NewCondition'));
    update_option('myls_sp_price_meta',           sanitize_text_field($_POST['myls_sp_price_meta'] ?? '_myls_price'));
    update_option('myls_sp_sale_meta',            sanitize_text_field($_POST['myls_sp_sale_meta'] ?? ''));
    update_option('myls_sp_price_fallback',       sanitize_text_field($_POST['myls_sp_price_fallback'] ?? ''));
    update_option('myls_sp_brand_meta',           sanitize_text_field($_POST['myls_sp_brand_meta'] ?? ''));
    update_option('myls_sp_sku_meta',             sanitize_text_field($_POST['myls_sp_sku_meta'] ?? ''));
    update_option('myls_sp_sku_prefix',           sanitize_text_field($_POST['myls_sp_sku_prefix'] ?? ''));
    update_option('myls_sp_image_meta',           sanitize_text_field($_POST['myls_sp_image_meta'] ?? ''));
    update_option('myls_sp_require_price',        sanitize_text_field($_POST['myls_sp_require_price'] ?? '0'));
    update_option('myls_sp_call_for_price_text',  sanitize_text_field($_POST['myls_sp_call_for_price_text'] ?? 'Call for Price'));
    update_option('myls_service_debug',           sanitize_text_field($_POST['myls_service_debug'] ?? '0'));

    // Save selected object IDs
    $ids = (isset($_POST['myls_sp_object_ids']) && is_array($_POST['myls_sp_object_ids']))
      ? array_map('absint', $_POST['myls_sp_object_ids'])
      : [];
    update_option('myls_sp_object_ids', $ids);
  }
];

if (defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY) return $spec;
return null;
