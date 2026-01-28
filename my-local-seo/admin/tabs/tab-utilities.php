<?php
if ( ! defined('ABSPATH') ) exit;

return [
	'id'    => 'utilities',
	'label' => 'Utilities',
	'order' => 90,
	'render'=> function () {

		// Default Utilities landing page
		echo '<div class="wrap">';
		echo '<h1>Utilities</h1>';
		echo '<p>Maintenance and migration tools.</p>';

		// Load migration tool
		require_once MYLS_PATH . 'admin/tabs/utilities/migration.php';

		echo '</div>';
	}
];
