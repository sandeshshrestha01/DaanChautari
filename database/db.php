<?php
/**
 * Dan Chautari - Database Connection Manager
 * Establishes PDO MySQL connection to the 'dan_chautari' database.
 */

// Config must be loaded first (provides BASE_URL, session, error settings)
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config.php';
}

// ── Database credentials ──────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'DaanChautari');
define('DB_USER', 'root');
define('DB_PASS', 'sandesh');
define('DB_CHARSET', 'utf8mb4');
// ─────────────────────────────────────────────────────────────────────────────

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

} catch (PDOException $e) {
    // Graceful error page — never expose raw errors in production
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Error – Dan Chautari</title>
        <link rel="stylesheet" href="../assets/css/db_error.css">
    </head>
    <body>
        <div class="box">
            <h1>⚠ Database Connection Failed</h1>
            <p>
                Could not connect to the database <code><?php echo htmlspecialchars(DB_NAME); ?></code>
                on <code><?php echo htmlspecialchars(DB_HOST); ?></code>.
            </p>
            <p><strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
            <div class="hint">
                <strong>Quick Fix:</strong><br>
                1. Make sure MySQL / MariaDB is running.<br>
                2. Import <code>database/dan_chautari.sql</code> to create the database.<br>
                3. Check the credentials in <code>database/db.php</code>
                &nbsp;(<code>DB_USER</code> / <code>DB_PASS</code>).
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
