<?php
/**
 * Daan Chautari — Browse Donations Page
 * Allows recipients (and guests/donors) to search and request available donations.
 */

require_once "../database/config.php";
require_once "../database/db.php";

// Auth Guard - Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    set_flash_message('error', 'Please log in to browse and request donations.');
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'recipient';

// Handle POST: Submit Item Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_item') {
    if ($user_role !== 'recipient') {
        set_flash_message('error', 'Only recipient accounts can request items.');
        header("Location: browse_donations.php");
        exit;
    }

    $donation_id = (int)($_POST['donation_id'] ?? 0);
    $message     = trim($_POST['message'] ?? '');

    if ($donation_id > 0) {
        try {
            // Fetch recipient_id from `recipients` table
            $stmt_rec = $pdo->prepare("SELECT recipient_id FROM recipients WHERE user_id = :u_id");
            $stmt_rec->execute(['u_id' => $user_id]);
            $rec_pk = $stmt_rec->fetchColumn();

            if (!$rec_pk) {
                $ins_rec = $pdo->prepare("INSERT INTO recipients (user_id, town, address) VALUES (:u, 'Kathmandu', '')");
                $ins_rec->execute(['u' => $user_id]);
                $rec_pk = $pdo->lastInsertId();
            }

            // Check donation details and calculate remaining stock
            $stmt_d = $pdo->prepare("
                SELECT d.donation_id, d.donor_id, d.title, d.quantity, d.status,
                       COALESCE((
                           SELECT SUM(dr.quantity) 
                           FROM donation_requests dr 
                           WHERE dr.donation_id = d.donation_id AND dr.status = 'approved'
                       ), 0) AS approved_qty
                FROM donations d 
                WHERE d.donation_id = :d_id
            ");
            $stmt_d->execute(['d_id' => $donation_id]);
            $don_item = $stmt_d->fetch();

            if (!$don_item) {
                set_flash_message('error', 'Donation item not found.');
            } elseif ((int)$don_item['donor_id'] === (int)$user_id) {
                // Donors cannot request their own donation items
                set_flash_message('error', 'You cannot request your own donation item. Only other recipients can request it.');
            } else {
                $remaining_qty = (int)$don_item['quantity'] - (int)$don_item['approved_qty'];

                if ($remaining_qty <= 0 || $don_item['status'] !== 'available') {
                    set_flash_message('error', 'This item is no longer available as all stock has been donated.');
                } else {
                    // Check if already requested
                    $check = $pdo->prepare("
                        SELECT request_id FROM donation_requests 
                        WHERE donation_id = :d_id AND (recipient_id = :r_id OR recipient_id = :u_id)
                    ");
                    $check->execute(['d_id' => $donation_id, 'r_id' => $rec_pk, 'u_id' => $user_id]);
                    if ($check->fetch()) {
                        set_flash_message('warning', 'You have already requested this item.');
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO donation_requests (donation_id, recipient_id, message, quantity, status, requested_at)
                            VALUES (:d_id, :r_id, :msg, 1, 'pending', NOW())
                        ");
                        $stmt->execute([
                            'd_id' => $donation_id,
                            'r_id' => $rec_pk,
                            'msg'  => $message
                        ]);
                        set_flash_message('success', 'Your request has been submitted successfully!');
                    }
                }
            }
        } catch (PDOException $e) {
            set_flash_message('error', 'Could not submit request. Please try again.');
        }
    }
    header("Location: browse_donations.php");
    exit;
}

$extra_css  = ['dashboard.css', 'recipient_dashboard.css'];
$page_title = 'Browse Available Donations';
include_once "../includes/header.php";

// Fetch Filter Inputs
$filter_category = trim($_GET['category'] ?? '');
$filter_town     = trim($_GET['town'] ?? '');
$search_query    = trim($_GET['search'] ?? '');

// Fetch Dynamic Categories from Database
$default_cats = ['Food', 'Clothing', 'Education', 'Essential Needs'];
try {
    $db_cats = $pdo->query("SELECT DISTINCT category FROM donations WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
    $categories = array_values(array_unique(array_merge($default_cats, $db_cats)));
} catch (PDOException $e) {
    $categories = $default_cats;
}

// Fetch Towns for Filter Options
try {
    $towns = $pdo->query("SELECT DISTINCT town FROM donations WHERE status = 'available' AND town IS NOT NULL AND town != '' ORDER BY town ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $towns = [];
}

// Pagination Setup
$items_per_page = 8;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $items_per_page;

// Fetch Available Donations with Filters (deducts approved quantity, hides 0 remaining stock)
try {
    // Build WHERE clause shared by count and data queries
    $where_sql = "
        WHERE d.status = 'available'
          AND (d.quantity - COALESCE((
                   SELECT SUM(dr.quantity) 
                   FROM donation_requests dr 
                   WHERE dr.donation_id = d.donation_id AND dr.status = 'approved'
               ), 0)) > 0
    ";
    $params = [];

    if (!empty($filter_category)) {
        $where_sql .= " AND d.category = :category";
        $params['category'] = $filter_category;
    }
    if (!empty($filter_town)) {
        $where_sql .= " AND d.town LIKE :town";
        $params['town'] = '%' . $filter_town . '%';
    }
    if (!empty($search_query)) {
        $where_sql .= " AND (d.title LIKE :search OR d.description LIKE :search)";
        $params['search'] = '%' . $search_query . '%';
    }

    // Count total matching items
    $count_sql = "SELECT COUNT(*) FROM donations d $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_items = (int)$count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_items / $items_per_page));

    // Clamp current page
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $items_per_page;
    }

    // Fetch paginated donations
    $sql = "
        SELECT d.*, u.full_name AS donor_name,
               COALESCE((
                   SELECT SUM(dr.quantity) 
                   FROM donation_requests dr 
                   WHERE dr.donation_id = d.donation_id AND dr.status = 'approved'
               ), 0) AS approved_qty,
               (d.quantity - COALESCE((
                   SELECT SUM(dr.quantity) 
                   FROM donation_requests dr 
                   WHERE dr.donation_id = d.donation_id AND dr.status = 'approved'
               ), 0)) AS remaining_qty
        FROM donations d
        JOIN users u ON d.donor_id = u.user_id
        $where_sql
        ORDER BY d.donated_at DESC
        LIMIT $items_per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_donations = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_donations = [];
    $total_items = 0;
    $total_pages = 1;
}

// Track IDs user has already requested
$requested_ids = [];
if ($user_role === 'recipient') {
    try {
        $stmt_rpk = $pdo->prepare("SELECT recipient_id FROM recipients WHERE user_id = :u_id");
        $stmt_rpk->execute(['u_id' => $user_id]);
        $cur_rpk = $stmt_rpk->fetchColumn();

        $req_stmt = $pdo->prepare("
            SELECT donation_id 
            FROM donation_requests 
            WHERE (recipient_id = :r_id OR recipient_id = :u_id) 
              AND status IN ('pending', 'approved')
        ");
        $req_stmt->execute(['r_id' => $cur_rpk ?: $user_id, 'u_id' => $user_id]);
        $requested_ids = $req_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $requested_ids = [];
    }
}
?>

<div class="dashboard-wrapper recipient-page-wrap">

    <!-- Top Header Banner -->
    <div class="db-header-banner" style="margin-bottom: 25px;">
        <div class="db-header-text">
            <h2>🎁 Browse Available Donations</h2>
            <p>Find essential items donated by generous community members near your area.</p>
        </div>
        <?php if ($user_role === 'recipient'): ?>
            <a href="recipient_dashboard.php" class="btn-primary-db recipient-header-badge-btn">
                ← Recipient Dashboard
            </a>
        <?php else: ?>
            <a href="donor_dashboard.php" class="btn-primary-db recipient-header-badge-btn">
                ← Donor Dashboard
            </a>
        <?php endif; ?>
    </div>

    <!-- Main Grid Layout (Filters + Grid) -->
    <div class="recipient-grid-layout">

        <!-- Main Items Grid -->
        <div class="db-panel recipient-main-panel">
            <div class="recipient-panel-title-bar">
                <h3 class="recipient-panel-title-text">
                    <?php if (!empty($search_query) || !empty($filter_category) || !empty($filter_town)): ?>
                        Search Results
                    <?php else: ?>
                        All Available Donations
                    <?php endif; ?>
                </h3>
                <span class="recipient-count-subtitle"><?php echo $total_items; ?> items found</span>
            </div>

            <?php if (empty($all_donations)): ?>
                <div class="db-panel recipient-empty-card">
                    <div class="recipient-empty-icon">📦</div>
                    <p class="recipient-empty-msg">No available donations match your search criteria.</p>
                    <a href="browse_donations.php" style="display:inline-block; margin-top:12px; font-size:13px; color:var(--accent-green); font-weight:600;">View All Donations</a>
                </div>
            <?php else: ?>
                <?php
                    // Build base URL for pagination links (preserving filters)
                    $pagination_params = [];
                    if (!empty($filter_category)) $pagination_params['category'] = $filter_category;
                    if (!empty($filter_town))     $pagination_params['town'] = $filter_town;
                    if (!empty($search_query))    $pagination_params['search'] = $search_query;

                    $range_start = $offset + 1;
                    $range_end   = min($offset + $items_per_page, $total_items);
                ?>

                <!-- Horizontal Flex Wrap Donation Cards Grid -->
                <div class="browse-cards-grid" id="browseDonationsList">
                    <?php foreach ($all_donations as $item): 
                        $rem_qty = (int)($item['remaining_qty'] ?? $item['quantity'] ?? 1);
                        $is_donor = ((int)$item['donor_id'] === (int)$user_id);
                        $is_requested = in_array((int)$item['donation_id'], array_map('intval', $requested_ids));
                        $item_img = !empty($item['img_url']) ? $item['img_url'] : ($item['photo'] ?? '');
                    ?>
                        <div class="browse-donation-card">
                            <!-- Image Section with Badges -->
                            <div class="browse-card-img-wrap">
                                <?php if (!empty($item_img)): ?>
                                    <img src="<?php echo BASE_URL . htmlspecialchars($item_img); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="browse-card-img" loading="lazy">
                                <?php else: ?>
                                    <div class="browse-card-placeholder">
                                        <span class="browse-placeholder-emoji"><?php echo cat_emoji($item['category']); ?></span>
                                        <span class="browse-placeholder-text"><?php echo htmlspecialchars($item['category']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="browse-card-badge-top-left">
                                    <span class="browse-category-pill">
                                        <?php echo cat_emoji($item['category']) . ' ' . htmlspecialchars($item['category']); ?>
                                    </span>
                                </div>

                                <div class="browse-card-badge-top-right">
                                    <span class="browse-qty-pill">
                                        <i class="fa-solid fa-boxes-stacked"></i> <?php echo $rem_qty; ?> Available
                                    </span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="browse-card-body">
                                <div class="browse-card-main-info">
                                    <h4 class="browse-card-title">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </h4>

                                    <div class="browse-card-meta-row">
                                        <span class="browse-meta-chip">
                                            <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($item['town']); ?>
                                        </span>
                                        <span class="browse-meta-chip">
                                            <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($item['donor_name'] ?? 'Community Donor'); ?>
                                        </span>
                                        <?php if (!empty($item['donated_at'])): ?>
                                            <span class="browse-meta-chip">
                                                <i class="fa-regular fa-clock"></i> <?php echo date('M j, Y', strtotime($item['donated_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($item['description'])): ?>
                                        <p class="browse-card-desc">
                                            <?php echo htmlspecialchars(mb_strimwidth($item['description'], 0, 100, '…')); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Bar -->
                                <div class="browse-card-action-bar">
                                    <div class="browse-card-status-info">
                                        <span class="browse-status-dot"></span>
                                        <span class="browse-status-text">Available</span>
                                    </div>

                                    <div class="browse-card-action-btn-wrap">
                                        <?php if ($user_role === 'recipient'): ?>
                                            <?php if ($is_donor): ?>
                                                <button type="button" disabled class="browse-action-btn btn-own-donation" title="You donated this item.">
                                                    <i class="fa-solid fa-heart"></i> Your Donation
                                                </button>
                                            <?php elseif ($is_requested): ?>
                                                <button type="button" disabled class="browse-action-btn btn-already-requested">
                                                    <i class="fa-solid fa-check"></i> Already Requested
                                                </button>
                                            <?php else: ?>
                                                <form method="POST" action="browse_donations.php" class="browse-request-form">
                                                    <input type="hidden" name="action" value="request_item">
                                                    <input type="hidden" name="donation_id" value="<?php echo $item['donation_id']; ?>">
                                                    <button type="submit" class="browse-action-btn btn-request-item">
                                                        <i class="fa-solid fa-hand-holding-heart"></i> Request Item
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="browse-donor-notice">
                                                <i class="fa-solid fa-eye"></i> Donor View Only
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- See More & See Less Pagination Bar (Server-Side) -->
                <?php if ($total_pages > 1): ?>
                    <div class="browse-pagination-bar">
                        <div class="browse-pagination-info">
                            Showing <strong><?php echo $range_start; ?>–<?php echo $range_end; ?></strong> of <strong><?php echo $total_items; ?></strong> items
                        </div>
                        <div class="browse-pagination-btns">
                            <?php if ($current_page > 1): ?>
                                <a href="browse_donations.php?<?php echo http_build_query(array_merge($pagination_params, ['page' => $current_page - 1])); ?>" class="btn-browse-page btn-page-less">
                                    <i class="fa-solid fa-chevron-left"></i> See Less
                                </a>
                            <?php endif; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <a href="browse_donations.php?<?php echo http_build_query(array_merge($pagination_params, ['page' => $current_page + 1])); ?>" class="btn-browse-page btn-page-more">
                                    See More <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <!-- Right Search & Filter Sidebar -->
        <div class="recipient-sidebar">
            <div class="recipient-filter-panel">
                <div class="recipient-filter-title">
                    🔍 Search & Filter
                </div>
                <form method="GET" action="browse_donations.php">
                    <!-- Text Search -->
                    <div class="form-group recipient-form-group-cat">
                        <label class="recipient-form-lbl">Keywords</label>
                        <input type="text" name="search" class="form-control recipient-form-control-input" placeholder="e.g. Blankets, Clothes..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>

                    <!-- Category Select -->
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

                    <!-- Town Select / Input -->
                    <div class="form-group recipient-form-group-town">
                        <label class="recipient-form-lbl">Town / City</label>
                        <input type="text" name="town" class="form-control recipient-form-control-input" placeholder="e.g. Kathmandu, Pokhara" value="<?php echo htmlspecialchars($filter_town); ?>">
                    </div>

                    <button type="submit" class="btn-primary-db recipient-filter-submit-btn">
                        Apply Filters
                    </button>

                    <?php if (!empty($filter_category) || !empty($filter_town) || !empty($search_query)): ?>
                        <a href="browse_donations.php" class="recipient-clear-link">Reset Filters</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Recipient Tip Panel -->
            <div class="recipient-tips-panel">
                <div class="recipient-tips-title">
                    💡 Helpful Advice
                </div>
                <p class="recipient-tips-desc">
                    Items are offered directly by local donors. Once requested, your request is reviewed for quick pickup or delivery coordination.
                </p>
            </div>
        </div>

    </div>

</div>

<?php
include_once "../includes/footer.php";
?>
