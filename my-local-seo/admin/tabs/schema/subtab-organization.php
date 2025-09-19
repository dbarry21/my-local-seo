<?php
// File: admin/tabs/schema/subtab-organization.php
if ( ! defined('ABSPATH') ) exit;

// Discovery spec
$spec = [
    'id'      => 'organization',
    'label'   => 'Organization',
    'render'  => function () {
        // Load values
        $v = [
            'name'        => myls_opt('myls_org_name', ''),
            'url'         => myls_opt('myls_org_url', ''),
            'logo'        => myls_opt('myls_org_logo', ''),
            'tel'         => myls_opt('myls_org_tel', ''),
            'email'       => myls_opt('myls_org_email', ''),
            'street'      => myls_opt('myls_org_street', ''),
            'locality'    => myls_opt('myls_org_locality', ''),
            'region'      => myls_opt('myls_org_region', ''),
            'postal'      => myls_opt('myls_org_postal', ''),
            'country'     => myls_opt('myls_org_country', ''),
            'social'      => myls_opt('myls_org_social', ''),   // newline list
            'areas'       => myls_opt('myls_org_areas', ''),    // newline list
            'lat'         => myls_opt('myls_org_lat', ''),
            'lng'         => myls_opt('myls_org_lng', ''),
            'description' => myls_opt('myls_org_description', ''),
        ];
        ?>
        <form method="post">
            <?php wp_nonce_field('myls_schema_save', 'myls_schema_nonce'); ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Organization Name</label>
                    <input type="text" name="myls_org_name" class="form-control" value="<?php echo esc_attr($v['name']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Organization URL</label>
                    <input type="url" name="myls_org_url" class="form-control" value="<?php echo esc_url($v['url']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Logo URL</label>
                    <input type="url" name="myls_org_logo" class="form-control" value="<?php echo esc_url($v['logo']); ?>">
                    <div class="form-text">Full image URL.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telephone</label>
                    <input type="text" name="myls_org_tel" class="form-control" value="<?php echo esc_attr($v['tel']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="myls_org_email" class="form-control" value="<?php echo esc_attr($v['email']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <textarea name="myls_org_description" class="form-control" rows="3"><?php echo esc_textarea($v['description']); ?></textarea>
                </div>

                <div class="col-12"><hr></div>

                <div class="col-md-6">
                    <label class="form-label">Street Address</label>
                    <input type="text" name="myls_org_street" class="form-control" value="<?php echo esc_attr($v['street']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Locality (City)</label>
                    <input type="text" name="myls_org_locality" class="form-control" value="<?php echo esc_attr($v['locality']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Region/State</label>
                    <input type="text" name="myls_org_region" class="form-control" value="<?php echo esc_attr($v['region']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Postal Code</label>
                    <input type="text" name="myls_org_postal" class="form-control" value="<?php echo esc_attr($v['postal']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Country (2-letter)</label>
                    <input type="text" name="myls_org_country" class="form-control" value="<?php echo esc_attr($v['country']); ?>" maxlength="2">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Latitude</label>
                    <input type="text" name="myls_org_lat" class="form-control" value="<?php echo esc_attr($v['lat']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Longitude</label>
                    <input type="text" name="myls_org_lng" class="form-control" value="<?php echo esc_attr($v['lng']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Social Profiles (one per line)</label>
                    <textarea name="myls_org_social" class="form-control" rows="4" placeholder="https://facebook.com/yourpage&#10;https://www.linkedin.com/company/yourco"><?php echo esc_textarea($v['social']); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Areas Served (one per line)</label>
                    <textarea name="myls_org_areas" class="form-control" rows="4" placeholder="Tampa, FL&#10;Orlando, FL"><?php echo esc_textarea($v['areas']); ?></textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Organization</button>
                <a class="btn btn-outline-secondary" href="<?php echo esc_url( add_query_arg('schema_subtab','organization') ); ?>">Refresh</a>
            </div>

            <?php
            // Live preview
            $node = myls_schema_org_build_node();
            if ( $node ) {
                $json = wp_json_encode(['@context'=>'https://schema.org'] + $node, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
                echo '<hr><h5 class="mt-4">Preview (JSON-LD)</h5><pre class="p-3 bg-light border rounded small" style="max-height:420px;overflow:auto;">'.esc_html($json).'</pre>';
            }
            ?>
        </form>
        <?php
    },
    'on_save' => function () {
        $fields = [
            'myls_org_name','myls_org_url','myls_org_logo','myls_org_tel','myls_org_email',
            'myls_org_street','myls_org_locality','myls_org_region','myls_org_postal','myls_org_country',
            'myls_org_social','myls_org_areas','myls_org_lat','myls_org_lng','myls_org_description'
        ];
        foreach ($fields as $key) {
            $val = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            if ( $key === 'myls_org_social' || $key === 'myls_org_areas' ) {
                $val = myls_sanitize_csv($val);
            } else {
                $val = is_string($val) ? trim($val) : $val;
            }
            myls_update_opt($key, $val);
        }
        flush_rewrite_rules(false);
    },
];

// Provider: build Organization node & attach to graph
if ( ! function_exists('myls_schema_org_build_node') ) {
    function myls_schema_org_build_node() {
        $name  = myls_opt('myls_org_name', '');
        $url   = myls_opt('myls_org_url', '');
        if ( $name === '' && $url === '' ) return null;

        $addr = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => myls_opt('myls_org_street', ''),
            'addressLocality' => myls_opt('myls_org_locality', ''),
            'addressRegion'   => myls_opt('myls_org_region', ''),
            'postalCode'      => myls_opt('myls_org_postal', ''),
            'addressCountry'  => myls_opt('myls_org_country', ''),
        ]);

        $sameAs = array_values(array_filter(array_map('trim', explode("\n", (string)myls_opt('myls_org_social','')))));

        $node = [
            '@type'        => 'Organization',
            '@id'          => $url ? trailingslashit($url) . '#organization' : null,
            'name'         => $name ?: null,
            'url'          => $url ?: null,
            'logo'         => myls_opt('myls_org_logo','') ?: null,
            'telephone'    => myls_opt('myls_org_tel','') ?: null,
            'email'        => myls_opt('myls_org_email','') ?: null,
            'description'  => myls_opt('myls_org_description','') ?: null,
            'address'      => $addr ?: null,
            'areaServed'   => array_values(array_filter(array_map('trim', explode("\n", (string)myls_opt('myls_org_areas',''))))) ?: null,
            'sameAs'       => $sameAs ?: null,
        ];

        // Geo as GeoCoordinates if both present
        $lat = myls_opt('myls_org_lat', '');
        $lng = myls_opt('myls_org_lng', '');
        if ($lat !== '' && $lng !== '') {
            $node['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float)$lat,
                'longitude' => (float)$lng,
            ];
        }

        // Strip nulls
        return array_filter($node, function($v){ return $v !== null && $v !== []; });
    }
}

// Contribute to @graph
add_filter('myls_schema_graph', function($graph){
    $org = myls_schema_org_build_node();
    if ( $org ) {
        // If there is an @context already in preview, keep node only
        if ( isset($org['@context']) ) unset($org['@context']);
        $graph[] = $org;
    }
    return $graph;
}, 10);

// Return spec during discovery
if ( defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY ) {
    return $spec;
}

return null;
