<?php
/**
 * Daan Chautari — Admin Login Page
 * Standalone admin-only login, separate from the public user login.
 */

require_once __DIR__ . '/../database/config.php';

// If already logged in as admin, redirect directly to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin') {
    header("Location: " . BASE_URL . "admin/dashboard.php");
    exit;
}

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Daan Chautari</title>
    <meta name="description" content="Secure administrator login portal for Daan Chautari management panel.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/admin.css">
</head>
<body class="admin-login-body">

<div class="admin-page-wrapper">
    <div class="admin-login-card">

        <!-- Brand -->
        <div class="admin-brand">
            <div class="admin-brand-icon"><img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="logo" width="50" height="50"></div>
        </div>

        <!-- Heading -->
        <h1 class="admin-card-title">Admin Sign In</h1>
        <p class="admin-card-subtitle">Access is restricted to authorized administrators only.</p>

        <!-- Flash message -->
        <?php if ($flash): ?>
            <div class="admin-alert admin-alert-<?php echo $flash['type'] === 'error' ? 'error' : 'success'; ?>">
                <span class="alert-icon"><?php echo $flash['type'] === 'error' ? '⚠️' : '✅'; ?></span>
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Login form -->
        <form id="adminLoginForm" action="admin_login_process.php" method="POST" novalidate>

            <!-- Email -->
            <div class="form-group">
                <label for="admin_email">Email Address</label>
                <div class="admin-input-wrap">
                    <span class="admin-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </span>
                    <input
                        type="email"
                        id="admin_email"
                        name="email"
                        placeholder="admin@daanchautari.org"
                        autocomplete="username"
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="admin_password">Password</label>
                <div class="admin-input-wrap">
                    <span class="admin-input-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </span>
                    <input
                        type="password"
                        id="admin_password"
                        name="password"
                        placeholder="••••••••••"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="admin-toggle-pw" id="togglePw" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="admin-btn-login" id="loginBtn">
                <span class="admin-spinner" id="loginSpinner"></span>
                <span id="loginBtnText">Sign in to Admin Panel</span>
            </button>

        </form>

        <!-- Divider + back link -->
        <div class="admin-divider">or</div>
        <a href="login.php" class="admin-back-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Return to public login
        </a>

        <!-- Security note -->
        <div class="admin-security-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Secured — Administrator access only
        </div>

    </div>
</div>

<script>
    // Toggle password visibility
    document.getElementById('togglePw').addEventListener('click', function () {
        const pw = document.getElementById('admin_password');
        const icon = document.getElementById('eyeIcon');
        const isText = pw.type === 'text';
        pw.type = isText ? 'password' : 'text';
        icon.innerHTML = isText
            ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
            : '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    });

    // Spinner on submit
    document.getElementById('adminLoginForm').addEventListener('submit', function () {
        const btn    = document.getElementById('loginBtn');
        const spinner = document.getElementById('loginSpinner');
        const text    = document.getElementById('loginBtnText');
        btn.disabled        = true;
        spinner.style.display = 'block';
        text.textContent    = 'Signing in…';
    });
</script>
</body>
</html>
