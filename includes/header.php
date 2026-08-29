<?php
/**
 * Dan Chautari - Shared Header Shell
 * Establishes page headers, meta attributes, dynamic stylesheets, and active navbar navigation state.
 */

// Initialize config and database connections globally
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Detect active script name to dynamically apply the active state in navbar links
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Fetch user profile data if logged in
$user_profile = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT email, role, profile_photo, created_at FROM users WHERE user_id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user_profile = $stmt->fetch();
    } catch (PDOException $e) {
        // Silently ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " - Dan Chautari" : "Dan Chautari - Sahayogko Chautari, Aashako Yatra"; ?></title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Design Assets Stylesheets -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <?php
    if (isset($extra_css)) {
        foreach ($extra_css as $css) {
            echo '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/' . htmlspecialchars($css) . '">';
        }
    }
    ?>
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/images/logo.png" type="image/png">
    <script src="<?php echo BASE_URL; ?>assets/js/navbar.js" defer></script>

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <!-- Navigation Header -->
    <header class="header-wrapper">
        <nav class="navbar">
            <!-- Hamburger Menu Button -->
            <button id="navToggle" class="nav-toggle">☰</button>

            <!-- Brand Logo -->
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>pages/homepage.php">
                    <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Dan Chautari Logo" onerror="this.src='<?php echo BASE_URL; ?>logoDan.png'">
                </a>
            </div>

            <!-- Responsive Nav Menu Container -->
            <div class="nav-menu" id="navMenu">
                <!-- Nav Links -->
                <ul class="nav-links">
                    <li><a href="<?php echo BASE_URL; ?>pages/homepage.php" class="<?php echo $current_page == 'homepage.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>pages/about.php" class="<?php echo $current_page == 'about.php' ? 'active' : ''; ?>">About</a></li>
                    <li><a href="<?php echo BASE_URL; ?>pages/services.php" class="<?php echo $current_page == 'services.php' ? 'active' : ''; ?>">Services</a></li>
                    
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">Admin Panel</a></li>
                            <li><a href="<?php echo BASE_URL; ?>admin/manage_users.php">Manage Users</a></li>
                            <li><a href="<?php echo BASE_URL; ?>admin/manage_donations.php">Donations Ledgers</a></li>
                        <?php elseif ($_SESSION['user_role'] === 'recipient'): ?>
                            <li><a href="<?php echo BASE_URL; ?>pages/request_aid.php" class="<?php echo $current_page == 'request_aid.php' ? 'active' : ''; ?>">Request Support</a></li>
                            <li><a href="<?php echo BASE_URL; ?>pages/recipient_dashboard.php" class="<?php echo $current_page == 'recipient_dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                        <?php elseif ($_SESSION['user_role'] === 'donor'): ?>
                            <li><a href="<?php echo BASE_URL; ?>pages/donate.php" class="<?php echo $current_page == 'donate.php' ? 'active' : ''; ?>">Donate</a></li>
                            <li><a href="<?php echo BASE_URL; ?>pages/donor_dashboard.php" class="<?php echo $current_page == 'donor_dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                        <?php endif; ?>
                    <?php else: // Guest (not logged in) ?>
                        <li><a href="<?php echo BASE_URL; ?>auth/login.php" class="<?php echo $current_page == 'donate.php' ? 'active' : ''; ?>">Donate</a></li>
                        <li><a href="<?php echo BASE_URL; ?>pages/contact.php" class="<?php echo $current_page == 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
                    <?php endif; ?>
                </ul>

                <!-- Authentication Buttons Area -->
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="profile-menu-wrap">
                            <button class="profile-chip" id="profileChipBtn">
                                <div class="avatar">
                                    <?php if ($user_profile && !empty($user_profile['profile_photo'])): ?>
                                        <img src="<?php echo BASE_URL . htmlspecialchars($user_profile['profile_photo']); ?>" alt="Profile">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(strtoupper(substr($_SESSION['user_name'], 0, 1))); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                    <div class="role">
                                        <?php
                                        $role_txt = ucfirst($_SESSION['user_role']);
                                        if ($user_profile && isset($user_profile['created_at'])) {
                                            $joined = date('M Y', strtotime($user_profile['created_at']));
                                            $role_txt .= ' since ' . $joined;
                                        }
                                        echo htmlspecialchars($role_txt);
                                        ?>
                                    </div>
                                </div>
                                <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div class="profile-dropdown" id="profileDropdown">
                                <div class="profile-dropdown-head">
                                    <div class="name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($user_profile['email'] ?? $_SESSION['user_email'] ?? ''); ?></div>
                                </div>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/dashboard.php">
                                    <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="9" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="3" width="7" height="5" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="3" y="16" width="7" height="5" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="12" width="7" height="9" rx="1.5" stroke="currentColor" stroke-width="1.8"/></svg>
                                    My Dashboard
                                </a>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>pages/profile.php">
                                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8"/><path d="M5 20c1.2-3.5 4-5.5 7-5.5s5.8 2 7 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    Edit Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                
                                <!-- Role Switcher -->
                                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin'): ?>
                                <div class="role-switch-container">
                                    <div class="role-switch-label">
                                        <span>Active Role Mode</span>
                                    </div>
                                    <form action="<?php echo BASE_URL; ?>auth/switch_role.php" method="POST" class="role-switch-form">
                                        <div class="role-toggle-pill">
                                            <button type="submit" name="role" value="donor" class="role-pill-btn <?php echo $_SESSION['user_role'] === 'donor' ? 'active' : ''; ?>">
                                                🤲 Donor
                                            </button>
                                            <button type="submit" name="role" value="recipient" class="role-pill-btn <?php echo $_SESSION['user_role'] === 'recipient' ? 'active' : ''; ?>">
                                                🙏 Recipient
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>

                                <a class="dropdown-item logout" href="<?php echo BASE_URL; ?>auth/logout.php" onclick="return confirm('Are you sure you want to log out?');">
                                    <svg viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    Log Out
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>auth/login.php" class="login-btn">Login</a>
                        <a href="<?php echo BASE_URL; ?>auth/signup.php" class="signup-btn">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <?php
    // ── Global SweetAlert2 flash renderer ─────────────────────────────────────
    $flash = get_flash_message();
    if ($flash):
        $icon    = htmlspecialchars($flash['type'],    ENT_QUOTES);
        $message = htmlspecialchars($flash['message'], ENT_QUOTES);
        // Map PHP flash types to Swal icons
        $swal_icon = ($icon === 'error') ? 'error' : (($icon === 'warning') ? 'warning' : (($icon === 'info') ? 'info' : 'success'));
        $swal_title = ($swal_icon === 'success') ? 'Success' : (($swal_icon === 'error') ? 'Oops...' : ucfirst($swal_icon));
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
    
          Swal.fire({
                icon: '<?php echo $swal_icon; ?>',
                title: '<?php echo $swal_title; ?>',
                text: '<?php echo $message; ?>',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
    });
    </script>
    <?php endif; ?>

    