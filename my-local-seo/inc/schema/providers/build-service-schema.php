<?php
/**
 * My Local SEO - Service Schema Builder (refactor of ssseo-tools build-service-schema)
 * Outputs Product schema for single 'service' posts with child offers.
 *
 * Hooks into the central @graph via 'myls_schema_graph'.
 */

if (!defined('ABSPATH')) exit;

/** Safe option getter (falls back to default if empty string) */
if (!function_exists('myls_opt')) {
  function myls_opt($key, $default = '') {
    $v = get_option($key, $default);
    return ($v === '' ? $default : $v);
  }
}

add_filter('myls_schema_graph', function(array $graph) {

  // Only on single 'service' posts
  if (!is_singular('service')  && !is_singular('service_area')) return $graph;

  $post_id = get_queried_object_id();
  if (!$post_id) return $graph;

  // --- Organization / Brand details (prefer my-local-seo keys, fallback to ssseo-tools, then WP)
  $org_name = myls_opt('myls_org_name', myls_opt('ssseo_organization_name', get_bloginfo('name')));
  $org_url  = myls_opt('myls_org_url',  myls_opt('ssseo_organization_url',  home_url()));
  $org_phone= myls_opt('myls_org_phone', myls_opt('ssseo_organization_phone', ''));
  $org_logo_id = absint(myls_opt('myls_org_logo_id', myls_opt('ssseo_organization_logo', 0)));
  $org_logo    = $org_logo_id ? wp_get_attachment_image_url($org_logo_id, 'full') : '';

  // Address (accept both sets of keys, clean empties)
  $org_address_raw = [
    'streetAddress'   => myls_opt('myls_org_address',       myls_opt('ssseo_organization_address', '')),
    'addressLocality' => myls_opt('myls_org_locality',      myls_opt('ssseo_organization_locality', '')),
    'addressRegion'   => myls_opt('myls_org_region',        myls_opt('ssseo_organization_state', '')),
    'postalCode'      => myls_opt('myls_org_postal_code',   myls_opt('ssseo_organization_postal_code', '')),
    'addressCountry'  => myls_opt('myls_org_country',       myls_opt('ssseo_organization_country', '')),
  ];
  $org_address = array_filter($org_address_raw);
  if (!empty($org_address)) $org_address = array_merge(['@type' => 'PostalAddress'], $org_address);

  // Social profiles
  $same_as = myls_opt('myls_org_sameas', myls_opt('ssseo_organization_social_profiles', []));
  $same_as = is_array($same_as) ? array_filter(array_map('esc_url_raw', $same_as)) : [];

  // Build Brand (Organization) — used on Product.brand
  $brand = [
    '@type' => 'Organization',
    'name'  => $org_name,
    'url'   => $org_url,
  ];
  if ($org_phone)     $brand['telephone'] = $org_phone;
  if ($org_logo)      $brand['logo']      = $org_logo;
  if (!empty($org_address)) $brand['address'] = $org_address;
  if (!empty($same_as))     $brand['sameAs']  = array_values($same_as);

  // Product basics
  $service_label = myls_opt('myls_default_service_label', myls_opt('ssseo_default_service_label', ''));
  $product_name  = $service_label ?: get_the_title($post_id);
  $description   = get_the_excerpt($post_id);
  $product_url   = get_permalink($post_id);

  // Prefer featured image for the current service; fallback to org logo
  $image_url = get_the_post_thumbnail_url($post_id, 'full');
  if (!$image_url && $org_logo) $image_url = $org_logo;

  // --- Offers: child services of the current service; fallback to top-level services if none
  $offers = [];

  $child_services = get_posts([
    'post_type'      => 'service',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'post_parent'    => $post_id,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
  ]);

  $source_services = $child_services;
  if (empty($source_services)) {
    $source_services = get_posts([
      'post_type'      => 'service',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'post_parent'    => 0,
      'orderby'        => 'menu_order',
      'order'          => 'ASC',
    ]);
  }

  foreach ($source_services as $svc) {
    $offers[] = [
      '@type'        => 'Offer',
      'name'         => get_the_title($svc->ID),
      'url'          => get_permalink($svc->ID),
      'description'  => get_the_excerpt($svc->ID),
      // Keep simple defaults (can be overridden later via UI/meta if desired)
      'price'        => 0,
      'priceCurrency'=> 'USD',
      'availability' => 'https://schema.org/InStock',
    ];
  }

  // Build Product node
  $product = [
    '@type'       => 'Product',
    'name'        => wp_strip_all_tags($product_name),
    'description' => wp_strip_all_tags($description),
    'url'         => $product_url,
    'brand'       => $brand,
  ];
  if ($image_url) $product['image'] = $image_url;
  if (!empty($offers)) $product['offers'] = $offers;

  // Push into central graph
  $graph[] = $product;

  // Simple trace comment in page source for sanity
  echo "\n<!-- MyLS build-service-schema: emitted Product for service #{$post_id} ; offers=" . count($offers) . " -->\n";

  return $graph;
}, 10, 1);
