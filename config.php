<?php
// 1. Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'moor_portal');

function db_connect() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_errno) {
        die('DB connect error: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

// 2. Session Configuration
// We check if a session is already active before setting ini values 
// to prevent the "Session name cannot be changed" warning.
if (session_status() === PHP_SESSION_NONE) {
    session_name('MPSESSID');

    $cookieParams = [
        'lifetime' => 2592000,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
    }

    ini_set('session.gc_maxlifetime', '2592000');
    ini_set('session.cookie_lifetime', '2592000');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '0'); // Set to '1' only when using HTTPS

    session_start();
}

// 3. Autoload check (for PHPMailer)
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // Optional: Log an error or ignore if not using mailer on every page
}
?>