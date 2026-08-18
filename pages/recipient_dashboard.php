<?php
/**
 * Daan Chautari — Recipient Dashboard
 * Allows recipients to browse available donations, request items, and view request status history.
 */

require_once "../database/config.php";
require_once "../database/db.php";

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'recipient') {
    set_flash_message('error', 'Please switch to Recipient mode to access this dashboard.');
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$recipient_id   = $_SESSION['user_id'];
$recipient_name = $_SESSION['user_name'];
$user_town      = $_SESSION['town'] ?? '';

// Handle POST: Submit Item Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_item') {
    $donation_id = (int)($_POST['donation_id'] ?? 0);
    $message     = trim($_POST['message'] ?? '');

    if ($donation_id > 0) {
        try {
            // Check if already requested
            $check = $pdo->prepare("SELECT request_id FROM donation_requests WHERE donation_id = :d_id AND recipient_id = :r_id");
            $check->execute(['d_id' => $donation_id, 'r_id' => $recipient_id]);
            if ($check->fetch()) {
                set_flash_message('warning', 'You have already requested this item.');
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO donation_requests (donation_id, recipient_id, message, status, requested_at)
                    VALUES (:d_id, :r_id, :msg, 'pending', NOW())
                ");
                $stmt->execute([
                    'd_id' => $donation_id,
                    'r_id' => $recipient_id,
                    'msg'  => $message
                ]);
                set_flash_message('success', 'Your request has been submitted to the donor!');
            }
        } catch (PDOException $e) {
            set_flash_message('error', 'Could not submit request. Please try again.');
        }
    }
    header("Location: recipient_dashboard.php");
    exit;
}

$extra_css  = ['dashboard.css', 'recipient_dashboard.css'];
$page_title = 'Recipient Dashboard';
include_once "../includes/header.php";

// Fetch Dynamic Categories from Database
$default_cats = ['Food', 'Clothing', 'Education', 'Essential Needs'];
try {
    $db_cats = $pdo->query("SELECT DISTINCT category FROM donations WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
    $categories = array_values(array_unique(array_merge($default_cats, $db_cats)));
} catch (PDOException $e) {
    $categories = $default_cats;
}

// Fetch Stats
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE recipient_id = :id AND status = 'approved'");
    $stmt->execute(['id' => $recipient_id]);
    $approved_requests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE recipient_id = :id AND status = 'pending'");
    $stmt->execute(['id' => $recipient_id]);
    $pending_requests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE recipient_id = :id AND status = 'approved'");
    $stmt->execute(['id' => $recipient_id]);
    $delivered_requests = $stmt->fetchColumn();
} catch (PDOException $e) {
    $approved_requests = $pending_requests = $delivered_requests = 0;
}

// Handle GET Filter Parameters
$filter_category = trim($_GET['category'] ?? '');
$filter_town     = trim($_GET['town'] ?? '');

// Fetch Available Donations to Request with filtering
try {
    $sql = "
        SELECT d.*, u.full_name AS donor_name
        FROM donations d
        JOIN users u ON d.donor_id = u.user_id
        WHERE d.status = 'available'
    ";
    $params = [];

    if (!empty($filter_category)) {
        $sql .= " AND d.category = :category";
        $params['category'] = $filter_category;
    }
    if (!empty($filter_town)) {
        $sql .= " AND d.town LIKE :town";
        $params['town'] = '%' . $filter_town . '%';
    }

    $sql .= " ORDER BY d.donated_at DESC LIMIT 12";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $available_items = $stmt->fetchAll();
} catch (PDOException $e) {
    $available_items = [];
}

// Fetch My Requests
try {
    $stmt = $pdo->prepare("
        SELECT dr.*, d.title AS donation_title, d.category, d.photo, d.town AS donor_town, u.full_name AS donor_name, u.phone AS donor_phone
        FROM donation_requests dr
        JOIN donations d ON dr.donation_id = d.donation_id
        JOIN users u ON d.donor_id = u.user_id
        WHERE dr.recipient_id = :id
        ORDER BY dr.requested_at DESC
        LIMIT 20
    ");
    $stmt->execute(['id' => $recipient_id]);
    $my_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $my_requests = [];
}

function badge_class(string $status): string {
    return match(strtolower($status)) {
        'available' => 'badge-info',
        'approved'  => 'badge-success',
        'rejected'  => 'badge-danger',
        'pending'   => 'badge-pending',
        'delivered' => 'badge-info',
        default     => 'badge-info',
    };
}
?>

<div class="dashboard-wrapper recipient-page-wrap">

    <!-- Top Header Banner -->
    <div class="db-header-banner">
        <div class="db-header-text">
            <h2>Welcome back, <?php echo htmlspecialchars($recipient_name); ?></h2>
            <p>Browse available donations near you and track the status of your requests.</p>
        </div>
        <div class="recipient-header-actions">
            <span class="db-header-badge">RECIPIENT ACCOUNT</span>
            <a href="<?php echo BASE_URL; ?>pages/browse_donations.php" class="btn-primary-db recipient-header-badge-btn">🔍 Browse Donations</a>
        </div>
    </div>

    <!-- Metrics Bar -->
    <div class="db-metrics-grid recipient-metrics-grid">
        <div class="db-stat-card recipient-stat-card">
            <div class="db-stat-number recipient-stat-val"><?php echo $approved_requests; ?></div>
            <div class="db-stat-label recipient-stat-lbl">APPROVED</div>
        </div>
        <div class="db-stat-card blue-border recipient-stat-card">
            <div class="db-stat-number recipient-stat-val"><?php echo $my_requests ? count($my_requests) : 0; ?></div>
            <div class="db-stat-label recipient-stat-lbl">REQUESTS MADE</div>
        </div>
        <div class="db-stat-card yellow-border recipient-stat-card">
            <div class="db-stat-number recipient-stat-val"><?php echo $delivered_requests; ?></div>
            <div class="db-stat-label recipient-stat-lbl">DELIVERED TO ME</div>
        </div>
        <div class="db-stat-card purple-border recipient-stat-card">
            <div class="db-stat-number recipient-stat-val"><?php echo $pending_requests; ?></div>
            <div class="db-stat-label recipient-stat-lbl">PENDING REVIEW</div>
        </div>
    </div>

    <!-- Main Content Layout (Grid + Filter Sidebar) -->
    <div class="recipient-grid-layout">

        <!-- Left Main Area: Available Items Grid -->
        <div class="db-panel recipient-main-panel">
            <div class="recipient-panel-title-bar">
                <h3 class="recipient-panel-title-text">Available Donations Near You</h3>
                <a href="<?php echo BASE_URL; ?>pages/browse_donations.php" class="recipient-count-subtitle" style="text-decoration: underline; color: var(--accent-green);">View All (<?php echo count($available_items); ?>)</a>
            </div>

            <?php if (empty($available_items)): ?>
                <div class="db-panel recipient-empty-card">
                    <div class="recipient-empty-icon">📦</div>
                    <p class="recipient-empty-msg">No matching donations found near your location.</p>
                </div>
            <?php else: ?>
                <div class="recipient-items-grid">
                    <?php foreach ($available_items as $item): ?>
                        <div class="recipient-item-card">
                            <!-- Item Image Preview Container -->
                            <div class="recipient-item-img-wrap">
                                <?php if (!empty($item['photo'])): ?>
                                    <img src="<?php echo BASE_URL . htmlspecialchars($item['photo']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <?php else: ?>
                                    <div class="recipient-item-placeholder">
                                        <?php echo cat_emoji($item['category']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Item Body Details -->
                            <div class="recipient-item-body">
                                <div>
                                    <h4 class="recipient-item-title">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </h4>
                                    <div class="recipient-item-info">
                                        📦 Qty <?php echo (int)($item['quantity'] ?? 1); ?> · <?php echo htmlspecialchars($item['category']); ?>
                                    </div>
                                    <div class="recipient-item-info">
                                        📍 <?php echo htmlspecialchars($item['town']); ?>
                                    </div>
                                </div>

                                <form method="POST" action="recipient_dashboard.php" class="recipient-item-form">
                                    <input type="hidden" name="action" value="request_item">
                                    <input type="hidden" name="donation_id" value="<?php echo $item['donation_id']; ?>">
                                    <button type="submit" class="btn-primary-db recipient-item-btn">
                                        Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar: Search & Filters -->
        <div class="recipient-sidebar">
            <!-- Filter Form -->
            <div class="recipient-filter-panel">
                <div class="recipient-filter-title">
                    🔍 Find What You Need
                </div>
                <form method="GET" action="browse_donations.php">
                    <div class="form-group recipient-form-group-cat">
                        <label class="recipient-form-lbl">Category</label>
                        <select name="category" class="form-control recipient-form-control-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                                    <?php echo cat_emoji($cat) . ' ' . htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group recipient-form-group-town">
                        <label class="recipient-form-lbl">Your Town / City</label>
                        <input type="text" name="town" class="form-control recipient-form-control-input" placeholder="Kathmandu" value="<?php echo htmlspecialchars($filter_town); ?>">
                    </div>

                    <button type="submit" class="btn-primary-db recipient-filter-submit-btn">
                        Search Donations
                    </button>
                    <?php if (!empty($filter_category) || !empty($filter_town)): ?>
                        <a href="recipient_dashboard.php" class="recipient-clear-link">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Recipient Tips -->
            <div class="recipient-tips-panel">
                <div class="recipient-tips-title">
                    💡 Recipient Tips
                </div>
                <p class="recipient-tips-desc">
                    Request only what you need so more donations can reach others in your community faster.
                </p>
            </div>
        </div>

    </div>

    <!-- My Requests Section -->
    <div class="recipient-requests-panel">
        <div class="recipient-panel-head">
            <h3 class="recipient-panel-title-text">My Requests</h3>
            <span class="recipient-count-subtitle">Latest 20</span>
        </div>

        <?php if (empty($my_requests)): ?>
            <div class="recipient-empty-table-msg">
                <p>You haven't requested any items yet.</p>
            </div>
        <?php else: ?>
            <div class="db-table-wrapper">
                <table class="db-table recipient-table-element">
                    <thead>
                        <tr>
                            <th class="recipient-th-cell">#</th>
                            <th class="recipient-th-cell">ITEM</th>
                            <th class="recipient-th-cell">DONOR TOWN</th>
                            <th class="recipient-th-cell">QTY</th>
                            <th class="recipient-th-cell">STATUS</th>
                            <th class="recipient-th-cell">REQUESTED</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_requests as $index => $req): ?>
                            <tr class="recipient-tr-row">
                                <td class="recipient-td-num"><?php echo $index + 1; ?></td>
                                <td class="recipient-td-title"><?php echo htmlspecialchars($req['donation_title']); ?></td>
                                <td class="recipient-td-val"><?php echo htmlspecialchars($req['donor_town'] ?? $req['town'] ?? 'Kathmandu'); ?></td>
                                <td class="recipient-td-val"><?php echo (int)($req['quantity'] ?? 1); ?></td>
                                <td class="recipient-td-badge">
                                    <span class="badge <?php echo badge_class($req['status']); ?> recipient-badge-pill">
                                        <?php echo strtoupper($req['status']); ?>
                                    </span>
                                </td>
                                <td class="recipient-td-date">
                                    <?php echo date('d M Y', strtotime($req['requested_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
