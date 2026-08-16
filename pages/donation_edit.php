<?php
/**
 * Daan Chautari — Edit Donation Page
 * Handles editing of an existing donation listing.
 */

// ── Bootstrap: config + DB before any HTML output ─────────────────────────────
require_once "../database/config.php";
require_once "../database/db.php";

// ── Auth Guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    set_flash_message('error', 'Please log in to edit a donation.');
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

if ($_SESSION['user_role'] !== 'donor') {
    set_flash_message('error', 'Only Donor accounts can access this section.');
    header("Location: " . BASE_URL . "pages/homepage.php");
    exit;
}

$donor_id    = $_SESSION['user_id'];
$donation_id = (int)($_GET['id'] ?? $_POST['donation_id'] ?? 0);

if (!$donation_id) {
    set_flash_message('error', 'Invalid donation listing ID.');
    header("Location: donor_dashboard.php");
    exit;
}

// ── Detect AJAX ───────────────────────────────────────────────────────────────
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// ── Helper: JSON for AJAX, flash+redirect for normal ─────────────────────────
function edit_respond(bool $ok, string $msg, bool $is_ajax, string $redirect = 'donor_dashboard.php'): void {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $msg]);
        exit;
    }
    set_flash_message($ok ? 'success' : 'error', $msg);
    header("Location: $redirect");
    exit;
}

// ── Fetch Donation (needed for validation AND to pre-populate the form) ───────
try {
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE donation_id = :id AND donor_id = :donor_id");
    $stmt->execute(['id' => $donation_id, 'donor_id' => $donor_id]);
    $donation = $stmt->fetch();

    if (!$donation) {
        edit_respond(false, 'Donation listing not found or access denied.', $is_ajax);
    }
    if ($donation['status'] !== 'available') {
        edit_respond(false, 'Only available donations can be edited.', $is_ajax);
    }
} catch (PDOException $e) {
    edit_respond(false, 'Database error. Please try again.', $is_ajax);
}

// ── Handle POST: Edit Donation ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_donation') {
    $title       = trim($_POST['title']    ?? '');
    $category    = $_POST['category']      ?? '';
    $quantity    = (int)($_POST['quantity'] ?? 1);
    $description = trim($_POST['description'] ?? '');
    $town        = trim($_POST['town']     ?? '');

    $allowed_cats = ['Food','Clothing','Education','Essential Needs'];

    if ($title && in_array($category, $allowed_cats) && $quantity > 0 && $town) {
        $photo_path = $donation['photo'];

        // ── Image Upload ───────────────────────────────────────────────────────
        if (!empty($_FILES['image']['name'])) {
            $file       = $_FILES['image'];
            $img_types  = ['image/jpeg','image/png','image/webp','image/gif'];
            $max_size   = 3 * 1024 * 1024; // 3 MB
            $upload_dir = __DIR__ . '/../assets/images/donations/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (!in_array($file['type'], $img_types)) {
                edit_respond(false, 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.', $is_ajax, 'donation_edit.php?id=' . $donation_id);
            }
            if ($file['size'] > $max_size) {
                edit_respond(false, 'Image is too large. Maximum size is 3 MB.', $is_ajax, 'donation_edit.php?id=' . $donation_id);
            }

            // Delete old photo
            if ($photo_path && file_exists(__DIR__ . '/../' . $photo_path)) {
                @unlink(__DIR__ . '/../' . $photo_path);
            }

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'donation_' . uniqid() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                edit_respond(false, 'Image upload failed. Please try again.', $is_ajax, 'donation_edit.php?id=' . $donation_id);
            }
            $photo_path = 'assets/images/donations/' . $filename;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE donations
                SET title = :title,
                    category = :category,
                    quantity = :quantity,
                    description = :description,
                    town = :town,
                    photo = :photo
                WHERE donation_id = :id AND donor_id = :donor_id AND status = 'available'
            ");
            $stmt->execute([
                'title'       => $title,
                'category'    => $category,
                'quantity'    => $quantity,
                'description' => $description,
                'town'        => $town,
                'photo'       => $photo_path,
                'id'          => $donation_id,
                'donor_id'    => $donor_id,
            ]);

            // Activity log (non-fatal)
            try {
                $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, module, reference_id)
                    VALUES (:user_id, 'Edited donation details', 'donations', :ref_id)
                ")->execute(['user_id' => $donor_id, 'ref_id' => $donation_id]);
            } catch (PDOException $le) {}

            edit_respond(true, 'Donation listing updated successfully.', $is_ajax);
        } catch (PDOException $e) {
            edit_respond(false, 'Failed to update donation details.', $is_ajax, 'donation_edit.php?id=' . $donation_id);
        }
    } else {
        edit_respond(false, 'Please fill in all required fields correctly.', $is_ajax, 'donation_edit.php?id=' . $donation_id);
    }
}

// ── Now safe to output HTML ───────────────────────────────────────────────────
$extra_css  = ['dashboard.css'];
$page_title = 'Edit Donation';
include_once "../includes/header.php";
?>

<div class="dashboard-wrapper" style="padding-top: 100px;">
    
    <div style="max-width: 750px; margin: 0 auto; animation: slideUp 0.4s ease-out;">
        
        <div class="db-header-banner" style="margin-bottom: 25px;">
            <div class="db-header-text">
                <h2>✏️ Edit Donation Listing</h2>
                <p>Modify the details of your donation <strong>"<?php echo htmlspecialchars($donation['title']); ?>"</strong>. Changes go live immediately for recipients.</p>
            </div>
            <a href="donor_dashboard.php" class="btn-primary-db" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); border-radius: 30px;">
                ← Cancel &amp; Return
            </a>
        </div>

        <!-- Form Card -->
        <div class="db-panel" style="box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-radius: 16px;">
            <form id="edit-donation-form" method="POST" action="donation_edit.php" enctype="multipart/form-data" style="margin: 0; display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="action" value="edit_donation">
                <input type="hidden" name="donation_id" value="<?php echo $donation_id; ?>">

                <!-- Title -->
                <div class="form-group" style="margin: 0;">
                    <label for="edit_title" style="font-size: 14px; font-weight: 600; color: #333;">Item Name / Title <span style="color:#c62828;">*</span></label>
                    <input type="text" id="edit_title" name="title" class="form-control"
                           value="<?php echo htmlspecialchars($donation['title']); ?>"
                           required maxlength="150" style="padding: 12px;">
                </div>

                <!-- Category & Quantity -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="edit_category" style="font-size: 14px; font-weight: 600; color: #333;">Category <span style="color:#c62828;">*</span></label>
                        <select id="edit_category" name="category" class="form-control" required style="padding: 12px; height: auto;">
                            <option value="Food"          <?php echo $donation['category'] === 'Food'           ? 'selected' : ''; ?>>🍱 Food</option>
                            <option value="Clothing"      <?php echo $donation['category'] === 'Clothing'       ? 'selected' : ''; ?>>👕 Clothing</option>
                            <option value="Education"     <?php echo $donation['category'] === 'Education'      ? 'selected' : ''; ?>>📚 Education</option>
                            <option value="Essential Needs" <?php echo $donation['category'] === 'Essential Needs' ? 'selected' : ''; ?>>🧴 Essential Needs</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="edit_quantity" style="font-size: 14px; font-weight: 600; color: #333;">Quantity <span style="color:#c62828;">*</span></label>
                        <input type="number" id="edit_quantity" name="quantity" class="form-control"
                               min="1" value="<?php echo $donation['quantity']; ?>" required style="padding: 12px;">
                    </div>
                </div>

                <!-- Location -->
                <div class="form-group" style="margin: 0;">
                    <label for="edit_town" style="font-size: 14px; font-weight: 600; color: #333;">Location (Town / City) <span style="color:#c62828;">*</span></label>
                    <input type="text" id="edit_town" name="town" class="form-control"
                           value="<?php echo htmlspecialchars($donation['town']); ?>"
                           required maxlength="100" style="padding: 12px;">
                </div>

                <!-- Image Upload -->
                <div class="form-group" style="margin: 0;">
                    <label for="edit_image" style="font-size: 14px; font-weight: 600; color: #333;">Item Image <span style="color:#aaa; font-size:11px;">(optional · JPG/PNG/WEBP · max 3MB)</span></label>
                    <input type="file" id="edit_image" name="image" class="form-control"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           style="padding:10px; cursor:pointer;" onchange="previewEditImage(this)">

                    <div id="edit_image_preview_container" style="margin-top:15px; text-align:center;">
                        <?php if ($donation['photo']): ?>
                            <img id="edit_image_thumb"
                                 src="<?php echo BASE_URL . $donation['photo']; ?>"
                                 data-original-photo="<?php echo htmlspecialchars($donation['photo']); ?>"
                                 alt="Preview"
                                 style="max-width:100%; max-height:180px; border-radius:12px; border:2px solid #c8e6c9; padding:2px; object-fit:cover;">
                            <button type="button" id="edit_image_remove_btn" onclick="clearEditImage()"
                                    style="display:inline-block; margin:8px auto 0; background:#ffebee; color:#c62828; border:none; padding:6px 16px; border-radius:8px; font-size:12px; cursor:pointer; font-weight:600;">
                                ✕ Cancel New Selection
                            </button>
                        <?php else: ?>
                            <img id="edit_image_thumb" src="" alt="Preview"
                                 style="max-width:100%; max-height:180px; border-radius:12px; border:2px dashed #ccc; padding:2px; object-fit:cover; display:none;">
                            <button type="button" id="edit_image_remove_btn" onclick="clearEditImage()"
                                    style="display:none; margin:8px auto 0; background:#ffebee; color:#c62828; border:none; padding:6px 16px; border-radius:8px; font-size:12px; cursor:pointer; font-weight:600;">
                                ✕ Remove selected image
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group" style="margin: 0;">
                    <label for="edit_description" style="font-size: 14px; font-weight: 600; color: #333;">Description &amp; Condition Details <span style="color:#aaa; font-size:11px;">(optional)</span></label>
                    <textarea id="edit_description" name="description" class="form-control"
                              rows="4" style="resize:vertical; padding: 12px;"><?php echo htmlspecialchars($donation['description']); ?></textarea>
                </div>

                <!-- Submit -->
                <button type="submit" id="edit-submit-btn" class="btn-primary-db"
                        style="width:100%; border-radius:10px; padding:15px; font-size:16px; background:#2e7d32; font-weight:600; margin-top:10px; cursor:pointer; border:none; box-shadow:0 4px 12px rgba(46,125,50,0.2); position:relative;">
                    💾 Save Changes &amp; Update Listing
                </button>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// ── Image preview helpers ─────────────────────────────────────────────────────
function previewEditImage(input) {
    const thumb     = document.getElementById('edit_image_thumb');
    const removeBtn = document.getElementById('edit_image_remove_btn');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            thumb.src = e.target.result;
            thumb.style.display = 'inline-block';
            removeBtn.style.display = 'inline-block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearEditImage() {
    document.getElementById('edit_image').value = '';
    const thumb         = document.getElementById('edit_image_thumb');
    const originalPhoto = thumb.dataset.originalPhoto;
    if (originalPhoto) {
        thumb.src = '<?php echo BASE_URL; ?>' + originalPhoto;
        thumb.style.display = 'inline-block';
    } else {
        thumb.src = '';
        thumb.style.display = 'none';
    }
    document.getElementById('edit_image_remove_btn').style.display = 'none';
}

// ── AJAX form submission ──────────────────────────────────────────────────────
document.getElementById('edit-donation-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const form    = this;
    const btn     = document.getElementById('edit-submit-btn');
    const origTxt = btn.innerHTML;

    // Loading state
    btn.disabled  = true;
    btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:8px;">'
                  + '<svg width="18" height="18" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#fff">'
                  + '<g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2">'
                  + '<circle stroke-opacity=".4" cx="18" cy="18" r="18"/>'
                  + '<path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.8s" repeatCount="indefinite"/></path>'
                  + '</g></g></svg> Saving…</span>';

    fetch('donation_edit.php', {
        method:  'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body:    new FormData(form)
    })
    .then(res => res.json())
    .then(json => {
        if (json.ok) {
            Swal.fire({
                icon:            'success',
                title:           'Updated!',
                text:            json.message,
                timer:           2000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast:           true,
                position:        'top-end'
            }).then(() => {
                window.location.href = 'donor_dashboard.php';
            });
        } else {
            Swal.fire({
                icon:               'error',
                title:              'Oops…',
                text:               json.message,
                confirmButtonColor: '#c62828'
            });
            btn.disabled  = false;
            btn.innerHTML = origTxt;
        }
    })
    .catch(() => {
        Swal.fire({
            icon:               'error',
            title:              'Network error',
            text:               'Could not reach the server. Please try again.',
            confirmButtonColor: '#c62828'
        });
        btn.disabled  = false;
        btn.innerHTML = origTxt;
    });
});
</script>

<?php include_once "../includes/footer.php"; ?>
