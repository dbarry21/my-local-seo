<?php if (!defined('ABSPATH')) exit;

$spec = [
  'id'    => 'faq',
  'label' => 'FAQ',
  'order' => 50,

  'render'=> function () {
    $enabled = get_option('myls_faq_enabled', '0'); // '0' or '1'
    ?>
    <!-- IMPORTANT: no <form>; this lives inside the main tab's form -->
    <div class="container-fluid px-0 myls-rounded">
      <div class="row g-3">
        <!-- LEFT: Form card -->
        <div class="col-12 col-lg-8">
          <div class="card mb-0 shadow-sm myls-card h-100">
            <div class="card-header bg-primary text-white">
              <strong>FAQ Schema</strong>
            </div>
            <div class="card-body">

              <!-- Enable toggle (switch) -->
              <div class="form-check form-switch mb-3">
                <input type="hidden" name="myls_faq_enabled" value="0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="myls_faq_enabled"
                  name="myls_faq_enabled"
                  value="1"
                  <?php checked('1', $enabled); ?>
                >
                <label class="form-check-label" for="myls_faq_enabled">
                  Enable FAQPage / FAQ markup
                </label>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save</button>
              </div>

            </div>
          </div>
        </div>

        <!-- RIGHT: Info card -->
        <div class="col-12 col-lg-4">
          <div class="card mb-0 shadow-sm myls-card h-100">
            <div class="card-header bg-primary text-white">
              <strong>Info</strong>
            </div>
            <div class="card-body">
              <p class="mb-2">
                This toggle enables the framework to output <em>FAQPage</em> / FAQ markup where appropriate.
                Individual Q&amp;A items are managed on the specific pages/posts (not here).
              </p>
              <ul class="mb-0">
                <li>Great for service pages with a short Q&amp;A section.</li>
                <li>Pairs with Organization/LocalBusiness data.</li>
                <li>Keep answers concise; avoid heavy HTML in JSON-LD.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
  },

  'on_save'=> function () {
    if (
      ! isset($_POST['myls_schema_nonce']) ||
      ! wp_verify_nonce($_POST['myls_schema_nonce'],'myls_schema_save') ||
      ! current_user_can('manage_options')
    ) {
      return;
    }

    // Only enable/disable here (Q&A moved to individual pages)
    $val = (isset($_POST['myls_faq_enabled']) && $_POST['myls_faq_enabled'] === '1') ? '1' : '0';
    update_option('myls_faq_enabled', $val);
  }
];

if (defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY) return $spec;
return null;
