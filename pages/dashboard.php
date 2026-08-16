<?php
/**
 * Dan Chautari - Dashboard Router
 * Routes authenticated users to their corresponding dashboard based on their role.
 */

require_once __DIR__ . '/../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

if ($_SESSION['user_role'] === 'admin') {
    header("Location: " . BASE_URL . "admin/dashboard.php");
} elseif ($_SESSION['user_role'] === 'recipient' || $_SESSION['user_role'] === 'needy') {
    header("Location: " . BASE_URL . "pages/recipient_dashboard.php");
} else {
    header("Location: " . BASE_URL . "pages/donor_dashboard.php");
}
exit;
