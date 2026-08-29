<?php
/**
 * Dan Chautari - OTP Verification Handler
 * Validates the 6-digit OTP entered by the user against database records.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

$reset_email = $_SESSION['reset_email'] ?? '';

if (!$reset_email) {
    set_flash_message('error', 'Please request a password reset first.');
    header("Location: forgot_password.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if (empty($otp) || strlen($otp) !== 6 || !ctype_digit($otp)) {
        $error = 'Please enter a valid 6-digit OTP code.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, reset_otp, otp_expiry FROM users WHERE email = :email AND status = 'active'");
            $stmt->execute(['email' => $reset_email]);
            $user = $stmt->fetch();

            if (!$user || !$user['reset_otp']) {
                $error = 'No active reset request found. Please request a new OTP.';
            } elseif ($user['reset_otp'] !== $otp) {
                $error = 'Invalid OTP code. Please check your email and try again.';
            } elseif (strtotime($user['otp_expiry']) < time()) {
                $error = 'The OTP code has expired. Please request a new one.';
            } else {
                // OTP verified successfully
                $_SESSION['otp_verified'] = true;
                set_flash_message('success', 'OTP verified successfully. Please enter your new password.');
                header("Location: reset_password.php");
                exit;
            }
        } catch (Exception $e) {
            error_log("OTP Verification Error: " . $e->getMessage());
            $error = 'An error occurred during verification. Please try again.';
        }
    }
}

$extra_css = ['auth.css'];
include_once "../includes/header.php";
?>

<div class="container">

    <div class="left">
        <h1>Verify <span class="yellow-text">OTP Code</span></h1>
        <p>Enter the 6-digit code sent to <?php echo htmlspecialchars($reset_email); ?></p>
    </div>

    <div class="form-box">

        <form action="verify_otp.php" method="POST" class="form active">

            <h2>Enter OTP</h2>
            <p class="auth-desc">
                Check your Gmail inbox for a 6-digit verification code.
            </p>

            <?php if (!empty($error)): ?>
                <div class="form-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="otp">6-Digit OTP</label>
                <input type="text" id="otp" name="otp" class="otp-input" placeholder="123456" maxlength="6" pattern="\d{6}" required autocomplete="off">
            </div>

            <button type="submit">Verify Code →</button>

            <div class="auth-links-row">
                <a href="forgot_password.php" class="link-green">Resend OTP</a>
                <a href="login.php" class="link-muted">Cancel</a>
            </div>

        </form>

    </div>

</div>

<script src="<?php echo BASE_URL; ?>assets/js/auth.js" defer></script>
</body>
</html>
