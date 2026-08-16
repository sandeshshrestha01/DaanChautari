<?php
/**
 * Dan Chautari - Signup Processor
 * Validates form input and inserts a new user into the `users` table.
 * Columns: user_id, full_name, email, password, phone, town, address, role, profile_photo, status
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: signup.php");
    exit;
}

// ── Collect & sanitize inputs ────────────────────────────────────────────────
$full_name        = trim(filter_input(INPUT_POST, 'fullname',         FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$email            = trim(filter_input(INPUT_POST, 'email',            FILTER_VALIDATE_EMAIL) ?? '');
$password         = $_POST['password']         ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$town             = trim(filter_input(INPUT_POST, 'town',             FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$phone            = trim(filter_input(INPUT_POST, 'phone',            FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$address          = trim(filter_input(INPUT_POST, 'address',          FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

// Role comes from the form as 'donor' or 'recipient' — map 'needy' for backward compat
$raw_role = $_POST['role'] ?? 'donor';
$role     = ($raw_role === 'needy') ? 'recipient' : $raw_role;
if (!in_array($role, ['donor', 'recipient'])) {
    $role = 'donor'; // safe default
}

// ── Validate required fields ─────────────────────────────────────────────────
if (empty($full_name) || !$email || empty($password) || empty($town) || empty($phone) || empty($address)) {
    set_flash_message('error', 'Please fill in all required fields.');
    header("Location: signup.php");
    exit;
}

if ($password !== $confirm_password) {
    set_flash_message('error', 'Passwords do not match. Please try again.');
    header("Location: signup.php");
    exit;
}

if (strlen($password) < 6) {
    set_flash_message('error', 'Password must be at least 6 characters long.');
    header("Location: signup.php");
    exit;
}

// ── Database operations ───────────────────────────────────────────────────────
try {
    // Check if email already registered
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        set_flash_message('error', 'An account with this email address already exists.');
        header("Location: signup.php");
        exit;
    }

    // Hash password securely with bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user into the users table
    $insert = $pdo->prepare("
        INSERT INTO users
            (full_name, email, password, phone, town, address, role, status)
        VALUES
            (:full_name, :email, :password, :phone, :town, :address, :role, 'active')
    ");
    $insert->execute([
        'full_name' => $full_name,
        'email'     => $email,
        'password'  => $hashed_password,
        'phone'     => $phone,
        'town'      => $town,
        'address'   => $address,
        'role'      => $role,
    ]);

    $user_id = $pdo->lastInsertId();

    // ── Auto-login: set session variables ────────────────────────────────────
    $_SESSION['user_id']    = $user_id;
    $_SESSION['user_name']  = $full_name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role']  = $role;
    $_SESSION['town']       = $town;

    set_flash_message('success', "Namaste $full_name! Your account has been created successfully. Welcome to Dan Chautari!");

    // ── Redirect to role-based dashboard ─────────────────────────────────────
    if ($role === 'recipient') {
        header("Location: " . BASE_URL . "pages/recipient_dashboard.php");
    } else {
        header("Location: " . BASE_URL . "pages/donor_dashboard.php");
    }
    exit;

} catch (PDOException $e) {
    error_log("Signup DB Error: " . $e->getMessage());
    set_flash_message('error', 'A database error occurred. Please try again later.');
    header("Location: signup.php");
    exit;
}
