<?php
/**
 * Email Configuration - Loads from .env file
 */

require_once __DIR__ . '/loadenv.php';

// Brevo API Configuration (from .env)
define('BREVO_API_KEY', BREVO_API_KEY);
define('BREVO_SMTP_KEY', BREVO_SMTP_KEY);
define('BREVO_SMTP_SERVER', BREVO_SMTP_SERVER);
define('BREVO_SMTP_PORT', BREVO_SMTP_PORT);
define('BREVO_SMTP_LOGIN', BREVO_SMTP_LOGIN);
define('BREVO_SENDER_EMAIL', BREVO_SENDER_EMAIL);
define('BREVO_SENDER_NAME', BREVO_SENDER_NAME);
?>