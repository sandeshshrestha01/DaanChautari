<?php
/**
 * Daan Chautari — Request Support Page
 * Allows recipients to submit a request for specific aid or items they need.
 */

require_once "../database/config.php";
require_once "../database/db.php";

// Auth Guard — Must be logged in as a Recipient
if (!isset($_SESSION['user_id'])) {
    set_flash_message('error', 'Please log in to submit a support request.');
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'recipient') {
    set_flash_message('error', 'Only Recipient accounts can request support.');
    header("Location: " . BASE_URL . "pages/homepage.php");
    exit;
}

$user_id        = $_SESSION['user_id'];
$recipient_name = $_SESSION['user_name'];
$user_town      = $_SESSION['town'] ?? '';

// Fetch primary key recipient_id from `recipients` table corresponding to logged-in user_id
try {
    $stmt_rec = $pdo->prepare("SELECT recipient_id FROM recipients WHERE user_id = :u_id");
    $stmt_rec->execute(['u_id' => $user_id]);
    $recipient_info = $stmt_rec->fetch();

    if ($recipient_info) {
        $recipient_id = $recipient_info['recipient_id'];
    } else {
        // Auto-create recipients profile entry if user registered/switched role without creating a recipients entry
        $ins_rec = $pdo->prepare("INSERT INTO recipients (user_id, town, address) VALUES (:u_id, :town, :address)");
        $ins_rec->execute([
            'u_id'    => $user_id,
            'town'    => !empty($user_town) ? $user_town : 'Kathmandu',
            'address' => $_SESSION['address'] ?? ''
        ]);
        $recipient_id = $pdo->lastInsertId();
    }
} catch (PDOException $e) {
    $recipient_id = $user_id;
}

// Fetch Dynamic Categories from Database
$default_cats = ['Food', 'Clothing', 'Education', 'Essential Needs'];
try {
    $db_cats = $pdo->query("SELECT DISTINCT category FROM donations WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
    $categories = array_values(array_unique(array_merge($default_cats, $db_cats)));
} catch (PDOException $e) {
    $categories = $default_cats;
}

// ── Handle POST: Create General Aid Request ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_support') {
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    if ($category === 'Other' || strtolower($category) === 'other') {
        $custom_cat = trim($_POST['custom_category'] ?? '');
        if (!empty($custom_cat)) {
            $category = $custom_cat;
        }
    }
    $quantity    = (int)($_POST['quantity'] ?? 1);
    $town        = trim($_POST['town'] ?? '');
    $urgency     = trim($_POST['urgency'] ?? 'Medium');
    $message     = trim($_POST['message'] ?? '');

    if ($title && !empty($category) && $quantity > 0 && $town && $message) {
        try {
            // Check if there is an available donation matching title/category in same town
            $find = $pdo->prepare("
                SELECT donation_id FROM donations 
                WHERE status = 'available' AND category = :cat AND town LIKE :town
                ORDER BY donated_at DESC LIMIT 1
            ");
            $find->execute(['cat' => $category, 'town' => '%' . $town . '%']);
            $matched = $find->fetch();

            if ($matched) {
                $donation_id = $matched['donation_id'];
            } else {
                // If no direct item available yet, match with the latest available donation or fall back gracefully
                $fallback = $pdo->prepare("SELECT donation_id FROM donations WHERE status = 'available' ORDER BY donated_at DESC LIMIT 1");
                $fallback->execute();
                $fb = $fallback->fetch();
                if ($fb) {
                    $donation_id = $fb['donation_id'];
                } else {
                    // If no donations exist in database yet, pick first donation or handle gracefully
                    $any = $pdo->query("SELECT donation_id FROM donations ORDER BY donation_id ASC LIMIT 1")->fetch();
                    $donation_id = $any ? $any['donation_id'] : 1;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO donation_requests (donation_id, recipient_id, message, status, requested_at)
                VALUES (:d_id, :r_id, :msg, 'pending', NOW())
            ");
            
            $formatted_msg = "[URGENCY: " . strtoupper($urgency) . "] Requested: " . $title . " (Qty: " . $quantity . ", Location: " . $town . ")\n" . $message;

            $stmt->execute([
                'd_id' => $donation_id,
                'r_id' => $recipient_id,
                'msg'  => $formatted_msg
            ]);

            set_flash_message('success', 'Your support request for "' . htmlspecialchars($title) . '" has been posted successfully!');
            header("Location: recipient_dashboard.php");
            exit;
        } catch (PDOException $e) {
            set_flash_message('error', 'Could not submit request. Please try again.');
        }
    } else {
        set_flash_message('error', 'Please fill in all required fields.');
    }
    header("Location: request_aid.php");
    exit;
}

$extra_css  = ['dashboard.css', 'recipient_dashboard.css'];
$page_title = 'Request Support';
include_once "../includes/header.php";
?>

<div class="dashboard-wrapper recipient-page-wrap">
    <div class="request-aid-card-wrap">
        
        <!-- Header Banner -->
        <div class="db-header-banner request-aid-header-banner">
            <div class="db-header-text">
                <h2>🤝 Request Support</h2>
                <p>Let community donors know what items or essential support you need assistance with.</p>
            </div>
            <a href="recipient_dashboard.php" class="btn-primary-db recipient-header-badge-btn">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Form Card Wrapper -->
        <div class="db-panel request-aid-form-card">
            <form method="POST" action="request_aid.php" class="request-aid-form">
                <input type="hidden" name="action" value="request_support">

                <!-- Title / Needed Item -->
                <div class="form-group request-aid-form-group">
                    <label for="req_title" class="request-aid-label">Needed Item / Support Title <span class="req">*</span></label>
                    <input type="text" id="req_title" name="title" class="form-control request-aid-input" placeholder="e.g. Winter Clothes for Family of 4, School Notebooks..." required maxlength="150">
                </div>

                <!-- Category & Quantity Row -->
                <div class="request-aid-grid-2">
                    <div class="form-group request-aid-form-group">
                        <label for="req_category" class="request-aid-label">Category <span class="req">*</span></label>
                        <select id="req_category" name="category" class="form-control request-aid-select" required onchange="toggleCustomCategory(this, 'req_custom_cat_wrap')">
                            <option value="">— Select Category —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php echo cat_emoji($cat) . ' ' . htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Other">➕ Other</option>
                        </select>
                    </div>
                    <div class="form-group request-aid-form-group">
                        <label for="req_quantity" class="request-aid-label">Quantity Needed <span class="req">*</span></label>
                        <input type="number" id="req_quantity" name="quantity" class="form-control request-aid-input" min="1" value="1" required>
                    </div>
                </div>

                <!-- Custom Category Input -->
                <div class="form-group request-aid-form-group request-aid-custom-cat" id="req_custom_cat_wrap">
                    <label for="req_custom_category" class="request-aid-label-custom">Specify Custom Category <span class="req">*</span></label>
                    <input type="text" id="req_custom_category" name="custom_category" class="form-control request-aid-input-custom" placeholder="e.g. Medical Supplies, Household Items..." maxlength="50">
                </div>

                <!-- Location Town & Urgency Row -->
                <div class="request-aid-grid-2">
                    <div class="form-group request-aid-form-group">
                        <label for="req_town" class="request-aid-label">Your Town / Location <span class="req">*</span></label>
                        <input type="text" id="req_town" name="town" class="form-control request-aid-input" value="<?php echo htmlspecialchars($user_town); ?>" placeholder="e.g. Kathmandu" required maxlength="100">
                    </div>

                    <div class="form-group request-aid-form-group">
                        <label for="req_urgency" class="request-aid-label">Urgency Level <span class="req">*</span></label>
                        <select id="req_urgency" name="urgency" class="form-control request-aid-select" required>
                            <option value="Low">Low (Within this month)</option>
                            <option value="Medium" selected>Medium (Within a week)</option>
                            <option value="High">High (Immediate / Urgent)</option>
                        </select>
                    </div>
                </div>

                <!-- Request Reason / Details -->
                <div class="form-group request-aid-form-group">
                    <label for="req_msg" class="request-aid-label">Description & Reason for Request <span class="req">*</span></label>
                    <textarea id="req_msg" name="message" class="form-control request-aid-textarea" rows="4" required placeholder="Please describe why you need this assistance and any details (size, condition needed, preferred pickup location)..."></textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary-db request-aid-submit-btn">
                    🚀 Post Support Request
                </button>
            </form>
        </div>

        <!-- Tip Box -->
        <div class="recipient-tips-panel request-aid-tips">
            <div class="recipient-tips-title">
                💡 Advice for Clear Requests
            </div>
            <p class="recipient-tips-desc">
                Be specific about sizes, quantities, and urgency. Clear descriptions help donors and admin coordinators respond much faster!
            </p>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/main.js" defer></script>

<?php
include_once "../includes/footer.php";
?>
