<?php
/**
 * Daan Chautari — Role Switcher
 * Switches the logged-in user's active role between 'donor' and 'recipient'.
 * Updates both the session AND the users table so the choice persists.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$new_role = $_POST['role'] ?? $_GET['role'] ?? '';

if (!in_array($new_role, ['donor', 'recipient'])) {
    // Just redirect back wherever they came from
    $ref = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'pages/dashboard.php';
    header("Location: $ref");
    exit;
}

try {
    // Persist in DB so next login remembers
    $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE user_id = :id");
    $stmt->execute(['role' => $new_role, 'id' => $_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("Role switch DB Error: " . $e->getMessage());
}

// Always update session regardless of DB result
$_SESSION['user_role'] = $new_role;

// Redirect to the correct dashboard
if ($new_role === 'recipient') {
    header('Location: ' . BASE_URL . 'pages/recipient_dashboard.php');
} else {
    header('Location: ' . BASE_URL . 'pages/donor_dashboard.php');
}
exit;
