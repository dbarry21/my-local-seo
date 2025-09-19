<?php
if ( ! defined('ABSPATH') ) exit;
$spec = ['id'=>'service','label'=>'Service','render'=>function(){ echo '<p>Service schema settings coming next.</p>'; },'on_save'=>function(){}];
if ( defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY ) return $spec;
return null;
