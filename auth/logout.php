<?php
/**
 * Dan Chautari - Logout Handler
 * Safely terminates active user session and redirects to homepage.
 */

require_once __DIR__ . '/../database/config.php';

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session completely
session_destroy();

// Start a fresh session just to carry the flash message
session_start();
set_flash_message('success', 'You have been logged out successfully. See you soon!');

// Redirect to homepage
header("Location: " . BASE_URL . "pages/homepage.php");
exit;
?>