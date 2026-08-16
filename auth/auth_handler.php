<?php
/**
 * Dan Chautari - Authentication Backend Router
 * Secures signups, validates passwords, hashes data, and establishes active user sessions.
 */

// Boot config and database handlers
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Check if request is a POST submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "pages/index.php");
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'signup') {
    // Collect and sanitize POST variables
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $is_volunteer = isset($_POST['is_volunteer']) ? 1 : 0;

    // Direct basic validations
    if (empty($name) || !$email || empty($password)) {
        set_flash_message('error', 'Please fill in all fields with valid information.');
        header("Location: " . BASE_URL . "auth/signup.php");
        exit;
    }

    if (strlen($password) < 6) {
        set_flash_message('error', 'Password must be at least 6 characters long.');
        header("Location: " . BASE_URL . "auth/signup.php");
        exit;
    }

    if ($password !== $confirm_password) {
        set_flash_message('error', 'Passwords do not match.');
        header("Location: " . BASE_URL . "auth/signup.php");
        exit;
    }

    // Verify if email is already taken
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            set_flash_message('error', 'An account with this email address already exists.');
            header("Location: " . BASE_URL . "auth/signup.php");
            exit;
        }

        // Hash password securely with BCRYPT
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Store user in the database
        $insert_stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, password, role, status)
            VALUES (:full_name, :email, :password, 'donor', 'active')
        ");
        $insert_stmt->execute([
            'full_name' => $name,
            'email'     => $email,
            'password'  => $hashed_password,
        ]);

        // Get newly inserted User's details to log them in automatically
        $user_id = $pdo->lastInsertId();
        
        $_SESSION['user_id']   = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'donor';

        set_flash_message('success', "Namaste $name, welcome to Dan Chautari! Your account has been created successfully.");
        header("Location: " . BASE_URL . "pages/donor_dashboard.php");
        exit;

    } catch (PDOException $e) {
        error_log("Signup DB Error: " . $e->getMessage());
        set_flash_message('error', 'A system database error occurred. Please try again later.');
        header("Location: " . BASE_URL . "auth/signup.php");
        exit;
    }

} elseif ($action === 'login') {
    // Collect credentials
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
    $password = $_POST['password'] ?? '';

    if (!$email || empty($password)) {
        set_flash_message('error', 'Please fill in both email and password fields.');
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }

    try {
        // Query user details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Verify password against database hashed password
        if ($user && password_verify($password, $user['password'])) {
            // Setup active user sessions
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['town']      = $user['town'];

            set_flash_message('success', "Welcome back, {$user['full_name']}!");

            // Route user depending on administrative permissions
            if ($user['role'] === 'admin') {
                header("Location: " . BASE_URL . "admin/dashboard.php");
            } else {
                header("Location: " . BASE_URL . "pages/dashboard.php");
            }
            exit;
        } else {
            set_flash_message('error', 'Invalid email or password.');
            header("Location: " . BASE_URL . "auth/login.php");
            exit;
        }

    } catch (PDOException $e) {
        error_log("Login DB Error: " . $e->getMessage());
        set_flash_message('error', 'A system database error occurred. Please try again later.');
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
} else {
    // Route unrecognized requests back to home page
    header("Location: " . BASE_URL . "pages/index.php");
    exit;
}
?>
