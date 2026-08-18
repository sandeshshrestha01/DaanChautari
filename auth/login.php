<?php
$extra_css = ['auth.css'];
include_once "../includes/header.php";
?>

<div class="container">

    <div class="left">
        <h1>Welcome <span class="yellow-text">Back</span></h1>
        <p>Sahayogko Chautari, Aashako Yatra</p>
    </div>

    <div class="form-box">

        <form action="login_process.php" method="POST" class="form active">

            <h2>Login</h2>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>


            <button type="submit">Login</button>

            <p>
                Don't have an account?
                <a href="signup.php">Sign Up</a>
            </p>

        </form>

    </div>

</div>

<script src="<?php echo BASE_URL; ?>assets/js/auth.js" defer></script>
</body>
</html>