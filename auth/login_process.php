<?php
/**
 * Dan Chautari - Login Processor
 * Validates credentials against the users table and establishes a session.
 * Columns used: user_id, full_name, email, password, role, status
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// ── Collect inputs ────────────────────────────────────────────────────────────
$email    = trim(filter_input(INPUT_POST, 'email',    FILTER_VALIDATE_EMAIL) ?? '');
$password = $_POST['password'] ?? '';
$form_role = $_POST['role']    ?? ''; // 'donor' or 'recipient' (optional field in login form)

if (!$email || empty($password)) {
    set_flash_message('error', 'Please enter your email and password.');
    header("Location: login.php");
    exit;
}

// ── Database lookup ───────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND status = 'active'");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        // Block admin accounts from logging in through public user login
        if ($user['role'] === 'admin') {
            set_flash_message('error', 'Invalid email or password. Please try again.');
            header("Location: login.php");
            exit;
        }

        // Only donor and recipient roles are allowed
        if ($user['role'] !== 'donor' && $user['role'] !== 'recipient') {
            set_flash_message('error', 'Invalid account role or access restricted.');
            header("Location: login.php");
            exit;
        }

        // ── Establish session for regular users (donor/recipient) ────────────
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['user_id'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['town']       = $user['town'] ?? null;

        set_flash_message('success', "Welcome back, {$user['full_name']}!");
        header("Location: " . BASE_URL . "pages/dashboard.php");
        exit;

    } else {
        set_flash_message('error', 'Invalid email or password. Please try again.');
        header("Location: login.php");
        exit;
    }

} catch (PDOException $e) {
    error_log("Login DB Error: " . $e->getMessage());
    set_flash_message('error', 'A database error occurred. Please try again later.');
    header("Location: login.php");
    exit;
}
