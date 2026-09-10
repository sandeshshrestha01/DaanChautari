<?php
/**
 * Daan Chautari — Admin Panel: Manage Donations & Respond to Requests
 * Allows admin to view, edit, update, and delete donations.
 * Dedicated "Respond to Donations" view to approve or reject recipient requests.
 */

// ── Bootstrap: DB & Config ───────────────────────────────────────────────────
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Auth Guard: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    set_flash_message('error', 'Unauthorized access. Please log in as an administrator.');
    header("Location: " . BASE_URL . "auth/admin_login.php");
    exit;
}

$admin_id   = (int)$_SESSION['user_id'];
$page_title = 'Manage Donations';

// ── 1. CSV Export Handler ─────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_tab = $_GET['tab'] ?? 'donations';
    $filename   = 'daan_chautari_' . ($export_tab === 'requests' ? 'donation_requests' : 'donations') . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    if ($export_tab === 'requests') {
        fputcsv($output, ['Request ID', 'Recipient Name', 'Recipient Town', 'Recipient Phone', 'Donation Item', 'Category', 'Donor Name', 'Requested Qty', 'Status', 'Requested At', 'Reviewed At'], ',', '"', "\\");
        $stmt = $pdo->query("
            SELECT dr.request_id,
                   COALESCE(u.full_name, u2.full_name, 'Unknown Requester') AS recipient_name,
                   COALESCE(r.town, u.town, u2.town, '—') AS recipient_town,
                   COALESCE(u.phone, u2.phone, '—') AS recipient_phone,
                   d.title AS donation_title,
                   d.category,
                   donor.full_name AS donor_name,
                   dr.quantity,
                   dr.status,
                   dr.requested_at,
                   dr.reviewed_at
            FROM donation_requests dr
            JOIN donations d ON dr.donation_id = d.donation_id
            LEFT JOIN recipients r ON dr.recipient_id = r.recipient_id
            LEFT JOIN users u ON r.user_id = u.user_id
            LEFT JOIN users u2 ON dr.recipient_id = u2.user_id
            LEFT JOIN users donor ON d.donor_id = donor.user_id
            ORDER BY dr.requested_at DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row, ',', '"', "\\");
        }
    } else {
        fputcsv($output, ['Donation ID', 'Title', 'Category', 'Donor Name', 'Donor Town', 'Donor Email', 'Quantity', 'Town', 'Status', 'Donated At'], ',', '"', "\\");
        $stmt = $pdo->query("
            SELECT d.donation_id, d.title, d.category, u.full_name AS donor_name, u.town AS donor_town,
                   u.email AS donor_email, d.quantity, d.town, d.status, d.donated_at
            FROM donations d
            JOIN users u ON d.donor_id = u.user_id
            ORDER BY d.donated_at DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row, ',', '"', "\\");
        }
    }
    fclose($output);
    exit;
}

// ── 2. POST Action Dispatcher ────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action: Edit / Update Donation
    if ($action === 'edit_donation') {
        $donation_id = (int)($_POST['donation_id'] ?? 0);
        $title       = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $category    = trim(filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        if ($category === 'Other' || strtolower($category) === 'other') {
            $custom_cat = trim(filter_input(INPUT_POST, 'custom_category', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
            if (!empty($custom_cat)) {
                $category = $custom_cat;
            }
        }
        $quantity    = (int)($_POST['quantity'] ?? 1);
        $town        = trim(filter_input(INPUT_POST, 'town', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = in_array($_POST['status'] ?? '', ['available', 'not_available'])
                       ? $_POST['status'] : 'available';

        if ($donation_id <= 0 || empty($title) || empty($category) || $quantity < 1 || empty($town)) {
            set_flash_message('error', 'Please fill in all required fields (Title, Category, Quantity >= 1, Town).');
        } else {
            try {
                // Fetch existing donation to check photo
                $cur_stmt = $pdo->prepare("SELECT * FROM donations WHERE donation_id = :id");
                $cur_stmt->execute(['id' => $donation_id]);
                $cur_don = $cur_stmt->fetch();

                if (!$cur_don) {
                    set_flash_message('error', 'Donation item not found.');
                } else {
                    $photo_path = $cur_don['img_url'];

                    // Image Upload Handler if a new file was provided
                    if (!empty($_FILES['image']['name'])) {
                        $file       = $_FILES['image'];
                        $img_types  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                        $max_size   = 3 * 1024 * 1024; // 3 MB
                        $upload_dir = __DIR__ . '/../assets/images/donations/';

                        if (!is_dir($upload_dir)) {
                            @mkdir($upload_dir, 0755, true);
                        }

                        if (!in_array($file['type'], $img_types)) {
                            set_flash_message('error', 'Invalid image format. Allowed: JPG, PNG, WEBP, GIF.');
                            header("Location: manage_donations.php?tab=donations");
                            exit;
                        }

                        if ($file['size'] > $max_size) {
                            set_flash_message('error', 'Image size is too large. Maximum size is 3 MB.');
                            header("Location: manage_donations.php?tab=donations");
                            exit;
                        }

                        // Remove previous file if exists and stored in uploads directory
                        if (!empty($photo_path) && !str_starts_with($photo_path, 'http')) {
                            $old_photo_file = __DIR__ . '/../' . ltrim($photo_path, '/');
                            if (file_exists($old_photo_file)) {
                                @unlink($old_photo_file);
                            }
                        }

                        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $filename = 'donation_' . uniqid() . '.' . $ext;
                        $dest     = $upload_dir . $filename;

                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            $photo_path = 'assets/images/donations/' . $filename;
                        }
                    }

                    // Update donation record
                    $upd_stmt = $pdo->prepare("
                        UPDATE donations
                        SET title = :t,
                            category = :c,
                            quantity = :q,
                            town = :tw,
                            description = :d,
                            status = :s,
                            img_url = :img
                        WHERE donation_id = :id
                    ");
                    $upd_stmt->execute([
                        't'   => $title,
                        'c'   => $category,
                        'q'   => $quantity,
                        'tw'  => $town,
                        'd'   => $description,
                        's'   => $status,
                        'img' => $photo_path,
                        'id'  => $donation_id
                    ]);

                    // Activity log
                    try {
                        $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, module, reference_id)
                            VALUES (:u, :act, 'donations', :ref)
                        ")->execute([
                            'u'   => $admin_id,
                            'act' => "Admin updated donation: \"$title\" (#$donation_id)",
                            'ref' => $donation_id
                        ]);
                    } catch (PDOException $e) {}

                    set_flash_message('success', "Donation \"$title\" has been updated successfully.");
                }
            } catch (PDOException $e) {
                set_flash_message('error', 'Database error: Could not update donation.');
            }
        }
        header("Location: manage_donations.php?tab=donations");
        exit;
    }

    // Action: Delete Donation
    if ($action === 'delete_donation') {
        $donation_id = (int)($_POST['donation_id'] ?? 0);

        if ($donation_id > 0) {
            try {
                $cur = $pdo->prepare("SELECT title, img_url FROM donations WHERE donation_id = :id");
                $cur->execute(['id' => $donation_id]);
                $item = $cur->fetch();

                if ($item) {
                    if (!empty($item['img_url']) && !str_starts_with($item['img_url'], 'http')) {
                        $f = __DIR__ . '/../' . ltrim($item['img_url'], '/');
                        if (file_exists($f)) {
                            @unlink($f);
                        }
                    }

                    $pdo->prepare("DELETE FROM donations WHERE donation_id = :id")->execute(['id' => $donation_id]);

                    try {
                        $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, module, reference_id)
                            VALUES (:u, :act, 'donations', :ref)
                        ")->execute([
                            'u'   => $admin_id,
                            'act' => "Admin deleted donation: \"" . $item['title'] . "\" (#$donation_id)",
                            'ref' => $donation_id
                        ]);
                    } catch (PDOException $e) {}

                    set_flash_message('success', "Donation listing deleted successfully.");
                } else {
                    set_flash_message('error', "Donation listing not found.");
                }
            } catch (PDOException $e) {
                set_flash_message('error', 'Database error: Could not delete donation.');
            }
        }
        header("Location: manage_donations.php?tab=donations");
        exit;
    }

    // Action: Respond to Donation Request (Approve / Reject / Reset)
    if ($action === 'update_request') {
        $req_id    = (int)($_POST['request_id'] ?? 0);
        $newstatus = in_array($_POST['new_status'] ?? '', ['approved', 'rejected', 'pending'])
                     ? $_POST['new_status'] : 'pending';

        if ($req_id > 0) {
            try {
                // Fetch donation and request info
                $chk_stmt = $pdo->prepare("
                    SELECT dr.donation_id, dr.quantity AS req_qty, d.quantity AS total_don_qty, d.title
                    FROM donation_requests dr
                    JOIN donations d ON dr.donation_id = d.donation_id
                    WHERE dr.request_id = :r
                ");
                $chk_stmt->execute(['r' => $req_id]);
                $qty_info = $chk_stmt->fetch();

                if ($qty_info && $newstatus === 'approved') {
                    $donation_id   = (int)$qty_info['donation_id'];
                    $total_don_qty = (int)$qty_info['total_don_qty'];
                    $req_qty       = (int)$qty_info['req_qty'];

                    // Calculate sum of already approved request quantities (excluding this request)
                    $approved_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(quantity), 0) FROM donation_requests
                        WHERE donation_id = :d_id AND status = 'approved' AND request_id != :r
                    ");
                    $approved_stmt->execute(['d_id' => $donation_id, 'r' => $req_id]);
                    $already_approved_qty = (int)$approved_stmt->fetchColumn();

                    $remaining_qty = $total_don_qty - $already_approved_qty;

                    if ($req_qty > $remaining_qty) {
                        set_flash_message('error', "Cannot approve request! Requested quantity ($req_qty) exceeds available stock ($remaining_qty remaining out of $total_don_qty total).");
                        $redirect_url = "manage_donations.php?tab=requests" . (!empty($_POST['redirect_don_id']) ? "&donation_id=" . (int)$_POST['redirect_don_id'] : '');
                        header("Location: $redirect_url");
                        exit;
                    }
                }

                $pdo->prepare("UPDATE donation_requests SET status = :s, reviewed_at = NOW(), reviewed_by = :a WHERE request_id = :r")
                    ->execute(['s' => $newstatus, 'a' => $admin_id, 'r' => $req_id]);

                // Sync donation listing status according to total approved quantity
                if ($qty_info) {
                    $d_id = (int)$qty_info['donation_id'];
                    $tot_qty = (int)$qty_info['total_don_qty'];
                    $approved_stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(quantity), 0) FROM donation_requests
                        WHERE donation_id = :d_id AND status = 'approved'
                    ");
                    $approved_stmt->execute(['d_id' => $d_id]);
                    $total_approved = (int)$approved_stmt->fetchColumn();

                    if ($total_approved >= $tot_qty) {
                        $pdo->prepare("UPDATE donations SET status = 'not_available' WHERE donation_id = :d_id")
                            ->execute(['d_id' => $d_id]);
                    } else {
                        $pdo->prepare("UPDATE donations SET status = 'available' WHERE donation_id = :d_id")
                            ->execute(['d_id' => $d_id]);
                    }
                }

                try {
                    $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, module, reference_id)
                        VALUES (:u, :act, 'requests', :ref)
                    ")->execute([
                        'u'   => $admin_id,
                        'act' => "Admin " . ($newstatus === 'approved' ? 'approved' : ($newstatus === 'rejected' ? 'rejected' : 'reset')) . " request #REQ-$req_id",
                        'ref' => $req_id
                    ]);
                } catch (PDOException $e) {}

                set_flash_message('success', "Request #REQ-$req_id has been " . strtoupper($newstatus) . ".");
            } catch (PDOException $e) {
                set_flash_message('error', 'Database error: Could not update request status.');
            }
        }

        $redir = "manage_donations.php?tab=requests" . (!empty($_POST['redirect_don_id']) ? "&donation_id=" . (int)$_POST['redirect_don_id'] : '');
        header("Location: $redir");
        exit;
    }
}

// ── 3. Determine Active Tab ──────────────────────────────────────────────────
$current_tab = $_GET['tab'] ?? (isset($_GET['filter']) && $_GET['filter'] === 'pending' ? 'requests' : 'donations');
if (!in_array($current_tab, ['donations', 'requests'])) {
    $current_tab = 'donations';
}

// ── 4. Metric Statistics ─────────────────────────────────────────────────────
try {
    $total_donations      = (int)$pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();
    $available_donations  = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'available'")->fetchColumn();
    $not_available_donations = (int)$pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'not_available'")->fetchColumn();
    $requested_donations  = 0; // Legacy — removed status
    $approved_donations   = $not_available_donations; // Map old metric to new

    $total_requests       = (int)$pdo->query("SELECT COUNT(*) FROM donation_requests")->fetchColumn();
    $pending_requests     = (int)$pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'pending'")->fetchColumn();
    $approved_requests    = (int)$pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'approved'")->fetchColumn();
    $rejected_requests    = (int)$pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'rejected'")->fetchColumn();
} catch (PDOException $e) {
    $total_donations = $available_donations = $not_available_donations = $approved_donations = 0;
    $requested_donations = 0;
    $total_requests  = $pending_requests = $approved_requests = $rejected_requests = 0;
}

// ── 5. Trend Chart Data (Last 7, 14, or 30 days) ──────────────────────────────
$chart_days_limit = isset($_GET['days']) && in_array((int)$_GET['days'], [7, 14, 30]) ? (int)$_GET['days'] : 14;

$don_trend_stmt = $pdo->prepare("
    SELECT DATE(donated_at) AS day, COUNT(*) AS count
    FROM donations
    WHERE donated_at >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
    GROUP BY DATE(donated_at)
    ORDER BY day ASC
");
$don_trend_stmt->execute(['d' => $chart_days_limit]);
$don_trend_rows = $don_trend_stmt->fetchAll();

$req_trend_stmt = $pdo->prepare("
    SELECT DATE(requested_at) AS day, COUNT(*) AS count
    FROM donation_requests
    WHERE requested_at >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
    GROUP BY DATE(requested_at)
    ORDER BY day ASC
");
$req_trend_stmt->execute(['d' => $chart_days_limit]);
$req_trend_rows = $req_trend_stmt->fetchAll();

$don_by_day = [];
foreach ($don_trend_rows as $r) $don_by_day[$r['day']] = (int)$r['count'];
$req_by_day = [];
foreach ($req_trend_rows as $r) $req_by_day[$r['day']] = (int)$r['count'];

$chart_labels      = [];
$chart_don_data    = [];
$chart_req_data    = [];

for ($i = $chart_days_limit - 1; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[]   = date('M d', strtotime($date_str));
    $chart_don_data[] = $don_by_day[$date_str] ?? 0;
    $chart_req_data[] = $req_by_day[$date_str] ?? 0;
}
$period_new_donations = array_sum($chart_don_data);
$period_new_requests  = array_sum($chart_req_data);

// ── 6. Category & Town Options for Filtering ─────────────────────────────────
$default_cats = ['Food', 'Clothing', 'Education', 'Essential Needs'];
try {
    $db_cats = $pdo->query("SELECT DISTINCT category FROM donations WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
    $category_options = array_values(array_unique(array_merge($default_cats, $db_cats)));
} catch (PDOException $e) {
    $category_options = $default_cats;
}

try {
    $town_options = $pdo->query("SELECT DISTINCT town FROM donations WHERE town IS NOT NULL AND town != '' ORDER BY town ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $town_options = [];
}

// ── 7. Fetch Records based on Active Tab ──────────────────────────────────────
$search_query    = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? 'all';
$status_filter   = $_GET['status'] ?? (isset($_GET['filter']) && $_GET['filter'] === 'pending' ? 'pending' : 'all');
$town_filter     = $_GET['town'] ?? 'all';
$filter_don_id   = isset($_GET['donation_id']) ? (int)$_GET['donation_id'] : 0;

$donations_list = [];
$requests_list  = [];

if ($current_tab === 'donations') {
    // Query Donations with donor details & request count
    $sql = "
        SELECT d.*,
               u.full_name AS donor_name,
               u.email AS donor_email,
               u.phone AS donor_phone,
               u.town AS donor_town,
               (SELECT COUNT(*) FROM donation_requests dr WHERE dr.donation_id = d.donation_id) AS total_requests_count,
               (SELECT COUNT(*) FROM donation_requests dr WHERE dr.donation_id = d.donation_id AND dr.status = 'pending') AS pending_requests_count,
               (SELECT COALESCE(SUM(quantity), 0) FROM donation_requests dr WHERE dr.donation_id = d.donation_id AND dr.status = 'approved') AS approved_qty
        FROM donations d
        JOIN users u ON d.donor_id = u.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($category_filter !== 'all' && !empty($category_filter)) {
        $sql .= " AND d.category = :cat";
        $params['cat'] = $category_filter;
    }
    if (in_array($status_filter, ['available', 'not_available'])) {
        $sql .= " AND d.status = :st";
        $params['st'] = $status_filter;
    }
    if ($town_filter !== 'all' && !empty($town_filter)) {
        $sql .= " AND d.town = :twn";
        $params['twn'] = $town_filter;
    }
    if (!empty($search_query)) {
        $sql .= " AND (d.title LIKE :sq_title OR d.description LIKE :sq_desc OR u.full_name LIKE :sq_name OR u.town LIKE :sq_town OR d.town LIKE :sq_dtown)";
        $term = "%$search_query%";
        $params['sq_title'] = $term;
        $params['sq_desc']  = $term;
        $params['sq_name']  = $term;
        $params['sq_town']  = $term;
        $params['sq_dtown'] = $term;
    }

    $sql .= " ORDER BY d.donated_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $donations_list = $stmt->fetchAll();

} else {
    // Query Requests for "Respond to Donations" Tab
    $sql = "
        SELECT dr.*,
               COALESCE(u.full_name, u2.full_name, 'Unknown Requester') AS recipient_name,
               COALESCE(u.email, u2.email, '—') AS recipient_email,
               COALESCE(u.phone, u2.phone, '—') AS recipient_phone,
               COALESCE(r.town, u.town, u2.town, '—') AS recipient_town,
               r.reason AS recipient_reason,
               d.title AS donation_title,
               d.category AS donation_category,
               d.quantity AS total_donation_qty,
               d.town AS donation_town,
               d.img_url AS donation_img,
               donor.full_name AS donor_name,
               donor.town AS donor_town,
               reviewer.full_name AS reviewer_name,
               (SELECT COALESCE(SUM(quantity), 0) FROM donation_requests WHERE donation_id = d.donation_id AND status = 'approved') AS total_approved_qty
        FROM donation_requests dr
        JOIN donations d ON dr.donation_id = d.donation_id
        LEFT JOIN recipients r ON dr.recipient_id = r.recipient_id
        LEFT JOIN users u ON r.user_id = u.user_id
        LEFT JOIN users u2 ON dr.recipient_id = u2.user_id
        LEFT JOIN users donor ON d.donor_id = donor.user_id
        LEFT JOIN users reviewer ON dr.reviewed_by = reviewer.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($filter_don_id > 0) {
        $sql .= " AND dr.donation_id = :did";
        $params['did'] = $filter_don_id;
    }
    if (in_array($status_filter, ['pending', 'approved', 'rejected'])) {
        $sql .= " AND dr.status = :st";
        $params['st'] = $status_filter;
    }
    if (!empty($search_query)) {
        $sql .= " AND (d.title LIKE :sq_item OR u.full_name LIKE :sq_name OR u2.full_name LIKE :sq_name2 OR dr.message LIKE :sq_msg OR r.town LIKE :sq_town)";
        $term = "%$search_query%";
        $params['sq_item']  = $term;
        $params['sq_name']  = $term;
        $params['sq_name2'] = $term;
        $params['sq_msg']   = $term;
        $params['sq_town']  = $term;
    }

    $sql .= " ORDER BY (dr.status = 'pending') DESC, dr.requested_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests_list = $stmt->fetchAll();
}

// ── 8. Include Layout Header ──────────────────────────────────────────────────
require_once __DIR__ . '/admin_header.php';
?>

<!-- ═══════════════════════ PAGE HEADER ═══════════════════════ -->
<div class="dash-header">
    <div>
        <h2 class="dash-title">
            <?php echo $current_tab === 'requests' ? 'Respond to Donations' : 'Manage Donations'; ?>
        </h2>
        <p class="dash-sub">
            <?php if ($current_tab === 'requests'): ?>
                Review recipient aid requests submitted in response to donations and manage approvals with stock tracking.
            <?php else: ?>
                Manage physical goods donations, adjust listing details, monitor inventory, and respond to community aid.
            <?php endif; ?>
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a href="manage_donations.php?export=csv&tab=<?php echo urlencode($current_tab); ?>" class="btn btn-outline">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export CSV
        </a>
    </div>
</div>

<!-- ═══════════════════════ STAT CARDS ═══════════════════════ -->
<div class="dash-stats">
    <div class="dstat-card dstat-green">
        <div class="dstat-icon">🎁</div>
        <div>
            <div class="dstat-num"><?php echo number_format($total_donations); ?></div>
            <div class="dstat-label">Total Donations</div>
        </div>
    </div>

    <div class="dstat-card dstat-blue">
        <div class="dstat-icon"><i class="fa-solid fa-store"></i></div>
        <div>
            <div class="dstat-num"><?php echo number_format($available_donations); ?></div>
            <div class="dstat-label">Available Listings</div>
        </div>
    </div>

    <div class="dstat-card dstat-orange">
        <div class="dstat-icon"><i class="fa-regular fa-bell"></i></div>
        <div>
            <div class="dstat-num"><?php echo number_format($pending_requests); ?></div>
            <div class="dstat-label">
                Pending Requests
                <?php if ($pending_requests > 0): ?>
                    <span style="color:#e65100; font-weight:800;">(Action needed)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dstat-card dstat-purple">
        <div class="dstat-icon"><i class="fa-regular fa-face-smile"></i></div>
        <div>
            <div class="dstat-num"><?php echo number_format($approved_requests); ?></div>
            <div class="dstat-label">Fulfilled Requests</div>
        </div>
    </div>
</div>

<!-- ═══════════════════════ TREND CHART PANEL ═══════════════════════ -->
<div class="dash-panel" style="margin-bottom: 24px;">
    <div class="dpanel-hdr" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 class="dpanel-title" style="display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color:var(--primary);">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
                Donation & Community Request Activity Trends
            </h3>
            <p style="font-size:12px; color:var(--muted); margin-top:2px;">
                <strong>+<?php echo $period_new_donations; ?> new donations</strong> and
                <strong>+<?php echo $period_new_requests; ?> aid requests</strong> over the past <?php echo $chart_days_limit; ?> days.
            </p>
        </div>

        <div class="chart-header-actions">
            <!-- Legend -->
            <div class="chart-legend-tags">
                <span class="chart-legend-tag">
                    <span class="chart-legend-dot" style="background:#10b981;"></span> New Donations
                </span>
                <span class="chart-legend-tag">
                    <span class="chart-legend-dot" style="background:#f97316;"></span> Recipient Requests
                </span>
            </div>

            <!-- Time Range Pills -->
            <div class="chart-time-pills">
                <?php
                $base_q = $_GET;
                unset($base_q['days']);
                $q_str = http_build_query($base_q);
                $sep   = !empty($q_str) ? '&' : '';
                ?>
                <a href="manage_donations.php?<?php echo $q_str . $sep; ?>days=7"
                   class="chart-time-btn <?php echo $chart_days_limit === 7 ? 'active' : ''; ?>">7 Days</a>
                <a href="manage_donations.php?<?php echo $q_str . $sep; ?>days=14"
                   class="chart-time-btn <?php echo $chart_days_limit === 14 ? 'active' : ''; ?>">14 Days</a>
                <a href="manage_donations.php?<?php echo $q_str . $sep; ?>days=30"
                   class="chart-time-btn <?php echo $chart_days_limit === 30 ? 'active' : ''; ?>">30 Days</a>
            </div>
        </div>
    </div>

    <div class="chart-wrap" style="padding: 16px 20px 20px;">
        <div class="user-chart-container">
            <canvas id="donationsTrendChart"></canvas>
        </div>
    </div>
</div>

<!-- ═══════════════════════ NAVIGATION TABS ═══════════════════════ -->
<div class="user-tabs-bar">
    <div class="user-tabs">
        <a href="manage_donations.php?tab=donations"
           class="user-tab-btn <?php echo $current_tab === 'donations' ? 'active' : ''; ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
            Manage Donations
            <span class="user-tab-count"><?php echo $total_donations; ?></span>
        </a>
        <a href="manage_donations.php?tab=requests"
           class="user-tab-btn <?php echo $current_tab === 'requests' ? 'active' : ''; ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            Respond to Donations
            <span class="user-tab-count" style="<?php echo $pending_requests > 0 ? 'background:#ffcdd2; color:#c62828;' : ''; ?>">
                <?php echo $pending_requests > 0 ? $pending_requests . ' pending' : $total_requests; ?>
            </span>
        </a>
    </div>

    <!-- Quick info badge -->
    <div style="font-size:12.5px; color:var(--muted);">
        <?php if ($current_tab === 'donations'): ?>
            Showing <strong><?php echo count($donations_list); ?></strong> donation listings
        <?php else: ?>
            Showing <strong><?php echo count($requests_list); ?></strong> requests awaiting response
        <?php endif; ?>
    </div>
</div>

<?php if ($current_tab === 'donations'): ?>
<!-- ═══════════════════════════════════════════════════════════════
     TAB 1: MANAGE DONATIONS
     ═══════════════════════════════════════════════════════════════ -->

<!-- Filter & Search Card -->
<form method="GET" action="manage_donations.php" class="user-filter-card">
    <input type="hidden" name="tab" value="donations">
    <?php if (isset($_GET['days'])): ?>
        <input type="hidden" name="days" value="<?php echo (int)$_GET['days']; ?>">
    <?php endif; ?>

    <div class="user-filter-left">
        <!-- Search -->
        <div class="input-with-icon" style="min-width: 240px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="form-control"
                   placeholder="Search item, donor, town..."
                   value="<?php echo htmlspecialchars($search_query); ?>">
        </div>

        <!-- Category Filter -->
        <select name="category" class="form-control" style="width: auto;" onchange="this.form.submit()">
            <option value="all">All Categories</option>
            <?php foreach ($category_options as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                    <?php echo cat_emoji($cat) . ' ' . htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Status Filter -->
        <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
            <option value="not_available" <?php echo $status_filter === 'not_available' ? 'selected' : ''; ?>>Not Available (Fulfilled)</option>
        </select>

        <!-- Location Filter -->
        <?php if (!empty($town_options)): ?>
        <select name="town" class="form-control" style="width: auto;" onchange="this.form.submit()">
            <option value="all">All Towns</option>
            <?php foreach ($town_options as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $town_filter === $t ? 'selected' : ''; ?>>
                    📍 <?php echo htmlspecialchars($t); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary" style="padding: 8px 14px;">Filter</button>

        <?php if (!empty($search_query) || $category_filter !== 'all' || $status_filter !== 'all' || $town_filter !== 'all'): ?>
            <a href="manage_donations.php?tab=donations" class="filter-badge-clear">✕ Clear Filters</a>
        <?php endif; ?>
    </div>
</form>

<!-- Donations Table Panel -->
<div class="dash-panel">
    <div class="dtbl-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Item</th>
                    <th>Title & Category</th>
                    <th>Donor</th>
                    <th>Stock / Qty</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Requests</th>
                    <th>Date Posted</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($donations_list)): ?>
                <tr>
                    <td colspan="9" class="td-empty" style="padding: 45px 20px;">
                        <div style="font-size: 32px; margin-bottom: 8px;">📦</div>
                        <p style="font-weight: 600; color:var(--text); margin-bottom: 4px;">No donations found matching your criteria.</p>
                        <p style="font-size: 12px; color:var(--muted);">Try adjusting your search query or reset the category/status filters.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($donations_list as $don):
                    $did           = (int)$don['donation_id'];
                    $img_src       = item_photo_src($don['img_url'] ?? '', BASE_URL);
                    $badge_class   = status_badge($don['status']);
                    $cat_cls       = cat_badge($don['category']);
                    $cat_ico       = cat_emoji($don['category']);
                    $total_qty     = (int)$don['quantity'];
                    $approved_qty  = (int)$don['approved_qty'];
                    $remaining_qty = max(0, $total_qty - $approved_qty);
                    $tot_req_count = (int)$don['total_requests_count'];
                    $pnd_req_count = (int)$don['pending_requests_count'];

                    // Stock pill class
                    $stock_class = 'stock-pill-ok';
                    if ($remaining_qty === 0) {
                        $stock_class = 'stock-pill-out';
                    } elseif ($remaining_qty <= 2 && $total_qty > 2) {
                        $stock_class = 'stock-pill-low';
                    }

                    // JSON data for Edit & Details Modals
                    $don_json = htmlspecialchars(json_encode([
                        'donation_id'   => $did,
                        'title'         => $don['title'],
                        'category'      => $don['category'],
                        'quantity'      => $total_qty,
                        'remaining_qty' => $remaining_qty,
                        'approved_qty'  => $approved_qty,
                        'town'          => $don['town'],
                        'description'   => $don['description'] ?? '',
                        'status'        => $don['status'],
                        'img_url'       => $img_src,
                        'donated_at'    => date('d M Y, h:i A', strtotime($don['donated_at'])),
                        'donor_name'    => $don['donor_name'],
                        'donor_email'   => $don['donor_email'],
                        'donor_phone'   => $don['donor_phone'] ?? '—',
                        'donor_town'    => $don['donor_town'] ?? '—',
                        'total_reqs'    => $tot_req_count,
                        'pending_reqs'  => $pnd_req_count
                    ]), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <!-- Thumbnail -->
                    <td>
                        <div class="don-thumb-wrap" onclick="viewPhoto('<?php echo htmlspecialchars($img_src, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($don['title'], ENT_QUOTES); ?>')">
                            <?php if (!empty($img_src)): ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($don['title']); ?>" class="don-thumb-img" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'don-thumb-placeholder\'><?php echo $cat_ico; ?></div>';">
                            <?php else: ?>
                                <div class="don-thumb-placeholder"><?php echo $cat_ico; ?></div>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- Title & Category -->
                    <td>
                        <div style="max-width: 240px;">
                            <a href="javascript:void(0)" onclick='openViewModal(<?php echo $don_json; ?>)' class="don-title-link">
                                <?php echo htmlspecialchars($don['title']); ?>
                            </a>
                            <span class="badge <?php echo $cat_cls; ?>" style="font-size:10.5px; padding:2px 7px; margin-top:3px;">
                                <?php echo $cat_ico . ' ' . htmlspecialchars($don['category']); ?>
                            </span>
                        </div>
                    </td>

                    <!-- Donor -->
                    <td>
                        <div style="font-weight: 600; color:var(--text);"><?php echo htmlspecialchars($don['donor_name']); ?></div>
                        <div style="font-size: 11.5px; color:var(--muted);"><?php echo htmlspecialchars($don['donor_town'] ?? '—'); ?></div>
                    </td>

                    <!-- Stock / Quantity -->
                    <td>
                        <div class="stock-pill <?php echo $stock_class; ?>">
                            <span><?php echo $remaining_qty; ?> / <?php echo $total_qty; ?> avail</span>
                        </div>
                    </td>

                    <!-- Location -->
                    <td>
                        <span style="display:inline-flex; align-items:center; gap:4px; font-size:12.5px;">
                            📍 <?php echo htmlspecialchars($don['town']); ?>
                        </span>
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="dbadge <?php echo $badge_class; ?>">
                            <?php echo $don['status'] === 'not_available' ? 'Not Available' : ucfirst($don['status']); ?>
                        </span>
                    </td>

                    <!-- Requests pill -->
                    <td>
                        <?php if ($pnd_req_count > 0): ?>
                            <a href="manage_donations.php?tab=requests&donation_id=<?php echo $did; ?>&status=pending"
                               class="btn-respond-tag has-pending"
                               title="Respond to <?php echo $pnd_req_count; ?> pending requests">
                                💬 Respond (<?php echo $pnd_req_count; ?>)
                            </a>
                        <?php elseif ($tot_req_count > 0): ?>
                            <a href="manage_donations.php?tab=requests&donation_id=<?php echo $did; ?>"
                               class="btn-respond-tag"
                               title="View <?php echo $tot_req_count; ?> requests">
                                👁 <?php echo $tot_req_count; ?> reqs
                            </a>
                        <?php else: ?>
                            <span style="color:var(--muted); font-size:11.5px;">No reqs</span>
                        <?php endif; ?>
                    </td>

                    <!-- Date -->
                    <td class="dtd-muted" style="white-space: nowrap;">
                        <?php echo date('d M Y', strtotime($don['donated_at'])); ?>
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="action-btn-group" style="justify-content: flex-end;">
                            <!-- View Details Button -->
                            <button type="button" class="btn-icon-sq" title="View Details"
                                    onclick='openViewModal(<?php echo $don_json; ?>)'>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>

                            <!-- Edit Donation Button -->
                            <button type="button" class="btn-icon-sq" title="Edit Donation Details"
                                    onclick='openEditModal(<?php echo $don_json; ?>)'>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>

                            <!-- Delete Donation Button -->
                            <form method="POST" id="del_form_<?php echo $did; ?>" style="display:inline;">
                                <input type="hidden" name="action" value="delete_donation">
                                <input type="hidden" name="donation_id" value="<?php echo $did; ?>">
                                <button type="button" class="btn-icon-sq btn-danger-hover" title="Delete Donation"
                                        onclick="confirmDeleteDonation(<?php echo $did; ?>, '<?php echo htmlspecialchars(addslashes($don['title'])); ?>')">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════
     TAB 2: RESPOND TO DONATIONS (Donation Requests)
     ═══════════════════════════════════════════════════════════════ -->

<?php if ($filter_don_id > 0): ?>
<div style="background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
    <div>
        <span style="font-size:12px; font-weight:700; color:#0277bd; text-transform:uppercase; letter-spacing:0.5px;">Filtered for Donation Item:</span>
        <strong style="font-size:14px; color:#01579b; margin-left:6px;">
            <?php echo htmlspecialchars($requests_list[0]['donation_title'] ?? ("Donation #$filter_don_id")); ?>
        </strong>
    </div>
    <a href="manage_donations.php?tab=requests" class="btn btn-outline" style="padding: 4px 10px; font-size:12px;">
        Show All Donation Requests →
    </a>
</div>
<?php endif; ?>

<!-- Filter & Search Card for Requests -->
<form method="GET" action="manage_donations.php" class="user-filter-card">
    <input type="hidden" name="tab" value="requests">
    <?php if ($filter_don_id > 0): ?>
        <input type="hidden" name="donation_id" value="<?php echo $filter_don_id; ?>">
    <?php endif; ?>

    <div class="user-filter-left">
        <!-- Search -->
        <div class="input-with-icon" style="min-width: 240px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="form-control"
                   placeholder="Search requester, item, message..."
                   value="<?php echo htmlspecialchars($search_query); ?>">
        </div>

        <!-- Status Filter Tabs / Select -->
        <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending (Action Required)</option>
            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>

        <button type="submit" class="btn btn-primary" style="padding: 8px 14px;">Filter</button>

        <?php if (!empty($search_query) || $status_filter !== 'all' || $filter_don_id > 0): ?>
            <a href="manage_donations.php?tab=requests" class="filter-badge-clear">✕ Clear Filters</a>
        <?php endif; ?>
    </div>
</form>

<!-- Requests Table Panel -->
<div class="dash-panel">
    <div class="dtbl-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th style="width: 85px;">Req ID</th>
                    <th>Recipient</th>
                    <th>Item </th>
                    <th>Donor</th>
                    <th>Qty</th>
                    <th>Stock Status</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Requested At</th>
                    <th style="text-align: right;">Respond / Decision</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($requests_list)): ?>
                <tr>
                    <td colspan="10" class="td-empty" style="padding: 45px 20px;">
                        <div style="font-size: 32px; margin-bottom: 8px;">📬</div>
                        <p style="font-weight: 600; color:var(--text); margin-bottom: 4px;">No requests found matching your filters.</p>
                        <p style="font-size: 12px; color:var(--muted);">Check again later or view all requests without filter.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($requests_list as $req):
                    $rid             = (int)$req['request_id'];
                    $req_qty         = (int)$req['quantity'];
                    $tot_item_qty    = (int)$req['total_donation_qty'];
                    $tot_appr_qty    = (int)$req['total_approved_qty'];
                    $remaining_stock = max(0, $tot_item_qty - $tot_appr_qty);
                    $rs              = $req['status'];
                    $rbadge          = match($rs) {
                        'approved' => 'b-approved',
                        'rejected' => 'b-rejected',
                        'pending'  => 'b-pending',
                        default    => 'b-inactive'
                    };
                    $cat_cls = cat_badge($req['donation_category']);
                    $cat_ico = cat_emoji($req['donation_category']);
                    $initial = strtoupper(substr($req['recipient_name'], 0, 1));
                    $is_over_stock = ($rs === 'pending' && $req_qty > $remaining_stock);
                ?>
                <tr>
                    <!-- Req ID -->
                    <td>
                        <span style="font-weight: 700; color: var(--primary); font-size: 12.5px;">
                            REQ-<?php echo $rid; ?>
                        </span>
                    </td>

                    <!-- Recipient Info -->
                    <td>
                        <div style="display:flex; align-items:flex-start; gap:10px;">
                            <div class="dreq-ava" style="margin-top:2px;"><?php echo $initial; ?></div>
                            <div>
                                <div style="font-weight: 600; color: var(--text);">
                                    <?php echo htmlspecialchars($req['recipient_name']); ?>
                                </div>
                                <div style="font-size: 11.5px; color: var(--muted);">
                                    <i class="fa-solid fa-map-pin"></i> <?php echo htmlspecialchars($req['recipient_town']); ?>
                                    <?php if (!empty($req['recipient_phone']) && $req['recipient_phone'] !== '—'): ?>
                                        &nbsp;•&nbsp;📞<?php echo htmlspecialchars($req['recipient_phone']); ?>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        </div>
                    </td>

                    <!-- Item Requested -->
                    <td>
                        <div style="font-weight: 600; color: var(--text); max-width: 200px;">
                            <?php echo htmlspecialchars($req['donation_title']); ?>
                        </div>
                        <span class="badge <?php echo $cat_cls; ?>" style="font-size:10px; padding:2px 6px; margin-top:2px;">
                            <?php echo $cat_ico . ' ' . htmlspecialchars($req['donation_category']); ?>
                        </span>
                    </td>

                    <!-- Donor -->
                    <td>
                        <div style="font-weight: 500; color: var(--text);">
                            <?php echo htmlspecialchars($req['donor_name'] ?? '—'); ?>
                        </div>
                        <div style="font-size: 11.5px; color: var(--muted);">
                            <?php echo htmlspecialchars($req['donor_town'] ?? '—'); ?>
                        </div>
                    </td>

                    <!-- Qty Requested -->
                    <td>
                        <strong style="font-size: 14px; color: var(--text);"><?php echo $req_qty; ?></strong> unit<?php echo $req_qty > 1 ? 's' : ''; ?>
                    </td>

                    <!-- Stock Status -->
                    <td>
                        <div class="stock-pill <?php echo $remaining_stock > 0 ? 'stock-pill-ok' : 'stock-pill-out'; ?>">
                            <?php echo $remaining_stock; ?> of <?php echo $tot_item_qty; ?> left
                        </div>
                        <?php if ($is_over_stock): ?>
                            <div style="font-size:10.5px; color:var(--danger); font-weight:700; margin-top:2px;">
                                Exceeds stock (<?php echo $remaining_stock; ?> avail)
                            </div>
                        <?php endif; ?>
                    </td>
                    <!-- Message -->
<td>
    <?php if (!empty($req['message'])): ?>
        <div class="req-message-box">
            "<?php echo htmlspecialchars($req['message']); ?>"
        </div>
    <?php else: ?>
        <span style="color: var(--muted); font-size: 12px;">—</span>
    <?php endif; ?>
</td>


                    <!-- Status Badge -->
                    <td>
                        <span class="dbadge <?php echo $rbadge; ?>">
                            <?php echo ucfirst($rs); ?>
                        </span>
                        <?php if (!empty($req['reviewer_name']) && $rs !== 'pending'): ?>
                            <div style="font-size: 10.5px; color: var(--muted); margin-top: 2px;">
                                By <?php echo htmlspecialchars($req['reviewer_name']); ?>
                            </div>
                        <?php endif; ?>
                    </td>

                    <!-- Requested At -->
                    <td class="dtd-muted" style="white-space: nowrap;">
                        <?php echo date('d M Y', strtotime($req['requested_at'])); ?>
                        <div style="font-size:10.5px;"><?php echo date('h:i A', strtotime($req['requested_at'])); ?></div>
                    </td>

                    <!-- Respond / Actions -->
                    <td>
                        <div class="action-btn-group" style="justify-content: flex-end;">
                            <?php if ($rs === 'pending'): ?>
                                <!-- Approve Form -->
                                <form method="POST" id="app_form_<?php echo $rid; ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="update_request">
                                    <input type="hidden" name="request_id" value="<?php echo $rid; ?>">
                                    <input type="hidden" name="new_status" value="approved">
                                    <?php if ($filter_don_id > 0): ?>
                                        <input type="hidden" name="redirect_don_id" value="<?php echo $filter_don_id; ?>">
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary" style="padding: 5px 12px; font-size:12px;"
                                            onclick="confirmApproveRequest(<?php echo $rid; ?>, '<?php echo htmlspecialchars(addslashes($req['recipient_name'])); ?>', <?php echo $req_qty; ?>, <?php echo $remaining_stock; ?>, <?php echo $tot_item_qty; ?>)">
                                        ✓ Approve
                                    </button>
                                </form>

                                <!-- Reject Form -->
                                <form method="POST" id="rej_form_<?php echo $rid; ?>" style="display:inline;">
                                    <input type="hidden" name="action" value="update_request">
                                    <input type="hidden" name="request_id" value="<?php echo $rid; ?>">
                                    <input type="hidden" name="new_status" value="rejected">
                                    <?php if ($filter_don_id > 0): ?>
                                        <input type="hidden" name="redirect_don_id" value="<?php echo $filter_don_id; ?>">
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline" style="padding: 5px 10px; font-size:12px; color:var(--danger); border-color:#ffcdd2;"
                                            onclick="confirmRejectRequest(<?php echo $rid; ?>, '<?php echo htmlspecialchars(addslashes($req['recipient_name'])); ?>')">
                                        ✕ Reject
                                    </button>
                                </form>
                            <?php else: ?>
                                <!-- Re-evaluate / Reset Option -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_request">
                                    <input type="hidden" name="request_id" value="<?php echo $rid; ?>">
                                    <input type="hidden" name="new_status" value="pending">
                                    <?php if ($filter_don_id > 0): ?>
                                        <input type="hidden" name="redirect_don_id" value="<?php echo $filter_don_id; ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="btn-icon-sq" title="Reset back to Pending"
                                            onclick="return confirm('Re-open request #REQ-<?php echo $rid; ?> back to pending?')">
                                        ↺
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ═══════════════════════ MODAL: VIEW DONATION DETAILS ═══════════════════════ -->
<div class="admin-modal-overlay" id="viewDonationModal">
    <div class="admin-modal-card" style="max-width: 620px;">
        <div class="admin-modal-hdr">
            <h3 id="viewModalTitle">
                <span id="viewModalEmoji">📦</span> <span id="viewModalHeaderTitle">Donation Overview</span>
            </h3>
            <button type="button" class="admin-modal-close" onclick="closeViewModal()">✕</button>
        </div>

        <div class="admin-modal-body">
            <!-- Photo preview -->
            <div id="viewModalPhotoWrap" style="display:none; text-align:center; margin-bottom: 16px;">
                <img id="viewModalPhoto" src="" alt="Donation Image" class="don-preview-modal-img">
            </div>

            <!-- Details Grid -->
            <div class="profile-meta-grid" style="grid-template-columns: repeat(2, 1fr);">
                <div class="pm-item">
                    <div class="pm-label">Category</div>
                    <div class="pm-value" id="viewModalCategory">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Location / Town</div>
                    <div class="pm-value" id="viewModalTown">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Stock Status</div>
                    <div class="pm-value" id="viewModalStock">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Listing Status</div>
                    <div class="pm-value" id="viewModalStatus">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Donor Name</div>
                    <div class="pm-value" id="viewModalDonorName">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Donor Contact</div>
                    <div class="pm-value" id="viewModalDonorContact">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Date Donated</div>
                    <div class="pm-value" id="viewModalDonatedAt">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Community Requests</div>
                    <div class="pm-value" id="viewModalRequests">—</div>
                </div>
            </div>

            <!-- Description -->
            <div style="margin-top: 14px;">
                <div class="pm-label">Item Description</div>
                <div id="viewModalDescription" style="font-size:13px; color:#333; line-height:1.6; background:#f9fbf9; border:1px solid var(--border); padding:12px; border-radius:8px; white-space:pre-wrap; margin-top:4px;">
                    —
                </div>
            </div>
        </div>

        <div class="admin-modal-foot">
            <a href="#" id="viewModalRespondLink" class="btn btn-primary" style="display:none;">
                💬 View & Respond to Requests
            </a>
            <button type="button" class="btn btn-outline" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════ MODAL: EDIT DONATION ═══════════════════════ -->
<div class="admin-modal-overlay" id="editDonationModal">
    <div class="admin-modal-card" style="max-width: 580px;">
        <div class="admin-modal-hdr">
            <h3>✏️ Edit Donation Listing</h3>
            <button type="button" class="admin-modal-close" onclick="closeEditModal()">✕</button>
        </div>

        <form method="POST" action="manage_donations.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_donation">
            <input type="hidden" name="donation_id" id="edit_donation_id">

            <div class="admin-modal-body">
                <!-- Title -->
                <div class="form-group">
                    <label class="form-label">Item Title <span class="req">*</span></label>
                    <input type="text" name="title" id="edit_title" class="form-control" required maxlength="150">
                </div>

                <div class="form-grid-2">
                    <!-- Category -->
                    <div class="form-group">
                        <label class="form-label">Category <span class="req">*</span></label>
                        <select name="category" id="edit_category" class="form-control" required onchange="toggleEditCustomCat(this.value)">
                            <?php foreach ($category_options as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php echo cat_emoji($cat) . ' ' . htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Other">➕ Other (Custom)</option>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="form-group">
                        <label class="form-label">Quantity <span class="req">*</span></label>
                        <input type="number" name="quantity" id="edit_quantity" class="form-control" min="1" required>
                    </div>
                </div>

                <!-- Custom Category Input (Hidden by default) -->
                <div class="form-group" id="edit_custom_cat_wrap" style="display:none;">
                    <label class="form-label">Custom Category Name</label>
                    <input type="text" name="custom_category" id="edit_custom_category" class="form-control" placeholder="e.g., Medical Supplies">
                </div>

                <div class="form-grid-2">
                    <!-- Town -->
                    <div class="form-group">
                        <label class="form-label">Location / Town <span class="req">*</span></label>
                        <input type="text" name="town" id="edit_town" class="form-control" required maxlength="100">
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label">Status <span class="req">*</span></label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="available">Available</option>
                            <option value="not_available">Not Available</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label class="form-label">Description / Condition</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Condition, size, age, special instructions..."></textarea>
                </div>

                <!-- Current Photo & Upload -->
                <div class="form-group">
                    <label class="form-label">Item Photo</label>
                    <div id="edit_photo_preview_wrap" style="display:none; margin-bottom: 8px; align-items:center; gap:10px;">
                        <img id="edit_photo_preview" src="" alt="Current photo" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid var(--border);">
                        <span style="font-size:12px; color:var(--muted);">Current image on file. Upload below to replace.</span>
                    </div>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small style="font-size:11px; color:var(--muted); margin-top:3px; display:block;">Supported: JPG, PNG, WEBP, GIF (Max 3 MB).</small>
                </div>
            </div>

            <div class="admin-modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════ MODAL: IMAGE LIGHTBOX ═══════════════════════ -->
<div class="admin-modal-overlay" id="photoLightboxModal" onclick="closePhotoLightbox()">
    <div style="max-width: 90vw; max-height: 90vh; position: relative; text-align:center;">
        <img id="lightboxImg" src="" alt="Enlarged Item" style="max-width: 100%; max-height: 85vh; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.4);">
        <p id="lightboxCaption" style="color: #fff; font-size: 14px; margin-top: 8px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.8);"></p>
    </div>
</div>

<!-- ═══════════════════════ JAVASCRIPT & CHARTS ═══════════════════════ -->
<script>
// ── Trend Line Chart ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('donationsTrendChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    {
                        label: 'New Donations',
                        data: <?php echo json_encode($chart_don_data); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.75)',
                        hoverBackgroundColor: 'rgba(16, 185, 129, 0.95)',
                        borderColor: '#10b981',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false
                    },
                    {
                        label: 'Recipient Requests',
                        data: <?php echo json_encode($chart_req_data); ?>,
                        backgroundColor: 'rgba(249, 115, 22, 0.70)',
                        hoverBackgroundColor: 'rgba(249, 115, 22, 0.95)',
                        borderColor: '#f97316',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        padding: 10,
                        backgroundColor: 'rgba(13, 31, 13, 0.92)',
                        titleFont: { size: 12, weight: '700' },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, color: '#777' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 }, color: '#777' },
                        grid: { color: 'rgba(0, 0, 0, 0.04)' }
                    }
                }
            }
        });
    }
});

// ── Modals Handling ──────────────────────────────────────────────────────────
function openViewModal(data) {
    document.getElementById('viewModalHeaderTitle').textContent = data.title || 'Donation Overview';
    document.getElementById('viewModalCategory').textContent    = data.category;
    document.getElementById('viewModalTown').textContent        = data.town;
    document.getElementById('viewModalStock').innerHTML       = `<strong>${data.remaining_qty}</strong> of ${data.quantity} units available (${data.approved_qty} fulfilled)`;
    document.getElementById('viewModalStatus').innerHTML      = `<span class="dbadge ${data.status === 'available' ? 'b-available' : 'b-rejected'}">${data.status === 'not_available' ? 'NOT AVAILABLE' : data.status.toUpperCase()}</span>`;
    document.getElementById('viewModalDonorName').textContent   = data.donor_name;
    document.getElementById('viewModalDonorContact').textContent= `${data.donor_email} • ${data.donor_phone}`;
    document.getElementById('viewModalDonatedAt').textContent   = data.donated_at;
    document.getElementById('viewModalRequests').textContent    = `${data.total_reqs} total (${data.pending_reqs} pending response)`;
    document.getElementById('viewModalDescription').textContent = data.description || 'No additional description provided.';

    const photoWrap = document.getElementById('viewModalPhotoWrap');
    const photoEl   = document.getElementById('viewModalPhoto');
    if (data.img_url) {
        photoEl.src = data.img_url;
        photoWrap.style.display = 'block';
    } else {
        photoWrap.style.display = 'none';
    }

    const respLink = document.getElementById('viewModalRespondLink');
    if (data.total_reqs > 0) {
        respLink.href = `manage_donations.php?tab=requests&donation_id=${data.donation_id}`;
        respLink.style.display = 'inline-flex';
    } else {
        respLink.style.display = 'none';
    }

    document.getElementById('viewDonationModal').classList.add('active');
}

function closeViewModal() {
    document.getElementById('viewDonationModal').classList.remove('active');
}

function openEditModal(data) {
    document.getElementById('edit_donation_id').value  = data.donation_id;
    document.getElementById('edit_title').value        = data.title;
    document.getElementById('edit_quantity').value     = data.quantity;
    document.getElementById('edit_town').value         = data.town;
    document.getElementById('edit_status').value       = data.status;
    document.getElementById('edit_description').value  = data.description || '';

    const catSelect = document.getElementById('edit_category');
    let found = false;
    for (let i = 0; i < catSelect.options.length; i++) {
        if (catSelect.options[i].value === data.category) {
            catSelect.selectedIndex = i;
            found = true;
            break;
        }
    }
    if (!found) {
        catSelect.value = 'Other';
        document.getElementById('edit_custom_cat_wrap').style.display = 'block';
        document.getElementById('edit_custom_category').value = data.category;
    } else {
        document.getElementById('edit_custom_cat_wrap').style.display = 'none';
        document.getElementById('edit_custom_category').value = '';
    }

    const prevWrap = document.getElementById('edit_photo_preview_wrap');
    const prevEl   = document.getElementById('edit_photo_preview');
    if (data.img_url) {
        prevEl.src = data.img_url;
        prevWrap.style.display = 'flex';
    } else {
        prevWrap.style.display = 'none';
    }

    document.getElementById('editDonationModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editDonationModal').classList.remove('active');
}

function toggleEditCustomCat(val) {
    document.getElementById('edit_custom_cat_wrap').style.display = (val === 'Other') ? 'block' : 'none';
}

function viewPhoto(url, caption) {
    if (!url) return;
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightboxCaption').textContent = caption || '';
    document.getElementById('photoLightboxModal').classList.add('active');
}

function closePhotoLightbox() {
    document.getElementById('photoLightboxModal').classList.remove('active');
}

// ── SweetAlert2 Confirmations ────────────────────────────────────────────────
function confirmDeleteDonation(id, title) {
    Swal.fire({
        title: 'Delete Donation?',
        html: `Are you sure you want to permanently delete <strong>"${title}"</strong>?<br><small style="color:#d32f2f;">Associated request records will also be removed.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#595959',
        confirmButtonText: 'Yes, Delete Listing',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('del_form_' + id).submit();
        }
    });
}

function confirmApproveRequest(id, recipient, qty, remaining, total) {
    if (qty > remaining) {
        Swal.fire({
            title: 'Insufficient Stock!',
            html: `Requested quantity (<strong>${qty}</strong>) exceeds available stock (<strong>${remaining}</strong> remaining of ${total} total).<br><small>Ask the donor to increase stock before approving.</small>`,
            icon: 'error',
            confirmButtonColor: '#2e7d32'
        });
        return;
    }

    Swal.fire({
        title: 'Approve Request?',
        html: `Approve request #REQ-<strong>${id}</strong> for <strong>${recipient}</strong> (${qty} unit${qty > 1 ? 's' : ''})?<br><small style="color:#2e7d32;">Stock remaining after approval: <strong>${remaining - qty}</strong> units.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2e7d32',
        cancelButtonColor: '#595959',
        confirmButtonText: 'Yes, Approve Request',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('app_form_' + id).submit();
        }
    });
}

function confirmRejectRequest(id, recipient) {
    Swal.fire({
        title: 'Reject Request?',
        html: `Are you sure you want to reject aid request #REQ-<strong>${id}</strong> for <strong>${recipient}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#595959',
        confirmButtonText: 'Yes, Reject',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('rej_form_' + id).submit();
        }
    });
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewModal();
        closeEditModal();
        closePhotoLightbox();
    }
});
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
