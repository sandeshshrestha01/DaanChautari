<?php
/**
 * Daan Chautari — Admin Panel: Manage Users & Community
 * Features user management, volunteer approval/management, filter controls,
 * and an interactive registration trend line chart.
 */

// ── Handle POST & CSV Export BEFORE any HTML output ──────────────────────────
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/db.php';

// Auth Guard: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    set_flash_message('error', 'Unauthorized access. Please log in as an administrator.');
    header("Location: " . BASE_URL . "auth/admin_login.php");
    exit;
}

$current_admin_id = (int)$_SESSION['user_id'];
$page_title       = 'Manage Users';

// ── 1. CSV Export Handler ─────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_role = $_GET['role'] ?? 'all';
    $filename    = 'daan_chautari_' . ($export_role === 'volunteer' ? 'volunteers' : 'users') . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    if ($export_role === 'volunteer') {
        fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'Town', 'Address', 'Skills', 'Availability', 'Status', 'Submitted At'], ',', '"', "\\");
        $vol_stmt = $pdo->query("SELECT volunteer_id, full_name, email, phone, town, address, skills, availability, status, submitted_at FROM volunteers ORDER BY submitted_at DESC");
        while ($row = $vol_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row, ',', '"', "\\");
        }
    } else {
        fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'Town', 'Address', 'Role', 'Status', 'Registered At'], ',', '"', "\\");
        $sql = "SELECT user_id, full_name, email, phone, town, address, role, status, created_at FROM users";
        if (in_array($export_role, ['donor', 'recipient', 'admin'])) {
            $sql .= " WHERE role = " . $pdo->quote($export_role);
        }
        $sql .= " ORDER BY created_at DESC";
        $u_stmt = $pdo->query($sql);
        while ($row = $u_stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row, ',', '"', "\\");
        }
    }
    fclose($output);
    exit;
}

// ── 2. POST Action Dispatcher ────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action: Add New User
    if ($action === 'add_user') {
        $fullname = trim(filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $email    = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
        $password = $_POST['password'] ?? '';
        $phone    = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $town     = trim(filter_input(INPUT_POST, 'town', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $address  = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $role     = in_array($_POST['role'] ?? '', ['donor', 'recipient', 'admin']) ? $_POST['role'] : 'donor';
        $status   = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($fullname) || !$email || empty($password) || empty($town)) {
            set_flash_message('error', 'Please fill in all required fields (Name, Email, Password, Town).');
        } elseif (strlen($password) < 6) {
            set_flash_message('error', 'Password must be at least 6 characters.');
        } else {
            try {
                $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = :e");
                $chk->execute(['e' => $email]);
                if ($chk->fetch()) {
                    set_flash_message('error', 'A user with this email address already exists.');
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("
                        INSERT INTO users (full_name, email, password, phone, town, address, role, status)
                        VALUES (:fn, :em, :pw, :ph, :tw, :ad, :ro, :st)
                    ");
                    $ins->execute([
                        'fn' => $fullname,
                        'em' => $email,
                        'pw' => $hashed,
                        'ph' => $phone,
                        'tw' => $town,
                        'ad' => $address,
                        'ro' => $role,
                        'st' => $status
                    ]);
                    $new_id = $pdo->lastInsertId();

                    // If recipient, ensure entry in recipients table
                    if ($role === 'recipient') {
                        $pdo->prepare("INSERT INTO recipients (user_id, town, address) VALUES (:u, :t, :a) ON DUPLICATE KEY UPDATE town = :t2")
                            ->execute(['u' => $new_id, 't' => $town, 'a' => $address, 't2' => $town]);
                    }

                    set_flash_message('success', "User \"$fullname\" has been created successfully.");
                }
            } catch (PDOException $e) {
                set_flash_message('error', 'Database error: Could not create user.');
            }
        }
        header("Location: manage_users.php" . ($role !== 'all' ? "?role=$role" : ''));
        exit;
    }

    // Action: Toggle User Status (Active / Inactive)
    if ($action === 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $current = $_POST['current_status'] ?? 'active';

        if ($user_id === $current_admin_id) {
            set_flash_message('error', 'Security protection: You cannot deactivate your own administrative account.');
        } elseif ($user_id > 0) {
            $new_status = ($current === 'active') ? 'inactive' : 'active';
            try {
                $pdo->prepare("UPDATE users SET status = :s WHERE user_id = :u")
                    ->execute(['s' => $new_status, 'u' => $user_id]);
                set_flash_message('success', "User status updated to " . strtoupper($new_status) . ".");
            } catch (PDOException $e) {
                set_flash_message('error', 'Could not update user status.');
            }
        }
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_users.php'));
        exit;
    }

    // Action: Change User Role
    if ($action === 'change_role') {
        $user_id  = (int)($_POST['user_id'] ?? 0);
        $new_role = in_array($_POST['new_role'] ?? '', ['donor', 'recipient']) ? $_POST['new_role'] : '';

        if ($user_id === $current_admin_id && $new_role !== 'admin') {
            set_flash_message('error', 'Security protection: You cannot remove your own admin privileges.');
        } elseif ($user_id > 0 && !empty($new_role)) {
            try {
                $pdo->prepare("UPDATE users SET role = :r WHERE user_id = :u")
                    ->execute(['r' => $new_role, 'u' => $user_id]);

                if ($new_role === 'recipient') {
                    $u_info = $pdo->prepare("SELECT town, address FROM users WHERE user_id = :u");
                    $u_info->execute(['u' => $user_id]);
                    $row = $u_info->fetch();
                    $pdo->prepare("INSERT INTO recipients (user_id, town, address) VALUES (:u, :t, :a) ON DUPLICATE KEY UPDATE town = :t2")
                        ->execute(['u' => $user_id, 't' => $row['town'] ?? '', 'a' => $row['address'] ?? '', 't2' => $row['town'] ?? '']);
                }

                set_flash_message('success', "User role successfully changed to " . ucfirst($new_role) . ".");
            } catch (PDOException $e) {
                set_flash_message('error', 'Could not change user role.');
            }
        }
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_users.php'));
        exit;
    }

    // Action: Delete User
    if ($action === 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($user_id === $current_admin_id) {
            set_flash_message('error', 'Security protection: You cannot delete your own admin account.');
        } elseif ($user_id > 0) {
            try {
                $pdo->prepare("DELETE FROM users WHERE user_id = :u")->execute(['u' => $user_id]);
                set_flash_message('success', 'User account deleted successfully.');
            } catch (PDOException $e) {
                set_flash_message('error', 'Could not delete user. (User may have existing donations or requests).');
            }
        }
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'manage_users.php'));
        exit;
    }

    // Action: Update Volunteer Status
    if ($action === 'update_volunteer_status') {
        $vol_id = (int)($_POST['volunteer_id'] ?? 0);
        $new_st = in_array($_POST['new_status'] ?? '', ['active', 'inactive', 'pending']) ? $_POST['new_status'] : 'active';

        if ($vol_id > 0) {
            try {
                $pdo->prepare("UPDATE volunteers SET status = :s WHERE volunteer_id = :v")
                    ->execute(['s' => $new_st, 'v' => $vol_id]);
                set_flash_message('success', "Volunteer status updated to " . strtoupper($new_st) . ".");
            } catch (PDOException $e) {
                set_flash_message('error', 'Could not update volunteer status.');
            }
        }
        header("Location: manage_users.php?role=volunteer");
        exit;
    }

    // Action: Delete Volunteer
    if ($action === 'delete_volunteer') {
        $vol_id = (int)($_POST['volunteer_id'] ?? 0);
        if ($vol_id > 0) {
            try {
                $pdo->prepare("DELETE FROM volunteers WHERE volunteer_id = :v")->execute(['v' => $vol_id]);
                set_flash_message('success', 'Volunteer application deleted.');
            } catch (PDOException $e) {
                set_flash_message('error', 'Could not delete volunteer.');
            }
        }
        header("Location: manage_users.php?role=volunteer");
        exit;
    }
}

// ── 3. Overview Metrics & Statistics ─────────────────────────────────────────
$total_users      = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_donors     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'donor'")->fetchColumn();
$total_recipients = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'recipient'")->fetchColumn();
$total_admins     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$total_volunteers = (int)$pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn();
$pending_vols     = (int)$pdo->query("SELECT COUNT(*) FROM volunteers WHERE status = 'pending'")->fetchColumn();
$active_users     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$active_rate      = $total_users > 0 ? round(($active_users / $total_users) * 100) : 100;

// ── 4. Line Chart Data Preparation ───────────────────────────────────────────
// Time range: 7, 14, or 30 days
$chart_days_limit = isset($_GET['days']) && in_array((int)$_GET['days'], [7, 14, 30]) ? (int)$_GET['days'] : 14;

// Daily user registrations by role
$user_reg_stmt = $pdo->prepare("
    SELECT DATE(created_at) AS reg_day, role, COUNT(*) AS count
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
    GROUP BY DATE(created_at), role
    ORDER BY reg_day ASC
");
$user_reg_stmt->execute(['d' => $chart_days_limit]);
$user_reg_rows = $user_reg_stmt->fetchAll();

// Daily volunteer applications
$vol_app_stmt = $pdo->prepare("
    SELECT DATE(submitted_at) AS sub_day, COUNT(*) AS count
    FROM volunteers
    WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL :d DAY)
    GROUP BY DATE(submitted_at)
    ORDER BY sub_day ASC
");
$vol_app_stmt->execute(['d' => $chart_days_limit]);
$vol_app_rows = $vol_app_stmt->fetchAll();

// Map DB rows to lookup arrays
$donors_by_day     = [];
$recipients_by_day = [];
$admins_by_day     = [];
$volunteers_by_day = [];

foreach ($user_reg_rows as $row) {
    $day = $row['reg_day'];
    $cnt = (int)$row['count'];
    if ($row['role'] === 'donor')     $donors_by_day[$day]     = $cnt;
    if ($row['role'] === 'recipient') $recipients_by_day[$day] = $cnt;
    if ($row['role'] === 'admin')     $admins_by_day[$day]     = $cnt;
}

foreach ($vol_app_rows as $row) {
    $volunteers_by_day[$row['sub_day']] = (int)$row['count'];
}

// Generate continuous dates array for chart
$chart_labels     = [];
$chart_total_data = [];
$chart_donor_data = [];
$chart_recip_data = [];
$chart_vol_data   = [];

for ($i = $chart_days_limit - 1; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $display_label = date('M d', strtotime($date_str));
    
    $d_count = $donors_by_day[$date_str]     ?? 0;
    $r_count = $recipients_by_day[$date_str] ?? 0;
    $a_count = $admins_by_day[$date_str]     ?? 0;
    $v_count = $volunteers_by_day[$date_str] ?? 0;
    $t_count = $d_count + $r_count + $a_count;

    $chart_labels[]     = $display_label;
    $chart_donor_data[] = $d_count;
    $chart_recip_data[] = $r_count;
    $chart_vol_data[]   = $v_count;
    $chart_total_data[] = $t_count;
}

$period_new_users = array_sum($chart_total_data);
$period_new_vols  = array_sum($chart_vol_data);

// ── 5. Filtering & Querying Records ──────────────────────────────────────────
$role_filter   = $_GET['role']   ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search_query  = trim($_GET['search'] ?? '');

$is_volunteer_view = ($role_filter === 'volunteer');

if ($is_volunteer_view) {
    // Query Volunteers
    $v_sql = "SELECT * FROM volunteers WHERE 1=1";
    $v_params = [];

    if (in_array($status_filter, ['active', 'inactive', 'pending'])) {
        $v_sql .= " AND status = :st";
        $v_params['st'] = $status_filter;
    }
    if (!empty($search_query)) {
        $v_sql .= " AND (full_name LIKE :sq OR email LIKE :sq OR phone LIKE :sq OR town LIKE :sq OR skills LIKE :sq)";
        $v_params['sq'] = "%$search_query%";
    }
    $v_sql .= " ORDER BY submitted_at DESC";
    $vol_stmt = $pdo->prepare($v_sql);
    $vol_stmt->execute($v_params);
    $volunteers_list = $vol_stmt->fetchAll();
    $users_list = [];
} else {
    // Query Users
    $u_sql = "
        SELECT u.*,
               (SELECT COUNT(*) FROM donations WHERE donor_id = u.user_id) AS total_donations,
               (SELECT COUNT(*) FROM donation_requests dr JOIN recipients r ON dr.recipient_id = r.recipient_id WHERE r.user_id = u.user_id) AS total_requests
        FROM users u
        WHERE 1=1
    ";
    $u_params = [];

    if (in_array($role_filter, ['donor', 'recipient', 'admin'])) {
        $u_sql .= " AND u.role = :ro";
        $u_params['ro'] = $role_filter;
    }
    if (in_array($status_filter, ['active', 'inactive'])) {
        $u_sql .= " AND u.status = :st";
        $u_params['st'] = $status_filter;
    }
    if (!empty($search_query)) {
        $u_sql .= " AND (u.full_name LIKE :sq OR u.email LIKE :sq OR u.phone LIKE :sq OR u.town LIKE :sq)";
        $u_params['sq'] = "%$search_query%";
    }
    $u_sql .= " ORDER BY u.created_at DESC";
    $u_stmt = $pdo->prepare($u_sql);
    $u_stmt->execute($u_params);
    $users_list = $u_stmt->fetchAll();
    $volunteers_list = [];
}

// ── 6. Include Admin Layout Header ───────────────────────────────────────────
require_once __DIR__ . '/admin_header.php';
?>

<!-- ═══════════════════════ PAGE HEADER ═══════════════════════ -->
<div class="dash-header">
    <div>
        <h2 class="dash-title">Manage Users & Community</h2>
        <p class="dash-sub">Monitor platform accounts, role distribution, volunteer submissions, and user onboarding trends.</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button type="button" class="btn btn-primary" onclick="openAddUserModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New User
        </button>
        <a href="manage_users.php?export=csv&role=<?php echo urlencode($role_filter); ?>" class="btn btn-outline">
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
        <div class="dstat-icon">👥</div>
        <div>
            <div class="dstat-num"><?php echo number_format($total_users); ?></div>
            <div class="dstat-label">Total Users</div>
        </div>
    </div>

    <div class="dstat-card dstat-blue">
        <div class="dstat-icon">🎁</div>
        <div>
            <div class="dstat-num"><?php echo number_format($total_donors); ?></div>
            <div class="dstat-label">Active Donors</div>
        </div>
    </div>

    <div class="dstat-card dstat-purple">
        <div class="dstat-icon">🤲</div>
        <div>
            <div class="dstat-num"><?php echo number_format($total_recipients); ?></div>
            <div class="dstat-label">Recipients</div>
        </div>
    </div>

    <div class="dstat-card dstat-orange">
        <div class="dstat-icon">🤝</div>
        <div>
            <div class="dstat-num"><?php echo number_format($total_volunteers); ?></div>
            <div class="dstat-label">
                Volunteers
                <?php if ($pending_vols > 0): ?>
                    <span style="color:#d32f2f; font-weight:800;">(<?php echo $pending_vols; ?> pending)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════ LINE CHART PANEL ═══════════════════════ -->
<div class="dash-panel" style="margin-bottom: 24px;">
    <div class="dpanel-hdr" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 class="dpanel-title" style="display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="color:var(--primary);">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
                Community Registration & Growth Trends
            </h3>
            <p style="font-size:12px; color:var(--muted); margin-top:2px;">
                <strong>+<?php echo $period_new_users; ?> new users</strong>
                <?php if ($period_new_vols > 0): ?>
                    and <strong>+<?php echo $period_new_vols; ?> volunteer applications</strong>
                <?php endif; ?>
                over the last <?php echo $chart_days_limit; ?> days.
            </p>
        </div>

        <div class="chart-header-actions">
            <!-- Legend Tags -->
            <div class="chart-legend-tags">
                <span class="chart-legend-tag">
                    <span class="chart-legend-dot" style="background:#2e7d32;"></span> Total Signups
                </span>
                <span class="chart-legend-tag">
                    <span class="chart-legend-dot" style="background:#0288d1;"></span> Donors
                </span>
                <span class="chart-legend-tag">
                    <span class="chart-legend-dot" style="background:#7b1fa2;"></span> Recipients
                </span>
                <span class="chart-legend-tag">
                    <span class="chart-legend-dot" style="background:#00796b;"></span> Volunteers
                </span>
            </div>

            <!-- Time Range Pills -->
            <div class="chart-time-pills">
                <?php
                $base_params = $_GET;
                unset($base_params['days']);
                $q_str = http_build_query($base_params);
                $sep = !empty($q_str) ? '&' : '';
                ?>
                <a href="manage_users.php?<?php echo $q_str . $sep; ?>days=7"
                   class="chart-time-btn <?php echo $chart_days_limit === 7 ? 'active' : ''; ?>">7 Days</a>
                <a href="manage_users.php?<?php echo $q_str . $sep; ?>days=14"
                   class="chart-time-btn <?php echo $chart_days_limit === 14 ? 'active' : ''; ?>">14 Days</a>
                <a href="manage_users.php?<?php echo $q_str . $sep; ?>days=30"
                   class="chart-time-btn <?php echo $chart_days_limit === 30 ? 'active' : ''; ?>">30 Days</a>
            </div>
        </div>
    </div>

    <!-- Chart Canvas -->
    <div class="chart-wrap" style="padding: 16px 20px 20px;">
        <div class="user-chart-container">
            <canvas id="usersGrowthLineChart"></canvas>
        </div>
    </div>
</div>

<!-- ═══════════════════════ NAVIGATION TABS ═══════════════════════ -->
<div class="user-tabs-bar">
    <div class="user-tabs">
        <a href="manage_users.php" class="user-tab-btn <?php echo $role_filter === 'all' ? 'active' : ''; ?>">
            All Users
            <span class="user-tab-count"><?php echo $total_users; ?></span>
        </a>
        <a href="manage_users.php?role=donor" class="user-tab-btn <?php echo $role_filter === 'donor' ? 'active' : ''; ?>">
            Donors
            <span class="user-tab-count"><?php echo $total_donors; ?></span>
        </a>
        <a href="manage_users.php?role=recipient" class="user-tab-btn <?php echo $role_filter === 'recipient' ? 'active' : ''; ?>">
            Recipients
            <span class="user-tab-count"><?php echo $total_recipients; ?></span>
        </a>
        <a href="manage_users.php?role=admin" class="user-tab-btn <?php echo $role_filter === 'admin' ? 'active' : ''; ?>">
            Admins
            <span class="user-tab-count"><?php echo $total_admins; ?></span>
        </a>
        <a href="manage_users.php?role=volunteer" class="user-tab-btn <?php echo $role_filter === 'volunteer' ? 'active' : ''; ?>">
            Volunteers
            <span class="user-tab-count"><?php echo $total_volunteers; ?></span>
        </a>
    </div>

    <div>
        <span style="font-size: 12.5px; color: var(--muted);">
            Showing <?php echo $is_volunteer_view ? count($volunteers_list) : count($users_list); ?> records
        </span>
    </div>
</div>

<!-- ═══════════════════════ FILTER & SEARCH BAR ═══════════════════════ -->
<div class="user-filter-card">
    <form method="GET" action="manage_users.php" class="user-filter-left">
        <?php if ($role_filter !== 'all'): ?>
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter); ?>">
        <?php endif; ?>
        <?php if ($chart_days_limit !== 14): ?>
            <input type="hidden" name="days" value="<?php echo $chart_days_limit; ?>">
        <?php endif; ?>

        <!-- Search Input -->
        <div class="input-with-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="search-input"
                   placeholder="<?php echo $is_volunteer_view ? 'Search by name, skills, town...' : 'Search by name, email, phone, town...'; ?>"
                   value="<?php echo htmlspecialchars($search_query); ?>">
        </div>

        <!-- Status Filter Dropdown -->
        <select name="status" class="filter-select">
            <option value="all">All Statuses</option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            <?php if ($is_volunteer_view): ?>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
            <?php endif; ?>
        </select>

        <button type="submit" class="btn btn-primary btn-sm">Filter</button>

        <?php if (!empty($search_query) || $status_filter !== 'all'): ?>
            <a href="manage_users.php<?php echo $role_filter !== 'all' ? '?role=' . urlencode($role_filter) : ''; ?>"
               class="btn btn-outline btn-sm">Clear Filter</a>
        <?php endif; ?>
    </form>

    <div class="user-filter-right">
        <span class="badge b-active"><?php echo $active_rate; ?>% Active Account Rate</span>
    </div>
</div>

<!-- ═══════════════════════ MAIN DATA TABLE ═══════════════════════ -->
<div class="dash-panel">
    <div class="dpanel-hdr">
        <h3 class="dpanel-title">
            <?php if ($is_volunteer_view): ?>
                Volunteer Applicants & Community Champions
            <?php else: ?>
                Registered Platform Users (<?php echo ucfirst($role_filter === 'all' ? 'All Roles' : $role_filter); ?>)
            <?php endif; ?>
        </h3>
    </div>

    <div class="dtbl-wrap">
        <?php if ($is_volunteer_view): ?>
            <!-- ─── Volunteer Directory Table ──────────────────────────────── -->
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Volunteer</th>
                        <th>Contact</th>
                        <th>Town / Location</th>
                        <th>Offered Skills</th>
                        <th>Availability</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($volunteers_list)): ?>
                    <tr>
                        <td colspan="8" class="td-empty">
                            <div class="empty-state">
                                <div class="ei">🤝</div>
                                <h3>No volunteer records found</h3>
                                <p>No volunteers match your selected search or filter criteria.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($volunteers_list as $vol):
                        $v_init  = strtoupper(substr($vol['full_name'], 0, 1));
                        $v_st    = $vol['status'];
                        $v_badge = match($v_st) {
                            'active'   => 'b-active',
                            'pending'  => 'b-pending',
                            'inactive' => 'b-inactive',
                            default    => 'b-inactive'
                        };
                        $skills_arr = array_filter(array_map('trim', explode(',', $vol['skills'] ?? '')));
                    ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="u-ava" style="background:#00796b;"><?php echo $v_init; ?></div>
                                <div>
                                    <div class="u-name"><?php echo htmlspecialchars($vol['full_name']); ?></div>
                                    <div class="badge-id">#VOL-<?php echo $vol['volunteer_id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12.5px; font-weight: 500;"><?php echo htmlspecialchars($vol['phone']); ?></div>
                            <div class="u-email"><?php echo htmlspecialchars($vol['email'] ?: 'No email'); ?></div>
                        </td>
                        <td>
                            <span style="font-size: 12.5px; font-weight: 500;"><?php echo htmlspecialchars($vol['town']); ?></span>
                            <?php if (!empty($vol['address'])): ?>
                                <div style="font-size: 11px; color: var(--muted);"><?php echo htmlspecialchars($vol['address']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 220px;">
                            <?php if (empty($skills_arr)): ?>
                                <span class="dtd-muted">General assistance</span>
                            <?php else: ?>
                                <?php foreach ($skills_arr as $sk): ?>
                                    <span class="skill-pill"><?php echo htmlspecialchars($sk); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12.5px;">
                            <?php echo htmlspecialchars($vol['availability'] ?: 'Flexible'); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $v_badge; ?>"><?php echo ucfirst($v_st); ?></span>
                        </td>
                        <td class="dtd-muted">
                            <?php echo date('d M Y', strtotime($vol['submitted_at'])); ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="action-btn-group" style="justify-content: flex-end;">
                                <?php if ($v_st === 'pending'): ?>
                                    <!-- Approve Button -->
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="update_volunteer_status">
                                        <input type="hidden" name="volunteer_id" value="<?php echo $vol['volunteer_id']; ?>">
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="btn btn-sm btn-approve" title="Approve Volunteer">
                                            ✓ Approve
                                        </button>
                                    </form>
                                <?php elseif ($v_st === 'active'): ?>
                                    <!-- Deactivate Button -->
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="update_volunteer_status">
                                        <input type="hidden" name="volunteer_id" value="<?php echo $vol['volunteer_id']; ?>">
                                        <input type="hidden" name="new_status" value="inactive">
                                        <button type="submit" class="btn-icon-sq btn-toggle-inactive" title="Mark Inactive">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                            </svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Reactivate Button -->
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="update_volunteer_status">
                                        <input type="hidden" name="volunteer_id" value="<?php echo $vol['volunteer_id']; ?>">
                                        <input type="hidden" name="new_status" value="active">
                                        <button type="submit" class="btn-icon-sq btn-toggle-active" title="Activate Volunteer">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Delete Volunteer -->
                                <button type="button" class="btn-icon-sq btn-danger-hover" title="Delete Volunteer Application"
                                        onclick="confirmDeleteVolunteer(<?php echo $vol['volunteer_id']; ?>, '<?php echo htmlspecialchars(addslashes($vol['full_name'])); ?>')">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

        <?php else: ?>
            <!-- ─── Registered Users Table ─────────────────────────────────── -->
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email & Phone</th>
                        <th>Role</th>
                        <th>Town</th>
                        <th>Activity</th>
                        <th>Joined Date</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users_list)): ?>
                    <tr>
                        <td colspan="8" class="td-empty">
                            <div class="empty-state">
                                <div class="ei">👥</div>
                                <h3>No users found</h3>
                                <p>Try adjusting your search keywords or role filters.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users_list as $user):
                        $u_init  = strtoupper(substr($user['full_name'], 0, 1));
                        $is_self = ($user['user_id'] === $current_admin_id);
                        $u_role  = $user['role'];
                        $role_badge = match($u_role) {
                            'admin'     => 'badge-admin',
                            'donor'     => 'b-donor',
                            'recipient' => 'b-recipient',
                            default     => 'b-inactive'
                        };
                        $status_badge = ($user['status'] === 'active') ? 'b-active' : 'b-inactive';
                        $don_count  = (int)($user['total_donations'] ?? 0);
                        $req_count  = (int)($user['total_requests'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="u-avatar-wrap">
                                    <div class="u-ava" style="background:<?php
                                        echo match($u_role) {
                                            'admin'     => '#4527a0',
                                            'recipient' => '#7b1fa2',
                                            default     => '#0277bd',
                                        };
                                    ?>;"><?php echo $u_init; ?></div>
                                    <span class="u-role-icon <?php echo $u_role; ?>" title="<?php echo ucfirst($u_role); ?>">
                                        <?php echo match($u_role) { 'admin' => '★', 'recipient' => '🤲', default => '🎁' }; ?>
                                    </span>
                                </div>
                                <div>
                                    <div class="u-name">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                        <?php if ($is_self): ?>
                                            <span style="font-size:10px; color:var(--primary); font-weight:700;">(You)</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge-id">USR-<?php echo $user['user_id']; ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="u-name" style="font-size: 13px; font-weight: normal;"><?php echo htmlspecialchars($user['email']); ?></div>
                            <div class="dtd-muted"><?php echo htmlspecialchars($user['phone'] ?: '—'); ?></div>
                        </td>

                        <td>
                            <span class="badge <?php echo $role_badge; ?>">
                                <?php echo ucfirst($u_role); ?>
                            </span>
                        </td>

                        <td>
                            <span style="font-size: 13px; font-weight: 500;"><?php echo htmlspecialchars($user['town'] ?: '—'); ?></span>
                        </td>

                        <td>
                            <?php if ($u_role === 'donor'): ?>
                                <span style="font-size: 12px; font-weight: 600; color: #1b5e20;">
                                     <?php echo $don_count; ?> donation<?php echo $don_count !== 1 ? 's' : ''; ?>
                                </span>
                            <?php elseif ($u_role === 'recipient'): ?>
                                <span style="font-size: 12px; font-weight: 600; color: #6a1b9a;">
                                     <?php echo $req_count; ?> request<?php echo $req_count !== 1 ? 's' : ''; ?>
                                </span>
                            <?php else: ?>
                                <span style="font-size: 11.5px; color: var(--muted);">System Admin</span>
                            <?php endif; ?>
                        </td>

                        <td class="dtd-muted">
                            <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                        </td>

                        <td>
                            <span class="badge <?php echo $status_badge; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>

                        <td style="text-align: right;">
                            <div class="action-btn-group" style="justify-content: flex-end;">
                                <!-- View Details -->
                                <button type="button" class="btn-icon-sq" title="View Details"
                                        onclick="openUserDetailsModal(<?php echo htmlspecialchars(json_encode([
                                            'id'         => $user['user_id'],
                                            'name'       => $user['full_name'],
                                            'email'      => $user['email'],
                                            'phone'      => $user['phone'] ?? '',
                                            'town'       => $user['town'] ?? '',
                                            'address'    => $user['address'] ?? '',
                                            'role'       => ucfirst($user['role']),
                                            'status'     => ucfirst($user['status']),
                                            'joined'     => date('d M Y, h:i A', strtotime($user['created_at'])),
                                            'donations'  => $don_count,
                                            'requests'   => $req_count,
                                        ])); ?>)">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>

                                <!-- Change Role Modal Opener -->
                                <?php if (!$is_self): ?>
                                    <button type="button" class="btn-icon-sq" title="Change Role"
                                            onclick="openChangeRoleModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>', '<?php echo $u_role; ?>')">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>

                                <!-- Toggle Active / Inactive -->
                                <?php if (!$is_self): ?>
                                    <form method="POST" style="margin:0;" id="toggleForm_<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">

                                        <?php if ($user['status'] === 'active'): ?>
                                            <button type="button" class="btn-icon-sq btn-toggle-inactive" title="Deactivate User"
                                                    onclick="confirmToggleStatus(<?php echo $user['user_id']; ?>, 'deactivate', '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                </svg>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-icon-sq btn-toggle-active" title="Activate User"
                                                    onclick="confirmToggleStatus(<?php echo $user['user_id']; ?>, 'activate', '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>

                                <!-- Delete User -->
                                <?php if (!$is_self): ?>
                                    <button type="button" class="btn-icon-sq btn-danger-hover" title="Delete User Account"
                                            onclick="confirmDeleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>')">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════════════ MODAL: ADD NEW USER ═══════════════════════ -->
<div class="admin-modal-overlay" id="addUserModal" onclick="closeModalOnBackdrop(event, 'addUserModal')">
    <div class="admin-modal-card">
        <div class="admin-modal-hdr">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--primary);">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
                Create New User Account
            </h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="manage_users.php">
            <input type="hidden" name="action" value="add_user">
            <div class="admin-modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="req">*</span></label>
                    <input type="text" name="fullname" class="form-control" placeholder="e.g. Aarav Sharma" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="aarav@example.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Temporary Password <span class="req">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="98XXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Town / City <span class="req">*</span></label>
                        <input type="text" name="town" class="form-control" placeholder="e.g. Kathmandu, Pokhara" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Detailed Street Address</label>
                    <input type="text" name="address" class="form-control" placeholder="e.g. Ward 4, New Road">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Initial Role <span class="req">*</span></label>
                        <select name="role" class="form-control">
                            <option value="donor">Donor</option>
                            <option value="recipient">Recipient</option>
                            <option value="admin">System Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="admin-modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════ MODAL: USER DETAILS ═══════════════════════ -->
<div class="admin-modal-overlay" id="userDetailsModal" onclick="closeModalOnBackdrop(event, 'userDetailsModal')">
    <div class="admin-modal-card">
        <div class="admin-modal-hdr">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--primary);">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                User Profile Information
            </h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('userDetailsModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="profile-meta-grid">
                <div class="pm-item">
                    <div class="pm-label">User ID</div>
                    <div class="pm-value" id="modalUserId">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Current Role</div>
                    <div class="pm-value" id="modalUserRole">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Full Name</div>
                    <div class="pm-value" id="modalUserName">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Account Status</div>
                    <div class="pm-value" id="modalUserStatus">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Email Address</div>
                    <div class="pm-value" id="modalUserEmail">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Phone Number</div>
                    <div class="pm-value" id="modalUserPhone">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Town / City</div>
                    <div class="pm-value" id="modalUserTown">—</div>
                </div>
                <div class="pm-item">
                    <div class="pm-label">Address</div>
                    <div class="pm-value" id="modalUserAddress">—</div>
                </div>
                <div class="pm-item" style="grid-column: span 2;">
                    <div class="pm-label">Member Since</div>
                    <div class="pm-value" id="modalUserJoined">—</div>
                </div>
            </div>

            <div class="activity-summary-boxes">
                <div class="act-box donations">
                    <span class="num" id="modalUserDonations">0</span>
                    <span class="lbl">Donations Listed</span>
                </div>
                <div class="act-box requests">
                    <span class="num" id="modalUserRequests">0</span>
                    <span class="lbl">Requests Submitted</span>
                </div>
            </div>
        </div>
        <div class="admin-modal-foot">
            <button type="button" class="btn btn-outline" onclick="closeModal('userDetailsModal')">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════ MODAL: CHANGE ROLE ═══════════════════════ -->
<div class="admin-modal-overlay" id="changeRoleModal" onclick="closeModalOnBackdrop(event, 'changeRoleModal')">
    <div class="admin-modal-card" style="max-width: 440px;">
        <div class="admin-modal-hdr">
            <h3>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--primary);">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                </svg>
                Change User Role
            </h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('changeRoleModal')">&times;</button>
        </div>
        <form method="POST" action="manage_users.php">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="user_id" id="roleModalUserId" value="">
            <div class="admin-modal-body">
                <p style="font-size: 13.5px; color: #444; margin-bottom: 14px;">
                    Select the new platform role for <strong id="roleModalUserName">User</strong>:
                </p>
                <div class="form-group">
                    <label class="form-label">Role Selection</label>
                    <select name="new_role" id="roleModalSelect" class="form-control" required>
                        <option value="donor">Donor (Can list and manage physical donations)</option>
                        <option value="recipient">Recipient (Can browse aid and submit requests)</option>
                    </select>
                </div>
            </div>
            <div class="admin-modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeModal('changeRoleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden forms for delete actions triggered by SweetAlert2 -->
<form id="deleteUserForm" method="POST" action="manage_users.php" style="display:none;">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="delete_user_id" value="">
</form>

<form id="deleteVolunteerForm" method="POST" action="manage_users.php" style="display:none;">
    <input type="hidden" name="action" value="delete_volunteer">
    <input type="hidden" name="volunteer_id" id="delete_vol_id" value="">
</form>

<!-- ═══════════════════════ CHART.JS & PAGE SCRIPTS ═══════════════════════ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── 1. Initialize Registration Growth Line Chart ──────────────────────────
    const lineCtx = document.getElementById('usersGrowthLineChart');
    if (lineCtx) {
        const labels     = <?php echo json_encode($chart_labels); ?>;
        const totalData  = <?php echo json_encode($chart_total_data); ?>;
        const donorData  = <?php echo json_encode($chart_donor_data); ?>;
        const recipData  = <?php echo json_encode($chart_recip_data); ?>;
        const volData    = <?php echo json_encode($chart_vol_data); ?>;

        const ctx2d = lineCtx.getContext('2d');

        // Create gradient fill for total users line
        const primaryGradient = ctx2d.createLinearGradient(0, 0, 0, 220);
        primaryGradient.addColorStop(0, 'rgba(46, 125, 50, 0.28)');
        primaryGradient.addColorStop(1, 'rgba(46, 125, 50, 0.00)');

        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total User Signups',
                        data: totalData,
                        borderColor: '#2e7d32',
                        backgroundColor: primaryGradient,
                        fill: true,
                        tension: 0.42,
                        pointBackgroundColor: '#2e7d32',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4.5,
                        pointHoverRadius: 7,
                        borderWidth: 3,
                        order: 1
                    },
                    {
                        label: 'Donors',
                        data: donorData,
                        borderColor: '#0288d1',
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#0288d1',
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        order: 2
                    },
                    {
                        label: 'Recipients',
                        data: recipData,
                        borderColor: '#7b1fa2',
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#7b1fa2',
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        order: 3
                    },
                    {
                        label: 'Volunteer Applications',
                        data: volData,
                        borderColor: '#00796b',
                        backgroundColor: 'transparent',
                        borderDash: [5, 4],
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#00796b',
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        order: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false // We render custom interactive legend pills above
                    },
                    tooltip: {
                        backgroundColor: '#1b5e20',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        titleFont: { size: 12.5, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        boxPadding: 4,
                        callbacks: {
                            label: function(ctx) {
                                return ` ${ctx.dataset.label}: ${ctx.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, family: 'Inter' },
                            color: '#718096'
                        },
                        border: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            font: { size: 11, family: 'Inter' },
                            color: '#718096'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        border: { display: false }
                    }
                }
            }
        });
    }
});

// ── Modal Helpers ─────────────────────────────────────────────────────────────
function openAddUserModal() {
    document.getElementById('addUserModal').classList.add('active');
}

function openUserDetailsModal(data) {
    document.getElementById('modalUserId').innerText = '#USR-' + data.id;
    document.getElementById('modalUserName').innerText = data.name;
    document.getElementById('modalUserEmail').innerText = data.email;
    document.getElementById('modalUserPhone').innerText = data.phone || '—';
    document.getElementById('modalUserTown').innerText = data.town || '—';
    document.getElementById('modalUserAddress').innerText = data.address || '—';
    document.getElementById('modalUserRole').innerText = data.role;
    document.getElementById('modalUserStatus').innerText = data.status;
    document.getElementById('modalUserJoined').innerText = data.joined;
    document.getElementById('modalUserDonations').innerText = data.donations;
    document.getElementById('modalUserRequests').innerText = data.requests;
    document.getElementById('userDetailsModal').classList.add('active');
}

function openChangeRoleModal(userId, userName, currentRole) {
    document.getElementById('roleModalUserId').value = userId;
    document.getElementById('roleModalUserName').innerText = userName;
    document.getElementById('roleModalSelect').value = currentRole;
    document.getElementById('changeRoleModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function closeModalOnBackdrop(event, modalId) {
    if (event.target === document.getElementById(modalId)) {
        closeModal(modalId);
    }
}

// ── SweetAlert Confirmation Prompts ───────────────────────────────────────────
function confirmToggleStatus(userId, actionType, userName) {
    const isAct = (actionType === 'activate');
    Swal.fire({
        title: isAct ? 'Activate User?' : 'Deactivate User?',
        text: `Are you sure you want to ${actionType} account access for "${userName}"?`,
        icon: isAct ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: isAct ? '#2e7d32' : '#e65100',
        cancelButtonColor: '#718096',
        confirmButtonText: isAct ? 'Yes, Activate' : 'Yes, Deactivate'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('toggleForm_' + userId).submit();
        }
    });
}

function confirmDeleteUser(userId, userName) {
    Swal.fire({
        title: 'Delete User Account?',
        text: `Are you sure you want to permanently delete "${userName}"? This cannot be undone!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Yes, Delete Permanently'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserForm').submit();
        }
    });
}

function confirmDeleteVolunteer(volId, volName) {
    Swal.fire({
        title: 'Delete Volunteer Application?',
        text: `Remove volunteer record for "${volName}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_vol_id').value = volId;
            document.getElementById('deleteVolunteerForm').submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
