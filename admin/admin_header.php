<?php
/**
 * Daan Chautari — Admin Panel Header
 * Dedicated admin layout with sidebar. Replaces global header for all admin/*.php pages.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Admin-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    set_flash_message('error', 'Unauthorized access. Please log in as admin.');
    header("Location: " . BASE_URL . "auth/admin_login.php");
    exit;
}

$current_page  = basename($_SERVER['SCRIPT_NAME']);
$admin_name    = $_SESSION['user_name'] ?? 'Administrator';
$admin_initial = strtoupper(substr($admin_name, 0, 1));
$page_title    = $page_title ?? 'Dashboard';

$flash = get_flash_message();

// Helper: category emoji
function cat_emoji(string $cat): string {
    return match($cat) {
        'Clothing'       => '👕',
        'Education'      => '📚',
        'Food'           => '🍱',
        'Essential Needs'=> '🏠',
        default          => '📦',
    };
}

// Helper: category badge class
function cat_badge(string $cat): string {
    return match($cat) {
        'Clothing'       => 'b-clothing',
        'Education'      => 'b-education',
        'Food'           => 'b-food',
        'Essential Needs'=> 'b-essential',
        default          => 'b-essential',
    };
}

// Helper: status badge class
function status_badge(string $status): string {
    return match($status) {
        'available' => 'b-available',
        'requested' => 'b-requested',
        'approved'  => 'b-approved',
        'rejected'  => 'b-rejected',
        'pending'   => 'b-pending',
        'active'    => 'b-active',
        'inactive'  => 'b-inactive',
        default     => 'b-inactive',
    };
}

// Helper: build photo src
function item_photo_src(?string $photo, string $base): string {
    if (empty($photo)) return '';
    if (str_starts_with($photo, 'http')) return $photo;
    return $base . ltrim($photo, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — Daan Chautari Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/admin.css">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="admin-body">

<!-- ═══════════════════════════ SIDEBAR ═══════════════════════════ -->
<aside class="admin-sidebar" id="adminSidebar">

    <div class="sidebar-brand">
        <div class="brand-name">🤝 Daan Chautari</div>
        <div class="brand-tag">ADMIN CONTROL PANEL</div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-sep">Overview</div>

        <a href="<?php echo BASE_URL; ?>admin/dashboard.php"
           class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                <rect x="3" y="16" width="7" height="5" rx="1.5"/>
                <rect x="14" y="12" width="7" height="9" rx="1.5"/>
            </svg>
            Dashboard
        </a>

        <div class="sidebar-sep">Donations</div>

        <a href="<?php echo BASE_URL; ?>admin/manage_donations.php"
           class="<?php echo $current_page === 'manage_donations.php' ? 'active' : ''; ?>">
            <svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
            View Donations
        </a>

        <a href="<?php echo BASE_URL; ?>admin/manage_donations.php?filter=pending"
           class="<?php echo ($current_page === 'manage_donations.php' && ($_GET['filter'] ?? '') === 'pending') ? 'active' : ''; ?>">
            <svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            Respond to Donations
        </a>

        <a href="<?php echo BASE_URL; ?>admin/manage_donations.php"
           class="<?php echo $current_page === 'manage_donations.php' ? 'active' : ''; ?>">
            <svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/>
            </svg>
            Manage Donations
        </a>

        <div class="sidebar-sep">People</div>

        <a href="<?php echo BASE_URL; ?>admin/manage_users.php"
           class="<?php echo $current_page === 'manage_users.php' ? 'active' : ''; ?>">
            <svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                <path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
            Manage Users
        </a>

        <a href="<?php echo BASE_URL; ?>admin/manage_users.php?role=volunteer"
           class="<?php echo ($current_page === 'manage_users.php' && ($_GET['role'] ?? '') === 'volunteer') ? 'active' : ''; ?>">
            <svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
            </svg>
            Volunteers
        </a>

        <div class="sidebar-sep">Site</div>

        <a href="<?php echo BASE_URL; ?>pages/homepage.php" target="_blank">
            <svg class="ni" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            View Website
        </a>
    </nav>

    <div class="sidebar-foot">
        <a href="<?php echo BASE_URL; ?>auth/logout.php"
           onclick="return confirm('Log out of the admin panel?')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Sign Out
        </a>
    </div>
</aside>

<!-- ═══════════════════════════ MAIN ═══════════════════════════ -->
<div class="admin-main">

    <!-- Top Bar -->
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle-btn" id="sidebarToggle">☰</button>
            <div class="topbar-left">
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <p>Overview of donations, requests and community activity</p>
            </div>
        </div>
        <div class="topbar-right">
            <div>
                <div class="topbar-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="topbar-role">System Administrator</div>
            </div>
            <div class="admin-ava"><?php echo $admin_initial; ?></div>
        </div>
    </div>

    <?php if ($flash):
        $swal_icon  = $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success');
        $swal_title = $swal_icon === 'success' ? 'Done!' : ($swal_icon === 'error' ? 'Oops...' : 'Warning');
        $swal_msg   = htmlspecialchars($flash['message'], ENT_QUOTES);
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?php echo $swal_icon; ?>',
            title: '<?php echo $swal_title; ?>',
            text: '<?php echo $swal_msg; ?>',
            timer: 2500,
            timerProgressBar: true,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    });
    </script>
    <?php endif; ?>

    <!-- Page Content begins here -->
    <div class="admin-content">
