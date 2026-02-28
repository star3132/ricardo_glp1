<?php
// ============================================================
// Public QR landing page
// URL: /?h=<64-char hash>
// ============================================================
require_once __DIR__ . '/includes/db.php';

// ---- Validate hash -----------------------------------------
$hash = trim($_GET['h'] ?? '');
if (!preg_match('/^[0-9a-f]{64}$/i', $hash)) {
    http_response_code(404);
    die('Box not found.');
}

// ---- Look up box -------------------------------------------
$box = db()->prepare('SELECT * FROM boxes WHERE hash = ? LIMIT 1');
$box->execute([$hash]);
$box = $box->fetch();

if (!$box) {
    http_response_code(404);
    die('Box not found.');
}

// ---- Log access --------------------------------------------
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
$logStmt = db()->prepare('INSERT INTO access_logs (box_id, ip_address, user_agent) VALUES (?, ?, ?)');
$logStmt->execute([$box['id'], $ip, $ua]);

// ---- Fetch all cells for this box --------------------------
$cellStmt = db()->prepare(
    'SELECT c.row_idx, c.col_idx, c.quantity, c.max_quantity,
            m.name AS med_name, m.color AS med_color, m.unit AS med_unit,
            c.medicine_id
     FROM cells c
     LEFT JOIN medicines m ON m.id = c.medicine_id
     WHERE c.box_id = ?'
);
$cellStmt->execute([$box['id']]);

$cellMap = [];
foreach ($cellStmt->fetchAll() as $cell) {
    $cellMap[$cell['row_idx']][$cell['col_idx']] = $cell;
}

// ---- Fetch medicine list for dropdowns ---------------------
$medicines = db()->query('SELECT id, name, color, unit FROM medicines ORDER BY name')->fetchAll();

$rows = (int)$box['rows'];
$cols = (int)$box['cols'];
$boxId = (int)$box['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#1e293b">
  <title><?= htmlspecialchars($box['name']) ?> — Syringe Box</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <!-- Header -->
  <header class="app-header">
    <div class="header-inner">
      <div class="header-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>
        </svg>
      </div>
      <div>
        <h1 class="header-title"><?= htmlspecialchars($box['name']) ?></h1>
        <p class="header-sub"><?= $rows ?>×<?= $cols ?> grid · <?= $rows * $cols ?> cells</p>
      </div>
    </div>
  </header>

  <!-- Grid -->
  <main class="main-content">
    <div class="grid-wrap">
      <div class="box-grid" style="--cols: <?= $cols ?>; --rows: <?= $rows ?>;"
           id="boxGrid">
        <?php for ($r = 0; $r < $rows; $r++): ?>
          <?php for ($c = 0; $c < $cols; $c++): ?>
            <?php
              $cell = $cellMap[$r][$c] ?? null;
              $medName  = $cell ? $cell['med_name']  : null;
              $medColor = $cell ? $cell['med_color']  : null;
              $medUnit  = $cell ? $cell['med_unit']   : null;
              $medId    = $cell ? (int)$cell['medicine_id'] : 0;
              $qty      = $cell ? (int)$cell['quantity']    : 0;
              $maxQty   = $cell ? (int)$cell['max_quantity'] : 10;
              $hasMed   = $medName !== null;
            ?>
            <button
              class="cell <?= $hasMed ? 'cell--filled' : 'cell--empty' ?>"
              style="<?= $hasMed ? '--cell-color:' . htmlspecialchars($medColor) . ';' : '' ?>"
              data-row="<?= $r ?>"
              data-col="<?= $c ?>"
              data-box="<?= $boxId ?>"
              data-med-id="<?= $medId ?>"
              data-qty="<?= $qty ?>"
              data-max="<?= $maxQty ?>"
              data-med-name="<?= htmlspecialchars((string)$medName) ?>"
              data-med-color="<?= htmlspecialchars((string)$medColor) ?>"
              data-med-unit="<?= htmlspecialchars((string)$medUnit) ?>"
              onclick="openCell(this)"
              aria-label="Cell row <?= $r+1 ?>, column <?= $c+1 ?><?= $hasMed ? ': ' . htmlspecialchars($medName) : '' ?>"
            >
              <?php if ($hasMed): ?>
                <span class="cell-dot" style="background:<?= htmlspecialchars($medColor) ?>"></span>
                <span class="cell-name"><?= htmlspecialchars($medName) ?></span>
                <span class="cell-qty"><?= $qty ?><small> <?= htmlspecialchars($medUnit) ?></small></span>
              <?php else: ?>
                <span class="cell-empty-icon">+</span>
              <?php endif; ?>
            </button>
          <?php endfor; ?>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Legend -->
    <?php if (!empty($medicines)): ?>
    <div class="legend">
      <p class="legend-title">Medicines</p>
      <div class="legend-items">
        <?php foreach ($medicines as $med): ?>
          <span class="legend-item">
            <span class="legend-dot" style="background:<?= htmlspecialchars($med['color']) ?>"></span>
            <?= htmlspecialchars($med['name']) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </main>

  <!-- ======================================================
       Cell Edit Modal
       ====================================================== -->
  <div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-handle"></div>

      <div class="modal-header">
        <h2 class="modal-title" id="modalTitle">Edit Cell</h2>
        <button class="modal-close" onclick="closeModalBtn()" aria-label="Close">✕</button>
      </div>

      <!-- Medicine selector -->
      <div class="modal-section">
        <label class="modal-label" for="medSelect">Medicine</label>
        <div class="select-wrap">
          <select id="medSelect" class="med-select" onchange="onMedChange(this)">
            <option value="0">— Empty —</option>
            <?php foreach ($medicines as $med): ?>
              <option value="<?= $med['id'] ?>"
                      data-color="<?= htmlspecialchars($med['color']) ?>"
                      data-unit="<?= htmlspecialchars($med['unit']) ?>">
                <?= htmlspecialchars($med['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="select-badge" id="medBadge"></span>
        </div>
      </div>

      <!-- Quantity controls -->
      <div class="modal-section" id="qtySection">
        <label class="modal-label">Quantity</label>
        <div class="qty-controls">
          <button class="qty-btn qty-btn--minus" id="qtyMinus" onclick="adjustQty(-1)" aria-label="Decrease">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
          </button>
          <div class="qty-display">
            <span class="qty-value" id="qtyValue">0</span>
            <span class="qty-unit" id="qtyUnit"></span>
          </div>
          <button class="qty-btn qty-btn--plus" id="qtyPlus" onclick="adjustQty(1)" aria-label="Increase">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <line x1="12" y1="5" x2="12" y2="19"/>
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
          </button>
        </div>
        <div class="qty-bar-wrap">
          <div class="qty-bar" id="qtyBar">
            <div class="qty-bar-fill" id="qtyBarFill"></div>
          </div>
          <span class="qty-max-label">Max: <span id="qtyMax">10</span></span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="modal-actions">
        <button class="btn btn-secondary" onclick="closeModalBtn()">Cancel</button>
        <button class="btn btn-primary" id="saveBtn" onclick="saveCell()">
          <span id="saveBtnText">Save</span>
          <span id="saveBtnSpinner" class="spinner" hidden></span>
        </button>
      </div>

      <p class="modal-error" id="modalError" hidden></p>
    </div>
  </div>

  <!-- Toast notification -->
  <div class="toast" id="toast" aria-live="polite"></div>

  <!-- Pass medicines data to JS -->
  <script>
    const MEDICINES = <?= json_encode($medicines) ?>;
    const BOX_ID    = <?= $boxId ?>;
  </script>
  <script src="assets/js/app.js"></script>
</body>
</html>
