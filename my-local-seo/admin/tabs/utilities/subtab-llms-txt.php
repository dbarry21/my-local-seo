<?php
/**
 * Utilities > llms.txt
 *
 * Location: admin/tabs/utilities/subtab-llms-txt.php
 *
 * Provides basic controls for the /llms.txt endpoint so you can tune
 * what gets exposed to AI tools without editing code.
 */

if ( ! defined('ABSPATH') ) exit;

return [
  'id'    => 'llms_txt',
  'label' => 'llms.txt',
  'order' => 30,
  'render'=> function(){

    if ( ! current_user_can('manage_options') ) {
      echo '<p class="muted">You do not have permission to edit this section.</p>';
      return;
    }

    // -------------------------------------------------
    // Save
    // -------------------------------------------------
    $saved = false;
    if ( isset($_POST['myls_llms_save']) ) {
      check_admin_referer('myls_llms_save');

      // Endpoint master switch
      update_option('myls_llms_enabled', ! empty($_POST['myls_llms_enabled']) ? '1' : '0');

      // Section toggles
      update_option('myls_llms_include_business_details', ! empty($_POST['myls_llms_include_business_details']) ? '1' : '0');
      update_option('myls_llms_include_services', ! empty($_POST['myls_llms_include_services']) ? '1' : '0');
      update_option('myls_llms_include_service_areas', ! empty($_POST['myls_llms_include_service_areas']) ? '1' : '0');
      update_option('myls_llms_include_faqs', ! empty($_POST['myls_llms_include_faqs']) ? '1' : '0');

      // Limits (clamped in generator as well; this is just UI hygiene)
      $svc_limit   = max(1, min(200, (int)($_POST['myls_llms_services_limit'] ?? 15)));
      $area_limit  = max(1, min(300, (int)($_POST['myls_llms_service_areas_limit'] ?? 20)));
      $faq_limit   = max(1, min(200, (int)($_POST['myls_llms_faqs_limit'] ?? 25)));

      update_option('myls_llms_services_limit', (string) $svc_limit);
      update_option('myls_llms_service_areas_limit', (string) $area_limit);
      update_option('myls_llms_faqs_limit', (string) $faq_limit);

      $saved = true;
    }

    // -------------------------------------------------
    // Values
    // -------------------------------------------------
    $enabled       = get_option('myls_llms_enabled', '1') === '1';
    $inc_business  = get_option('myls_llms_include_business_details', '1') === '1';
    $inc_services  = get_option('myls_llms_include_services', '1') === '1';
    $inc_areas     = get_option('myls_llms_include_service_areas', '1') === '1';
    $inc_faqs      = get_option('myls_llms_include_faqs', '1') === '1';

    $svc_limit     = (int) get_option('myls_llms_services_limit', '15');
    $area_limit    = (int) get_option('myls_llms_service_areas_limit', '20');
    $faq_limit     = (int) get_option('myls_llms_faqs_limit', '25');

    $endpoint_url  = home_url('/llms.txt');

    if ( $saved ) {
      echo '<div class="notice notice-success"><p><strong>Saved.</strong> If /llms.txt returns 404, visit <em>Settings → Permalinks</em> and click <em>Save Changes</em> to flush rewrite rules.</p></div>';
    }
    ?>

    <div class="row">
      <div class="col-12 col-md-8">
        <div class="cardish">
          <h2 style="margin-top:0;">/llms.txt Controls</h2>
          <p class="muted">Controls what is emitted by <code><?php echo esc_html($endpoint_url); ?></code>.</p>

          <form method="post" action="">
            <?php wp_nonce_field('myls_llms_save'); ?>

            <div class="mb-3">
              <label class="form-label">
                <input type="checkbox" name="myls_llms_enabled" value="1" <?php checked($enabled); ?> />
                Enable /llms.txt endpoint
              </label>
              <div class="muted">When disabled, the URL will return 404 (rewrite rule remains in place).</div>
            </div>

            <hr />

            <h3 style="margin:0 0 .5rem 0;">Include sections</h3>

            <div class="mb-2">
              <label class="form-label" style="display:flex; gap:.5rem; align-items:center;">
                <input type="checkbox" name="myls_llms_include_business_details" value="1" <?php checked($inc_business); ?> />
                Business details (Organization / LocalBusiness)
              </label>
              <div class="muted">Outputs legal name, website, phone, and address using MYLS schema settings.</div>
            </div>

            <div class="mb-2">
              <label class="form-label" style="display:flex; gap:.5rem; align-items:center;">
                <input type="checkbox" name="myls_llms_include_services" value="1" <?php checked($inc_services); ?> />
                Primary services (Service CPT)
              </label>
              <div class="row">
                <div class="col-12 col-md-4">
                  <label class="form-label">Max services</label>
                  <input type="text" class="form-control" name="myls_llms_services_limit" value="<?php echo esc_attr($svc_limit); ?>" />
                </div>
              </div>
            </div>

            <div class="mb-2 mt-3">
              <label class="form-label" style="display:flex; gap:.5rem; align-items:center;">
                <input type="checkbox" name="myls_llms_include_service_areas" value="1" <?php checked($inc_areas); ?> />
                Service areas (Service Area CPT)
              </label>
              <div class="row">
                <div class="col-12 col-md-4">
                  <label class="form-label">Max service areas</label>
                  <input type="text" class="form-control" name="myls_llms_service_areas_limit" value="<?php echo esc_attr($area_limit); ?>" />
                </div>
              </div>
            </div>

            <div class="mb-2 mt-3">
              <label class="form-label" style="display:flex; gap:.5rem; align-items:center;">
                <input type="checkbox" name="myls_llms_include_faqs" value="1" <?php checked($inc_faqs); ?> />
                FAQs (master list from post meta <code>_myls_faq_items</code>)
              </label>
              <div class="row">
                <div class="col-12 col-md-4">
                  <label class="form-label">Max FAQ links</label>
                  <input type="text" class="form-control" name="myls_llms_faqs_limit" value="<?php echo esc_attr($faq_limit); ?>" />
                </div>
              </div>
            </div>

            <div class="mt-3">
              <button type="submit" name="myls_llms_save" class="btn btn-primary">Save llms.txt settings</button>
            </div>

          </form>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="cardish">
          <h3 style="margin-top:0;">Quick checks</h3>
          <p><strong>Endpoint:</strong><br />
            <a href="<?php echo esc_url($endpoint_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($endpoint_url); ?></a>
          </p>

          <ul style="margin:0; padding-left:1.1rem;">
            <li>If you see 404, flush rewrites: <em>Settings → Permalinks → Save Changes</em>.</li>
            <li>Keep limits reasonable (10–25 is usually enough).</li>
            <li>FAQs link to stable anchors like <code>#faq-1</code> on the page.</li>
          </ul>

          <hr />

          <p class="muted" style="margin:0;">Tip: If a page has more than one FAQ accordion, anchors may repeat. Best practice is one FAQ accordion per page.</p>
        </div>
      </div>
    </div>

    <?php
  }
];
