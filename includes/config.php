<?php
/**
 * Application Configuration
 */

// Environment configuration
define('ENVIRONMENT', 'development'); // Change to 'production' for live site

// Application settings
define('APP_NAME', 'BotSystem');
define('APP_VERSION', '2.0.0');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Include database connection
require_once __DIR__ . '/db.php';
?>