<?php
// ============================================================
// Admin — Manage Boxes
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/config.php';

$db = db();
$flash = '';
$flashType = 'success';

// ---- Handle actions ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $name = trim($_POST['name'] ?? '');
        $size = $_POST['size'] ?? '3x3';

        if ($name === '') {
            $flash = 'Box name is required.';
            $flashType = 'error';
        } elseif (!preg_match('/^(\d+)x(\d+)$/', $size, $m) || $m[1] < 1 || $m[1] > 10 || $m[2] < 1 || $m[2] > 10) {
            $flash = 'Invalid size.';
            $flashType = 'error';
        } else {
            $rows = (int)$m[1];
            $cols = (int)$m[2];
            $hash = bin2hex(random_bytes(32));

            $db->prepare('INSERT INTO boxes (hash, name, rows, cols) VALUES (?, ?, ?, ?)')
               ->execute([$hash, $name, $rows, $cols]);

            $flash = 'Box "' . htmlspecialchars($name) . '" created successfully.';
        }
    } elseif ($act === 'delete') {
        $id = filter_var($_POST['box_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $db->prepare('DELETE FROM boxes WHERE id = ?')->execute([$id]);
            $flash = 'Box deleted.';
        }
    }
}

// ---- Fetch all boxes with access counts --------------------
$boxes = $db->query(
    'SELECT b.*, COUNT(al.id) AS access_count
     FROM boxes b
     LEFT JOIN access_logs al ON al.box_id = b.id
     GROUP BY b.id
     ORDER BY b.created_at DESC'
)->fetchAll();

$sizes = ['3x3','4x4','5x5','6x6','7x7','8x8','9x9','10x10'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Boxes — Syringe Box Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
  <?php include __DIR__ . '/includes/nav.php'; ?>

  <div class="admin-wrap">
    <div class="page-header">
      <div>
        <h1>Boxes</h1>
        <p>Create and manage your 3D-printed syringe boxes</p>
      </div>
      <button class="btn-primary" onclick="openCreateModal()">+ New Box</button>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flashType ?>"><?= $flash ?></div>
    <?php endif; ?>

    <!-- Box list -->
    <?php if (empty($boxes)): ?>
      <div class="card empty-state">
        No boxes yet. Create your first box to generate a QR code.
      </div>
    <?php else: ?>
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Size</th>
              <th>Hash</th>
              <th>Scans</th>
              <th>Created</th>
              <th>QR Code</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($boxes as $box): ?>
            <tr>
              <td><strong><?= htmlspecialchars($box['name']) ?></strong></td>
              <td><?= $box['rows'] ?>×<?= $box['cols'] ?></td>
              <td class="mono hash-cell" title="<?= htmlspecialchars($box['hash']) ?>">
                <?= substr($box['hash'], 0, 12) ?>…
              </td>
              <td><?= number_format($box['access_count']) ?></td>
              <td class="nowrap"><?= date('M j, Y', strtotime($box['created_at'])) ?></td>
              <td>
                <button class="btn-sm btn-outline"
                  onclick="showQR(<?= htmlspecialchars(json_encode($box['hash'])) ?>, <?= htmlspecialchars(json_encode($box['name'])) ?>)">
                  View QR
                </button>
              </td>
              <td>
                <form method="POST" action="boxes.php" onsubmit="return confirm('Delete this box and all its data?')">
                  <input type="hidden" name="act" value="delete">
                  <input type="hidden" name="box_id" value="<?= $box['id'] ?>">
                  <button type="submit" class="btn-sm btn-danger">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Create Box Modal -->
  <div class="modal-overlay" id="createModalOverlay" onclick="closeCreateModal(event)" hidden>
    <div class="modal-dialog">
      <div class="modal-dialog-header">
        <h2>Create New Box</h2>
        <button onclick="closeCreateModalBtn()" class="modal-close">✕</button>
      </div>
      <form method="POST" action="boxes.php">
        <input type="hidden" name="act" value="create">
        <div class="form-group">
          <label for="newName">Box Name</label>
          <input type="text" id="newName" name="name" placeholder="e.g. Master Bedroom Box" required>
        </div>
        <div class="form-group">
          <label for="newSize">Grid Size</label>
          <select id="newSize" name="size">
            <?php foreach ($sizes as $s): ?>
              <option value="<?= $s ?>"><?= $s ?> (<?= explode('x',$s)[0]*explode('x',$s)[1] ?> cells)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeCreateModalBtn()">Cancel</button>
          <button type="submit" class="btn-primary">Create Box</button>
        </div>
      </form>
    </div>
  </div>

  <!-- QR Code Modal -->
  <div class="modal-overlay" id="qrModalOverlay" onclick="closeQRModal(event)" hidden>
    <div class="modal-dialog modal-dialog--qr">
      <div class="modal-dialog-header">
        <h2 id="qrModalTitle">QR Code</h2>
        <button onclick="closeQRModalBtn()" class="modal-close">✕</button>
      </div>
      <div class="qr-body">
        <div id="qrcode"></div>
        <p class="qr-url" id="qrUrl"></p>
        <div class="qr-actions">
          <button class="btn-secondary" onclick="copyQRUrl()">Copy URL</button>
          <button class="btn-primary" onclick="downloadQR()">Download PNG</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const APP_URL = <?= json_encode(rtrim(APP_URL, '/')) ?>;
  </script>
  <!-- QR code library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
          integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSE1QMoBucfTcKDe4As5afAprVLas/tCF5rJQ=="
          crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
