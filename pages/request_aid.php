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

$recipient_id   = $_SESSION['user_id'];
$recipient_name = $_SESSION['user_name'];
$user_town      = $_SESSION['town'] ?? '';

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
            // Find any matching existing available donation or log a request
            // We store support requests in donation_requests with a general message / note
            // For general requests without a specific donation_id upfront, we can find/associate or insert.
            
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
                // If no direct item available yet, match with the latest available donation in category or fallback 1
                $fallback = $pdo->prepare("SELECT donation_id FROM donations WHERE status = 'available' ORDER BY donated_at DESC LIMIT 1");
                $fallback->execute();
                $fb = $fallback->fetch();
                $donation_id = $fb ? $fb['donation_id'] : 1;
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
            set_flash_message('error', 'Could not submit request. Item is not available');
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
    <div style="max-width: 750px; margin: 0 auto; animation: slideUp 0.4s ease-out;">
        
        <!-- Header Banner -->
        <div class="db-header-banner" style="margin-bottom: 25px;">
            <div class="db-header-text">
                <h2>🤝 Request Support</h2>
                <p>Let community donors know what items or essential support you need assistance with.</p>
            </div>
            <a href="recipient_dashboard.php" class="btn-primary-db recipient-header-badge-btn">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Form Card Wrapper -->
        <div class="db-panel" style="box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-radius: 16px; background: #ffffff; padding: 30px; border: 1px solid var(--border-color);">
            <form method="POST" action="request_aid.php" style="margin: 0; display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="action" value="request_support">

                <!-- Title / Needed Item -->
                <div class="form-group" style="margin: 0;">
                    <label for="req_title" style="font-size: 14px; font-weight: 600; color: #333;">Needed Item / Support Title <span style="color:#c62828;">*</span></label>
                    <input type="text" id="req_title" name="title" class="form-control" placeholder="e.g. Winter Clothes for Family of 4, School Notebooks..." required maxlength="150" style="padding: 12px;">
                </div>

                <!-- Category & Quantity Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="req_category" style="font-size: 14px; font-weight: 600; color: #333;">Category <span style="color:#c62828;">*</span></label>
                        <select id="req_category" name="category" class="form-control" required style="padding: 12px; height: auto;" onchange="toggleCustomCategory(this, 'req_custom_cat_wrap')">
                            <option value="">— Select Category —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php echo cat_emoji($cat) . ' ' . htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Other">➕ Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="req_quantity" style="font-size: 14px; font-weight: 600; color: #333;">Quantity Needed <span style="color:#c62828;">*</span></label>
                        <input type="number" id="req_quantity" name="quantity" class="form-control" min="1" value="1" required style="padding: 12px;">
                    </div>
                </div>

                <!-- Custom Category Input -->
                <div class="form-group" id="req_custom_cat_wrap" style="display:none; margin: 0;">
                    <label for="req_custom_category" style="font-size:13px; font-weight:600; color:#2e7d32;">Specify Custom Category <span style="color:#c62828;">*</span></label>
                    <input type="text" id="req_custom_category" name="custom_category" class="form-control" placeholder="e.g. Medical Supplies, Household Items..." maxlength="50" style="padding: 12px; border-color:#a5d6a7; background:#f1f8e9;">
                </div>

                <!-- Location Town & Urgency Row -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="req_town" style="font-size: 14px; font-weight: 600; color: #333;">Your Town / Location <span style="color:#c62828;">*</span></label>
                        <input type="text" id="req_town" name="town" class="form-control" value="<?php echo htmlspecialchars($user_town); ?>" placeholder="e.g. Kathmandu" required maxlength="100" style="padding: 12px;">
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="req_urgency" style="font-size: 14px; font-weight: 600; color: #333;">Urgency Level <span style="color:#c62828;">*</span></label>
                        <select id="req_urgency" name="urgency" class="form-control" required style="padding: 12px; height: auto;">
                            <option value="Low">Low (Within this month)</option>
                            <option value="Medium" selected>Medium (Within a week)</option>
                            <option value="High">High (Immediate / Urgent)</option>
                        </select>
                    </div>
                </div>

                <!-- Request Reason / Details -->
                <div class="form-group" style="margin: 0;">
                    <label for="req_msg" style="font-size: 14px; font-weight: 600; color: #333;">Description & Reason for Request <span style="color:#c62828;">*</span></label>
                    <textarea id="req_msg" name="message" class="form-control" rows="4" required placeholder="Please describe why you need this assistance and any details (size, condition needed, preferred pickup location)..." style="resize:vertical; padding: 12px;"></textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary-db" style="width:100%; border-radius:10px; padding:15px; font-size:16px; font-weight:600; margin-top:10px; cursor: pointer; border: none;">
                    🚀 Post Support Request
                </button>
            </form>
        </div>

        <!-- Tip Box -->
        <div class="recipient-tips-panel" style="margin-top: 20px;">
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
