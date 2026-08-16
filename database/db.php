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
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Segoe UI', sans-serif;
                background: #f4f7f4;
                color: #333;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .box {
                background: #fff;
                border-top: 5px solid #c62828;
                border-radius: 10px;
                padding: 48px 40px;
                max-width: 600px;
                width: 100%;
                box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            }
            h1 { color: #c62828; font-size: 22px; margin-bottom: 16px; }
            p  { line-height: 1.7; margin-bottom: 12px; color: #555; font-size: 14px; }
            code {
                background: #f1f1f1;
                padding: 2px 7px;
                border-radius: 4px;
                font-family: Consolas, monospace;
                font-size: 13px;
                color: #c62828;
            }
            .hint {
                margin-top: 20px;
                background: #fff8e1;
                border-left: 4px solid #f9a825;
                padding: 12px 16px;
                border-radius: 0 6px 6px 0;
                font-size: 13px;
                color: #555;
            }
        </style>
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
