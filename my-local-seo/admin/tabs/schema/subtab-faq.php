<?php
if ( ! defined('ABSPATH') ) exit;
$spec = ['id'=>'faq','label'=>'FAQ','render'=>function(){ echo '<p>FAQ schema settings coming next.</p>'; },'on_save'=>function(){}];
if ( defined('MYLS_SCHEMA_DISCOVERY') && MYLS_SCHEMA_DISCOVERY ) return $spec;
return null;
