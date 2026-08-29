<?php
/**
 * Dan Chautari - New Password Reset Processor
 * Allows user to set a new password after successful OTP verification.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

$reset_email  = $_SESSION['reset_email'] ?? '';
$otp_verified = $_SESSION['otp_verified'] ?? false;

if (!$reset_email || !$otp_verified) {
    set_flash_message('error', 'Unauthorized access. Please verify your OTP first.');
    header("Location: forgot_password.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password        = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Hash password with bcrypt
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Update user password and clear OTP
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = :password, reset_otp = NULL, otp_expiry = NULL 
                WHERE email = :email AND status = 'active'
            ");
            $stmt->execute([
                'password' => $hashed_password,
                'email'    => $reset_email
            ]);

            // Clear reset session variables
            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified']);

            set_flash_message('success', 'Your password has been reset successfully! You can now log in with your new password.');
            header("Location: login.php");
            exit;

        } catch (Exception $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            $error = 'An error occurred while updating your password. Please try again.';
        }
    }
}

$extra_css = ['auth.css'];
include_once "../includes/header.php";
?>

<div class="container">

    <div class="left">
        <h1>Set New <span class="yellow-text">Password</span></h1>
        <p>Choose a strong password for your Daan Chautari account</p>
    </div>

    <div class="form-box">

        <form action="reset_password.php" method="POST" class="form active">

            <h2>New Password</h2>

            <?php if (!empty($error)): ?>
                <div class="form-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required minlength="6">
            </div>

            <button type="submit">Update Password →</button>

        </form>

    </div>

</div>

<script src="<?php echo BASE_URL; ?>assets/js/auth.js" defer></script>
</body>
</html>
