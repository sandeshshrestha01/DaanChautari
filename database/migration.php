<?php
/**
 * Dan Chautari — Database Migration Script
 * Run once from CLI or browser to create all tables.
 *
 * Usage (CLI):  php database/migration.php
 * Usage (web):  http://localhost:8000/database/migration.php
 */

require_once __DIR__ . '/config.php';

// ── Connect WITHOUT a specific database first so we can CREATE it ─────────────
$host    = 'localhost';
$user    = 'root';
$pass    = 'sandesh';
$db_name = 'DaanChautari';

try {
    $pdo = new PDO(
        "mysql:host=$host;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db_name`");

} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}

// ── Table definitions ─────────────────────────────────────────────────────────
$tables = [];

// 1. USERS TABLE (Unified — Donors, Recipients & Admins)
$tables['users'] = "
CREATE TABLE IF NOT EXISTS users (
    user_id        INT           AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100)  NOT NULL,
    email          VARCHAR(100)  NOT NULL UNIQUE,
    password       VARCHAR(255)  NOT NULL COMMENT 'bcrypt hashed password',
    phone          VARCHAR(15)   DEFAULT NULL,
    town           VARCHAR(100)  DEFAULT NULL COMMENT 'used for location-based search',
    address        VARCHAR(255)  DEFAULT NULL,
    role           ENUM('donor','recipient','admin') NOT NULL,
    profile_photo  VARCHAR(255)  DEFAULT NULL COMMENT 'profile image file path',
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";  

// 3. RECIPIENTS TABLE
$tables['recipients'] = "
CREATE TABLE IF NOT EXISTS recipients (
    recipient_id   INT           AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL UNIQUE COMMENT 'FK to users table',
    reason         TEXT          DEFAULT NULL  COMMENT 'Why they need donations',
    town           VARCHAR(100)  NOT NULL      COMMENT 'Recipient town for matching',
    address        VARCHAR(255)  DEFAULT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_recipient_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 4. DONATIONS TABLE
$tables['donations'] = "
CREATE TABLE IF NOT EXISTS donations (
    donation_id    INT           AUTO_INCREMENT PRIMARY KEY,
    donor_id       INT           NOT NULL COMMENT 'FK to users table (role=donor)',
    title          VARCHAR(150)  NOT NULL,
    category       ENUM('Food','Clothing','Education','Essential Needs') NOT NULL,
    quantity       INT           NOT NULL DEFAULT 1,
    description    TEXT          DEFAULT NULL,
    town           VARCHAR(100)  NOT NULL COMMENT 'Location of donation item',
    photo          VARCHAR(255)  DEFAULT NULL COMMENT 'Image file path',
    status         ENUM('available','requested','approved','rejected') NOT NULL DEFAULT 'available',
    donated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP COMMENT 'Auto-recorded when donor submits',
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_donation_donor
        FOREIGN KEY (donor_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_category   (category),
    INDEX idx_town       (town),
    INDEX idx_status     (status),
    INDEX idx_donated_at (donated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 5. DONATION REQUESTS TABLE
$tables['donation_requests'] = "
CREATE TABLE IF NOT EXISTS donation_requests (
    request_id     INT           AUTO_INCREMENT PRIMARY KEY,
    donation_id    INT           NOT NULL COMMENT 'FK to donations table',
    recipient_id   INT           NOT NULL COMMENT 'FK to users table (role=recipient)',
    message        TEXT          DEFAULT NULL COMMENT 'Message from recipient',
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    reviewed_at    TIMESTAMP     DEFAULT NULL COMMENT 'When admin approved or rejected',
    reviewed_by    INT           DEFAULT NULL COMMENT 'FK to users table (role=admin)',
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_request_donation
        FOREIGN KEY (donation_id) REFERENCES donations(donation_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_request_recipient
        FOREIGN KEY (recipient_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_request_admin
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX idx_request_status    (status),
    INDEX idx_requested_at      (requested_at),
    INDEX idx_request_donation  (donation_id),
    INDEX idx_request_recipient (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 6. VOLUNTEERS TABLE
$tables['volunteers'] = "
CREATE TABLE IF NOT EXISTS volunteers (
    volunteer_id   INT           AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100)  NOT NULL,
    email          VARCHAR(100)  DEFAULT NULL,
    phone          VARCHAR(15)   NOT NULL,
    town           VARCHAR(100)  NOT NULL,
    address        VARCHAR(255)  DEFAULT NULL,
    skills         VARCHAR(255)  NOT NULL COMMENT 'What skills volunteer offers',
    availability   VARCHAR(100)  NOT NULL COMMENT 'Available days/time',
    status         ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending'
                                 COMMENT 'Admin sets to active after review',
    submitted_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_volunteer_town   (town),
    INDEX idx_volunteer_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// 6. ACTIVITY LOGS TABLE (user_id covers all roles incl. admin)
$tables['activity_logs'] = "
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id         INT           AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           DEFAULT NULL COMMENT 'FK to users — covers all roles including admin',
    action         VARCHAR(255)  NOT NULL,
    module         VARCHAR(100)  NOT NULL,
    reference_id   INT           DEFAULT NULL,
    logged_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX idx_logged_at (logged_at),
    INDEX idx_module    (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── Run migrations ────────────────────────────────────────────────────────────
$results = [];

foreach ($tables as $table_name => $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['table' => $table_name, 'status' => 'ok', 'msg' => 'Created / already exists'];
    } catch (PDOException $e) {
        $results[] = ['table' => $table_name, 'status' => 'error', 'msg' => $e->getMessage()];
    }
}

// ── Seed default admin into users table (only if no admin exists) ────────────
$admin_count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($admin_count === 0) {
    try {
        $hashed = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, password, phone, town, role, status)
            VALUES (?, ?, ?, ?, 'Kathmandu', 'admin', 'active')
        ");
        $stmt->execute(['Super Admin', 'admin@daanchautari.com', $hashed, '9800000000']);
        $results[] = ['table' => 'users (admin seed)', 'status' => 'ok', 'msg' => 'Default admin inserted (email: admin@daanchautari.com, pass: admin123)'];
    } catch (PDOException $e) {
        $results[] = ['table' => 'users (admin seed)', 'status' => 'error', 'msg' => $e->getMessage()];
    }
} else {
    $results[] = ['table' => 'users (admin seed)', 'status' => 'skip', 'msg' => 'Admin user already exists, skipped'];
}

// ── CLI output ────────────────────────────────────────────────────────────────
if (php_sapi_name() === 'cli') {
    echo "\n Dan Chautari — Migration Results\n";
    echo " Database: $db_name\n";
    echo str_repeat('─', 58) . "\n";
    foreach ($results as $r) {
        $icon = $r['status'] === 'ok' ? '✅' : ($r['status'] === 'skip' ? '⏭ ' : '❌');
        printf(" %s  %-22s %s\n", $icon, $r['table'], $r['msg']);
    }
    echo str_repeat('─', 58) . "\n\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration — Dan Chautari</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f7f4;
            color: #333;
            padding: 40px 20px;
        }
        .container {
            max-width: 680px;
            margin: 0 auto;
        }
        h1 {
            font-size: 22px;
            color: #1b5e20;
            margin-bottom: 6px;
        }
        .subtitle {
            font-size: 13px;
            color: #777;
            margin-bottom: 28px;
        }
        .subtitle span {
            font-weight: 600;
            color: #2e7d32;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
        }
        thead tr {
            background: #2e7d32;
            color: #fff;
        }
        th, td {
            padding: 13px 18px;
            text-align: left;
            font-size: 14px;
        }
        tbody tr:nth-child(even) { background: #f9fbf9; }
        tbody tr:hover           { background: #eef7ee; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-ok    { background: #e8f5e9; color: #2e7d32; }
        .badge-error { background: #ffebee; color: #c62828; }
        .badge-skip  { background: #fff8e1; color: #f57f17; }
        .msg { color: #555; font-size: 13px; }
        .footer-note {
            margin-top: 20px;
            font-size: 12px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🌱 Dan Chautari — Migration</h1>
    <p class="subtitle">Database: <span><?php echo htmlspecialchars($db_name); ?></span> &nbsp;|&nbsp; Host: <span><?php echo htmlspecialchars($host); ?></span></p>

    <table>
        <thead>
            <tr>
                <th>Table</th>
                <th>Status</th>
                <th>Message</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['table']); ?></strong></td>
                <td>
                    <?php
                    $cls = $r['status'] === 'ok' ? 'badge-ok' : ($r['status'] === 'skip' ? 'badge-skip' : 'badge-error');
                    $icon = $r['status'] === 'ok' ? '✅ OK' : ($r['status'] === 'skip' ? '⏭ Skipped' : '❌ Error');
                    ?>
                    <span class="badge <?php echo $cls; ?>"><?php echo $icon; ?></span>
                </td>
                <td class="msg"><?php echo htmlspecialchars($r['msg']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="footer-note">Migration complete. You may delete or restrict access to this file before going to production.</p>
</div>
</body>
</html>
