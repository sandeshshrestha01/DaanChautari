<?php
/**
 * Dan Chautari - Global Configuration File
 * Initializes sessions, dynamic base URLs, and global configurations.
 */

// Secure session initiation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting settings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Base URL configuration (automatic detection)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';

    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $pos = strpos($script_dir, '/Daan Chautari');
    if ($pos !== false) {
        $base_path = substr($script_dir, 0, $pos + strlen('/Daan Chautari')) . '/';
    } else {
        $base_path = '/';
    }

    $base_url = $protocol . $host . $base_path;
    define('BASE_URL', $base_url);
}

// ── Flash message helpers ─────────────────────────────────────────────────────

/**
 * Store a one-time flash message in the session.
 * @param string $type    'success' | 'error' | 'info' | 'warning'
 * @param string $message Human-readable message text
 */
function set_flash_message(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the stored flash message.
 * @return array|null ['type' => ..., 'message' => ...] or null
 */
function get_flash_message(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
