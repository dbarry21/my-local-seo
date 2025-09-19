<?php
if ( ! defined('ABSPATH') ) exit;

$spec = [
  'id' => 'localbusiness',
  'label' => 'Local Business',
  'render' => function () {
      echo '<p>LocalBusiness settings UI coming next. We will support multiple locations, opening hours, and page assignments.</p>';
  },
  'on_save' => function () {}
];

if ( defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY ) return $spec;
return null;
