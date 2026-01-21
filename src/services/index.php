<?php

require_once plugin_dir_path( __FILE__ ) . 'Preferences_Service.php';
new Preferences_Service();

require_once plugin_dir_path( __FILE__ ) . 'Republish_Service.php';
new Republish_Service();

require_once plugin_dir_path( __FILE__ ) . 'Logging_Service.php';
new Logging_Service();

require_once plugin_dir_path( __FILE__ ) . 'Process_Service.php';
new Process_Service();

require_once plugin_dir_path( __FILE__ ) . 'Calculation_Service.php';
new Calculation_Service();
