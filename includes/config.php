<?php
/**
 * includes/config.php
 * Global configuration for Waste Management System
 */

// App info
define('APP_NAME', 'e-Waste Management System');
define('APP_VERSION', '1.0.0');

// Google Maps key (placeholder — won’t load the map until you get a real key)
define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_BROWSER_KEY_HERE');

// Base paths (adjust if installed elsewhere)
define('BASE_URL', 'http://localhost/e-waste/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('API_URL', BASE_URL . 'api/');

date_default_timezone_set('Africa/Dar_es_Salaam');
setlocale(LC_ALL, 'en_US.UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
