<?php

require_once plugin_dir_path( __FILE__ ) . 'Preferences_Controller.php';
new Preferences_Controller();

require_once plugin_dir_path( __FILE__ ) . 'Republish_Controller.php';
new Republish_Controller();

require_once plugin_dir_path( __FILE__ ) . 'Process_Controller.php';
new Process_Controller();

require_once plugin_dir_path( __FILE__ ) . 'Logging_Controller.php';
new Logging_Controller();

require_once plugin_dir_path( __FILE__ ) . 'Calculation_Controller.php';
new Calculation_Controller();

require_once plugin_dir_path( __FILE__ ) . 'History_Controller.php';
new History_Controller();
