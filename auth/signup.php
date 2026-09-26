<?php
$extra_css = ['auth.css'];
include_once "../includes/header.php";
?>

<div class="container">

    <div class="left">
        <h1>Join Daan <span class="yellow-text">Chautari</span></h1>
        <p>Daan Garaun, Sahara Banaun</p>
        <p class="signup-hero-text">
            Connect with a community that shares goods and kindness — no money, only support.
        </p>
    </div>

    <div class="form-box">

        <form action="signup_process.php" method="POST" class="form active">

            <span class="form-label">GET STARTED</span>
            <h2>Create your account</h2>

            <div class="form-group">
                <label for="fullname">Full Name <span class="req">*</span></label>
                <input type="text" id="fullname" name="fullname" placeholder="Sandesh Shrestha" required>
            </div>

            <div class="form-group">
                <label for="email">Email <span class="req">*</span></label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>

            <div class="form-group">
                <label for="town">Town / City <span class="req">*</span></label>
                <input type="text" id="town" name="town" placeholder="Kathmandu" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone <span class="req">*</span></label>
                <input type="tel" id="phone" name="phone" placeholder="98XXXXXXXX" maxlength="15" required>
            </div>

            <div class="form-group">
                <label for="address">Address <span class="req">*</span></label>
                <input type="text" id="address" name="address" placeholder="Thamel, Ward 26" required>
            </div>


            <div class="form-group">
                <label for="password">Password <span class="req">*</span></label>
                <div class="password-input-wrap">
                    <input type="password" id="password" name="password" placeholder="Min. 6 characters" required autocomplete="new-password">
                    <button type="button" class="toggle-pw-btn" data-target="password" aria-label="Toggle password visibility">
                        <svg class="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                <div class="password-input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required autocomplete="new-password">
                    <button type="button" class="toggle-pw-btn" data-target="confirm_password" aria-label="Toggle confirm password visibility">
                        <svg class="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-check">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
            </div>

            <button type="submit">Create Account</button>

            <p>
                Already have an account?
                <a href="login.php">Log in</a>
            </p>

        </form>

    </div>

</div>

<script src="<?php echo BASE_URL; ?>assets/js/auth.js" defer></script>
</body>
</html>