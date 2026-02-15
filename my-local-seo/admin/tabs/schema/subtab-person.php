<?php
/**
 * Subtab: Person / People
 * Path: admin/tabs/schema/subtab-person.php
 *
 * Multi-person support with per-person page assignment.
 * Stored as: myls_person_profiles => [ 0 => [...], 1 => [...], ... ]
 *
 * Each profile:
 *  - name, job_title, description, url, image_url, email, phone
 *  - honorific_prefix, gender
 *  - same_as => [ url, url, ... ]
 *  - knows_about => [ ['name'=>'', 'wikidata'=>'', 'wikipedia'=>''], ... ]
 *  - credentials => [ ['name'=>'', 'abbr'=>'', 'issuer'=>'', 'issuer_url'=>''], ... ]
 *  - alumni => [ ['name'=>'', 'url'=>''], ... ]
 *  - member_of => [ ['name'=>'', 'url'=>''], ... ]
 *  - awards => [ 'text', ... ]
 *  - languages => [ 'text', ... ]
 *  - works_for_override => '' (blank = use org)
 *  - pages => [ post_id, ... ]
 *  - enabled => '1'|'0'
 *
 * @since 4.12.0
 */

if (!defined('ABSPATH')) exit;

$spec = [
  'id'    => 'person',
  'label' => 'Person',
  'order' => 15,

  'render' => function () {

    // Enqueue media for image picker
    if (function_exists('wp_enqueue_media')) wp_enqueue_media();

    // Load saved profiles
    $profiles = get_option('myls_person_profiles', []);
    if (!is_array($profiles) || empty($profiles)) {
      $profiles = [ myls_person_default_profile() ];
    }

    // Ensure every profile has all keys
    foreach ($profiles as $i => $p) {
      $profiles[$i] = wp_parse_args($p, myls_person_default_profile());
    }

    // Assignable pages
    $assignable = get_posts([
      'post_type'   => ['page','post','service','service_area'],
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby'     => 'title',
      'order'       => 'asc',
    ]);

    // Org name for "worksFor" display
    $org_name = get_option('myls_org_name', get_bloginfo('name'));

    ?>
    <style>
      /* Person subtab scoped styles */
      .myls-person-wrap { width:100%; }

      /* Accordion */
      .myls-person-accordion { display:flex; flex-direction:column; gap:10px; }
      .myls-person-card { border:1px solid #000; border-radius:1em; overflow:hidden; background:#fff; }
      .myls-person-card.is-collapsed .myls-person-body { display:none; }
      .myls-person-header {
        display:flex; align-items:center; gap:10px; padding:12px 16px;
        background:#f8f9fa; cursor:pointer; user-select:none;
        border-bottom:1px solid #e5e5e5;
      }
      .myls-person-header:hover { background:#f0f0f0; }
      .myls-person-header .person-avatar {
        width:36px; height:36px; border-radius:50%; object-fit:cover;
        background:#e9ecef; flex-shrink:0;
      }
      .myls-person-header .person-avatar-placeholder {
        width:36px; height:36px; border-radius:50%; background:#e9ecef;
        display:flex; align-items:center; justify-content:center; font-size:16px; color:#adb5bd; flex-shrink:0;
      }
      .myls-person-header .person-info { flex:1; min-width:0; }
      .myls-person-header .person-name { font-weight:700; font-size:15px; }
      .myls-person-header .person-meta { font-size:12px; color:#6c757d; }
      .myls-person-header .toggle-icon { font-size:18px; color:#6c757d; transition:transform .2s; }
      .myls-person-card:not(.is-collapsed) .toggle-icon { transform:rotate(180deg); }
      .myls-person-header .person-badge {
        font-size:11px; padding:2px 8px; border-radius:10px; font-weight:600;
      }
      .myls-person-header .badge-enabled { background:#d1fae5; color:#065f46; }
      .myls-person-header .badge-disabled { background:#fee2e2; color:#991b1b; }

      .myls-person-body { padding:16px; }

      /* Grid layout inside each person */
      .myls-person-grid { display:flex; flex-wrap:wrap; gap:16px; }
      .myls-person-col-main { flex:2 1 400px; min-width:300px; }
      .myls-person-col-side { flex:1 1 280px; min-width:260px; }

      /* Field groups */
      .myls-fieldgroup {
        border:1px solid #e5e5e5; border-radius:.75em; padding:14px; margin-bottom:14px; background:#fafafa;
      }
      .myls-fieldgroup-title {
        font-weight:700; font-size:14px; margin:0 0 10px; display:flex; align-items:center; gap:6px;
      }

      /* Inputs */
      .myls-person-wrap input[type="text"], .myls-person-wrap input[type="email"],
      .myls-person-wrap input[type="url"], .myls-person-wrap input[type="tel"],
      .myls-person-wrap textarea, .myls-person-wrap select {
        border:1px solid #ced4da !important; border-radius:.5em !important; padding:.45rem .65rem; width:100%; font-size:14px;
      }
      .myls-person-wrap textarea { min-height:70px; }
      .myls-person-wrap .form-label { font-weight:600; margin-bottom:4px; display:block; font-size:13px; }
      .myls-person-wrap .form-hint { font-size:12px; color:#6c757d; margin-top:2px; }
      .myls-field-row { margin-bottom:10px; }
      .myls-field-half { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
      .myls-field-third { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }

      /* Repeater rows */
      .myls-repeater-row {
        display:flex; gap:6px; align-items:center; margin-bottom:6px;
      }
      .myls-repeater-row input { flex:1; }
      .myls-repeater-row .myls-btn-xs {
        flex-shrink:0; width:28px; height:28px; border-radius:50%; border:1px solid #ced4da;
        background:#fff; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center;
        color:#dc3545;
      }
      .myls-repeater-row .myls-btn-xs:hover { background:#fee2e2; }

      /* Composite repeater (multi-field per row) */
      .myls-composite-row {
        display:grid; gap:6px; margin-bottom:8px; padding:8px; background:#fff;
        border:1px solid #e9ecef; border-radius:.5em; position:relative;
      }
      .myls-composite-row.cols-2 { grid-template-columns:1fr 1fr; }
      .myls-composite-row.cols-3 { grid-template-columns:1fr 1fr 1fr; }
      .myls-composite-row.cols-4 { grid-template-columns:1fr 1fr 1fr 1fr; }
      .myls-composite-row .row-remove {
        position:absolute; top:4px; right:4px; width:20px; height:20px;
        border-radius:50%; border:none; background:#fee2e2; color:#dc3545;
        font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center;
        line-height:1;
      }

      /* Page assignment */
      .myls-page-list {
        max-height:200px; overflow-y:auto; border:1px solid #e5e5e5;
        border-radius:.5em; padding:6px; background:#fff;
      }
      .myls-page-list label { display:flex; align-items:center; gap:6px; padding:3px 4px; font-size:13px; cursor:pointer; }
      .myls-page-list label:hover { background:#f0f4ff; border-radius:4px; }

      /* Image preview */
      .myls-img-preview { display:flex; align-items:center; gap:10px; margin-top:6px; }
      .myls-img-preview img { width:60px; height:60px; object-fit:cover; border-radius:8px; border:1px solid #dee2e6; }

      /* Action buttons */
      .myls-person-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; padding-top:12px; border-top:1px solid #e5e5e5; }
      .myls-btn-sm {
        display:inline-flex; align-items:center; gap:4px;
        font-weight:600; font-size:13px; border:1px solid #ced4da; padding:6px 12px;
        border-radius:.5em; background:#fff; color:#212529; cursor:pointer;
      }
      .myls-btn-sm:hover { background:#f0f0f0; }
      .myls-btn-add { background:#d1fae5; border-color:#a7f3d0; color:#065f46; }
      .myls-btn-add:hover { background:#a7f3d0; }
      .myls-btn-danger { background:#fee2e2; border-color:#fecaca; color:#991b1b; }
      .myls-btn-danger:hover { background:#fecaca; }

      /* Info callout */
      .myls-info-box {
        background:#eff6ff; border:1px solid #bfdbfe; border-radius:.75em;
        padding:12px 14px; font-size:13px; color:#1e40af; line-height:1.5;
      }
      .myls-info-box strong { display:block; margin-bottom:2px; }

      @media (max-width: 700px) {
        .myls-field-half, .myls-field-third { grid-template-columns:1fr; }
        .myls-composite-row.cols-3, .myls-composite-row.cols-4 { grid-template-columns:1fr; }
        .myls-person-grid { flex-direction:column; }
      }
    </style>

    <div class="myls-person-wrap">

      <!-- Top info -->
      <div class="myls-info-box" style="margin-bottom:16px;">
        <strong><i class="bi bi-person-badge"></i> Person Schema — E-E-A-T &amp; AI Visibility</strong>
        Add owners, founders, or key team members. Each person gets their own schema markup on assigned pages.
        Connect expertise to Wikidata/Wikipedia for maximum AI citation potential.
        worksFor automatically links to your <strong><?php echo esc_html($org_name); ?></strong> Organization schema.
      </div>

      <!-- Accordion of people -->
      <div class="myls-person-accordion" id="myls-person-list">
        <?php foreach ($profiles as $idx => $p) :
          $name_display = $p['name'] ?: 'Person #' . ($idx + 1);
          $job_display = $p['job_title'] ?: 'No title set';
          $is_enabled = ($p['enabled'] ?? '1') === '1';
          $img_url = '';
          if (!empty($p['image_id'])) {
            $img_url = wp_get_attachment_image_url((int)$p['image_id'], 'thumbnail');
          }
          if (!$img_url && !empty($p['image_url'])) {
            $img_url = $p['image_url'];
          }
          $page_ids = array_map('absint', (array)($p['pages'] ?? []));
          $collapsed = $idx > 0 ? ' is-collapsed' : '';
        ?>
        <div class="myls-person-card<?php echo $collapsed; ?>" data-person-idx="<?php echo $idx; ?>">

          <!-- Header (click to toggle) -->
          <div class="myls-person-header" onclick="this.parentElement.classList.toggle('is-collapsed')">
            <?php if ($img_url): ?>
              <img class="person-avatar" src="<?php echo esc_url($img_url); ?>" alt="" />
            <?php else: ?>
              <span class="person-avatar-placeholder"><i class="bi bi-person"></i></span>
            <?php endif; ?>
            <div class="person-info">
              <span class="person-name"><?php echo esc_html($name_display); ?></span>
              <span class="person-meta"><?php echo esc_html($job_display); ?> · <?php echo count($page_ids); ?> page(s) assigned</span>
            </div>
            <span class="person-badge <?php echo $is_enabled ? 'badge-enabled' : 'badge-disabled'; ?>">
              <?php echo $is_enabled ? 'Active' : 'Disabled'; ?>
            </span>
            <span class="toggle-icon"><i class="bi bi-chevron-down"></i></span>
          </div>

          <!-- Body -->
          <div class="myls-person-body">
            <div class="myls-person-grid">

              <!-- LEFT COLUMN: All fields -->
              <div class="myls-person-col-main">

                <!-- Enable + Basics -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-person-fill"></i> Identity</div>

                  <div style="margin-bottom:10px;">
                    <label style="display:flex;align-items:center;gap:6px;font-weight:600;font-size:14px;cursor:pointer;">
                      <input type="checkbox" name="myls_person[<?php echo $idx; ?>][enabled]" value="1" <?php checked($p['enabled'] ?? '1', '1'); ?> />
                      Enable Person Schema for this profile
                    </label>
                  </div>

                  <div class="myls-field-half">
                    <div class="myls-field-row">
                      <label class="form-label">Full Name <span style="color:#dc3545;">*</span></label>
                      <input type="text" name="myls_person[<?php echo $idx; ?>][name]" value="<?php echo esc_attr($p['name']); ?>" placeholder="Jane Smith" />
                    </div>
                    <div class="myls-field-row">
                      <label class="form-label">Job Title</label>
                      <input type="text" name="myls_person[<?php echo $idx; ?>][job_title]" value="<?php echo esc_attr($p['job_title']); ?>" placeholder="Owner &amp; Founder" />
                    </div>
                  </div>

                  <div class="myls-field-half">
                    <div class="myls-field-row">
                      <label class="form-label">Honorific Prefix</label>
                      <input type="text" name="myls_person[<?php echo $idx; ?>][honorific_prefix]" value="<?php echo esc_attr($p['honorific_prefix'] ?? ''); ?>" placeholder="Dr., Rev., etc." />
                    </div>
                    <div class="myls-field-row">
                      <label class="form-label">Profile / About URL</label>
                      <input type="url" name="myls_person[<?php echo $idx; ?>][url]" value="<?php echo esc_attr($p['url']); ?>" placeholder="https://yoursite.com/about" />
                    </div>
                  </div>

                  <div class="myls-field-row">
                    <label class="form-label">Bio / Description</label>
                    <textarea name="myls_person[<?php echo $idx; ?>][description]" placeholder="Brief professional bio (1-3 sentences recommended)"><?php echo esc_textarea($p['description']); ?></textarea>
                  </div>

                  <div class="myls-field-half">
                    <div class="myls-field-row">
                      <label class="form-label">Email</label>
                      <input type="email" name="myls_person[<?php echo $idx; ?>][email]" value="<?php echo esc_attr($p['email'] ?? ''); ?>" placeholder="jane@example.com" />
                    </div>
                    <div class="myls-field-row">
                      <label class="form-label">Phone</label>
                      <input type="tel" name="myls_person[<?php echo $idx; ?>][phone]" value="<?php echo esc_attr($p['phone'] ?? ''); ?>" placeholder="+1-555-123-4567" />
                    </div>
                  </div>

                  <!-- Image picker -->
                  <div class="myls-field-row">
                    <label class="form-label">Photo / Headshot</label>
                    <input type="hidden" class="person-image-id" name="myls_person[<?php echo $idx; ?>][image_id]" value="<?php echo esc_attr($p['image_id'] ?? ''); ?>" />
                    <input type="url" class="person-image-url" name="myls_person[<?php echo $idx; ?>][image_url]" value="<?php echo esc_attr($p['image_url'] ?? ''); ?>" placeholder="Or paste image URL directly" />
                    <div class="myls-img-preview">
                      <?php if ($img_url): ?>
                        <img src="<?php echo esc_url($img_url); ?>" alt="" />
                      <?php endif; ?>
                      <button type="button" class="myls-btn-sm" onclick="mylsPersonPickImage(this, <?php echo $idx; ?>)"><i class="bi bi-image"></i> Choose Image</button>
                    </div>
                  </div>
                </div>

                <!-- sameAs (Social / Profiles) -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-link-45deg"></i> Social Profiles &amp; sameAs</div>
                  <div class="form-hint" style="margin-bottom:8px;">LinkedIn, Facebook, X/Twitter, YouTube, Wikipedia, Wikidata, Crunchbase, etc.</div>
                  <div class="myls-repeater" data-field="same_as" data-idx="<?php echo $idx; ?>">
                    <?php
                    $same_as = (array)($p['same_as'] ?? ['']);
                    if (empty($same_as)) $same_as = [''];
                    foreach ($same_as as $si => $sa_url): ?>
                    <div class="myls-repeater-row">
                      <input type="url" name="myls_person[<?php echo $idx; ?>][same_as][]" value="<?php echo esc_attr($sa_url); ?>" placeholder="https://linkedin.com/in/..." />
                      <button type="button" class="myls-btn-xs" onclick="this.parentElement.remove()" title="Remove">×</button>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="myls-btn-sm myls-btn-add" onclick="mylsPersonAddRepeater(this, 'same_as')">
                    <i class="bi bi-plus-circle"></i> Add Profile
                  </button>
                </div>

                <!-- knowsAbout -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-lightbulb"></i> Areas of Expertise (knowsAbout)</div>
                  <div class="form-hint" style="margin-bottom:8px;">Link topics to Wikidata &amp; Wikipedia for best AI recognition. <a href="https://www.wikidata.org/" target="_blank" rel="noopener">Search Wikidata →</a></div>
                  <div class="myls-composite-repeater" data-field="knows_about" data-idx="<?php echo $idx; ?>">
                    <?php
                    $knows = (array)($p['knows_about'] ?? []);
                    if (empty($knows)) $knows = [['name'=>'','wikidata'=>'','wikipedia'=>'']];
                    foreach ($knows as $ki => $k): ?>
                    <div class="myls-composite-row cols-3">
                      <div>
                        <label class="form-label">Topic Name</label>
                        <input type="text" name="myls_person[<?php echo $idx; ?>][knows_about][<?php echo $ki; ?>][name]" value="<?php echo esc_attr($k['name'] ?? ''); ?>" placeholder="e.g. Plumbing" />
                      </div>
                      <div>
                        <label class="form-label">Wikidata URL</label>
                        <input type="url" name="myls_person[<?php echo $idx; ?>][knows_about][<?php echo $ki; ?>][wikidata]" value="<?php echo esc_attr($k['wikidata'] ?? ''); ?>" placeholder="https://www.wikidata.org/wiki/Q..." />
                      </div>
                      <div>
                        <label class="form-label">Wikipedia URL</label>
                        <input type="url" name="myls_person[<?php echo $idx; ?>][knows_about][<?php echo $ki; ?>][wikipedia]" value="<?php echo esc_attr($k['wikipedia'] ?? ''); ?>" placeholder="https://en.wikipedia.org/wiki/..." />
                      </div>
                      <button type="button" class="row-remove" onclick="this.parentElement.remove()" title="Remove">×</button>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="myls-btn-sm myls-btn-add" onclick="mylsPersonAddComposite(this, 'knows_about', ['name','wikidata','wikipedia'], ['Topic Name','Wikidata URL','Wikipedia URL'], ['e.g. HVAC','https://www.wikidata.org/wiki/Q...','https://en.wikipedia.org/wiki/...'])">
                    <i class="bi bi-plus-circle"></i> Add Topic
                  </button>
                </div>

                <!-- hasCredential -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-award"></i> Credentials &amp; Licenses</div>
                  <div class="form-hint" style="margin-bottom:8px;">Professional licenses, certifications (CPA, CFP, state contractor license, etc.)</div>
                  <div class="myls-composite-repeater" data-field="credentials" data-idx="<?php echo $idx; ?>">
                    <?php
                    $creds = (array)($p['credentials'] ?? []);
                    if (empty($creds)) $creds = [['name'=>'','abbr'=>'','issuer'=>'','issuer_url'=>'']];
                    foreach ($creds as $ci => $c): ?>
                    <div class="myls-composite-row cols-4">
                      <div>
                        <label class="form-label">Credential Name</label>
                        <input type="text" name="myls_person[<?php echo $idx; ?>][credentials][<?php echo $ci; ?>][name]" value="<?php echo esc_attr($c['name'] ?? ''); ?>" placeholder="Certified Financial Planner" />
                      </div>
                      <div>
                        <label class="form-label">Abbreviation</label>
                        <input type="text" name="myls_person[<?php echo $idx; ?>][credentials][<?php echo $ci; ?>][abbr]" value="<?php echo esc_attr($c['abbr'] ?? ''); ?>" placeholder="CFP" />
                      </div>
                      <div>
                        <label class="form-label">Issuing Organization</label>
                        <input type="text" name="myls_person[<?php echo $idx; ?>][credentials][<?php echo $ci; ?>][issuer]" value="<?php echo esc_attr($c['issuer'] ?? ''); ?>" placeholder="CFP Board" />
                      </div>
                      <div>
                        <label class="form-label">Issuer URL</label>
                        <input type="url" name="myls_person[<?php echo $idx; ?>][credentials][<?php echo $ci; ?>][issuer_url]" value="<?php echo esc_attr($c['issuer_url'] ?? ''); ?>" placeholder="https://www.cfp.net/" />
                      </div>
                      <button type="button" class="row-remove" onclick="this.parentElement.remove()" title="Remove">×</button>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="myls-btn-sm myls-btn-add" onclick="mylsPersonAddComposite(this, 'credentials', ['name','abbr','issuer','issuer_url'], ['Credential Name','Abbreviation','Issuing Org','Issuer URL'], ['Licensed Plumber','LP','State Board','https://...'])">
                    <i class="bi bi-plus-circle"></i> Add Credential
                  </button>
                </div>

                <!-- alumniOf -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-mortarboard"></i> Education (alumniOf)</div>
                  <div class="myls-composite-repeater" data-field="alumni" data-idx="<?php echo $idx; ?>">
                    <?php
                    $alumni = (array)($p['alumni'] ?? []);
                    if (empty($alumni)) $alumni = [['name'=>'','url'=>'']];
                    foreach ($alumni as $ai => $a): ?>
                    <div class="myls-composite-row cols-2">
                      <div>
                        <label class="form-label">Institution Name</label>
                        <input type="text" name="myls_person[<?php echo $idx; ?>][alumni][<?php echo $ai; ?>][name]" value="<?php echo esc_attr($a['name'] ?? ''); ?>" placeholder="University of Florida" />
                      </div>
                      <div>
                        <label class="form-label">Institution URL</label>
                        <input type="url" name="myls_person[<?php echo $idx; ?>][alumni][<?php echo $ai; ?>][url]" value="<?php echo esc_attr($a['url'] ?? ''); ?>" placeholder="https://www.ufl.edu/" />
                      </div>
                      <button type="button" class="row-remove" onclick="this.parentElement.remove()" title="Remove">×</button>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="myls-btn-sm myls-btn-add" onclick="mylsPersonAddComposite(this, 'alumni', ['name','url'], ['Institution Name','Institution URL'], ['MIT','https://www.mit.edu/'])">
                    <i class="bi bi-plus-circle"></i> Add School
                  </button>
                </div>

                <!-- memberOf -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-people"></i> Memberships (memberOf)</div>
                  <div class="form-hint" style="margin-bottom:8px;">Trade associations, BBB, Chamber of Commerce, etc.</div>
                  <div class="myls-composite-repeater" data-field="member_of" data-idx="<?php echo $idx; ?>">
                    <?php
                    $members = (array)($p['member_of'] ?? []);
                    if (empty($members)) $members = [['name'=>'','url'=>'']];
                    foreach ($members as $mi => $m): ?>
                    <div class="myls-composite-row cols-2">
                      <div>
                        <label class="form-label">Organization Name</label>
                        <input type="text" name="myls_person[<?php echo $idx; ?>][member_of][<?php echo $mi; ?>][name]" value="<?php echo esc_attr($m['name'] ?? ''); ?>" placeholder="Better Business Bureau" />
                      </div>
                      <div>
                        <label class="form-label">Organization URL</label>
                        <input type="url" name="myls_person[<?php echo $idx; ?>][member_of][<?php echo $mi; ?>][url]" value="<?php echo esc_attr($m['url'] ?? ''); ?>" placeholder="https://www.bbb.org/" />
                      </div>
                      <button type="button" class="row-remove" onclick="this.parentElement.remove()" title="Remove">×</button>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="myls-btn-sm myls-btn-add" onclick="mylsPersonAddComposite(this, 'member_of', ['name','url'], ['Org Name','Org URL'], ['Chamber of Commerce','https://...'])">
                    <i class="bi bi-plus-circle"></i> Add Membership
                  </button>
                </div>

                <!-- Awards + Languages (simple repeaters side by side) -->
                <div class="myls-field-half">
                  <div class="myls-fieldgroup">
                    <div class="myls-fieldgroup-title"><i class="bi bi-trophy"></i> Awards</div>
                    <div class="myls-repeater" data-field="awards" data-idx="<?php echo $idx; ?>">
                      <?php
                      $awards = (array)($p['awards'] ?? ['']);
                      if (empty($awards)) $awards = [''];
                      foreach ($awards as $aw): ?>
                      <div class="myls-repeater-row">
                        <input type="text" name="myls_person[<?php echo $idx; ?>][awards][]" value="<?php echo esc_attr($aw); ?>" placeholder="Best of 2024" />
                        <button type="button" class="myls-btn-xs" onclick="this.parentElement.remove()">×</button>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <button type="button" class="myls-btn-sm myls-btn-add" onclick="mylsPersonAddRepeater(this, 'awards')"><i class="bi bi-plus-circle"></i> Add</button>
                  </div>
                  <div class="myls-fieldgroup">
                    <div class="myls-fieldgroup-title"><i class="bi bi-translate"></i> Languages</div>
                    <div class="myls-repeater" data-field="languages" data-idx="<?php echo $idx; ?>">
                      <?php
                      $langs = (array)($p['languages'] ?? ['']);
                      if (empty($langs)) $langs = [''];
                      foreach ($langs as $lg): ?>
                      <div class="myls-repeater-row">
                        <input type="text" name="myls_person[<?php echo $idx; ?>][languages][]" value="<?php echo esc_attr($lg); ?>" placeholder="English" />
                        <button type="button" class="myls-btn-xs" onclick="this.parentElement.remove()">×</button>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <button type="button" class="myls-btn-sm myls-btn-add" onclick="mylsPersonAddRepeater(this, 'languages')"><i class="bi bi-plus-circle"></i> Add</button>
                  </div>
                </div>

              </div><!-- /col-main -->

              <!-- RIGHT COLUMN: Page assignment + tips -->
              <div class="myls-person-col-side">

                <!-- Page Assignment -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-file-earmark-check"></i> Page Assignment</div>
                  <div class="form-hint" style="margin-bottom:8px;">Schema outputs only on checked pages.</div>
                  <div class="myls-page-list">
                    <?php foreach ($assignable as $post):
                      $checked = in_array($post->ID, $page_ids) ? 'checked' : '';
                      $type_label = get_post_type_object($post->post_type)->labels->singular_name ?? $post->post_type;
                    ?>
                    <label>
                      <input type="checkbox" name="myls_person[<?php echo $idx; ?>][pages][]" value="<?php echo $post->ID; ?>" <?php echo $checked; ?> />
                      <?php echo esc_html($post->post_title); ?>
                      <span style="color:#adb5bd;font-size:11px;">(<?php echo esc_html($type_label); ?>)</span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- Tips -->
                <div class="myls-fieldgroup" style="background:#eff6ff; border-color:#bfdbfe;">
                  <div class="myls-fieldgroup-title" style="color:#1e40af;"><i class="bi bi-info-circle"></i> Pro Tips</div>
                  <ul style="margin:0;padding-left:1.1rem;font-size:13px;color:#1e40af;line-height:1.6;">
                    <li><strong>sameAs</strong> — LinkedIn is the #1 most impactful profile link for E-E-A-T</li>
                    <li><strong>knowsAbout</strong> — Use Wikidata IDs (Q-numbers) to connect topics to Google's Knowledge Graph</li>
                    <li><strong>hasCredential</strong> — State licenses and industry certifications build trust signals</li>
                    <li><strong>worksFor</strong> — Automatically linked to your Organization schema</li>
                    <li><strong>Best pages</strong> — Assign to About, Homepage, and key service pages</li>
                    <li>AI assistants use this data to verify expertise when citing your content</li>
                  </ul>
                </div>

                <!-- Schema preview hint -->
                <div class="myls-fieldgroup">
                  <div class="myls-fieldgroup-title"><i class="bi bi-code-slash"></i> Output</div>
                  <p style="font-size:13px;margin:0;color:#6c757d;">
                    JSON-LD will output in <code>&lt;head&gt;</code> on assigned pages.
                    Validate with <a href="https://validator.schema.org/" target="_blank" rel="noopener">Schema.org Validator</a>
                    or the Admin Bar → SEO Stuff → Test Schema.ORG link.
                  </p>
                </div>

                <!-- Remove person button -->
                <?php if (count($profiles) > 1): ?>
                <div style="margin-top:8px;">
                  <button type="button" class="myls-btn-sm myls-btn-danger" onclick="if(confirm('Remove this person profile? This cannot be undone.')) this.closest('.myls-person-card').remove()">
                    <i class="bi bi-trash"></i> Remove This Person
                  </button>
                </div>
                <?php endif; ?>

              </div><!-- /col-side -->
            </div><!-- /grid -->
          </div><!-- /body -->
        </div><!-- /card -->
        <?php endforeach; ?>
      </div><!-- /accordion -->

      <!-- Add Person button -->
      <div style="margin-top:14px;">
        <button type="button" class="myls-btn-sm myls-btn-add" id="myls-add-person" style="font-size:14px;padding:8px 16px;">
          <i class="bi bi-person-plus"></i> Add Another Person
        </button>
      </div>

    </div><!-- /wrap -->

    <script>
    (function(){
      /* Simple repeater: sameAs, awards, languages */
      window.mylsPersonAddRepeater = function(btn, field) {
        const container = btn.previousElementSibling;
        const idx = container.dataset.idx;
        const row = document.createElement('div');
        row.className = 'myls-repeater-row';
        row.innerHTML = '<input type="url" name="myls_person['+idx+']['+field+'][]" value="" placeholder="" />'
          + '<button type="button" class="myls-btn-xs" onclick="this.parentElement.remove()" title="Remove">×</button>';
        if (field === 'awards' || field === 'languages') {
          row.querySelector('input').type = 'text';
        }
        container.appendChild(row);
        row.querySelector('input').focus();
      };

      /* Composite repeater: knowsAbout, credentials, alumni, memberOf */
      window.mylsPersonAddComposite = function(btn, field, keys, labels, placeholders) {
        const container = btn.previousElementSibling;
        const idx = container.dataset.idx;
        // Find next sub-index
        const existing = container.querySelectorAll('.myls-composite-row');
        const subIdx = existing.length;
        const colClass = 'cols-' + keys.length;
        const row = document.createElement('div');
        row.className = 'myls-composite-row ' + colClass;
        let html = '';
        for (let i = 0; i < keys.length; i++) {
          html += '<div><label class="form-label">'+labels[i]+'</label>';
          const inputType = keys[i].includes('url') || keys[i].includes('wikidata') || keys[i].includes('wikipedia') ? 'url' : 'text';
          html += '<input type="'+inputType+'" name="myls_person['+idx+']['+field+']['+subIdx+']['+keys[i]+']" value="" placeholder="'+placeholders[i]+'" />';
          html += '</div>';
        }
        html += '<button type="button" class="row-remove" onclick="this.parentElement.remove()" title="Remove">×</button>';
        row.innerHTML = html;
        container.appendChild(row);
        row.querySelector('input').focus();
      };

      /* Image picker via WP Media */
      window.mylsPersonPickImage = function(btn, idx) {
        const frame = wp.media({ title: 'Select Person Photo', multiple: false, library: { type: 'image' } });
        frame.on('select', function() {
          const att = frame.state().get('selection').first().toJSON();
          const card = btn.closest('.myls-person-card');
          card.querySelector('.person-image-id').value = att.id;
          card.querySelector('.person-image-url').value = att.url;
          const preview = btn.closest('.myls-img-preview');
          let img = preview.querySelector('img');
          if (!img) { img = document.createElement('img'); preview.prepend(img); }
          img.src = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
        });
        frame.open();
      };

      /* Add new person (clone first profile as template) */
      document.getElementById('myls-add-person')?.addEventListener('click', function() {
        const list = document.getElementById('myls-person-list');
        const cards = list.querySelectorAll('.myls-person-card');
        const newIdx = cards.length;
        const first = cards[0];
        const clone = first.cloneNode(true);

        // Update data-idx
        clone.dataset.personIdx = newIdx;

        // Clear all inputs
        clone.querySelectorAll('input[type="text"], input[type="email"], input[type="url"], input[type="tel"], textarea').forEach(function(el) { el.value = ''; });
        clone.querySelectorAll('input[type="checkbox"]').forEach(function(el) { el.checked = el.value === '1'; }); // enable by default
        clone.querySelectorAll('input[type="checkbox"][name*="[pages]"]').forEach(function(el) { el.checked = false; });

        // Update all name attributes
        clone.querySelectorAll('[name]').forEach(function(el) {
          el.name = el.name.replace(/myls_person\[\d+\]/, 'myls_person[' + newIdx + ']');
        });

        // Update repeater data-idx
        clone.querySelectorAll('[data-idx]').forEach(function(el) { el.dataset.idx = newIdx; });

        // Update header display
        const nameEl = clone.querySelector('.person-name');
        if (nameEl) nameEl.textContent = 'Person #' + (newIdx + 1);
        const metaEl = clone.querySelector('.person-meta');
        if (metaEl) metaEl.textContent = 'No title set · 0 page(s) assigned';

        // Clear image preview
        const imgPreview = clone.querySelector('.myls-img-preview img');
        if (imgPreview) imgPreview.remove();
        const avatarImg = clone.querySelector('.person-avatar');
        if (avatarImg) {
          const placeholder = document.createElement('span');
          placeholder.className = 'person-avatar-placeholder';
          placeholder.innerHTML = '<i class="bi bi-person"></i>';
          avatarImg.replaceWith(placeholder);
        }

        // Start collapsed
        clone.classList.remove('is-collapsed');

        // Ensure remove button exists
        let removeBtn = clone.querySelector('.myls-btn-danger');
        if (!removeBtn) {
          const sideCol = clone.querySelector('.myls-person-col-side');
          if (sideCol) {
            const div = document.createElement('div');
            div.style.marginTop = '8px';
            div.innerHTML = '<button type="button" class="myls-btn-sm myls-btn-danger" onclick="if(confirm(\'Remove this person profile?\')) this.closest(\'.myls-person-card\').remove()"><i class="bi bi-trash"></i> Remove This Person</button>';
            sideCol.appendChild(div);
          }
        }

        list.appendChild(clone);
        clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });

    })();
    </script>
    <?php
  },

  'on_save' => function () {
    $raw = $_POST['myls_person'] ?? [];
    if (!is_array($raw)) $raw = [];

    $profiles = [];
    foreach ($raw as $idx => $data) {
      if (!is_array($data)) continue;

      $p = myls_person_default_profile();
      $p['enabled']          = !empty($data['enabled']) ? '1' : '0';
      $p['name']             = sanitize_text_field(wp_unslash($data['name'] ?? ''));
      $p['job_title']        = sanitize_text_field(wp_unslash($data['job_title'] ?? ''));
      $p['honorific_prefix'] = sanitize_text_field(wp_unslash($data['honorific_prefix'] ?? ''));
      $p['description']      = sanitize_textarea_field(wp_unslash($data['description'] ?? ''));
      $p['url']              = esc_url_raw(wp_unslash($data['url'] ?? ''));
      $p['email']            = sanitize_email(wp_unslash($data['email'] ?? ''));
      $p['phone']            = sanitize_text_field(wp_unslash($data['phone'] ?? ''));
      $p['image_id']         = absint($data['image_id'] ?? 0);
      $p['image_url']        = esc_url_raw(wp_unslash($data['image_url'] ?? ''));

      // sameAs
      $p['same_as'] = [];
      if (!empty($data['same_as']) && is_array($data['same_as'])) {
        foreach ($data['same_as'] as $url) {
          $url = esc_url_raw(wp_unslash(trim($url)));
          if ($url) $p['same_as'][] = $url;
        }
      }

      // knowsAbout
      $p['knows_about'] = [];
      if (!empty($data['knows_about']) && is_array($data['knows_about'])) {
        foreach ($data['knows_about'] as $ka) {
          $name = sanitize_text_field(wp_unslash($ka['name'] ?? ''));
          if (!$name) continue;
          $p['knows_about'][] = [
            'name'      => $name,
            'wikidata'  => esc_url_raw(wp_unslash($ka['wikidata'] ?? '')),
            'wikipedia' => esc_url_raw(wp_unslash($ka['wikipedia'] ?? '')),
          ];
        }
      }

      // credentials
      $p['credentials'] = [];
      if (!empty($data['credentials']) && is_array($data['credentials'])) {
        foreach ($data['credentials'] as $cr) {
          $name = sanitize_text_field(wp_unslash($cr['name'] ?? ''));
          if (!$name) continue;
          $p['credentials'][] = [
            'name'       => $name,
            'abbr'       => sanitize_text_field(wp_unslash($cr['abbr'] ?? '')),
            'issuer'     => sanitize_text_field(wp_unslash($cr['issuer'] ?? '')),
            'issuer_url' => esc_url_raw(wp_unslash($cr['issuer_url'] ?? '')),
          ];
        }
      }

      // alumni
      $p['alumni'] = [];
      if (!empty($data['alumni']) && is_array($data['alumni'])) {
        foreach ($data['alumni'] as $al) {
          $name = sanitize_text_field(wp_unslash($al['name'] ?? ''));
          if (!$name) continue;
          $p['alumni'][] = [
            'name' => $name,
            'url'  => esc_url_raw(wp_unslash($al['url'] ?? '')),
          ];
        }
      }

      // memberOf
      $p['member_of'] = [];
      if (!empty($data['member_of']) && is_array($data['member_of'])) {
        foreach ($data['member_of'] as $mo) {
          $name = sanitize_text_field(wp_unslash($mo['name'] ?? ''));
          if (!$name) continue;
          $p['member_of'][] = [
            'name' => $name,
            'url'  => esc_url_raw(wp_unslash($mo['url'] ?? '')),
          ];
        }
      }

      // awards
      $p['awards'] = [];
      if (!empty($data['awards']) && is_array($data['awards'])) {
        foreach ($data['awards'] as $aw) {
          $aw = sanitize_text_field(wp_unslash(trim($aw)));
          if ($aw) $p['awards'][] = $aw;
        }
      }

      // languages
      $p['languages'] = [];
      if (!empty($data['languages']) && is_array($data['languages'])) {
        foreach ($data['languages'] as $lg) {
          $lg = sanitize_text_field(wp_unslash(trim($lg)));
          if ($lg) $p['languages'][] = $lg;
        }
      }

      // pages
      $p['pages'] = [];
      if (!empty($data['pages']) && is_array($data['pages'])) {
        $p['pages'] = array_map('absint', $data['pages']);
      }

      // Only save if name is set
      if ($p['name']) {
        $profiles[] = $p;
      }
    }

    update_option('myls_person_profiles', $profiles);
  },
];

/* Helper: default empty profile */
if (!function_exists('myls_person_default_profile')) {
  function myls_person_default_profile(): array {
    return [
      'enabled'          => '1',
      'name'             => '',
      'job_title'        => '',
      'honorific_prefix' => '',
      'description'      => '',
      'url'              => '',
      'email'            => '',
      'phone'            => '',
      'image_id'         => 0,
      'image_url'        => '',
      'same_as'          => [],
      'knows_about'      => [],
      'credentials'      => [],
      'alumni'           => [],
      'member_of'        => [],
      'awards'           => [],
      'languages'        => [],
      'pages'            => [],
    ];
  }
}

return $spec;
