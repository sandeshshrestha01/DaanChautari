<?php
/**
 * Daan Chautari — Donor Dashboard
 * Displays donation stats, recent donations, and incoming requests for the logged-in donor.
 */

// ── Bootstrap: load config + DB before any HTML output ───────────────────────
require_once "../database/config.php";
require_once "../database/db.php";

// ── Auth Guard (before header.php so redirects still work) ───────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'donor') {
    set_flash_message('error', 'Please log in as a Donor to access this page.');
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

$donor_id   = $_SESSION['user_id'];
$donor_name = $_SESSION['user_name'];
$town       = $_SESSION['town'] ?? '';


// ── Detect AJAX (fetch) request ───────────────────────────────────────────────
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// ── Helper: AJAX JSON response OR flash + redirect ────────────────────────────
function ajax_respond(bool $ok, string $msg, bool $is_ajax): void {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $msg]);
        exit;
    }
    set_flash_message($ok ? 'success' : 'error', $msg);
    header('Location: donor_dashboard.php');
    exit;
}

// ── Handle POST: Add New Donation ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_donation') {
    $title       = trim($_POST['title']    ?? '');
    $category    = $_POST['category']      ?? '';
    $quantity    = (int)($_POST['quantity'] ?? 1);
    $description = trim($_POST['description'] ?? '');
    $town        = trim($_POST['town']     ?? '');

    $allowed_cats = ['Food','Clothing','Education','Essential Needs'];

    if ($title && in_array($category, $allowed_cats) && $quantity > 0 && $town) {

        // ── Image Upload ───────────────────────────────────────────────────────
        $photo_path = null;
        if (!empty($_FILES['image']['name'])) {
            $file       = $_FILES['image'];
            $img_types  = ['image/jpeg','image/png','image/webp','image/gif'];
            $max_size   = 3 * 1024 * 1024; // 3 MB
            $upload_dir = __DIR__ . '/../assets/images/donations/';

            if (!in_array($file['type'], $img_types)) {
                ajax_respond(false, 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.', $is_ajax);
            }
            if ($file['size'] > $max_size) {
                ajax_respond(false, 'Image is too large. Maximum size is 3 MB.', $is_ajax);
            }

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'donation_' . uniqid() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                ajax_respond(false, 'Image upload failed. Please try again.', $is_ajax);
            }
            $photo_path = 'assets/images/donations/' . $filename;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO donations (donor_id, title, category, quantity, description, town, photo, status)
                VALUES (:donor_id, :title, :category, :quantity, :description, :town, :photo, 'available')
            ");
            $stmt->execute([
                'donor_id'    => $donor_id,
                'title'       => $title,
                'category'    => $category,
                'quantity'    => $quantity,
                'description' => $description,
                'town'        => $town,
                'photo'       => $photo_path,
            ]);
            ajax_respond(true, "Your donation \"$title\" has been listed successfully!", $is_ajax);
        } catch (PDOException $e) {
            ajax_respond(false, 'Failed to add donation. Please try again.', $is_ajax);
        }
    } else {
        ajax_respond(false, 'Please fill in all required fields correctly.', $is_ajax);
    }
}

// ── Handle POST: Delete Donation ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_donation') {
    $del_id = (int)($_POST['donation_id'] ?? 0);
    if ($del_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM donations WHERE donation_id = :id AND donor_id = :donor_id");
            $stmt->execute(['id' => $del_id, 'donor_id' => $donor_id]);
            set_flash_message('success', 'Donation removed successfully.');
        } catch (PDOException $e) {
            set_flash_message('error', 'Could not delete the donation.');
        }
    }
    header("Location: donor_dashboard.php");
    exit;
}

// ── Now that all POSTs are handled, output HTML ───────────────────────────────
$extra_css  = ['dashboard.css'];
$page_title = 'Donor Dashboard';
include_once "../includes/header.php";

// ── Fetch Stats ───────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = :id");
    $stmt->execute(['id' => $donor_id]);
    $total_donations = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = :id AND status = 'available'");
    $stmt->execute(['id' => $donor_id]);
    $available_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = :id AND status = 'approved'");
    $stmt->execute(['id' => $donor_id]);
    $approved_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM donation_requests dr
        JOIN donations d ON dr.donation_id = d.donation_id
        WHERE d.donor_id = :id AND dr.status = 'pending'
    ");
    $stmt->execute(['id' => $donor_id]);
    $pending_requests = $stmt->fetchColumn();

} catch (PDOException $e) {
    $total_donations  = $available_count = $approved_count = $pending_requests = 0;
}

// ── Fetch My Donations ────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT donation_id, title, category, quantity, town, status, donated_at, description, photo
        FROM donations
        WHERE donor_id = :id
        ORDER BY donated_at DESC
        LIMIT 20
    ");
    $stmt->execute(['id' => $donor_id]);
    $my_donations = $stmt->fetchAll();
} catch (PDOException $e) {
    $my_donations = [];
}

// ── Fetch Incoming Requests on My Donations ----
try {
    $stmt = $pdo->prepare("
        SELECT dr.request_id, d.title AS donation_title, d.category,
               u.full_name AS recipient_name, u.town AS recipient_town,
               dr.message, dr.status, dr.requested_at
        FROM donation_requests dr
        JOIN donations d  ON dr.donation_id  = d.donation_id
        JOIN users     u  ON dr.recipient_id = u.user_id
        WHERE d.donor_id = :id
        ORDER BY dr.requested_at DESC
        LIMIT 15
    ");
    $stmt->execute(['id' => $donor_id]);
    $incoming_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $incoming_requests = [];
}

// Helper: status badge CSS class
function badge_class(string $status): string {
    return match($status) {
        'available' => 'badge-info',
        'approved'  => 'badge-success',
        'requested' => 'badge-pending',
        'rejected'  => 'badge-danger',
        'pending'   => 'badge-pending',
        default     => 'badge-info',
    };
}
?>

<div class="dashboard-wrapper" style="padding-top: 100px;">

    <!-- ══ HEADER BANNER ══════════════════════════════════════════════════════ -->
    <div class="db-header-banner">
        <div class="db-header-text">
            <h2>Welcome back, <?php echo htmlspecialchars($donor_name);?>!</h2>
            <p>Manage your donations and see who is requesting your help from the community.</p>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <span class="db-header-badge">Donor Account</span>
            <a href="#add-donation-form" class="btn-primary-db" style="border-radius:30px;">+ Post a Donation</a>
        </div>
    </div>

    <!-- ══ STAT CARDS ═════════════════════════════════════════════════════════ -->
    <div class="db-metrics-grid">
        <div class="db-stat-card">
            <div class="db-stat-number"><?php echo $total_donations; ?></div>
            <div class="db-stat-label">Total Donations</div>
        </div>
        <div class="db-stat-card yellow-border">
            <div class="db-stat-number"><?php echo $available_count; ?></div>
            <div class="db-stat-label">Currently Available</div>
        </div>
        <div class="db-stat-card blue-border">
            <div class="db-stat-number"><?php echo $approved_count; ?></div>
            <div class="db-stat-label">Items Delivered</div>
        </div>
        <div class="db-stat-card purple-border">
            <div class="db-stat-number"><?php echo $pending_requests; ?></div>
            <div class="db-stat-label">Pending Requests</div>
        </div>
    </div>

    <!-- ══ MAIN 2-COLUMN GRID ════════════════════════════════════════════════ -->
    <div class="db-content-grid">

        <!-- LEFT: My Donations Table -->
        <div>
            <div class="db-panel">
                <div class="db-panel-title">
                    <span>My Donations</span>
                    <span style="font-size:13px; color:#999; font-weight:400;">Latest 20</span>
                </div>

                <?php if (empty($my_donations)): ?>
                    <div style="text-align:center; padding:40px 20px; color:#999;">
                        <div style="font-size:48px; margin-bottom:12px;">📭</div>
                        <p style="font-size:15px;">You haven't posted any donations yet.</p>
                        <a href="#add-donation-form" class="btn-primary-db" style="margin-top:15px; display:inline-block; border-radius:30px;">Post Your First Donation</a>
                    </div>
                <?php else: ?>
                    <div class="db-table-wrapper">
                        <table class="db-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Qty</th>
                                    <th>Town</th>
                                    <th>Status</th>
                                    <th>Posted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_donations as $i => $d): ?>
                                <tr>
                                    <td style="color:#aaa; font-size:12px;"><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($d['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($d['category']); ?></td>
                                    <td><?php echo $d['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($d['town']); ?></td>
                                    <td>
                                        <span class="badge <?php echo badge_class($d['status']); ?>">
                                            <?php echo ucfirst($d['status']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px; color:#999;">
                                        <?php echo date('d M Y', strtotime($d['donated_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($d['status'] === 'available'): ?>
                                        <div style="display:flex; gap:6px; align-items:center;">
                                            <a href="donation_edit.php?id=<?php echo $d['donation_id']; ?>"
                                               style="background:#e8f5e9; color:#2e7d32; text-decoration:none; padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; font-weight:600; display:inline-block;">
                                                ✏️ Edit
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Remove this donation listing?');" style="display:inline;">
                                                <input type="hidden" name="action"      value="delete_donation">
                                                <input type="hidden" name="donation_id" value="<?php echo $d['donation_id']; ?>">
                                                <button type="submit" style="background:#ffebee; color:#c62828; border:none; padding:5px 10px; border-radius:6px; font-size:12px; cursor:pointer; font-weight:600;">
                                                    🗑 Remove
                                                </button>
                                            </form>
                                        </div>
                                        <?php else: ?>
                                            <span style="font-size:12px; color:#bbb;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Incoming Requests on my donations -->
            <div class="db-panel">
                <div class="db-panel-title">
                    <span>🔔 Requests on My Donations</span>
                    <span style="font-size:13px; color:#999; font-weight:400;"><?php echo count($incoming_requests); ?> total</span>
                </div>

                <?php if (empty($incoming_requests)): ?>
                    <div style="text-align:center; padding:30px 20px; color:#aaa;">
                        <div style="margin-bottom:12px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"
                                 width="48" height="48" fill="#cccccc">
                                <path d="M466.8 186.5l42.5-42.5 34.7 0c17.7 0 32-14.3 32-32l0-64c0-17.7-14.3-32-32-32L223.6 16c-29 0-57.3 9.3-80.7 26.5L16.3 135.8c-17.8 13.1-21.6 38.1-8.5 55.9s38.1 21.6 55.9 8.5L183.4 112 296 112c13.3 0 24 10.7 24 24s-10.7 24-24 24l-72 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l152.2 0c33.9 0 66.5-13.5 90.5-37.5zm-357.5 139L66.7 368 32 368c-17.7 0-32 14.3-32 32l0 64c0 17.7 14.3 32 32 32l320.5 0c29 0 57.3-9.3 80.7-26.5l126.6-93.3c17.8-13.1 21.6-38.1 8.5-55.9s-38.1-21.6-55.9-8.5L392.6 400 280 400c-13.3 0-24-10.7-24-24s10.7-24 24-24l72 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-152.2 0c-33.9 0-66.5 13.5-90.5 37.5z"/>
                            </svg>
                        </div>
                        <p style="font-size:14px;">No one has requested your donations yet.</p>
                    </div>
                <?php else: ?>
                    <div class="db-table-wrapper">
                        <table class="db-table">
                            <thead>
                                <tr>
                                    <th>Item Requested</th>
                                    <th>Recipient</th>
                                    <th>Town</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incoming_requests as $r): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['donation_title']); ?></strong>
                                        <br><span style="font-size:11px; color:#aaa;"><?php echo htmlspecialchars($r['category']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['recipient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['recipient_town']); ?></td>
                                    <td style="max-width:180px; font-size:12px; color:#777;">
                                        <?php echo $r['message'] ? htmlspecialchars(mb_strimwidth($r['message'], 0, 80, '…')) : '<em style="color:#ccc;">No message</em>'; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo badge_class($r['status']); ?>">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px; color:#999;">
                                        <?php echo date('d M Y', strtotime($r['requested_at'])); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT SIDEBAR: Add donation form + quick tips -->
        <div>
            <!-- Add Donation Form -->
            <div class="db-panel" id="add-donation-form">
                <div class="db-panel-title">➕ Post a New Donation</div>

                <form id="quick-donate-form" method="POST" action="donor_dashboard.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_donation">

                    <div class="form-group">
                        <label for="dd_title">Item Name <span style="color:#c62828;">*</span></label>
                        <input type="text" id="dd_title" name="title" class="form-control"
                               placeholder="e.g. Winter Blankets" required maxlength="150">
                    </div>

                    <div class="form-group">
                        <label for="dd_category">Category <span style="color:#c62828;">*</span></label>
                        <select id="dd_category" name="category" class="form-control" required>
                            <option value="">— Select Category —</option>
                            <option value="Food">🍱 Food</option>
                            <option value="Clothing">👕 Clothing</option>
                            <option value="Education">📚 Education</option>
                            <option value="Essential Needs">🧴 Essential Needs</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dd_image">Item Image <span style="color:#aaa; font-size:11px;">(optional · JPG/PNG/WEBP · max 3MB)</span></label>
                        <input type="file" id="dd_image" name="image" class="form-control"
                               accept="image/jpeg,image/png,image/webp,image/gif"
                               style="padding:6px; cursor:pointer;"
                               onchange="previewDonationImage(this)">
                        <div id="dd_image_preview" style="display:none; margin-top:10px; text-align:center;">
                            <img id="dd_image_thumb" src="" alt="Preview"
                                 style="max-width:100%; max-height:160px; border-radius:8px; border:2px solid #c8e6c9; object-fit:cover;">
                            <button type="button" onclick="clearDonationImage()"
                                    style="display:block; margin:6px auto 0; background:#ffebee; color:#c62828; border:none; padding:4px 12px; border-radius:6px; font-size:12px; cursor:pointer; font-weight:600;">
                                ✕ Remove image
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="dd_quantity">Quantity <span style="color:#c62828;">*</span></label>
                        <input type="number" id="dd_quantity" name="quantity" class="form-control"
                               min="1" value="1" required>
                    </div>

                    <div class="form-group">
                        <label for="dd_town">Your Town / City <span style="color:#c62828;">*</span></label>
                        <input type="text" id="dd_town" name="town" class="form-control" value="<?php echo htmlspecialchars($town); ?>"
                               placeholder="e.g. Kathmandu" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="dd_desc">Description <span style="color:#aaa; font-size:11px;">(optional)</span></label>
                        <textarea id="dd_desc" name="description" class="form-control"
                                  rows="3" placeholder="Condition, size, quantity details…" style="resize:vertical;"></textarea>
                    </div>

                    <button type="submit" id="dd-submit-btn" class="btn-primary-db" style="width:100%; border-radius:8px; padding:12px; font-size:15px; position:relative;">
                        List Donation
                    </button>
                </form>
            </div>

            <!-- Quick Tips Card -->
            <div class="db-panel" style="background: linear-gradient(135deg,#e8f5e9,#f1f8e9); border-color:#c8e6c9;">
                <div class="db-panel-title" style="color:#2e7d32;">💡 Donor Tips</div>
                <ul style="list-style:none; padding:0; font-size:13px; color:#558b2f; line-height:2;">
                    <li>✅ Add a clear description to get faster requests</li>
                    <li>📍 List your correct town so nearby recipients find you</li>
                    <li>🔔 Requests are reviewed and approved by our admin team</li>
                    <li>🗑 You can remove listings that are still "Available"</li>
                    <li>🎁 Every donation makes a real difference — thank you!</li>
                </ul>
            </div>

        
        </div>
    </div>



</div>

</body>

<script>
function previewDonationImage(input) {
    const preview = document.getElementById('dd_image_preview');
    const thumb   = document.getElementById('dd_image_thumb');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            thumb.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearDonationImage() {
    document.getElementById('dd_image').value = '';
    document.getElementById('dd_image_thumb').src = '';
    document.getElementById('dd_image_preview').style.display = 'none';
}

// ── AJAX donation form submission ─────────────────────────────────────────────
document.getElementById('quick-donate-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const form   = this;
    const btn    = document.getElementById('dd-submit-btn');
    const origTxt = btn.innerHTML;

    // Show loading state
    btn.disabled  = true;
    btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:8px;">'
                  + '<svg width="18" height="18" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#fff">'
                  + '<g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2">'
                  + '<circle stroke-opacity=".4" cx="18" cy="18" r="18"/>'
                  + '<path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.8s" repeatCount="indefinite"/></path>'
                  + '</g></g></svg> Posting…</span>';

    const data = new FormData(form);

    fetch('donor_dashboard.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data
    })
    .then(res => res.json())
    .then(json => {
        if (json.ok) {
            // Show success toast, then reload to refresh table + stats
            Swal.fire({
                icon: 'success',
                title: 'Posted!',
                text: json.message,
                timer: 2200,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Oops…',
                text: json.message,
                confirmButtonColor: '#c62828'
            });
            btn.disabled  = false;
            btn.innerHTML = origTxt;
        }
    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'Network error',
            text: 'Could not reach the server. Please try again.',
            confirmButtonColor: '#c62828'
        });
        btn.disabled  = false;
        btn.innerHTML = origTxt;
    });
});
</script>

</html>
