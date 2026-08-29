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

$user_id        = $_SESSION['user_id'];
$recipient_name = $_SESSION['user_name'];
$user_town      = $_SESSION['town'] ?? '';

// Fetch recipient_id from `recipients` table corresponding to logged-in user_id
try {
    $stmt_rec = $pdo->prepare("SELECT recipient_id, reason, town, address FROM recipients WHERE user_id = :u_id");
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
        $recipient_info = [
            'recipient_id' => $recipient_id,
            'reason'       => null,
            'town'         => !empty($user_town) ? $user_town : 'Kathmandu',
            'address'      => $_SESSION['address'] ?? ''
        ];
    }
} catch (PDOException $e) {
    // Fallback in case of DB read error
    $recipient_id   = $user_id;
    $recipient_info = null;
}

// Handle POST: Submit Item Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_item') {
    $donation_id = (int)($_POST['donation_id'] ?? 0);
    $quantity    = (int)($_POST['quantity'] ?? 1);
    $reason      = trim($_POST['reason'] ?? '');
    $town        = trim($_POST['town'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $message     = trim($_POST['message'] ?? '');

    if ($quantity < 1) {
        $quantity = 1;
    }

    if ($donation_id > 0) {
        try {
            // Check available donation quantity
            $stmt_d = $pdo->prepare("SELECT quantity FROM donations WHERE donation_id = :d_id");
            $stmt_d->execute(['d_id' => $donation_id]);
            $don_item = $stmt_d->fetch();
            $available_qty = $don_item ? (int)$don_item['quantity'] : 1;

            if ($quantity > $available_qty) {
                set_flash_message('error', "Requested quantity ($quantity) cannot exceed available quantity ($available_qty).");
            } else {
                // Check if already requested
                $check = $pdo->prepare("SELECT request_id FROM donation_requests WHERE donation_id = :d_id AND recipient_id = :r_id");
                $check->execute(['d_id' => $donation_id, 'r_id' => $recipient_id]);
                if ($check->fetch()) {
                    set_flash_message('warning', 'You have already requested this item.');
                } else {
                    // Update recipient details (reason, town, address) in `recipients` table
                    if ($recipient_info && isset($recipient_info['recipient_id'])) {
                        $upd_rec = $pdo->prepare("
                            UPDATE recipients 
                            SET reason = :reason, town = :town, address = :address, updated_at = NOW() 
                            WHERE recipient_id = :r_id
                        ");
                        $upd_rec->execute([
                            'reason'  => $reason,
                            'town'    => !empty($town) ? $town : ($recipient_info['town'] ?? 'Kathmandu'),
                            'address' => $address,
                            'r_id'    => $recipient_id
                        ]);
                    }

                    // If user provided a reason, prepend it into the request message if message is empty
                    $request_message = !empty($message) ? $message : ($reason ? "Reason: " . $reason : '');

                    $stmt = $pdo->prepare("
                        INSERT INTO donation_requests (donation_id, recipient_id, message, quantity, status, requested_at)
                        VALUES (:d_id, :r_id, :msg, :qty, 'pending', NOW())
                    ");
                    $stmt->execute([
                        'd_id' => $donation_id,
                        'r_id' => $recipient_id,
                        'msg'  => $request_message,
                        'qty'  => $quantity
                    ]);
                    set_flash_message('success', 'Your request has been submitted successfully!');
                }
            }
        } catch (PDOException $e) {
            set_flash_message('error', 'Could not submit request. Please try again.');
        }
    }
    header("Location: recipient_dashboard.php");
    exit;
}

// Handle POST: Cancel Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_request') {
    $request_id = (int)($_POST['request_id'] ?? 0);

    if ($request_id > 0) {
        try {
            // Ensure request belongs to current recipient and is still pending
            $stmt = $pdo->prepare("DELETE FROM donation_requests WHERE request_id = :req_id AND recipient_id = :rec_id AND status = 'pending'");
            $stmt->execute([
                'req_id' => $request_id,
                'rec_id' => $recipient_id
            ]);

            if ($stmt->rowCount() > 0) {
                set_flash_message('success', 'Your request has been cancelled successfully.');
            } else {
                set_flash_message('warning', 'Only pending requests can be cancelled.');
            }
        } catch (PDOException $e) {
            set_flash_message('error', 'Could not cancel request. Please try again.');
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

// Fetch Stats according to donation_requests table schema
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE recipient_id = :id AND status = 'approved'");
    $stmt->execute(['id' => $recipient_id]);
    $approved_requests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE recipient_id = :id AND status = 'pending'");
    $stmt->execute(['id' => $recipient_id]);
    $pending_requests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE recipient_id = :id AND status = 'rejected'");
    $stmt->execute(['id' => $recipient_id]);
    $rejected_requests = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donation_requests WHERE recipient_id = :id");
    $stmt->execute(['id' => $recipient_id]);
    $total_requests = $stmt->fetchColumn();
} catch (PDOException $e) {
    $approved_requests = $pending_requests = $rejected_requests = $total_requests = 0;
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

// Fetch My Requests joined with donations, users (donor), and admin (reviewer)
try {
    $stmt = $pdo->prepare("
        SELECT dr.*, 
               dr.quantity AS requested_quantity,
               d.title AS donation_title, 
               d.category, 
               d.img_url, 
               d.quantity AS total_donation_quantity,
               d.town AS donor_town, 
               u_donor.full_name AS donor_name, 
               u_donor.phone AS donor_phone,
               u_admin.full_name AS reviewer_name
        FROM donation_requests dr
        JOIN donations d ON dr.donation_id = d.donation_id
        JOIN users u_donor ON d.donor_id = u_donor.user_id
        LEFT JOIN users u_admin ON dr.reviewed_by = u_admin.user_id
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
            <div class="db-stat-label recipient-stat-lbl">APPROVED REQUESTS</div>
        </div>
        <div class="db-stat-card blue-border recipient-stat-card">
            <div class="db-stat-number recipient-stat-val"><?php echo $total_requests; ?></div>
            <div class="db-stat-label recipient-stat-lbl">TOTAL REQUESTS MADE</div>
        </div>
        <div class="db-stat-card yellow-border recipient-stat-card">
            <div class="db-stat-number recipient-stat-val"><?php echo $pending_requests; ?></div>
            <div class="db-stat-label recipient-stat-lbl">PENDING REVIEW</div>
        </div>
        <div class="db-stat-card purple-border recipient-stat-card">
            <div class="db-stat-number recipient-stat-val"><?php echo $rejected_requests; ?></div>
            <div class="db-stat-label recipient-stat-lbl">REJECTED REQUESTS</div>
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
                                <?php if (!empty($item['img_url'])): ?>
                                    <img src="<?php echo BASE_URL . htmlspecialchars($item['img_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
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

                                <button type="button" class="btn-primary-db recipient-item-btn" onclick="openRequestModal(<?php echo $item['donation_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['title'])); ?>', '<?php echo htmlspecialchars(addslashes($item['category'])); ?>', <?php echo (int)($item['quantity'] ?? 1); ?>)">
                                    Request
                                </button>
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

            <!-- Recipient Profile Info snippet if stored in database -->
            <?php if (!empty($recipient_info)): ?>
            <div class="recipient-tips-panel" style="margin-bottom: 15px;">
                <div class="recipient-tips-title">
                    📋 Recipient Info
                </div>
                <p class="recipient-tips-desc">
                    <strong>Town:</strong> <?php echo htmlspecialchars($recipient_info['town'] ?? $user_town); ?><br>
                    <?php if (!empty($recipient_info['address'])): ?>
                        <strong>Address:</strong> <?php echo htmlspecialchars($recipient_info['address']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($recipient_info['reason'])): ?>
                        <strong>Reason:</strong> <?php echo htmlspecialchars($recipient_info['reason']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>

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
                            <th class="recipient-th-cell">REQ ID</th>
                            <th class="recipient-th-cell">ITEM TITLE</th>
                            <th class="recipient-th-cell">DONOR & TOWN</th>
                            <th class="recipient-th-cell">QTY</th>
                            <th class="recipient-th-cell">MESSAGE</th>
                            <th class="recipient-th-cell">STATUS</th>
                            <th class="recipient-th-cell">REQUESTED AT</th>
                            <th class="recipient-th-cell">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_requests as $req): ?>
                            <tr class="recipient-tr-row">
                                <td class="recipient-td-num">#<?php echo $req['request_id']; ?></td>
                                <td class="recipient-td-title">
                                    <strong><?php echo htmlspecialchars($req['donation_title']); ?></strong><br>
                                    <small style="color: #666;"><?php echo cat_emoji($req['category']) . ' ' . htmlspecialchars($req['category']); ?></small>
                                </td>
                                <td class="recipient-td-val">
                                    <?php echo htmlspecialchars($req['donor_name']); ?><br>
                                    <small style="color: #666;">📍 <?php echo htmlspecialchars($req['donor_town']); ?></small>
                                </td>
                                <td class="recipient-td-val"><?php echo (int)($req['requested_quantity'] ?? $req['quantity'] ?? 1); ?></td>
                                <td class="recipient-td-val">
                                    <small><?php echo htmlspecialchars($req['message'] ?? 'N/A'); ?></small>
                                </td>
                                <td class="recipient-td-badge">
                                    <span class="badge <?php echo badge_class($req['status']); ?> recipient-badge-pill">
                                        <?php echo strtoupper($req['status']); ?>
                                    </span>
                                </td>
                                <td class="recipient-td-date">
                                    <?php echo date('d M Y, h:i A', strtotime($req['requested_at'])); ?>
                                </td>
                                <td class="recipient-td-val">
                                    <?php if (strtolower($req['status']) === 'pending'): ?>
                                        <form method="POST" action="recipient_dashboard.php" onsubmit="return confirm('Are you sure you want to cancel this request?');" style="display:inline;">
                                            <input type="hidden" name="action" value="cancel_request">
                                            <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                            <button type="submit" class="btn-secondary-db" style="padding: 4px 10px; font-size: 12px; background-color: #e63946; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #888; font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Popup Screen for Requesting Donation -->
<div id="requestModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center;">
    <div style="background-color: #ffffff; width: 90%; max-width: 520px; border-radius: 16px; padding: 25px 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); position: relative; animation: slideUp 0.3s ease-out;">
        <button type="button" onclick="closeRequestModal()" style="position: absolute; right: 20px; top: 20px; background: none; border: none; font-size: 24px; cursor: pointer; color: #888;">&times;</button>
        <h3 style="margin-top: 0; color: #1b5e20; font-size: 20px; border-bottom: 2px solid #e8f5e9; padding-bottom: 12px;">🎁 Request Donation Item</h3>
        <p id="modalItemSubtitle" style="font-size: 13px; color: #555; margin-bottom: 20px;"></p>
        
        <form method="POST" action="recipient_dashboard.php">
            <input type="hidden" name="action" value="request_item">
            <input type="hidden" id="modal_donation_id" name="donation_id" value="">

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px;">Quantity Needed <span style="color: #e63946;">*</span> <small id="modalMaxQtyText" style="color:#666; font-weight:normal;"></small></label>
                <input type="number" id="modal_quantity" name="quantity" class="form-control" min="1" value="1" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px;">Your Town / City <span style="color: #e63946;">*</span></label>
                <input type="text" name="town" class="form-control" placeholder="e.g. Kathmandu, Lalitpur" value="<?php echo htmlspecialchars($recipient_info['town'] ?? $user_town); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px;">Your Full Address <span style="color: #e63946;">*</span></label>
                <input type="text" name="address" class="form-control" placeholder="e.g. New Road, House #45" value="<?php echo htmlspecialchars($recipient_info['address'] ?? ''); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px;">Reason For Request <span style="color: #e63946;">*</span></label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Explain why you need this item..." required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; resize: vertical;"><?php echo htmlspecialchars($recipient_info['reason'] ?? ''); ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px;">Additional Note / Message to Donor (Optional)</label>
                <input type="text" name="message" class="form-control" placeholder="Any special note or preferred delivery time..." style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeRequestModal()" style="padding: 10px 18px; background: #e0e0e0; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: #444;">Cancel</button>
                <button type="submit" class="btn-primary-db" style="padding: 10px 24px; background: #2e7d32; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRequestModal(donationId, itemTitle, category, maxQty) {
    document.getElementById('modal_donation_id').value = donationId;
    document.getElementById('modalItemSubtitle').innerText = 'Item: ' + itemTitle + ' (' + category + ')';
    
    var qtyInput = document.getElementById('modal_quantity');
    var maxQtyText = document.getElementById('modalMaxQtyText');
    if (maxQty) {
        qtyInput.max = maxQty;
        qtyInput.value = 1;
        maxQtyText.innerText = '(Max available: ' + maxQty + ')';
    } else {
        qtyInput.removeAttribute('max');
        qtyInput.value = 1;
        maxQtyText.innerText = '';
    }

    var modal = document.getElementById('requestModal');
    modal.style.display = 'flex';
}

function closeRequestModal() {
    var modal = document.getElementById('requestModal');
    modal.style.display = 'none';
}

window.onclick = function(event) {
    var modal = document.getElementById('requestModal');
    if (event.target === modal) {
        closeRequestModal();
    }
};
</script>

</body>
</html>
