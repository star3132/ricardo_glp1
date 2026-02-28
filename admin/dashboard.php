<?php
// ============================================================
// Admin Dashboard
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

$db = db();

// ---- Stats -------------------------------------------------
$totalBoxes   = (int)$db->query('SELECT COUNT(*) FROM boxes')->fetchColumn();
$totalMeds    = (int)$db->query('SELECT COUNT(*) FROM medicines')->fetchColumn();
$totalAccess  = (int)$db->query('SELECT COUNT(*) FROM access_logs')->fetchColumn();
$accessToday  = (int)$db->query(
    "SELECT COUNT(*) FROM access_logs WHERE accessed_at >= CURDATE()"
)->fetchColumn();
$accessWeek   = (int)$db->query(
    "SELECT COUNT(*) FROM access_logs WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetchColumn();

// ---- Recent access log -------------------------------------
$recentLogs = $db->query(
    'SELECT al.accessed_at, al.ip_address, al.user_agent, b.name AS box_name, b.rows, b.cols
     FROM access_logs al
     JOIN boxes b ON b.id = al.box_id
     ORDER BY al.accessed_at DESC
     LIMIT 25'
)->fetchAll();

// ---- Most accessed boxes -----------------------------------
$topBoxes = $db->query(
    'SELECT b.name, b.rows, b.cols, COUNT(al.id) AS hits
     FROM boxes b
     LEFT JOIN access_logs al ON al.box_id = b.id
     GROUP BY b.id
     ORDER BY hits DESC
     LIMIT 5'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Syringe Box Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
  <?php include __DIR__ . '/includes/nav.php'; ?>

  <div class="admin-wrap">
    <div class="page-header">
      <h1>Dashboard</h1>
      <p>Overview of your syringe box system</p>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon stat-icon--blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>
          </svg>
        </div>
        <div>
          <div class="stat-value"><?= $totalBoxes ?></div>
          <div class="stat-label">Total Boxes</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon--green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/>
            <polyline points="9 3 9 9 15 9"/>
            <line x1="12" y1="12" x2="12" y2="18"/>
            <line x1="9" y1="15" x2="15" y2="15"/>
          </svg>
        </div>
        <div>
          <div class="stat-value"><?= $totalMeds ?></div>
          <div class="stat-label">Medicines</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon--purple">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div>
          <div class="stat-value"><?= number_format($totalAccess) ?></div>
          <div class="stat-label">Total Scans</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon--orange">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>
        <div>
          <div class="stat-value"><?= $accessToday ?></div>
          <div class="stat-label">Today's Scans</div>
          <div class="stat-sub"><?= $accessWeek ?> this week</div>
        </div>
      </div>
    </div>

    <div class="dashboard-cols">
      <!-- Recent access log -->
      <div class="card">
        <div class="card-header">
          <h2>Recent Scans</h2>
        </div>
        <?php if (empty($recentLogs)): ?>
          <div class="empty-state">No scans yet. Share a QR code to get started.</div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Box</th>
                <th>Size</th>
                <th>IP Address</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentLogs as $log): ?>
              <tr>
                <td><?= htmlspecialchars($log['box_name']) ?></td>
                <td><?= $log['rows'] ?>×<?= $log['cols'] ?></td>
                <td class="mono"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                <td class="nowrap"><?= htmlspecialchars(date('M j, g:i a', strtotime($log['accessed_at']))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Top boxes -->
      <div class="card">
        <div class="card-header">
          <h2>Most Scanned Boxes</h2>
        </div>
        <?php if (empty($topBoxes)): ?>
          <div class="empty-state">No data yet.</div>
        <?php else: ?>
        <ul class="top-list">
          <?php foreach ($topBoxes as $i => $b): ?>
          <li class="top-item">
            <span class="top-rank"><?= $i + 1 ?></span>
            <div class="top-info">
              <span class="top-name"><?= htmlspecialchars($b['name']) ?></span>
              <span class="top-meta"><?= $b['rows'] ?>×<?= $b['cols'] ?></span>
            </div>
            <span class="top-count"><?= number_format($b['hits']) ?> scans</span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
</body>
</html>
