<?php if (!defined('ABSPATH')) exit;

$spec = [
  'id'    => 'localbusiness',
  'label' => 'Local Business',
  'order' => 20,

  'render'=> function () {

    // ---------- US states & Countries ----------
    $us_states = [
      'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut',
      'DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa',
      'KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan',
      'MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri','MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire',
      'NJ'=>'New Jersey','NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio','OK'=>'Oklahoma',
      'OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota','TN'=>'Tennessee',
      'TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont','VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming',
      'DC'=>'District of Columbia','PR'=>'Puerto Rico'
    ];
    $state_name_to_code = [];
    foreach ($us_states as $code => $name) $state_name_to_code[strtolower($name)] = $code;

    $countries = [
      'US'=>'United States','CA'=>'Canada','MX'=>'Mexico','GB'=>'United Kingdom','IE'=>'Ireland','AU'=>'Australia','NZ'=>'New Zealand',
      'DE'=>'Germany','FR'=>'France','ES'=>'Spain','IT'=>'Italy','NL'=>'Netherlands','SE'=>'Sweden','NO'=>'Norway','DK'=>'Denmark',
      'FI'=>'Finland','PT'=>'Portugal','CH'=>'Switzerland','AT'=>'Austria','BE'=>'Belgium','PL'=>'Poland','CZ'=>'Czech Republic',
      'JP'=>'Japan','SG'=>'Singapore','IN'=>'India','ZA'=>'South Africa','BR'=>'Brazil','AR'=>'Argentina'
    ];

    // ---------- Normalizers ----------
    $normalize_state = function($state) use ($us_states, $state_name_to_code) {
      $s = trim((string)$state);
      if (isset($us_states[strtoupper($s)])) return strtoupper($s);
      $k = strtolower($s);
      return $state_name_to_code[$k] ?? '';
    };
    $normalize_country = function($country) use ($countries) {
      $c = trim((string)$country);
      if (isset($countries[strtoupper($c)])) return strtoupper($c);
      foreach ($countries as $code=>$name) if (strcasecmp($name, $c) === 0) return $code;
      return 'US';
    };

    // ---------- Collect raw org options with fallbacks to ssseo_* ----------
    $org_options_raw = [
      '_myls' => [
        'name'     => get_option('myls_org_name', ''),
        'tel'      => get_option('myls_org_tel', ''),
        'street'   => get_option('myls_org_street', ''),
        'locality' => get_option('myls_org_locality', ''),
        'region'   => get_option('myls_org_region', ''),
        'postal'   => get_option('myls_org_postal', ''),
        'country'  => get_option('myls_org_country', ''),
        'lat'      => get_option('myls_org_lat', ''),
        'lng'      => get_option('myls_org_lng', ''),
      ],
      '_ssseo' => [
        'name'     => get_option('ssseo_organization_name',''),
        'tel'      => get_option('ssseo_organization_phone',''),
        'street'   => get_option('ssseo_organization_address',''),
        'locality' => get_option('ssseo_organization_locality',''),
        'region'   => get_option('ssseo_organization_state',''),
        'postal'   => get_option('ssseo_organization_postal_code',''),
        'country'  => get_option('ssseo_organization_country',''),
        'lat'      => get_option('ssseo_organization_latitude',''),
        'lng'      => get_option('ssseo_organization_longitude',''),
      ],
    ];

    // Effective org values = myls_* OR fallback to ssseo_*
    $org_effective = [
      'name'     => $org_options_raw['_myls']['name']     !== '' ? $org_options_raw['_myls']['name']     : $org_options_raw['_ssseo']['name'],
      'tel'      => $org_options_raw['_myls']['tel']      !== '' ? $org_options_raw['_myls']['tel']      : $org_options_raw['_ssseo']['tel'],
      'street'   => $org_options_raw['_myls']['street']   !== '' ? $org_options_raw['_myls']['street']   : $org_options_raw['_ssseo']['street'],
      'locality' => $org_options_raw['_myls']['locality'] !== '' ? $org_options_raw['_myls']['locality'] : $org_options_raw['_ssseo']['locality'],
      'region'   => $org_options_raw['_myls']['region']   !== '' ? $org_options_raw['_myls']['region']   : $org_options_raw['_ssseo']['region'],
      'postal'   => $org_options_raw['_myls']['postal']   !== '' ? $org_options_raw['_myls']['postal']   : $org_options_raw['_ssseo']['postal'],
      'country'  => $org_options_raw['_myls']['country']  !== '' ? $org_options_raw['_myls']['country']  : $org_options_raw['_ssseo']['country'],
      'lat'      => $org_options_raw['_myls']['lat']      !== '' ? $org_options_raw['_myls']['lat']      : $org_options_raw['_ssseo']['lat'],
      'lng'      => $org_options_raw['_myls']['lng']      !== '' ? $org_options_raw['_myls']['lng']      : $org_options_raw['_ssseo']['lng'],
    ];

    // ---------- Organization defaults (normalized to codes) ----------
    $org_defaults = [
      'location_label' => 'Headquarters (Default)',
      'name'    => $org_effective['name'],
      'phone'   => $org_effective['tel'],
      'price'   => '',
      'street'  => $org_effective['street'],
      'city'    => $org_effective['locality'],
      'state'   => $normalize_state($org_effective['region']),
      'zip'     => $org_effective['postal'],
      'country' => $normalize_country($org_effective['country']),
      'lat'     => $org_effective['lat'],
      'lng'     => $org_effective['lng'],
      'hours'   => [['day'=>'','open'=>'','close'=>'']],
      'pages'   => [],
    ];

    // ---------- DEBUG scaffold ----------
    $debug = [
      'org_options'   => $org_options_raw,
      'org_effective' => $org_effective,
      'org_defaults'  => $org_defaults,
      'raw_option'    => null,
      'seed_path'     => [],
      'locations_after' => null,
    ];

    // ---------- Load & prefill (fixed: empty-safe merge) ----------
    $locations_raw = get_option('myls_lb_locations', []);
    $debug['raw_option'] = $locations_raw;

    if (!is_array($locations_raw)) $locations_raw = [];

    $merge_with_defaults = function(array $defaults, array $loc) use ($normalize_state, $normalize_country) : array {
        if (isset($loc['state']))   { $loc['state']   = $normalize_state($loc['state']); }
        if (isset($loc['country'])) { $loc['country'] = $normalize_country($loc['country'] ?: 'US'); }

        $out = $defaults;
        foreach ($defaults as $k => $defVal) {
            if (!array_key_exists($k, $loc)) continue;
            $val = $loc[$k];
            $is_empty = ($val === '' || $val === null || (is_array($val) && count(array_filter($val, function($x){
                if (is_array($x)) { return implode('', array_map('strval', $x)) !== ''; }
                return (string)$x !== '';
            })) === 0));
            if (!$is_empty) $out[$k] = $val;
        }
        if (empty($out['hours']) || !is_array($out['hours'])) $out['hours'] = [['day'=>'','open'=>'','close'=>'']];
        if (!isset($out['pages']) || !is_array($out['pages'])) $out['pages'] = [];
        return $out;
    };

    $locations = [];

    if (empty($locations_raw) || (count($locations_raw) === 1 && !is_array($locations_raw[0]))) {
        $locations = [ $org_defaults ];
        $debug['seed_path'][] = 'seed_from_org: empty_or_malformed_db';
    } else {
        foreach ($locations_raw as $i => $loc) {
            $loc = is_array($loc) ? $loc : [];
            $locations[$i] = $merge_with_defaults($org_defaults, $loc);
        }
        $critical = ['name','street','city','state','zip','country'];
        $first_missing = true;
        foreach ($critical as $k) { if (!empty($locations[0][$k])) { $first_missing = false; break; } }
        if ($first_missing) {
            $locations[0] = $org_defaults;
            $debug['seed_path'][] = 'first_empty_overwrite';
        } else {
            $debug['seed_path'][] = 'empty_safe_merge';
        }
    }

    // Build pages map for the Assignments UI
    $loc_pages_map = [];
    foreach ($locations as $i => $loc) $loc_pages_map[$i] = array_map('absint', (array)($loc['pages'] ?? []));

    $debug['locations_after'] = $locations;

    $org_all_blank = (trim($org_defaults['name'].$org_defaults['street'].$org_defaults['city'].$org_defaults['state'].$org_defaults['zip']) === '');

    // ---------- Assignable content ----------
    $assignable = get_posts([
      'post_type'   => ['page','post','service_area'],
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby'     => 'title',
      'order'       => 'asc',
    ]);
    ?>

    <style>
      .myls-lb-wrap { width: 100%; }
      .myls-lb-grid { display:flex; flex-wrap:wrap; gap:8px; align-items:stretch; }
      .myls-lb-left  { flex:3 1 520px; min-width:320px; }
      .myls-lb-right { flex:1 1 280px; min-width:260px; }

      .myls-block { background:#fff; border:1px solid #000; border-radius:1em; padding:12px; }
      .myls-block-title { font-weight:800; margin:0 0 8px; }

      .myls-lb-wrap input[type="text"], .myls-lb-wrap input[type="email"], .myls-lb-wrap input[type="url"],
      .myls-lb-wrap input[type="time"], .myls-lb-wrap input[type="tel"], .myls-lb-wrap textarea, .myls-lb-wrap select {
        border:1px solid #000 !important; border-radius:1em !important; padding:.6rem .9rem; width:100%;
      }
      .form-label { font-weight:600; margin-bottom:.35rem; display:block; }
      .myls-hr { height:1px; background:#000; opacity:.15; border:0; margin:8px 0 10px; }
      .myls-actions { margin-top:10px; display:flex; gap:.5rem; }
      .myls-btn { display:inline-block; font-weight:600; border:1px solid #000; padding:.45rem .9rem; border-radius:1em; background:#f8f9fa; color:#111; cursor:pointer; }
      .myls-btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
      .myls-btn-outline { background:transparent; }
      .myls-btn-danger  { border-color:#dc3545; color:#dc3545; }
      .myls-btn-danger:hover { background:#dc3545; color:#fff; }
      .myls-btn:hover { filter:brightness(.97); }

      .myls-row { display:flex; flex-wrap:wrap; margin-left:-.5rem; margin-right:-.5rem; }
      .myls-col { padding-left:.5rem; padding-right:.5rem; margin-bottom:.75rem; }
      .col-12 { flex:0 0 100%; max-width:100%; }
      .col-6  { flex:0 0 50%;  max-width:50%; }
      .col-3  { flex:0 0 25%;  max-width:25%; }

      .myls-fold { border:1px solid #000; border-radius:1em; padding:8px 12px; margin-bottom:8px; background:#fff; }
      .myls-fold > summary { cursor:pointer; font-weight:700; list-style:none; margin:-8px -12px 8px -12px; padding:8px 12px; border-radius:1em; background:#f0f6ff; }
      .myls-fold[open] > summary { background:#e2ecff; }
      .myls-fold summary::-webkit-details-marker { display:none; }

      .myls-debug details { margin-top:8px; }
      .myls-debug pre { max-height:360px; overflow:auto; padding:8px; border:1px solid #000; border-radius:8px; background:#fafafa; }
      .myls-note { margin:8px 0; padding:8px 10px; border:1px dashed #666; border-radius:8px; background:#fffef5; font-size:13px; }
    </style>

    <!-- IMPORTANT: No <form> here. This stays inside the main tab's form. -->
    <div class="myls-lb-wrap">
      <div class="myls-lb-grid">
        <!-- LEFT 75% -->
        <div class="myls-lb-left">
          <div class="myls-block">
            <div class="myls-block-title">Locations <span style="font-weight:600">(Location #1 is default)</span></div>

            <?php if ($org_all_blank): ?>
              <div class="myls-note">
                Organization values look empty. Open <em>Schema → Organization</em> and click <strong>Save Settings</strong> once to persist values to <code>myls_org_*</code>.
                (This tab will also use <code>ssseo_*</code> if present.)
              </div>
            <?php endif; ?>

            <div id="myls-location-list">
              <?php foreach ($locations as $i=>$loc):
                ob_start();
                echo '<option value="">— Select —</option>';
                foreach ($us_states as $code=>$name) {
                  printf('<option value="%s"%s>%s</option>',
                    esc_attr($code),
                    selected($loc['state'], $code, false),
                    esc_html($name)
                  );
                }
                $state_options_html = ob_get_clean();

                ob_start();
                foreach ($countries as $code=>$name) {
                  printf('<option value="%s"%s>%s</option>',
                    esc_attr($code),
                    selected(($loc['country'] ?: 'US'), $code, false),
                    esc_html($name)
                  );
                }
                $country_options_html = ob_get_clean();
              ?>
              <details class="myls-fold" <?php echo $i===0 ? 'open' : ''; ?>>
                <summary><?php echo $loc['location_label'] ? esc_html($loc['location_label']) : 'Location #'.($i+1); ?></summary>

                <div class="myls-row">
                  <div class="myls-col col-6">
                    <label class="form-label">Location Label</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][location_label]" value="<?php echo esc_attr($loc['location_label']); ?>">
                  </div>
                  <div class="myls-col col-6">
                    <label class="form-label">Business Name</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][name]" value="<?php echo esc_attr($loc['name']); ?>">
                  </div>
                  <div class="myls-col col-6">
                    <label class="form-label">Business Image URL</label>
                    <input type="url" name="myls_locations[<?php echo $i; ?>][image_url]" value="<?php echo esc_attr($loc['image_url'] ?? ''); ?>" placeholder="https://example.com/path/to/image.jpg">
                  </div>

                  <div class="myls-col col-6">
                    <label class="form-label">Phone</label>
                    <input class="myls-phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(555) 555-1234"
                           name="myls_locations[<?php echo $i;?>][phone]" value="<?php echo esc_attr($loc['phone']); ?>">
                  </div>
                  <div class="myls-col col-6">
                    <label class="form-label">Price Range</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][price]" value="<?php echo esc_attr($loc['price']); ?>">
                  </div>

                  <div class="myls-col col-6">
                    <label class="form-label">Street</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][street]" value="<?php echo esc_attr($loc['street']); ?>">
                  </div>
                  <div class="myls-col col-3">
                    <label class="form-label">City</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][city]" value="<?php echo esc_attr($loc['city']); ?>">
                  </div>
                  <div class="myls-col col-3">
                    <label class="form-label">State</label>
                    <select name="myls_locations[<?php echo $i;?>][state]"><?php echo $state_options_html; ?></select>
                  </div>
                  <div class="myls-col col-3">
                    <label class="form-label">ZIP</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][zip]" value="<?php echo esc_attr($loc['zip']); ?>">
                  </div>
                  <div class="myls-col col-3">
                    <label class="form-label">Country</label>
                    <select name="myls_locations[<?php echo $i;?>][country]"><?php echo $country_options_html; ?></select>
                  </div>

                  <div class="myls-col col-3">
                    <label class="form-label">Latitude</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][lat]" value="<?php echo esc_attr($loc['lat']); ?>">
                  </div>
                  <div class="myls-col col-3">
                    <label class="form-label">Longitude</label>
                    <input type="text" name="myls_locations[<?php echo $i;?>][lng]" value="<?php echo esc_attr($loc['lng']); ?>">
                  </div>
                </div>

                <hr class="myls-hr">

                <label class="form-label">Opening Hours</label>
                <?php foreach ($loc['hours'] as $j => $h): ?>
				  <div class="myls-row" id="hours-<?php echo $i . '-' . $j; ?>">
					<div class="myls-col col-3">
					  <select name="myls_locations[<?php echo $i;?>][hours][<?php echo $j;?>][day]">
						<option value="">-- Day --</option>
						<?php foreach (["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"] as $d): ?>
						  <option value="<?php echo esc_attr($d); ?>" <?php selected($h['day']??'', $d); ?>>
							<?php echo esc_html($d); ?>
						  </option>
						<?php endforeach; ?>
					  </select>
					</div>
					<div class="myls-col col-3">
					  <input type="time" name="myls_locations[<?php echo $i;?>][hours][<?php echo $j;?>][open]" value="<?php echo esc_attr($h['open']??''); ?>">
					</div>
					<div class="myls-col col-3">
					  <input type="time" name="myls_locations[<?php echo $i;?>][hours][<?php echo $j;?>][close]" value="<?php echo esc_attr($h['close']??''); ?>">
					</div>
				  </div>
				<?php endforeach; ?>

                <div class="myls-actions">
                  <button type="button" class="myls-btn myls-btn-outline myls-add-hours" data-target="hours-<?php echo $i; ?>" data-index="<?php echo $i; ?>">+ Add Hours Row</button>
                  <button type="submit" name="myls_delete_location" value="<?php echo esc_attr($i); ?>" class="myls-btn myls-btn-danger">Delete This Location</button>
                </div>
              </details>
              <?php endforeach; ?>
            </div>

            <div class="myls-actions">
              <button type="button" class="myls-btn myls-btn-outline" id="myls-add-location">+ Add Location</button>
              <!-- This submit will submit the MAIN form (no nested form here) -->
              <button class="myls-btn myls-btn-primary" type="submit">Save Locations</button>
            </div>

            <!-- DEBUG PANEL -->
            <div class="myls-debug">
              <details>
                <summary><strong>Debug (LocalBusiness)</strong></summary>
                <pre><?php echo esc_html( wp_json_encode($debug, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) ); ?></pre>
              </details>
            </div>
            <!-- /DEBUG PANEL -->

          </div>
        </div>

        <!-- RIGHT 25% -->
        <div class="myls-lb-right">
          <div class="myls-block">
            <div class="myls-block-title">Assignments</div>

            <label class="form-label" for="myls-assign-loc">Edit assignments for:</label>
            <select id="myls-assign-loc"></select>

            <label class="form-label" style="margin-top:.5rem">Pages / Posts / Service Areas</label>
            <select id="myls-assign-pages" multiple size="16" style="min-height: 420px;">
              <?php foreach ($assignable as $p):
                $pt = get_post_type($p->ID);
                $pre = $pt==='service_area' ? 'Service Area: ' : ($pt==='post' ? 'Post: ' : '');
                echo '<option value="'.absint($p->ID).'">'.$pre.esc_html($p->post_title).'</option>';
              endforeach; ?>
            </select>
            <div id="myls-assignment-hidden"></div>
            <div class="small" style="opacity:.8; margin-top:.5rem;">
              Tip: pick a location above, select its pages here, then Save.
            </div>
          </div>
        </div>
      </div>

      <div class="myls-block" style="margin-top:8px;">
        <div class="myls-block-title">Tips</div>
        <p>Use clear <em>Location Label</em> names (e.g., “Downtown Tampa”).</p>
        <p><strong>Note:</strong> Location #1 is the default for generation when a page doesn’t match any assigned location.</p>
      </div>
    </div>

    <script>
    (function(){
      const ORG_DEFAULTS = <?php echo wp_json_encode($org_defaults, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
      const LOC_PAGES  = <?php echo wp_json_encode($loc_pages_map, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
      const esc = (s)=> String(s===null||s===undefined?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');

      function formatUSPhone(value) {
        const digits = value.replace(/\D/g, '').slice(0, 10);
        const len = digits.length;
        if (len < 4) return digits;
        if (len < 7) return `(${digits.slice(0,3)}) ${digits.slice(3)}`;
        return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
      }
      function attachPhoneMask(root=document) {
        root.querySelectorAll('input.myls-phone').forEach(inp => {
          inp.addEventListener('input', () => {
            const start = inp.selectionStart;
            const before = inp.value;
            inp.value = formatUSPhone(inp.value);
            if (document.activeElement === inp) {
              const diff = inp.value.length - before.length;
              inp.setSelectionRange(start + diff, start + diff);
            }
          });
          inp.value = formatUSPhone(inp.value);
        });
      }

      const assignLocSel   = document.getElementById('myls-assign-loc');
      const assignPagesSel = document.getElementById('myls-assign-pages');
      const hiddenWrap     = document.getElementById('myls-assignment-hidden');

      function readLocationLabels(){
        const items = document.querySelectorAll('#myls-location-list details.myls-fold');
        const labels = [];
        items.forEach((item, idx) => {
          const labelInput = item.querySelector('input[name="myls_locations['+idx+'][location_label]"]');
          let label = labelInput ? labelInput.value.trim() : '';
          if (!label) label = 'Location #'+(idx+1)+(idx===0?' (Default)':'');
          labels.push({idx, label});
        });
        return labels;
      }
      function renderAssignLocationDropdown(){
        const labels = readLocationLabels();
        assignLocSel.innerHTML = labels.map(l => `<option value="${l.idx}">${esc(l.label)}</option>`).join('');
      }
      function syncAssignListFor(index){
        const selected = new Set((LOC_PAGES[index] || []).map(String));
        for (const opt of assignPagesSel.options) opt.selected = selected.has(opt.value);
        hiddenWrap.innerHTML = '';
        (LOC_PAGES[index] || []).forEach(val => {
          hiddenWrap.insertAdjacentHTML('beforeend',
            `<input type="hidden" name="myls_locations[${index}][pages][]" value="${String(val).replace(/"/g,'&quot;')}">`);
        });
      }
      function commitAssignSelectionTo(index){
        const chosen = Array.from(assignPagesSel.selectedOptions).map(o => o.value);
        LOC_PAGES[index] = chosen.map(v => parseInt(v,10)).filter(v => !isNaN(v));
        hiddenWrap.innerHTML = '';
        LOC_PAGES[index].forEach(val => {
          hiddenWrap.insertAdjacentHTML('beforeend',
            `<input type="hidden" name="myls_locations[${index}][pages][]" value="${val}">`);
        });
      }

      renderAssignLocationDropdown();
      syncAssignListFor(parseInt(assignLocSel.value || '0', 10));
      attachPhoneMask(document);

      assignLocSel.addEventListener('change', () => {
        const idx = parseInt(assignLocSel.value, 10);
        syncAssignListFor(idx);
      });
      assignPagesSel.addEventListener('change', () => {
        const idx = parseInt(assignLocSel.value, 10);
        commitAssignSelectionTo(idx);
      });
      document.getElementById('myls-location-list')?.addEventListener('input', (e) => {
        if (e.target && /myls_locations\[\d+\]\[location_label\]/.test(e.target.name)) {
          renderAssignLocationDropdown();
        }
      });

      // Add Location (prefill from ORG_DEFAULTS)
      document.getElementById('myls-add-location')?.addEventListener('click', function(){
        const list = document.getElementById('myls-location-list');
        const idx  = list.querySelectorAll('details.myls-fold').length;

        const stateOptions = `<?php
          ob_start();
          echo '<option value="">— Select —</option>';
          foreach ($us_states as $code=>$name){
            echo '<option value="'.esc_attr($code).'">'.esc_html($name).'</option>';
          }
          $state_opts = ob_get_clean();
          echo $state_opts;
        ?>`.replace(
          new RegExp('value="' + ORG_DEFAULTS.state + '"'),
          'value="' + ORG_DEFAULTS.state + '" selected'
        );

        const countryOptions = `<?php
          ob_start();
          foreach ($countries as $code=>$name){
            echo '<option value="'.esc_attr($code).'">'.esc_html($name).'</option>';
          }
          $country_opts = ob_get_clean();
          echo $country_opts;
        ?>`.replace(
          new RegExp('value="' + (ORG_DEFAULTS.country || 'US') + '"'),
          'value="' + (ORG_DEFAULTS.country || 'US') + '" selected'
        );

        const html = `
<details class="myls-fold" open>
  <summary>${esc(ORG_DEFAULTS.location_label || ('Location #'+(idx+1)))}</summary>
  <div class="myls-row">
    <div class="myls-col col-6"><label class="form-label">Location Label</label><input type="text" name="myls_locations[${idx}][location_label]" value="${esc(ORG_DEFAULTS.location_label)}"></div>
    <div class="myls-col col-6"><label class="form-label">Business Name</label><input type="text" name="myls_locations[${idx}][name]" value="${esc(ORG_DEFAULTS.name)}"></div>
    <div class="myls-col col-6"><label class="form-label">Phone</label><input class="myls-phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(555) 555-1234" name="myls_locations[${idx}][phone]" value="${esc(ORG_DEFAULTS.phone)}"></div>
    <div class="myls-col col-6"><label class="form-label">Price Range</label><input type="text" name="myls_locations[${idx}][price]" value="${esc(ORG_DEFAULTS.price)}"></div>

    <div class="myls-col col-6"><label class="form-label">Street</label><input type="text" name="myls_locations[${idx}][street]" value="${esc(ORG_DEFAULTS.street)}"></div>
    <div class="myls-col col-3"><label class="form-label">City</label><input type="text" name="myls_locations[${idx}][city]" value="${esc(ORG_DEFAULTS.city)}"></div>
    <div class="myls-col col-3"><label class="form-label">State</label><select name="myls_locations[${idx}][state]">${stateOptions}</select></div>
    <div class="myls-col col-3"><label class="form-label">ZIP</label><input type="text" name="myls_locations[${idx}][zip]" value="${esc(ORG_DEFAULTS.zip)}"></div>
    <div class="myls-col col-3"><label class="form-label">Country</label><select name="myls_locations[${idx}][country]">${countryOptions}</select></div>

    <div class="myls-col col-3"><label class="form-label">Latitude</label><input type="text" name="myls_locations[${idx}][lat]" value="${esc(ORG_DEFAULTS.lat)}"></div>
    <div class="myls-col col-3"><label class="form-label">Longitude</label><input type="text" name="myls_locations[${idx}][lng]" value="${esc(ORG_DEFAULTS.lng)}"></div>
  </div>

  <hr class="myls-hr">

  <label class="form-label">Opening Hours</label>
  <div class="myls-row" id="hours-${idx}">
    <div class="myls-col col-3">
      <select name="myls_locations[${idx}][hours][0][day]">
        <option value="">-- Day --</option>
        ${["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"].map(d=>`<option value="${d}">${d}</option>`).join('')}
      </select>
    </div>
    <div class="myls-col col-3"><input type="time" name="myls_locations[${idx}][hours][0][open]"></div>
    <div class="myls-col col-3"><input type="time" name="myls_locations[${idx}][hours][0][close]"></div>
  </div>

  <div class="myls-actions">
    <button type="button" class="myls-btn myls-btn-outline myls-add-hours" data-target="hours-${idx}" data-index="${idx}">+ Add Hours Row</button>
    <button type="submit" name="myls_delete_location" value="${idx}" class="myls-btn myls-btn-danger">Delete This Location</button>
  </div>
</details>`;
        list.insertAdjacentHTML('beforeend', html);
        attachPhoneMask(list.lastElementChild);
        renderAssignLocationDropdown();
      });

      document.addEventListener('click', (e)=>{
        const btn = e.target.closest('.myls-add-hours');
        if (!btn) return;
        const tgt = document.getElementById(btn.getAttribute('data-target'));
        const idx = btn.getAttribute('data-index');
        const j = tgt.querySelectorAll('select').length;
        const row = document.createElement('div');
        row.className = 'myls-row';
        row.style.marginTop = '.25rem';
        row.innerHTML =
          '<div class="myls-col col-3"><select name="myls_locations['+idx+'][hours]['+j+'][day]"><option value="">-- Day --</option>'+
          ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"].map(d=>`<option value="${d}">${d}</option>`).join('')+
          '</select></div>'+
          '<div class="myls-col col-3"><input type="time" name="myls_locations['+idx+'][hours]['+j+'][open]"></div>'+
          '<div class="myls-col col-3"><input type="time" name="myls_locations['+idx+'][hours]['+j+'][close]"></div>';
        while (row.firstChild) tgt.appendChild(row.firstChild);
      });

      // IMPORTANT: hook the PARENT form (from the main Schema tab),
      // not a nested form here.
      const parentForm = document.querySelector('.myls-lb-wrap')?.closest('form');
      parentForm?.addEventListener('submit', () => {
        const idx = parseInt(assignLocSel.value || '0', 10);
        commitAssignSelectionTo(idx);
      });

      renderAssignLocationDropdown();
      syncAssignListFor(parseInt(document.getElementById('myls-assign-loc').value || '0', 10));
      attachPhoneMask(document);
    })();
    </script>
    <?php
  },

  'on_save'=> function () {
    if (
      ! isset($_POST['myls_schema_nonce']) ||
      ! wp_verify_nonce($_POST['myls_schema_nonce'],'myls_schema_save') ||
      ! current_user_can('manage_options')
    ) { return; }

    // Handle delete single location action
    if (isset($_POST['myls_delete_location'])) {
      $idx = (int) $_POST['myls_delete_location'];
      $ex  = (array) get_option('myls_lb_locations',[]);
      if (isset($ex[$idx])) { unset($ex[$idx]); update_option('myls_lb_locations', array_values($ex)); }
      return;
    }

    $valid_states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC','PR'];
    $valid_countries = ['US','CA','MX','GB','IE','AU','NZ','DE','FR','ES','IT','NL','SE','NO','DK','FI','PT','CH','AT','BE','PL','CZ','JP','SG','IN','ZA','BR','AR'];

    $raw = isset($_POST['myls_locations']) && is_array($_POST['myls_locations']) ? $_POST['myls_locations'] : [];
    $clean = [];
    foreach ($raw as $loc){
      $state   = strtoupper(trim((string)($loc['state'] ?? '')));
      $country = strtoupper(trim((string)($loc['country'] ?? 'US')));
      if (!in_array($state, $valid_states, true))       $state = '';
      if (!in_array($country, $valid_countries, true))  $country = 'US';

      $one = [
        'location_label' => sanitize_text_field($loc['location_label'] ?? ''),
        'name'       => sanitize_text_field($loc['name'] ?? ''),
        'image_url'  => esc_url_raw($loc['image_url'] ?? ''), // keep
        'phone'      => sanitize_text_field($loc['phone'] ?? ''),
        'price'      => sanitize_text_field($loc['price'] ?? ''),
        'street'     => sanitize_text_field($loc['street'] ?? ''),
        'city'       => sanitize_text_field($loc['city'] ?? ''),
        'state'      => $state,
        'zip'        => sanitize_text_field($loc['zip'] ?? ''),
        'country'    => $country,
        'lat'        => sanitize_text_field($loc['lat'] ?? ''),
        'lng'        => sanitize_text_field($loc['lng'] ?? ''),
        'pages'      => array_map('absint', (array)($loc['pages'] ?? [])),
        'hours'      => [],
      ];

      if (!empty($loc['hours']) && is_array($loc['hours'])){
        foreach ($loc['hours'] as $h){
          $d = sanitize_text_field($h['day']   ?? '');
          $o = sanitize_text_field($h['open']  ?? '');
          $c = sanitize_text_field($h['close'] ?? '');
          if ($d || $o || $c) $one['hours'][] = ['day'=>$d,'open'=>$o,'close'=>$c];
        }
      }
      $clean[] = $one;
    }
    update_option('myls_lb_locations', $clean);
  }
];

if (defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY) return $spec;
return null;
