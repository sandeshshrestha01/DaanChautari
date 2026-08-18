<?php
/**
 * Daan Chautari — Admin Dashboard
 * Overview stats, recent donations table, donation requests, quick actions, recent activity.
 */

$page_title = 'Dashboard';
require_once __DIR__ . '/admin_header.php';

// ── Fetch Stats ───────────────────────────────────────────────────────────────
try {
    $total_donations = (int)$pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();
    $pending_req     = (int)$pdo->query("SELECT COUNT(*) FROM donation_requests WHERE status = 'pending'")->fetchColumn();
    $total_users     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $active_vols     = (int)$pdo->query("SELECT COUNT(*) FROM volunteers WHERE status = 'active'")->fetchColumn();

    // Recent donations (last 5)
    $recent_donations = $pdo->query("
        SELECT d.donation_id, d.title, d.status, d.donated_at,
               u.full_name AS donor_name, u.town AS donor_town
        FROM donations d
        JOIN users u ON d.donor_id = u.user_id
        ORDER BY d.donated_at DESC
        LIMIT 5
    ")->fetchAll();

    // Donation requests awaiting response (pending, limit 5)
    $pending_requests = $pdo->query("
        SELECT dr.request_id, dr.status, dr.requested_at,
               d.title AS item_requested,
               donor.full_name AS donor_name,
               recip.full_name AS requester_name
        FROM donation_requests dr
        JOIN donations d ON dr.donation_id = d.donation_id
        JOIN users recip ON dr.recipient_id = recip.user_id
        JOIN users donor ON d.donor_id = donor.user_id
        ORDER BY dr.requested_at DESC
        LIMIT 5
    ")->fetchAll();

    // Chart: donations per day for last 7 days (line chart)
    $chart_days = $pdo->query("
        SELECT DATE(donated_at) as day, COUNT(*) as cnt
        FROM donations
        WHERE donated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(donated_at)
        ORDER BY day ASC
    ")->fetchAll();

    // Build chart data with all 7 days filled in
    $chart_labels = [];
    $chart_values = [];
    $day_map = [];
    foreach ($chart_days as $r) $day_map[$r['day']] = (int)$r['cnt'];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('M d', strtotime($d));
        $chart_values[] = $day_map[$d] ?? 0;
    }

    // Chart: request status breakdown (pie chart)
    $req_status_rows = $pdo->query("
        SELECT status, COUNT(*) as cnt FROM donation_requests GROUP BY status
    ")->fetchAll();
    $req_status_map = [];
    foreach ($req_status_rows as $r) $req_status_map[$r['status']] = (int)$r['cnt'];

    // Recent activity log
    $activity_logs = [];
    try {
        $activity_logs = $pdo->query("
            SELECT al.action, al.created_at, u.full_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.user_id
            ORDER BY al.created_at DESC
            LIMIT 4
        ")->fetchAll();
    } catch (PDOException $e) { /* activity_logs table may not exist */ }

} catch (PDOException $e) {
    error_log("Admin Dashboard DB Error: " . $e->getMessage());
    $total_donations = $pending_req = $total_users = $active_vols = 0;
    $recent_donations = $pending_requests = $activity_logs = [];
    $chart_labels = $chart_values = [];
    $req_status_map = [];
}

// Handle request status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    $req_id    = (int)($_POST['request_id'] ?? 0);
    $newstatus = in_array($_POST['new_status'] ?? '', ['approved','rejected','pending'])
                 ? $_POST['new_status'] : 'pending';
    try {
        $pdo->prepare("UPDATE donation_requests SET status = :s, reviewed_at = NOW(), reviewed_by = :a WHERE request_id = :r")
            ->execute(['s' => $newstatus, 'a' => $_SESSION['user_id'], 'r' => $req_id]);
        if ($newstatus === 'approved') {
            $pdo->prepare("UPDATE donations d JOIN donation_requests dr ON d.donation_id = dr.donation_id SET d.status = 'approved' WHERE dr.request_id = :r")
                ->execute(['r' => $req_id]);
        }
        set_flash_message('success', "Request #REQ-$req_id updated to " . strtoupper($newstatus));
    } catch (PDOException $e) {
        set_flash_message('error', 'Could not update request status.');
    }
    header("Location: dashboard.php");
    exit;
}
?>

<!-- ── Dashboard Header ────────────────────────────────────── -->
<div class="dash-header">
    <div>
        <h2 class="dash-title">Dashboard</h2>
        <p class="dash-sub">Overview of donations, requests and community activity</p>
    </div>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────── -->
<div class="dash-stats">
    <div class="dstat-card dstat-green">
        <div class="dstat-icon">🎁</div>
        <div class="dstat-body">
            <div class="dstat-num"><?php echo number_format($total_donations); ?></div>
            <div class="dstat-label">Total Donations</div>
        </div>
    </div>
    <div class="dstat-card dstat-orange">
        <div class="dstat-icon">🔔</div>
        <div class="dstat-body">
            <div class="dstat-num"><?php echo number_format($pending_req); ?></div>
            <div class="dstat-label">Pending Requests</div>
        </div>
    </div>
    <div class="dstat-card dstat-blue">
        <div class="dstat-icon"><i class="fa-regular fa-user"></i></div>
        <div class="dstat-body">
            <div class="dstat-num"><?php echo number_format($total_users); ?></div>
            <div class="dstat-label">Total Users</div>
        </div>
    </div>
    <div class="dstat-card dstat-purple">
        <div class="dstat-icon"><i class="fa-solid fa-hand-holding-heart"></i></div>
        <div class="dstat-body">
            <div class="dstat-num"><?php echo number_format($active_vols); ?></div>
            <div class="dstat-label">Volunteers</div>
        </div>
    </div>
</div>

<!-- ── Main Content Grid ───────────────────────────────────── -->
<div class="dash-grid">

    <!-- Left Column -->
    <div class="dash-left">

        <!-- Recent Donations Table + Line Chart -->
        <div class="dash-panel">
            <div class="dpanel-hdr">
                <div>
                    <h3 class="dpanel-title">Recent Donations</h3>
                </div>
                <a href="manage_donations.php" class="dview-all">View all →</a>
            </div>

            <!-- Line Chart -->
            <div class="chart-wrap">
                <canvas id="donationsLineChart" height="120"></canvas>
            </div>

            <!-- Table -->
            <div class="dtbl-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Donor</th>
                            <th>Town</th>
                            <th>Status</th>
                            <th>Posted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recent_donations)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:28px;">No donations yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_donations as $d):
                            $s = $d['status'];
                            $badge = match($s) {
                                'available'  => 'db-available',
                                'approved'   => 'db-approved',
                                'pending'    => 'db-pending',
                                'requested'  => 'db-requested',
                                default      => 'db-muted',
                            };
                        ?>
                        <tr>
                            <td class="dtd-bold"><?php echo htmlspecialchars($d['title']); ?></td>
                            <td><?php echo htmlspecialchars($d['donor_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['donor_town'] ?? '—'); ?></td>
                            <td><span class="dbadge <?php echo $badge; ?>"><?php echo ucfirst($s); ?></span></td>
                            <td class="dtd-muted"><?php echo date('d M Y', strtotime($d['donated_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Donation Requests Awaiting Response + Pie Chart -->
        <div class="dash-panel" style="margin-top:22px;">
            <div class="dpanel-hdr">
                <div>
                    <h3 class="dpanel-title">Donation Requests Awaiting Response</h3>
                </div>
                <a href="manage_donations.php" class="dview-all">View all →</a>
            </div>

            <!-- Pie Chart -->
            <div class="chart-wrap chart-pie-wrap">
                <div class="pie-chart-container">
                    <canvas id="requestsPieChart"></canvas>
                </div>
                <div class="pie-legend" id="pieLegend"></div>
            </div>

            <!-- Table -->
            <div class="dtbl-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Requester</th>
                            <th>Item Requested</th>
                            <th>Donor</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($pending_requests)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:28px;">No pending requests.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $req):
                            $rs = $req['status'];
                            $rbadge = match($rs) {
                                'pending'  => 'db-pending',
                                'approved' => 'db-approved',
                                'rejected' => 'db-rejected',
                                default    => 'db-muted',
                            };
                            $initial = strtoupper(substr($req['requester_name'], 0, 1));
                        ?>
                        <tr>
                            <td>
                                <div class="dreq-user">
                                    <div class="dreq-ava"><?php echo $initial; ?></div>
                                    <span><?php echo htmlspecialchars($req['requester_name']); ?></span>
                                </div>
                            </td>
                            <td class="dtd-bold"><?php echo htmlspecialchars($req['item_requested']); ?></td>
                            <td><?php echo htmlspecialchars($req['donor_name']); ?></td>
                            <td><span class="dbadge <?php echo $rbadge; ?>"><?php echo ucfirst($rs); ?></span></td>
                            <td>
                                <?php if ($rs === 'pending'): ?>
                                <div class="dreq-actions">
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="update_request" value="1">
                                        <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                        <input type="hidden" name="new_status" value="approved">
                                        <button type="submit" class="dact-btn dact-approve">Approve</button>
                                    </form>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Reject this request?')">
                                        <input type="hidden" name="update_request" value="1">
                                        <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                        <input type="hidden" name="new_status" value="rejected">
                                        <button type="submit" class="dact-btn dact-reject">Reject</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span style="font-size:12px;color:var(--muted);">Reviewed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.dash-left -->

    <!-- Right Column -->
    <div class="dash-right">

        <!-- Quick Actions -->
        <div class="dash-panel">
            <div class="dpanel-hdr">
                <h3 class="dpanel-title">Quick Actions</h3>
            </div>
            <div class="dquick-list">
                <a href="manage_donations.php" class="dquick-item">
                    <span class="dq-icon dq-green">🎁</span>
                    <span>Review Pending Donations</span>
                    <span class="dq-arrow">›</span>
                </a>
                <a href="manage_donations.php" class="dquick-item">
                    <span class="dq-icon dq-blue">💬</span>
                    <span>Respond to Requests</span>
                    <span class="dq-arrow">›</span>
                </a>
                <a href="manage_users.php" class="dquick-item">
                    <span class="dq-icon dq-teal">👥</span>
                    <span>Approve New Volunteers</span>
                    <span class="dq-arrow">›</span>
                </a>
                <a href="manage_donations.php" class="dquick-item">
                    <span class="dq-icon dq-orange">📋</span>
                    <span>Manage Donation Listings</span>
                    <span class="dq-arrow">›</span>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="dash-panel" style="margin-top:22px;">
            <div class="dpanel-hdr">
                <h3 class="dpanel-title">Recent Activity</h3>
            </div>
            <div class="dactivity-list">
                <?php if (!empty($activity_logs)): ?>
                    <?php foreach ($activity_logs as $log): ?>
                    <div class="dact-item">
                        <div class="dact-dot"></div>
                        <div class="dact-text">
                            <span class="dact-name"><?php echo htmlspecialchars($log['full_name'] ?? 'Someone'); ?></span>
                            <?php echo htmlspecialchars($log['action']); ?>
                        </div>
                        <div class="dact-time"><?php echo date('M d', strtotime($log['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php
                    // Fallback: build activity from recent data
                    $fallback = [];
                    foreach (array_slice($recent_donations, 0, 2) as $don) {
                        $fallback[] = [
                            'color' => '#f57c00',
                            'text'  => '<strong>' . htmlspecialchars($don['donor_name']) . '</strong> posted a new donation',
                            'time'  => date('M d', strtotime($don['donated_at'])),
                        ];
                    }
                    foreach (array_slice($pending_requests, 0, 2) as $req) {
                        $fallback[] = [
                            'color' => '#2e7d32',
                            'text'  => '<strong>' . htmlspecialchars($req['requester_name']) . '</strong> requested ' . htmlspecialchars($req['item_requested']),
                            'time'  => date('M d', strtotime($req['requested_at'])),
                        ];
                    }
                    foreach (array_slice($fallback, 0, 4) as $fb): ?>
                    <div class="dact-item">
                        <div class="dact-dot" style="background:<?php echo $fb['color']; ?>;"></div>
                        <div class="dact-text"><?php echo $fb['text']; ?></div>
                        <div class="dact-time"><?php echo $fb['time']; ?></div>
                    </div>
                    <?php endforeach;
                    if (empty($fallback)): ?>
                        <p style="color:var(--muted);font-size:12px;padding:16px 0;">No recent activity.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.dash-right -->

</div><!-- /.dash-grid -->

<!-- ── Chart.js Scripts ────────────────────────────────────── -->
<script>
(function() {
    // ── Line Chart: Donations over last 7 days ─────────────
    const lineCtx = document.getElementById('donationsLineChart');
    if (lineCtx) {
        const labels = <?php echo json_encode($chart_labels); ?>;
        const values = <?php echo json_encode($chart_values); ?>;

        new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Donations',
                    data: values,
                    borderColor: '#2e7d32',
                    backgroundColor: 'rgba(46,125,50,0.10)',
                    tension: 0.45,
                    fill: true,
                    pointBackgroundColor: '#2e7d32',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2.5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1b5e20',
                        titleFont: { size: 12 },
                        bodyFont: { size: 12 },
                        padding: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} donation${ctx.parsed.y !== 1 ? 's' : ''}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, color: '#8a8a8a' },
                        border: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: { size: 11 },
                            color: '#8a8a8a',
                            precision: 0
                        },
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        border: { display: false }
                    }
                }
            }
        });
    }

    // ── Pie Chart: Request Status Breakdown ────────────────
    const pieCtx = document.getElementById('requestsPieChart');
    if (pieCtx) {
        const statusData = <?php echo json_encode([
            'Pending'  => $req_status_map['pending']  ?? 0,
            'Approved' => $req_status_map['approved'] ?? 0,
            'Rejected' => $req_status_map['rejected'] ?? 0,
        ]); ?>;

        const statusColors = {
            'Pending':  '#f57c00',
            'Approved': '#2e7d32',
            'Rejected': '#c62828',
        };

        const labels = Object.keys(statusData);
        const values = Object.values(statusData);
        const colors = labels.map(l => statusColors[l]);

        const total = values.reduce((a, b) => a + b, 0);

        const pie = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values.length && total > 0 ? values : [1],
                    backgroundColor: total > 0 ? colors : ['#e0e0e0'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: total > 0,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed} request${ctx.parsed !== 1 ? 's' : ''}`
                        }
                    }
                }
            }
        });

        // Custom legend
        const legendEl = document.getElementById('pieLegend');
        if (legendEl && total > 0) {
            legendEl.innerHTML = labels.map((label, i) => `
                <div class="pie-legend-item">
                    <span class="pie-dot" style="background:${colors[i]}"></span>
                    <span class="pie-label-text">${label}</span>
                    <span class="pie-count">${values[i]}</span>
                </div>
            `).join('');
        } else if (legendEl) {
            legendEl.innerHTML = '<p style="color:#aaa;font-size:12px;">No data yet</p>';
        }
    }
})();
</script>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
