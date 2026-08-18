<?php
/**
 * Daan Chautari — Post a Donation & Receipt Page
 * Handles both the donation submission form and the official receipt invoicing.
 */

$extra_css  = ['dashboard.css'];
$page_title = 'Donate Support';
include_once "../includes/header.php";

// ── Auth Guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    set_flash_message('error', 'Please log in to list a donation.');
    header("Location: " . BASE_URL . "auth/login.php");
    exit;
}

if ($_SESSION['user_role'] !== 'donor') {
    set_flash_message('error', 'Only Donor accounts can access this section.');
    header("Location: " . BASE_URL . "pages/homepage.php");
    exit;
}

$donor_id = $_SESSION['user_id'];

// ── Pre-fill Category from Cause ID (if coming from Home Page needs) ──────────
$selected_category = '';
if (isset($_GET['cause_id'])) {
    $cause_id = (int)$_GET['cause_id'];
    if ($cause_id === 1) {
        $selected_category = 'Education';
    } elseif ($cause_id === 2) {
        $selected_category = 'Essential Needs';
    } elseif ($cause_id === 3) {
        $selected_category = 'Food';
    }
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
            $allowed    = ['image/jpeg','image/png','image/webp','image/gif'];
            $max_size   = 3 * 1024 * 1024; // 3 MB
            $upload_dir = __DIR__ . '/../assets/images/donations/';

            // Ensure directory exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (!in_array($file['type'], $allowed)) {
                set_flash_message('error', 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.');
                header("Location: donate.php");
                exit;
            }
            if ($file['size'] > $max_size) {
                set_flash_message('error', 'Image is too large. Maximum size is 3 MB.');
                header("Location: donate.php");
                exit;
            }

            $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename   = 'donation_' . uniqid() . '.' . $ext;
            $dest       = $upload_dir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                set_flash_message('error', 'Image upload failed. Please try again.');
                header("Location: donate.php");
                exit;
            }
            $photo_path =BASE_URL.'assets/images/donations/' . $filename;
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
            
            $new_id = $pdo->lastInsertId();
            
            // Add activity log
            try {
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, module, reference_id)
                    VALUES (:user_id, 'Listed donation', 'donations', :ref_id)
                ");
                $log_stmt->execute([
                    'user_id' => $donor_id,
                    'ref_id'  => $new_id
                ]);
            } catch (PDOException $le) {
                // Ignore log insertion failure
            }

            set_flash_message('success', "Your donation has been listed successfully!");
            header("Location: donate.php?success=1&id=" . $new_id);
            exit;
        } catch (PDOException $e) {
            set_flash_message('error', 'Failed to add donation. Please try again.');
        }
    } else {
        set_flash_message('error', 'Please fill in all required fields correctly.');
    }
    header("Location: donate.php");
    exit;
}

// ── Check if showing Receipt/Reference page ──────────────────────────────────
$show_receipt = false;
$donation = null;
if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['id'])) {
    $donation_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, u.full_name AS donor_name, u.phone AS donor_phone, u.email AS donor_email
            FROM donations d
            JOIN users u ON d.donor_id = u.user_id
            WHERE d.donation_id = :id AND d.donor_id = :donor_id
        ");
        $stmt->execute(['id' => $donation_id, 'donor_id' => $donor_id]);
        $donation = $stmt->fetch();
        if ($donation) {
            $show_receipt = true;
        }
    } catch (PDOException $e) {
        $donation = null;
    }
}
?>

<div class="dashboard-wrapper" style="padding-top: 100px;">
    
    <?php if ($show_receipt && $donation): ?>
        <!-- ═══ DONATION RECEIPT & REFERENCE VIEW ═══ -->
        <div style="max-width: 700px; margin: 0 auto; animation: slideUp 0.4s ease-out;">
            
            <!-- Receipt Card wrapper -->
            <div id="receipt-print-area" class="db-panel" style="border: 2px dashed #c8e6c9; border-radius: 16px; padding: 40px; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.05); position: relative;">
                
                <!-- Corner Stamp decoration -->
                <div style="position: absolute; top: 30px; right: 30px; border: 3px double #2e7d32; color: #2e7d32; padding: 6px 12px; font-weight: 700; font-size: 13px; text-transform: uppercase; transform: rotate(10deg); border-radius: 6px; letter-spacing: 1px; user-select: none;">
                    ✓ VERIFIED
                </div>

                <!-- Header Logo & Title -->
                <div style="text-align: center; border-bottom: 2px solid #e8f5e9; padding-bottom: 25px; margin-bottom: 35px;">
                    <div style="font-size: 32px; margin-bottom: 5px;">🤝</div>
                    <h2 style="color: #1b5e20; font-size: 24px; font-weight: 700; margin-bottom: 5px;">Daan Chautari</h2>
                    <p style="color: #666; font-size: 13px; font-style: italic; margin: 0;">Sahayogko Chautari, Aashako Yatra</p>
                    <h3 style="margin-top: 20px; font-size: 15px; text-transform: uppercase; letter-spacing: 1.5px; color: #555; font-weight: 600;">Official Donation Receipt & Reference</h3>
                </div>

                <!-- Reference Grid Info -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 35px; background: #f9fbf9; padding: 20px; border-radius: 10px; border: 1px solid #e8f5e9;">
                    <div>
                        <span style="display: block; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 600;">Reference Number</span>
                        <strong style="font-size: 16px; color: #1b5e20; font-family: monospace;">DC-DON-<?php echo str_pad($donation['donation_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 600;">Date & Time</span>
                        <strong style="font-size: 14px; color: #333; font-weight: 600;"><?php echo date('d M Y, h:i A', strtotime($donation['donated_at'])); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 600;">Donor Name</span>
                        <strong style="font-size: 14px; color: #333; font-weight: 600;"><?php echo htmlspecialchars($donation['donor_name']); ?></strong>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11px; text-transform: uppercase; color: #888; font-weight: 600;">Status</span>
                        <span class="badge badge-info" style="font-size: 10px; padding: 3px 8px; vertical-align: middle;"><?php echo ucfirst($donation['status']); ?></span>
                    </div>
                </div>

                <!-- Goods Listing Details -->
                <div style="margin-bottom: 35px;">
                    <h4 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-bottom: 15px; color: #555;">Donated Item Specification</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f1f8e9;">
                                <th style="padding: 10px; text-align: left; font-size: 12px; color: #2e7d32; font-weight: 600; border-bottom: 1px solid #c8e6c9;">Description</th>
                                <th style="padding: 10px; text-align: left; font-size: 12px; color: #2e7d32; font-weight: 600; border-bottom: 1px solid #c8e6c9;">Category</th>
                                <th style="padding: 10px; text-align: right; font-size: 12px; color: #2e7d32; font-weight: 600; border-bottom: 1px solid #c8e6c9;">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px 10px; font-size: 14px; font-weight: 700; color: #333; border-bottom: 1px solid #eee;">
                                    <?php echo htmlspecialchars($donation['title']); ?>
                                    <?php if (!empty($donation['description'])): ?>
                                        <br><span style="font-size: 12px; color: #777; font-weight: 400;"><?php echo htmlspecialchars($donation['description']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 10px; font-size: 14px; color: #555; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($donation['category']); ?></td>
                                <td style="padding: 12px 10px; font-size: 14px; text-align: right; color: #333; font-weight: bold; border-bottom: 1px solid #eee;"><?php echo $donation['quantity']; ?> Unit(s)</td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding: 12px 10px; font-size: 13px; color: #666; font-weight: 600; text-align: right;">Location:</td>
                                <td style="padding: 12px 10px; font-size: 13px; text-align: right; color: #333; font-weight: 600;"><?php echo htmlspecialchars($donation['town']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Uploaded Photo if exists -->
                <?php if ($donation['photo']): ?>
                    <div style="margin-bottom: 35px; text-align: center;">
                        <h4 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-bottom: 15px; color: #555; text-align: left;">Uploaded Item Reference Photo</h4>
                        <img src="<?php echo BASE_URL . $donation['photo']; ?>" alt="Donation item" style="max-width: 100%; max-height: 250px; border-radius: 12px; border: 2px solid #e8f5e9; box-shadow: 0 4px 15px rgba(0,0,0,0.05); object-fit: cover;">
                    </div>
                <?php endif; ?>

                <!-- Footer Terms -->
                <div style="border-top: 1px solid #eee; padding-top: 20px; text-align: center; color: #888; font-size: 12px; line-height: 1.6;">
                    <p>This is a simulated reference receipt. Thank you for contributing to your community!</p>
                    <p style="margin-top: 5px; font-weight: 600; color: #2e7d32;">"Every gift, no matter how small, makes a real impact."</p>
                </div>
            </div>

            <!-- Print Actions -->
            <div style="display: flex; gap: 12px; justify-content: center; margin-top: 30px;" class="no-print">
                <button onclick="window.print()" class="btn-primary-db" style="background: #1b5e20; display: inline-flex; align-items: center; gap: 8px; border-radius: 8px;">
                    🖨 Print Invoice / Receipt
                </button>
                <a href="donor_dashboard.php" class="btn-secondary-db" style="border-radius: 8px;">
                    📊 Go to Dashboard
                </a>
                <a href="donate.php" class="btn-primary-db" style="background: #f9a825; border-radius: 8px;">
                    🎁 List Another Item
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- ═══ POST NEW DONATION FORM VIEW ═══ -->
        <div style="max-width: 750px; margin: 0 auto; animation: slideUp 0.4s ease-out;">
            
            <div class="db-header-banner" style="margin-bottom: 25px;">
                <div class="db-header-text">
                    <h2>Post a Support Donation</h2>
                    <p>Specify the details of the items you wish to offer. Registered recipients and volunteers can view and coordinate pickups.</p>
                </div>
                <a href="donor_dashboard.php" class="btn-primary-db" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); border-radius: 30px;">
                    ← Back to Dashboard
                </a>
            </div>

            <!-- Form Card Wrapper -->
            <div class="db-panel" style="box-shadow: 0 10px 30px rgba(0,0,0,0.05); border-radius: 16px;">
                <form method="POST" action="donate.php" enctype="multipart/form-data" style="margin: 0; display: flex; flex-direction: column; gap: 20px;">
                    <input type="hidden" name="action" value="add_donation">

                    <!-- Title -->
                    <div class="form-group" style="margin: 0;">
                        <label for="dn_title" style="font-size: 14px; font-weight: 600; color: #333;">Item Name / Title <span style="color:#c62828;">*</span></label>
                        <input type="text" id="dn_title" name="title" class="form-control" placeholder="e.g. Warm Winter Jackets (Medium Size)" required maxlength="150" style="padding: 12px;">
                    </div>

                    <!-- Category & Quantity Row -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group" style="margin: 0;">
                            <label for="dn_category" style="font-size: 14px; font-weight: 600; color: #333;">Category <span style="color:#c62828;">*</span></label>
                            <select id="dn_category" name="category" class="form-control" required style="padding: 12px; height: auto;">
                                <option value="">— Select Category —</option>
                                <option value="Food" <?php echo $selected_category === 'Food' ? 'selected' : ''; ?>>🍱 Food</option>
                                <option value="Clothing" <?php echo $selected_category === 'Clothing' ? 'selected' : ''; ?>>👕 Clothing</option>
                                <option value="Education" <?php echo $selected_category === 'Education' ? 'selected' : ''; ?>>📚 Education</option>
                                <option value="Essential Needs" <?php echo $selected_category === 'Essential Needs' ? 'selected' : ''; ?>>🧴 Essential Needs</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="dn_quantity" style="font-size: 14px; font-weight: 600; color: #333;">Quantity <span style="color:#c62828;">*</span></label>
                            <input type="number" id="dn_quantity" name="quantity" class="form-control" min="1" value="1" required style="padding: 12px;">
                        </div>
                    </div>

                    <!-- Location Town -->
                    <div class="form-group" style="margin: 0;">
                        <label for="dn_town" style="font-size: 14px; font-weight: 600; color: #333;">Your Town / City Location <span style="color:#c62828;">*</span></label>
                        <input type="text" id="dn_town" name="town" class="form-control" placeholder="e.g. Pokhara, Kaski" required maxlength="100" style="padding: 12px;">
                        <span style="font-size: 11px; color: #888; display: block; margin-top: 4px;">Helps local recipients coordinate pickup near them.</span>
                    </div>

                    <!-- Image Upload with Preview -->
                    <div class="form-group" style="margin: 0;">
                        <label for="dn_image" style="font-size: 14px; font-weight: 600; color: #333;">Item Reference Image <span style="color:#aaa; font-size:11px;">(optional · JPG/PNG/WEBP · max 3MB)</span></label>
                        <input type="file" id="dn_image" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" style="padding:10px; cursor:pointer;" onchange="previewDonationImage(this)">
                        
                        <div id="dn_image_preview" style="display:none; margin-top:15px; text-align:center;">
                            <img id="dn_image_thumb" src="" alt="Preview" style="max-width:100%; max-height:200px; border-radius:12px; border:2px dashed #2e7d32; padding:4px; object-fit:cover;">
                            <button type="button" onclick="clearDonationImage()" style="display:block; margin:8px auto 0; background:#ffebee; color:#c62828; border:none; padding:6px 16px; border-radius:8px; font-size:12px; cursor:pointer; font-weight:600; transition: background 0.2s;">
                                ✕ Remove Image
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group" style="margin: 0;">
                        <label for="dn_desc" style="font-size: 14px; font-weight: 600; color: #333;">Description & Notes <span style="color:#aaa; font-size:11px;">(optional)</span></label>
                        <textarea id="dn_desc" name="description" class="form-control" rows="4" placeholder="Condition details, size details, availability hours, best way to reach you, etc..." style="resize:vertical; padding: 12px;"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-primary-db" style="width:100%; border-radius:10px; padding:15px; font-size:16px; background:#2e7d32; font-weight:600; margin-top:10px; cursor: pointer; border: none; box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);">
                        🚀 Submit & Get Donation Reference Card
                    </button>
                </form>
            </div>
            
            <!-- Quick Info -->
            <div class="db-panel" style="background: linear-gradient(135deg,#e8f5e9,#f1f8e9); border-color:#c8e6c9; border-radius: 12px; display: flex; gap: 15px; align-items: flex-start; padding: 20px;">
                <div style="font-size: 24px;">💡</div>
                <div style="font-size: 13px; color: #2e7d32; line-height: 1.6;">
                    <strong style="display: block; margin-bottom: 5px; font-size: 14px;">Next Steps:</strong>
                    1. After submitting, you'll receive a printable <strong>Donation Reference Card</strong> with a transaction ID.<br>
                    2. The listing goes live immediately under status <strong>"Available"</strong>.<br>
                    3. Local recipients can submit requests, which are queued for review. You can monitor requests in your dashboard.
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>



<script>
function previewDonationImage(input) {
    const preview = document.getElementById('dn_image_preview');
    const thumb   = document.getElementById('dn_image_thumb');
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
    document.getElementById('dn_image').value = '';
    document.getElementById('dn_image_thumb').src = '';
    document.getElementById('dn_image_preview').style.display = 'none';
}
</script>

<?php
include_once "../includes/footer.php";
?>
