<?php
if ( ! defined('ABSPATH') ) exit;

myls_register_admin_tab('dashboard', 'Dashboard', function(){
    echo '<div class="card p-3"><h2>Dashboard</h2><p>If you see this, the tab loader is working.</p></div>';
}, 1); // low priority so it’s first
