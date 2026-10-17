<?php
require_once 'config.php';
// Clear session data and cookie
$_SESSION = [];
session_unset();
session_destroy();
// Remove session cookie
if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
}
header('Location: index.php');
exit;
