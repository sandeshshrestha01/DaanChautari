<?php
/**
 * Daan Chautari — Admin Login Processor
 * Validates credentials, enforces role = 'admin', and redirects to dashboard.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_login.php");
    exit;
}

// ── Collect & sanitize inputs ─────────────────────────────────────────────────
$email    = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    set_flash_message('error', 'Please enter your email address and password.');
    header("Location: admin_login.php");
    exit;
}

// ── Database lookup ───────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE email = :email AND role = 'admin' AND status = 'active'"
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        // ── Establish session ─────────────────────────────────────────────
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['user_id']    = $user['user_id'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['town']       = $user['town'] ?? null;

        set_flash_message('success', "Welcome back, {$user['full_name']}! You are logged in as Admin.");
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit;

    } else {
        // Generic error — don't reveal whether email or password was wrong
        set_flash_message('error', 'Invalid credentials or you do not have admin access.');
        header("Location: admin_login.php");
        exit;
    }

} catch (PDOException $e) {
    error_log("Admin Login DB Error: " . $e->getMessage());
    set_flash_message('error', 'A database error occurred. Please try again later.');
    header("Location: admin_login.php");
    exit;
}
