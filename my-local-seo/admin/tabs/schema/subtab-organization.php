<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Organization subtab (Two-column layout)
 * - Left: full form (Basics, Logo, Address/Geo, Areas, Social)
 * - Right: Post chooser (Pages + Service Areas)
 * - 1px black borders + 1em radius on columns and fields
 * - Default Service Label now a dropdown (matches ssseo-tools set)
 *
 * NOTE: Do NOT open a <form> here — main tab wraps this in a single form.
 */

$spec = [
	'id'    => 'organization',
	'label' => 'Organization',
	'order' => 10,

	'render'=> function () {

		// Media modal for logo
		if ( function_exists('wp_enqueue_media') ) {
			wp_enqueue_media();
		}

		// Service Type options (from ssseo-tools)
		$service_types = [
			'',
			'LocalBusiness','Plumber','Electrician','HVACBusiness','RoofingContractor',
			'PestControl','LegalService','CleaningService','AutoRepair','MedicalBusiness',
			'Locksmith','MovingCompany','RealEstateAgent','ITService',
		];

		// Values (fallback to older ssseo_* options if migrating)
		$v = [
			'name'        => get_option('myls_org_name',        get_option('ssseo_organization_name','')),
			'url'         => get_option('myls_org_url',         get_option('ssseo_organization_url','')),
			'tel'         => get_option('myls_org_tel',         get_option('ssseo_organization_phone','')),
			'email'       => get_option('myls_org_email',       get_option('ssseo_organization_email','')),
			'description' => get_option('myls_org_description', get_option('ssseo_organization_description','')),
			'street'      => get_option('myls_org_street',      get_option('ssseo_organization_address','')),
			'locality'    => get_option('myls_org_locality',    get_option('ssseo_organization_locality','')),
			'region'      => get_option('myls_org_region',      get_option('ssseo_organization_state','')),
			'postal'      => get_option('myls_org_postal',      get_option('ssseo_organization_postal_code','')),
			'country'     => get_option('myls_org_country',     get_option('ssseo_organization_country','')),
			'lat'         => get_option('myls_org_lat',         get_option('ssseo_organization_latitude','')),
			'lng'         => get_option('myls_org_lng',         get_option('ssseo_organization_longitude','')),
			'default_service_label' => get_option('myls_org_default_service_label', get_option('ssseo_default_service_label','')),
			'areas'       => get_option('myls_org_areas',       get_option('ssseo_organization_areas_served','')),
		];

		$logo_id  = (int) get_option('myls_org_logo_id', (int) get_option('ssseo_organization_logo', 0));
		$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

		$socials  = (array) get_option('myls_org_social_profiles', (array) get_option('ssseo_organization_social_profiles', []));
		if ( empty($socials) ) { $socials = ['']; }

		$awards  = (array) get_option('myls_org_awards', []);
		$awards  = array_values(array_filter(array_map('sanitize_text_field', $awards)));
		if ( empty($awards) ) { $awards = ['']; }


		$certs  = (array) get_option('myls_org_certifications', []);
		$certs  = array_values(array_filter(array_map('sanitize_text_field', $certs)));
		if ( empty($certs) ) { $certs = ['']; }

		$sel_pages = array_map('absint', (array) get_option('myls_org_pages', (array) get_option('ssseo_organization_schema_pages', [])));

		// Assignable: Pages + Service Areas
		$assignable = get_posts([
			'post_type'   => ['page','service_area'],
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'asc',
		]);
		?>
		<style>
			/* Two-column split + borders/radius */
			.myls-org-two-col { display:flex; gap:24px; flex-wrap:wrap; }
			.myls-org-left, .myls-org-right {
				background:#fff; border:1px solid #000; border-radius:1em; padding:16px;
			}
			.myls-org-left  { flex:2 1 520px; }
			.myls-org-right { flex:1 1 320px; }

			/* Fields: 1px black border + 1em radius */
			.myls-org-two-col input[type="text"],
			.myls-org-two-col input[type="email"],
			.myls-org-two-col input[type="url"],
			.myls-org-two-col input[type="time"],
			.myls-org-two-col textarea,
			.myls-org-two-col select {
				border:1px solid #000 !important; border-radius:1em !important;
				padding:.6rem .9rem; width:100%;
			}
			.myls-org-two-col .form-label { font-weight:600; margin-bottom:.35rem; display:block; }

			/* Grid helpers */
			.myls-org-two-col .row { display:flex; flex-wrap:wrap; margin-left:-.5rem; margin-right:-.5rem; }
			.myls-org-two-col .row > [class^="col-"] { padding-left:.5rem; padding-right:.5rem; margin-bottom:1rem; }
			.myls-org-two-col .col-12 { flex:0 0 100%; max-width:100%; }
			.myls-org-two-col .col-md-3 { flex:0 0 25%; max-width:25%; }
			.myls-org-two-col .col-md-4 { flex:0 0 33.333%; max-width:33.333%; }
			.myls-org-two-col .col-md-6 { flex:0 0 50%; max-width:50%; }
			.myls-org-two-col .col-md-8 { flex:0 0 66.666%; max-width:66.666%; }

			/* Section titles + dividers */
			.myls-org-section-title { font-weight:800; margin:4px 0 10px; }
			.myls-org-hr { height:1px; background:#000; opacity:.15; border:0; margin:12px 0 18px; }

			/* Buttons (within subtab content; main Save is in parent form) */
			.myls-org-actions { margin-top:14px; display:flex; gap:.5rem; }
			.myls-btn { display:inline-block; font-weight:600; border:1px solid #000; padding:.45rem .9rem; border-radius:1em; background:#f8f9fa; color:#111; cursor:pointer; }
			.myls-btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
			.myls-btn-outline { background:transparent; }
			.myls-btn-danger  { border-color:#dc3545; color:#dc3545; }
			.myls-btn-danger:hover { background:#dc3545; color:#fff; }
			.myls-btn:hover { filter:brightness(.97); }

			/* Right column: chooser */
			.myls-chooser-title { font-weight:800; margin-bottom:.5rem; }
			.myls-chooser select { min-height:520px; width:100%; }
			.myls-chooser-tip { font-size:12px; opacity:.8; margin-top:.5rem; }
			.myls-chooser-toolbar { display:flex; gap:.5rem; margin-bottom:.75rem; }
			.myls-logo-preview img { max-width:220px; height:auto; border:1px solid #000; border-radius:1em; }
		</style>

		<div class="myls-org-two-col">
			<!-- LEFT: FORM FIELDS (no <form> tag here) -->
			<div class="myls-org-left">
				<div class="myls-org-section">
					<div class="myls-org-section-title">Organization Basics</div>
					<div class="row">
						<div class="col-md-6">
							<label class="form-label">Organization Name</label>
							<input type="text" name="myls_org_name" value="<?php echo esc_attr($v['name']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Website URL</label>
							<input type="url" name="myls_org_url" value="<?php echo esc_url($v['url']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Email</label>
							<input type="email" name="myls_org_email" value="<?php echo esc_attr($v['email']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Phone</label>
							<input type="text" name="myls_org_tel" value="<?php echo esc_attr($v['tel']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Default Service Label</label>
							<select name="myls_org_default_service_label">
								<?php foreach ( $service_types as $opt ): ?>
									<option value="<?php echo esc_attr($opt); ?>" <?php selected($v['default_service_label'], $opt); ?>>
										<?php echo $opt === '' ? '— Select —' : esc_html($opt); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<div class="form-text">Used by Service/LocalBusiness schema when a label is not provided.</div>
						</div>
						<div class="col-md-6">
							<label class="form-label">Description</label>
							<textarea rows="3" name="myls_org_description"><?php echo esc_textarea($v['description']); ?></textarea>
						</div>
					</div>
				</div>

				<hr class="myls-org-hr">

				<div class="myls-org-section">
					<div class="myls-org-section-title">Logo</div>
					<input type="hidden" id="myls_org_logo_id" name="myls_org_logo_id" value="<?php echo esc_attr($logo_id); ?>">
					<div class="myls-logo-preview">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url($logo_url); ?>" alt="">
						<?php endif; ?>
					</div>
					<div class="myls-org-actions">
						<button type="button" class="myls-btn myls-btn-outline" id="myls-org-logo-btn">Select Logo</button>
						<button type="button" class="myls-btn myls-btn-danger" id="myls-org-logo-remove">Remove</button>
					</div>
				</div>

				<hr class="myls-org-hr">

				<div class="myls-org-section">
					<div class="myls-org-section-title">Address & Geo</div>
					<div class="row">
						<div class="col-md-6">
							<label class="form-label">Street</label>
							<input type="text" name="myls_org_street" value="<?php echo esc_attr($v['street']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">City</label>
							<input type="text" name="myls_org_locality" value="<?php echo esc_attr($v['locality']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">State/Region</label>
							<input type="text" name="myls_org_region" value="<?php echo esc_attr($v['region']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Postal Code</label>
							<input type="text" name="myls_org_postal" value="<?php echo esc_attr($v['postal']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Country</label>
							<input type="text" name="myls_org_country" value="<?php echo esc_attr($v['country']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Latitude</label>
							<input type="text" name="myls_org_lat" value="<?php echo esc_attr($v['lat']); ?>">
						</div>
						<div class="col-md-3">
							<label class="form-label">Longitude</label>
							<input type="text" name="myls_org_lng" value="<?php echo esc_attr($v['lng']); ?>">
						</div>
					</div>
				</div>

				<hr class="myls-org-hr">

				<div class="myls-org-section">
					<div class="myls-org-section-title">Areas Served & Social</div>
					<div class="row">
						<div class="col-md-6">
							<label class="form-label">Areas Served (one per line)</label>
							<textarea rows="5" name="myls_org_areas"><?php echo esc_textarea($v['areas']); ?></textarea>
						</div>
						<div class="col-md-6">
							<label class="form-label">Social Profiles</label>
							<div id="myls-org-socials">
								<?php foreach ( $socials as $i => $u ) : ?>
								<div class="d-flex" style="gap:.5rem; margin-bottom:.5rem;">
									<input type="url" name="myls_org_social_profiles[]" value="<?php echo esc_url($u); ?>" placeholder="https://example.com/your-profile">
									<button class="myls-btn myls-btn-outline myls-remove-social" type="button">Remove</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button class="myls-btn myls-btn-outline" type="button" id="myls-org-add-social">+ Add Profile</button>
						</div>
					</div>
				</div>

				
					<div class="row" style="margin-top:16px;">
						<div class="col-12">
							<label class="form-label">Awards (one per line)</label>
							<div class="text-muted" style="margin:-6px 0 10px 0; font-size: 12px;">
								These are output on Organization / LocalBusiness schema as <code>award</code> strings (Schema.org-valid).
							</div>

							<div id="myls-org-awards">
								<?php foreach ( $awards as $i => $a_txt ) : ?>
									<div class="d-flex" style="gap:.5rem; margin-bottom:.5rem;">
										<input type="text" name="myls_org_awards[]" value="<?php echo esc_attr($a_txt); ?>" placeholder="e.g. Best of Tampa Bay 2024">
										<button class="myls-btn myls-btn-outline myls-remove-award" type="button">Remove</button>
									</div>
								<?php endforeach; ?>
							</div>

							<button class="myls-btn myls-btn-outline" type="button" id="myls-org-add-award">+ Add Award</button>
						</div>
					</div>

					<div class="row" style="margin-top:16px;">
						<div class="col-12">
							<label class="form-label">Certifications (one per line)</label>
							<div class="text-muted" style="margin:-6px 0 10px 0; font-size: 12px;">
								These are output on Organization / LocalBusiness schema as <code>hasCertification</code> (Schema.org-valid).
							</div>

							<div id="myls-org-certs">
								<?php foreach ( $certs as $i => $c_txt ) : ?>
									<div class="d-flex" style="gap:.5rem; margin-bottom:.5rem;">
										<input type="text" name="myls_org_certifications[]" value="<?php echo esc_attr($c_txt); ?>" placeholder="e.g. GAF Master Elite Contractor">
										<button class="myls-btn myls-btn-outline myls-remove-cert" type="button">Remove</button>
									</div>
								<?php endforeach; ?>
							</div>

							<button class="myls-btn myls-btn-outline" type="button" id="myls-org-add-cert">+ Add Certification</button>

						</div>
					</div>


				<!-- (No submit button here; the main tab provides Save Settings) -->
			</div>

			<!-- RIGHT: POST CHOOSER -->
			<div class="myls-org-right">
				<div class="myls-chooser">
					<div class="myls-chooser-title">Include Organization schema on:</div>
					<div class="myls-chooser-toolbar">
						<button type="button" class="myls-btn myls-btn-outline" id="myls-chooser-select-all">Select All</button>
						<button type="button" class="myls-btn myls-btn-outline" id="myls-chooser-clear">Clear</button>
					</div>
					<select class="form-select" name="myls_org_pages[]" id="myls-org-pages" multiple>
						<?php foreach ( $assignable as $p ) :
							$selected = in_array($p->ID, $sel_pages, true) ? 'selected' : '';
							$prefix   = (get_post_type($p->ID)==='service_area') ? 'Service Area: ' : '';
							echo '<option value="'. esc_attr($p->ID) .'" '. $selected .'>'. esc_html($prefix.$p->post_title) .'</option>';
						endforeach; ?>
					</select>
					<div class="myls-chooser-tip">Hold <strong>Ctrl/Cmd</strong> to select multiple. Leave empty for site-wide.</div>
				</div>
			</div>
		</div>

		<script>
		// Logo media picker
		(function(){
		  const btn = document.getElementById('myls-org-logo-btn');
		  const rmv = document.getElementById('myls-org-logo-remove');
		  const idField = document.getElementById('myls_org_logo_id');
		  const prev = document.querySelector('.myls-logo-preview');
		  let frame;

		  btn?.addEventListener('click', function(e){
			e.preventDefault();
			if (frame){ frame.open(); return; }
			frame = wp.media({ title: 'Select Logo', button: { text: 'Use this logo' }, multiple: false });
			frame.on('select', function(){
			  const att = frame.state().get('selection').first().toJSON();
			  idField.value = att.id;
			  const url = (att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url);
			  prev.innerHTML = '<img src="'+ url +'" alt="">';
			});
			frame.open();
		  });

		  rmv?.addEventListener('click', function(){
			idField.value = '';
			prev.innerHTML = '';
		  });
		})();

		// Social rows
		(function(){
		  const wrap = document.getElementById('myls-org-socials');
		  document.getElementById('myls-org-add-social')?.addEventListener('click', function(){
			const row = document.createElement('div');
			row.className = 'd-flex';
			row.style.gap = '.5rem';
			row.style.marginBottom = '.5rem';
			row.innerHTML = '<input type="url" name="myls_org_social_profiles[]" placeholder="https://example.com/your-profile">'
						  + '<button class="myls-btn myls-btn-outline myls-remove-social" type="button">Remove</button>';
			wrap.appendChild(row);
		  });
		  wrap?.addEventListener('click', function(e){
			const btn = e.target.closest('.myls-remove-social');
			if (btn) { btn.parentElement.remove(); }
		  });
		})(); 

		// Awards rows
		(function(){
		  const wrap = document.getElementById('myls-org-awards');
		  document.getElementById('myls-org-add-award')?.addEventListener('click', function(){
			const row = document.createElement('div');
			row.className = 'd-flex';
			row.style.gap = '.5rem';
			row.style.marginBottom = '.5rem';
			row.innerHTML = '<input type="text" name="myls_org_awards[]" placeholder="e.g. Best of Tampa Bay 2024">'
						  + '<button class="myls-btn myls-btn-outline myls-remove-award" type="button">Remove</button>';
			wrap.appendChild(row);
		  });
		  wrap?.addEventListener('click', function(e){
			const btn = e.target.closest('.myls-remove-award');
			if (btn) { btn.parentElement.remove(); }
		  });
		

		// Certifications rows
		(function(){
		  const wrap = document.getElementById('myls-org-certs');
		  document.getElementById('myls-org-add-cert')?.addEventListener('click', function(){
			const row = document.createElement('div');
			row.className = 'd-flex';
			row.style.gap = '.5rem';
			row.style.marginBottom = '.5rem';
			row.innerHTML = '<input type="text" name="myls_org_certifications[]" placeholder="e.g. GAF Master Elite Contractor">'
						  + '<button class="myls-btn myls-btn-outline myls-remove-cert" type="button">Remove</button>';
			wrap.appendChild(row);
		  });
		  wrap?.addEventListener('click', function(e){
			const btn = e.target.closest('.myls-remove-cert');
			if (btn) { btn.parentElement.remove(); }
		  });
		})(); 

})(); 

		// Chooser toolbar
		(function(){
		  const sel = document.getElementById('myls-org-pages');
		  document.getElementById('myls-chooser-select-all')?.addEventListener('click', function(){
			if (!sel) return; for (const o of sel.options) o.selected = true;
		  });
		  document.getElementById('myls-chooser-clear')?.addEventListener('click', function(){
			if (!sel) return; for (const o of sel.options) o.selected = false;
		  });
		})();
		</script>
		<?php
	},

	'on_save'=> function () {
		// Basics
		update_option('myls_org_name',  sanitize_text_field($_POST['myls_org_name'] ?? ''));
		update_option('myls_org_url',   esc_url_raw($_POST['myls_org_url'] ?? ''));
		update_option('myls_org_tel',   sanitize_text_field($_POST['myls_org_tel'] ?? ''));
		update_option('myls_org_email', sanitize_email($_POST['myls_org_email'] ?? ''));
		update_option('myls_org_description', sanitize_textarea_field($_POST['myls_org_description'] ?? ''));
		update_option('myls_org_default_service_label', sanitize_text_field($_POST['myls_org_default_service_label'] ?? ''));

		// Logo
		update_option('myls_org_logo_id', absint($_POST['myls_org_logo_id'] ?? 0));

		// Address & Geo
		update_option('myls_org_street',   sanitize_text_field($_POST['myls_org_street'] ?? ''));
		update_option('myls_org_locality', sanitize_text_field($_POST['myls_org_locality'] ?? ''));
		update_option('myls_org_region',   sanitize_text_field($_POST['myls_org_region'] ?? ''));
		update_option('myls_org_postal',   sanitize_text_field($_POST['myls_org_postal'] ?? ''));
		update_option('myls_org_country',  sanitize_text_field($_POST['myls_org_country'] ?? ''));
		update_option('myls_org_lat',      sanitize_text_field($_POST['myls_org_lat'] ?? ''));
		update_option('myls_org_lng',      sanitize_text_field($_POST['myls_org_lng'] ?? ''));

		// Areas
		update_option('myls_org_areas', sanitize_textarea_field($_POST['myls_org_areas'] ?? ''));

		// Socials
		$raw_socials = (isset($_POST['myls_org_social_profiles']) && is_array($_POST['myls_org_social_profiles']))
			? array_map('esc_url_raw', $_POST['myls_org_social_profiles']) : [];
		$raw_socials = array_values(array_filter($raw_socials));
		update_option('myls_org_social_profiles', $raw_socials);


		// Awards
		$raw_awards = (isset($_POST['myls_org_awards']) && is_array($_POST['myls_org_awards']))
			? array_map('sanitize_text_field', $_POST['myls_org_awards']) : [];
		$raw_awards = array_values(array_filter($raw_awards));
		update_option('myls_org_awards', $raw_awards);

		// Certifications
		$raw_certs = (isset($_POST['myls_org_certifications']) && is_array($_POST['myls_org_certifications']))
			? array_map('sanitize_text_field', $_POST['myls_org_certifications']) : [];
		$raw_certs = array_values(array_filter($raw_certs));
		update_option('myls_org_certifications', $raw_certs);
		

		// Assignments
		$pages = (isset($_POST['myls_org_pages']) && is_array($_POST['myls_org_pages']))
			? array_map('absint', $_POST['myls_org_pages']) : [];
		update_option('myls_org_pages', $pages);
	}
];

if ( defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY ) return $spec;
return null;
