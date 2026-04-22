<?php
/**
 * Load Environment Variables
 * This file loads sensitive data from .env file
 */

// Check if .env file exists
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value
        $parts = explode('=', $line, 2);
        if (count($parts) == 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Define constants from environment variables
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

// Database constants
if (!defined('DB_HOST')) define('DB_HOST', env('DB_HOST', 'localhost'));
if (!defined('DB_USER')) define('DB_USER', env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', env('DB_PASS', ''));
if (!defined('DB_NAME')) define('DB_NAME', env('DB_NAME', 'online_exam_system'));

// Brevo Email constants
if (!defined('BREVO_API_KEY')) define('BREVO_API_KEY', env('BREVO_API_KEY', ''));
if (!defined('BREVO_SMTP_KEY')) define('BREVO_SMTP_KEY', env('BREVO_SMTP_KEY', ''));
if (!defined('BREVO_SMTP_LOGIN')) define('BREVO_SMTP_LOGIN', env('BREVO_SMTP_LOGIN', ''));
if (!defined('BREVO_SMTP_SERVER')) define('BREVO_SMTP_SERVER', 'smtp-relay.brevo.com');
if (!defined('BREVO_SMTP_PORT')) define('BREVO_SMTP_PORT', 587);
if (!defined('BREVO_SENDER_EMAIL')) define('BREVO_SENDER_EMAIL', 'noreply@yourapp.com');
if (!defined('BREVO_SENDER_NAME')) define('BREVO_SENDER_NAME', 'Your App Name');

// Gemini API
if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));
if (!defined('GEMINI_MODEL')) define('GEMINI_MODEL', 'gemini-2.5-flash');

// Site URL
if (!defined('SITE_URL')) define('SITE_URL', env('SITE_URL', 'http://localhost/online_exam_system'));

// Email toggle
if (!defined('ENABLE_EMAILS')) define('ENABLE_EMAILS', env('ENABLE_EMAILS', '0'));

// Site name
if (!defined('SITE_NAME')) define('SITE_NAME', 'Online Examination System');
?>