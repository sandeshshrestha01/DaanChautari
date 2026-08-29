<?php
/**
 * Dan Chautari - Profile Settings
 * Allows logged-in users to update their personal information, password, and profile picture.
 */

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    set_flash_message('error', 'A database error occurred while fetching your details.');
    header("Location: " . BASE_URL . "pages/index.php");
    exit;
}

if (!$user) {
    set_flash_message('error', 'User not found.');
    header("Location: " . BASE_URL . "pages/index.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['fullname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $town      = trim($_POST['town'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validations
    if (empty($full_name) || empty($email) || empty($phone) || empty($town) || empty($address)) {
        set_flash_message('error', 'Please fill in all required fields.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('error', 'Please enter a valid email address.');
    } else {
        try {
            // Check email uniqueness
            $email_check = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :id");
            $email_check->execute(['email' => $email, 'id' => $user_id]);
            if ($email_check->fetch()) {
                set_flash_message('error', 'This email address is already in use by another account.');
            } else {
                
                // Password change handling
                $password_update_sql = "";
                $params = [
                    'fullname' => $full_name,
                    'email'    => $email,
                    'phone'    => $phone,
                    'town'     => $town,
                    'address'  => $address,
                    'id'       => $user_id
                ];

                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        set_flash_message('error', 'Password must be at least 6 characters long.');
                        header("Location: profile.php");
                        exit;
                    }
                    if ($password !== $confirm_password) {
                        set_flash_message('error', 'Passwords do not match.');
                        header("Location: profile.php");
                        exit;
                    }
                    $password_update_sql = ", password = :password";
                    $params['password']  = password_hash($password, PASSWORD_BCRYPT);
                }

                // Profile Image Upload Handling
                $photo_path = $user['profile_photo'];
                if (isset($_FILES['profile_image']) && !empty($_FILES['profile_image']['name'])) {
                    $file     = $_FILES['profile_image'];
                    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    $max_size = 3 * 1024 * 1024; // 3 MB
                    $upload_dir = __DIR__ . '/../assets/images/profiles/';

                    if (!in_array($file['type'], $allowed)) {
                        set_flash_message('error', 'Invalid photo format. Only JPG, PNG, WEBP, and GIF are allowed.');
                        header("Location: profile.php");
                        exit;
                    }
                    if ($file['size'] > $max_size) {
                        set_flash_message('error', 'Photo is too large. Max size allowed is 3 MB.');
                        header("Location: profile.php");
                        exit;
                    }

                    // Delete previous profile picture from assets/images/profiles/
                    if (!empty($user['profile_photo'])) {
                        $old_profile_filename = basename($user['profile_photo']);
                        $old_profile_filepath = $upload_dir . $old_profile_filename;
                        if (file_exists($old_profile_filepath)) {
                            @unlink($old_profile_filepath);
                        }
                    }

                    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $ext;
                    $dest     = $upload_dir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $photo_path = 'assets/images/profiles/' . $filename;
                    } else {
                        set_flash_message('error', 'Failed to upload profile photo.');
                        header("Location: profile.php");
                        exit;
                    }
                }

                // Update users database table
                $update_stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = :fullname, 
                        email = :email, 
                        phone = :phone, 
                        town = :town, 
                        address = :address, 
                        profile_photo = :photo
                        $password_update_sql
                    WHERE user_id = :id
                ");
                $params['photo'] = $photo_path;

                $update_stmt->execute($params);

                // Update session info
                $_SESSION['user_name']  = $full_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['town']       = $town;

                set_flash_message('success', 'Your profile details have been successfully updated.');
                header("Location: profile.php");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            set_flash_message('error', 'A database error occurred while updating your profile.');
        }
    }
    header("Location: profile.php");
    exit;
}

// Set page title and load header include
$extra_css  = ['profile.css'];
$page_title = "Edit Profile – Dan Chautari";
include_once __DIR__ . '/../includes/header.php';
?>

<div class="profile-page-wrapper">
    <div class="profile-card-container">
        
        <!-- Header banner -->
        <div class="profile-banner">
            <h1>Profile Settings</h1>
            <p>Manage your personal information, address, phone number, and password details.</p>
            <a href="<?php echo BASE_URL; ?>pages/dashboard.php" class="profile-back-btn">
                ← Back to Dashboard
            </a>
        </div>

        <form method="POST" action="profile.php" enctype="multipart/form-data" class="profile-form-wrap">
            
            <!-- Left Side: Profile Image Upload -->
            <div class="profile-left-col">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar-circle">
                        <img id="profile_avatar_preview" 
                             src="<?php echo !empty($user['profile_photo']) ? BASE_URL . htmlspecialchars($user['profile_photo']) : ''; ?>" 
                             alt="Avatar" 
                             class="profile-avatar-img"
                             style="<?php echo empty($user['profile_photo']) ? 'display: none;' : ''; ?>">
                        <span id="profile_initials_preview" style="<?php echo !empty($user['profile_photo']) ? 'display: none;' : ''; ?>">
                            <?php echo htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1))); ?>
                        </span>
                    </div>
                    <label for="profile_image_input" class="profile-avatar-upload-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </label>
                    <input type="file" id="profile_image_input" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif" class="profile-file-input" onchange="previewAvatar(this)">
                </div>

                <div class="profile-user-name">
                    <?php echo htmlspecialchars($user['full_name']); ?>
                </div>
                <div class="profile-user-role-badge">
                    <?php echo htmlspecialchars($user['role']); ?>
                </div>
                
                <div class="profile-support-hint">
                    Support files: JPG, PNG, WEBP, GIF. Max file size: 3MB.
                </div>

                <!-- Profile Page Role Switcher Card -->
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin'): ?>
                <div class="profile-role-switcher-card">
                    <div class="profile-role-switcher-title">
                        🔄 Switch Role
                    </div>
                    <p class="profile-role-switcher-desc">
                        Currently operating as <strong><?php echo ucfirst($_SESSION['user_role']); ?></strong>. Switch instantly below:
                    </p>
                    <form action="<?php echo BASE_URL; ?>auth/switch_role.php" method="POST">
                        <div class="profile-role-pill-wrap">
                            <button type="submit" name="role" value="donor" class="profile-role-btn <?php echo $_SESSION['user_role'] === 'donor' ? 'active' : ''; ?>">
                                🤲 Donor
                            </button>
                            <button type="submit" name="role" value="recipient" class="profile-role-btn <?php echo $_SESSION['user_role'] === 'recipient' ? 'active' : ''; ?>">
                                🙏 Recipient
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Side: Profile Details -->
            <div class="profile-right-col">
                
                <h3 class="profile-sec-title">Personal Information</h3>
                
                <div class="profile-form-row">
                    <div class="profile-form-group">
                        <label class="profile-field-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['full_name']); ?>" required class="profile-field-input">
                    </div>
                    <div class="profile-form-group">
                        <label class="profile-field-label">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="profile-field-input">
                    </div>
                </div>

                <div class="profile-form-row">
                    <div class="profile-form-group">
                        <label class="profile-field-label">Phone Number <span class="req">*</span></label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required class="profile-field-input">
                    </div>
                    <div class="profile-form-group">
                        <label class="profile-field-label">Town / City <span class="req">*</span></label>
                        <input type="text" name="town" value="<?php echo htmlspecialchars($user['town']); ?>" required class="profile-field-input">
                    </div>
                </div>

                <div class="profile-form-group-full">
                    <label class="profile-field-label">Detailed Address <span class="req">*</span></label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" required placeholder="e.g. House No, Street, Ward No" class="profile-field-input">
                </div>

                <h3 class="profile-sec-title">Security Settings</h3>
                
                <div class="profile-form-row">
                    <div class="profile-form-group">
                        <label class="profile-field-label">New Password <span class="profile-field-hint">(leave blank to keep current)</span></label>
                        <input type="password" name="password" placeholder="Min. 6 characters" class="profile-field-input">
                    </div>
                    <div class="profile-form-group">
                        <label class="profile-field-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Repeat new password" class="profile-field-input">
                    </div>
                </div>

                <button type="submit" class="profile-save-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="16" height="16" fill="currentColor"><path d="M64 80c-8.8 0-16 7.2-16 16l0 320c0 8.8 7.2 16 16 16l320 0c8.8 0 16-7.2 16-16l0-242.7c0-4.2-1.7-8.3-4.7-11.3L320 86.6 320 176c0 17.7-14.3 32-32 32l-160 0c-17.7 0-32-14.3-32-32l0-96-32 0zm80 0l0 80 128 0 0-80-128 0zM0 96C0 60.7 28.7 32 64 32l242.7 0c17 0 33.3 6.7 45.3 18.7L429.3 128c12 12 18.7 28.3 18.7 45.3L448 416c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zM160 320a64 64 0 1 1 128 0 64 64 0 1 1 -128 0z"/></svg> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewAvatar(input) {
    const avatar = document.getElementById('profile_avatar_preview');
    const initials = document.getElementById('profile_initials_preview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            avatar.src = e.target.result;
            avatar.style.display = 'block';
            initials.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
