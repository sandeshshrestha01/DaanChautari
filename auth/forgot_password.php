<?php
/**
 * Dan Chautari - Forgot Password Request Handler
 * Prompts user for email and sends a 6-digit OTP via PHPMailer.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');

    if (!$email) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT user_id, full_name, email FROM users WHERE email = :email AND status = 'active'");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate random 6-digit OTP
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Save OTP to DB
                $update = $pdo->prepare("UPDATE users SET reset_otp = :otp, otp_expiry = :expiry WHERE user_id = :id");
                $update->execute([
                    'otp'    => $otp,
                    'expiry' => $expiry,
                    'id'     => $user['user_id']
                ]);

                // Send email
                $sent = send_otp_email($user['email'], $user['full_name'], $otp);

                if ($sent) {
                    $_SESSION['reset_email'] = $user['email'];
                    set_flash_message('success', 'A 6-digit OTP has been sent to your email address.');
                    header("Location: verify_otp.php");
                    exit;
                } else {
                    $error = 'Failed to send OTP email. Please verify mailer settings and try again.';
                }
            } else {
                // Security practice: show same feedback or clear message if email not found
                $error = 'No active account found with that email address.';
            }
        } catch (Exception $e) {
            error_log("Forgot Password Error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}

$extra_css = ['auth.css'];
include_once "../includes/header.php";
?>

<div class="container">

    <div class="left">
        <h1>Forgot <span class="yellow-text">Password?</span></h1>
        <p>Enter your email to receive a 6-digit OTP code</p>
    </div>

    <div class="form-box">

        <form action="forgot_password.php" method="POST" class="form active">

            <h2>Reset Password</h2>
            <p class="auth-desc">
                Enter the email address registered with your Daan Chautari account.
            </p>

            <?php if (!empty($error)): ?>
                <div class="form-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <button type="submit">Send OTP →</button>

            <p class="auth-links-row">
                Remember your password?
                <a href="login.php" class="link-green">Back to Login</a>
            </p>

        </form>

    </div>

</div>

<script src="<?php echo BASE_URL; ?>assets/js/auth.js" defer></script>
</body>
</html>
