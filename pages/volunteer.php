<?php
/**
 * Dan Chautari — Volunteer Registration Page
 * Public form: NO login required (as per volunteers table schema)
 * Inserts into the `volunteers` table on submission.
 */

$extra_css  = ['volunteer.css'];
$page_title = 'Become a Volunteer';
require __DIR__ . '/../includes/header.php';

// ─── Handle POST ──────────────────────────────────────────────────────────────
$submitted   = false;
$errors      = [];
$vol_ref     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'volunteer_register') {

    $full_name    = trim($_POST['full_name']    ?? '');
    $email        = trim($_POST['email']        ?? '');
    $phone        = trim($_POST['phone']        ?? '');
    $town         = trim($_POST['town']         ?? '');
    $address      = trim($_POST['address']      ?? '');
    $skills       = trim($_POST['skills']       ?? '');
    $availability = trim($_POST['availability'] ?? '');

    // ── Validation ────────────────────────────────────────────────────────────
    if (empty($full_name))    $errors[] = 'Full name is required.';
    if (empty($phone))        $errors[] = 'Phone number is required.';
    if (empty($town))         $errors[] = 'Town / City is required.';
    if (empty($skills))       $errors[] = 'Please enter at least one skill.';
    if (empty($availability)) $errors[] = 'Please select your availability.';

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!empty($phone) && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $errors[] = 'Phone number must be 7–15 digits.';
    }

    // ── Insert ────────────────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO volunteers (full_name, email, phone, town, address, skills, availability, status)
                VALUES (:full_name, :email, :phone, :town, :address, :skills, :availability, 'pending')
            ");
            $stmt->execute([
                'full_name'    => $full_name,
                'email'        => $email ?: null,
                'phone'        => $phone,
                'town'         => $town,
                'address'      => $address ?: null,
                'skills'       => $skills,
                'availability' => $availability,
            ]);

            $new_id    = $pdo->lastInsertId();
            $vol_ref   = 'DC-VOL-' . str_pad($new_id, 5, '0', STR_PAD_LEFT);
            $submitted = true;

            // ── Activity log (no user_id since public) ────────────────────────
            try {
                $log = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, module, reference_id)
                    VALUES (NULL, 'Volunteer registration submitted', 'volunteers', :ref)
                ");
                $log->execute(['ref' => $new_id]);
            } catch (PDOException $le) { /* Ignore log failure */ }

        } catch (PDOException $e) {
            $errors[] = 'Something went wrong. Please try again later.';
        }
    }
}

// Preserve POST values on error
$old = $_POST ?? [];
?>

<!-- ─── HERO ─── -->
<section class="vol-hero">
    <div class="vol-hero-inner">
        <div class="eyebrow">
            <span class="dot"></span> Join the Movement
        </div>
        <h1>Become a <span>Volunteer</span></h1>
        <p>
            Give your time, skills, and energy to help connect donors with families in need.
            No money required — just a willing heart and a few hours a week.
        </p>
    </div>
</section>

<!-- Wave Divider -->
<div class="vol-wave">
    <svg viewBox="0 0 1200 60" preserveAspectRatio="none">
        <path d="M0,0 C300,60 900,60 1200,0 L1200,60 L0,60 Z" fill="#1b5e20"/>
    </svg>
</div>

<!-- ─── MAIN SECTION ─── -->
<section class="vol-section">
    <div class="vol-layout">

        <!-- ══ LEFT: Info Panel ══ -->
        <aside class="vol-info">
            <div class="vol-info-badge">🌱 Volunteer Program</div>
            <h2>Make a Real <span>Difference</span> in Your Community</h2>
            <p>
                Our volunteers are the backbone of Daan Chautari. From sorting donations
                to organizing distribution drives, every hour you give has a direct impact
                on families across Nepal.
            </p>

            <div class="vol-perks">
                <div class="vol-perk-item">
                    <div class="vol-perk-icon">🤝</div>
                    <div class="vol-perk-text">
                        <strong>Community Connection</strong>
                        <span>Work alongside passionate people who care about social good.</span>
                    </div>
                </div>
                <div class="vol-perk-item">
                    <div class="vol-perk-icon">📋</div>
                    <div class="vol-perk-text">
                        <strong>Volunteer Certificate</strong>
                        <span>Receive an official recognition letter after active service.</span>
                    </div>
                </div>
                <div class="vol-perk-item">
                    <div class="vol-perk-icon">📍</div>
                    <div class="vol-perk-text">
                        <strong>Work Near You</strong>
                        <span>Volunteer opportunities matched to your town and schedule.</span>
                    </div>
                </div>
                <div class="vol-perk-item">
                    <div class="vol-perk-icon">⚡</div>
                    <div class="vol-perk-text">
                        <strong>Flexible Commitment</strong>
                        <span>Choose hours that fit your lifestyle — weekends or weekdays.</span>
                    </div>
                </div>
            </div>
<!-- 
            <div class="vol-stats-strip">
                <div class="vol-stat">
                    <span class="vol-stat-num">45+</span>
                    <span class="vol-stat-lbl">Active Volunteers</span>
                </div>
                <div class="vol-stat">
                    <span class="vol-stat-num">3</span>
                    <span class="vol-stat-lbl">Districts Covered</span>
                </div>
                <div class="vol-stat">
                    <span class="vol-stat-num">2K+</span>
                    <span class="vol-stat-lbl">Lives Impacted</span>
                </div>
            </div> -->
        </aside>

        <!-- ══ RIGHT: Form Card ══ -->
        <div class="vol-form-card">

            <!-- Form Header -->
            <div class="vol-form-header">
                <div class="vol-form-header-icon">🙋</div>
                <div>
                    <h3>Volunteer Registration Form</h3>
                    <p>Fill in the details below — our team reviews applications within 48 hours.</p>
                </div>
            </div>

            <div class="vol-form-body">

                <?php if ($submitted): ?>
                <!-- ══ SUCCESS STATE ══ -->
                <div class="vol-success">
                    <div class="vol-success-icon">✓</div>
                    <h3>Application Submitted!</h3>
                    <p>
                        Thank you for stepping up, <strong><?php echo htmlspecialchars($full_name); ?></strong>!
                        Our team will review your application and contact you within 48 hours.
                    </p>
                    <div class="vol-success-ref">
                        <span>Your Reference ID</span>
                        <strong><?php echo htmlspecialchars($vol_ref); ?></strong>
                    </div>
                    <div class="vol-success-actions">
                        <a href="volunteer.php" class="primary-btn">📝 Submit Another</a>
                        <a href="homepage.php" class="secondary-btn">🏠 Back to Home</a>
                    </div>
                </div>

                <?php else: ?>
                <!-- ══ REGISTRATION FORM ══ -->

                <!-- Step Indicator -->
                <div class="vol-form-steps">
                    <div class="vol-step active">
                        <div class="vol-step-num">1</div>
                        Personal Info
                    </div>
                    <div class="vol-step-line"></div>
                    <div class="vol-step active">
                        <div class="vol-step-num">2</div>
                        Location
                    </div>
                    <div class="vol-step-line"></div>
                    <div class="vol-step active">
                        <div class="vol-step-num">3</div>
                        Skills & Schedule
                    </div>
                </div>

                <!-- Error Alert -->
                <?php if (!empty($errors)): ?>
                <div class="vol-error-alert">
                    ⚠️
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <?php echo htmlspecialchars($err); ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="volunteer.php" id="volunteerForm" novalidate>
                    <input type="hidden" name="action" value="volunteer_register">

                    <!-- ── SECTION 1: Personal Information ── -->
                    <div class="vf-section-label">👤 Personal Information</div>

                    <div class="vf-row">
                        <div class="vf-group">
                            <label for="vf_full_name">Full Name <span class="req">*</span></label>
                            <input
                                type="text"
                                id="vf_full_name"
                                name="full_name"
                                placeholder="e.g. Sandesh Shrestha"
                                maxlength="100"
                                required
                                value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>"
                            >
                        </div>
                        <div class="vf-group">
                            <label for="vf_phone">Phone Number <span class="req">*</span></label>
                            <input
                                type="tel"
                                id="vf_phone"
                                name="phone"
                                placeholder="e.g. 9800000000"
                                maxlength="15"
                                required
                                value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>"
                            >
                        </div>
                    </div>

                    <div class="vf-group">
                        <label for="vf_email">Email Address <span class="opt">(optional)</span></label>
                        <input
                            type="email"
                            id="vf_email"
                            name="email"
                            placeholder="you@example.com"
                            maxlength="100"
                            value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>"
                        >
                        <span class="vf-hint">We'll use this to send your confirmation and updates.</span>
                    </div>

                    <div class="vf-divider"></div>

                    <!-- ── SECTION 2: Location ── -->
                    <div class="vf-section-label">📍 Location Details</div>

                    <div class="vf-row">
                        <div class="vf-group">
                            <label for="vf_town">Town / City <span class="req">*</span></label>
                            <input
                                type="text"
                                id="vf_town"
                                name="town"
                                placeholder="e.g. Kathmandu"
                                maxlength="100"
                                required
                                value="<?php echo htmlspecialchars($old['town'] ?? ''); ?>"
                            >
                            <span class="vf-hint">We match volunteers with local opportunities.</span>
                        </div>
                        <div class="vf-group">
                            <label for="vf_address">Street / Ward Address <span class="opt">(optional)</span></label>
                            <input
                                type="text"
                                id="vf_address"
                                name="address"
                                placeholder="e.g. Ward 5, Lalitpur"
                                maxlength="255"
                                value="<?php echo htmlspecialchars($old['address'] ?? ''); ?>"
                            >
                        </div>
                    </div>

                    <div class="vf-divider"></div>

                    <!-- ── SECTION 3: Skills & Availability ── -->
                    <div class="vf-section-label">⚡ Skills & Availability</div>

                    <!-- Skills Tag Input -->
                    <div class="vf-group">
                        <label>Your Skills <span class="req">*</span></label>
                        <!-- Hidden input that stores comma-separated skills -->
                        <input type="hidden" id="vf_skills_value" name="skills" value="<?php echo htmlspecialchars($old['skills'] ?? ''); ?>">

                        <div class="skills-tags-wrap" id="skillsTagsWrap" onclick="document.getElementById('skillsTagInput').focus()">
                            <!-- Tags injected by JS here -->
                            <input
                                type="text"
                                id="skillsTagInput"
                                class="skills-tag-input"
                                placeholder="Type a skill and press Enter or comma…"
                                maxlength="50"
                            >
                        </div>
                        <span class="vf-hint">Press <strong>Enter</strong> or <strong>,</strong> after each skill to add it as a tag.</span>

                        <!-- Quick-add suggestions -->
                        <div class="skills-suggestions" id="skillsSuggestions">
                            <span style="font-size:11.5px; color:#999; align-self:center;">Quick add:</span>
                            <?php
                            $suggest_skills = ['Teaching','Driving','Cooking','First Aid','Logistics','IT/Tech','Photography','Social Media','Accounting','Event Planning','Counselling','Carpentry'];
                            foreach ($suggest_skills as $sk):
                            ?>
                            <button type="button" class="skill-suggest-btn" onclick="addSkillTag('<?php echo htmlspecialchars($sk); ?>')"><?php echo htmlspecialchars($sk); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Availability Checkboxes -->
                    <div class="vf-group">
                        <label>Availability <span class="req">*</span></label>
                        <input type="hidden" id="vf_availability_value" name="availability" value="<?php echo htmlspecialchars($old['availability'] ?? ''); ?>">

                        <div class="avail-grid" id="availGrid">
                            <?php
                            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                            $old_avail = array_filter(array_map('trim', explode(',', $old['availability'] ?? '')));
                            foreach ($days as $day):
                                $is_checked = in_array($day, $old_avail);
                            ?>
                            <label class="avail-chip <?php echo $is_checked ? 'checked' : ''; ?>" id="chip_<?php echo $day; ?>">
                                <input type="checkbox" value="<?php echo $day; ?>" <?php echo $is_checked ? 'checked' : ''; ?> onchange="updateAvailability()">
                                <?php echo $day; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <span class="vf-hint">Select all days when you're generally available to volunteer.</span>
                    </div>

                    <!-- Submit -->
                    <div class="vol-submit-area">
                        <button type="submit" class="vol-submit-btn" id="volunteerSubmitBtn">
                            <span>🚀</span> Submit Volunteer Application
                        </button>
                        <p class="vol-privacy-note">
                            🔒 Your information is secure and will only be used for volunteer coordination.
                        </p>
                    </div>

                </form>
                <?php endif; ?>

            </div><!-- /.vol-form-body -->
        </div><!-- /.vol-form-card -->

    </div><!-- /.vol-layout -->
</section>

<!-- ─── SCRIPT: Skills Tags + Availability ─── -->
<script>
// ── SKILLS TAG SYSTEM ─────────────────────────────────────────────────────────
const skillsWrap    = document.getElementById('skillsTagsWrap');
const skillsInput   = document.getElementById('skillsTagInput');
const skillsHidden  = document.getElementById('vf_skills_value');
let   skillsList    = [];

// Pre-fill tags from PHP old value (on form errors)
(function initSkills() {
    const raw = skillsHidden.value.trim();
    if (!raw) return;
    raw.split(',').forEach(s => { const t = s.trim(); if (t) addSkillTag(t); });
})();

function addSkillTag(skill) {
    skill = skill.trim().replace(/,/g, '');
    if (!skill || skillsList.includes(skill.toLowerCase())) return;
    if (skillsList.length >= 15) return;

    skillsList.push(skill.toLowerCase());

    const tag = document.createElement('span');
    tag.className = 'skill-tag';
    tag.innerHTML = `${escHtml(skill)} <button type="button" class="skill-tag-remove" onclick="removeSkillTag(this, '${escHtml(skill.toLowerCase())}')">×</button>`;
    skillsWrap.insertBefore(tag, skillsInput);
    skillsHidden.value = skillsList.join(', ');

    // Hide suggestion button if added
    document.querySelectorAll('.skill-suggest-btn').forEach(btn => {
        if (btn.textContent.toLowerCase() === skill.toLowerCase()) {
            btn.style.display = 'none';
        }
    });
}

function removeSkillTag(btn, skill) {
    skillsList = skillsList.filter(s => s !== skill);
    btn.closest('.skill-tag').remove();
    skillsHidden.value = skillsList.join(', ');

    // Restore suggestion button
    document.querySelectorAll('.skill-suggest-btn').forEach(b => {
        if (b.textContent.toLowerCase() === skill.toLowerCase()) {
            b.style.display = '';
        }
    });
}

skillsInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addSkillTag(this.value);
        this.value = '';
    }
    if (e.key === 'Backspace' && !this.value && skillsList.length > 0) {
        const last = skillsList[skillsList.length - 1];
        document.querySelectorAll('.skill-tag').forEach(tag => {
            if (tag.textContent.trim().slice(0, -1).toLowerCase() === last) {
                removeSkillTag(tag.querySelector('.skill-tag-remove'), last);
            }
        });
    }
});

// ── AVAILABILITY CHECKBOXES ───────────────────────────────────────────────────
function updateAvailability() {
    const checks  = document.querySelectorAll('#availGrid input[type="checkbox"]');
    const selected = [];
    checks.forEach(cb => {
        const chip = cb.closest('.avail-chip');
        if (cb.checked) {
            selected.push(cb.value);
            chip.classList.add('checked');
        } else {
            chip.classList.remove('checked');
        }
    });
    document.getElementById('vf_availability_value').value = selected.join(', ');
}

// ── CLIENT-SIDE VALIDATION ────────────────────────────────────────────────────
document.getElementById('volunteerForm').addEventListener('submit', function(e) {
    let valid = true;
    const required = ['vf_full_name', 'vf_phone', 'vf_town'];

    required.forEach(id => {
        const el = document.getElementById(id);
        if (!el.value.trim()) {
            el.classList.add('invalid');
            valid = false;
        } else {
            el.classList.remove('invalid');
        }
    });

    // Skills check
    if (!skillsHidden.value.trim()) {
        skillsWrap.style.borderColor = '#c62828';
        skillsWrap.style.boxShadow = '0 0 0 3px rgba(198,40,40,0.1)';
        valid = false;
    } else {
        skillsWrap.style.borderColor = '';
        skillsWrap.style.boxShadow = '';
    }

    // Availability check
    if (!document.getElementById('vf_availability_value').value.trim()) {
        document.getElementById('availGrid').style.outline = '2px solid #c62828';
        document.getElementById('availGrid').style.borderRadius = '10px';
        valid = false;
    } else {
        document.getElementById('availGrid').style.outline = '';
    }

    if (!valid) {
        e.preventDefault();
        window.scrollTo({ top: document.querySelector('.vol-form-card').offsetTop - 120, behavior: 'smooth' });
        return;
    }

    // Loading state
    const btn = document.getElementById('volunteerSubmitBtn');
    btn.innerHTML = '<span class="spinner"></span> Submitting…';
    btn.disabled = true;
});

// Remove invalid class on input
document.querySelectorAll('.vf-group input, .vf-group select, .vf-group textarea').forEach(el => {
    el.addEventListener('input', () => el.classList.remove('invalid'));
});

// ── HELPERS ───────────────────────────────────────────────────────────────────
function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
