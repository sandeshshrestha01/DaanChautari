<?php
$extra_css = ['auth.css'];
include_once "../includes/header.php";
?>

<div class="container">

    <div class="left">
        <h1>Join Daan <span class="yellow-text">Chautari</span></h1>
        <p>Daan Garaun, Sahara Banaun</p>
        <p style="margin-top:20px; font-size:0.9rem; opacity:0.8; max-width:280px; line-height:1.6;">
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
                <input type="password" id="password" name="password" placeholder="Min. 6 characters" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
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



<script>
(function () {
    var cards = document.querySelectorAll('.role-card');

    // Mark the currently-checked radio's card as active on load
    document.querySelectorAll('input[name="role"]').forEach(function (radio) {
        if (radio.checked) {
            var card = document.getElementById('card-' + radio.value);
            if (card) card.classList.add('active');
        }
    });

    // Toggle active class when any card is clicked
    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            cards.forEach(function (c) { c.classList.remove('active'); });
            card.classList.add('active');
        });
    });
}());
</script>

</body>
</html>