<?php
// ============================================================
// Admin — Manage Medicines
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';

$db = db();
$flash = '';
$flashType = 'success';

// ---- Handle actions ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $name  = trim($_POST['name']  ?? '');
        $color = trim($_POST['color'] ?? '#3b82f6');
        $unit  = trim($_POST['unit']  ?? 'units');

        if ($name === '') {
            $flash = 'Medicine name is required.';
            $flashType = 'error';
        } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $flash = 'Invalid color value.';
            $flashType = 'error';
        } else {
            $db->prepare('INSERT INTO medicines (name, color, unit) VALUES (?, ?, ?)')
               ->execute([$name, $color, $unit ?: 'units']);
            $flash = 'Medicine "' . htmlspecialchars($name) . '" added.';
        }

    } elseif ($act === 'update') {
        $id    = filter_var($_POST['med_id'] ?? null, FILTER_VALIDATE_INT);
        $name  = trim($_POST['name']  ?? '');
        $color = trim($_POST['color'] ?? '#3b82f6');
        $unit  = trim($_POST['unit']  ?? 'units');

        if ($id && $name !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $db->prepare('UPDATE medicines SET name=?, color=?, unit=? WHERE id=?')
               ->execute([$name, $color, $unit ?: 'units', $id]);
            $flash = 'Medicine updated.';
        } else {
            $flash = 'Invalid input.';
            $flashType = 'error';
        }

    } elseif ($act === 'delete') {
        $id = filter_var($_POST['med_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            // Cells using this medicine will have medicine_id set to NULL (FK ON DELETE SET NULL)
            $db->prepare('DELETE FROM medicines WHERE id = ?')->execute([$id]);
            $flash = 'Medicine deleted. Affected cells are now empty.';
        }
    }
}

$medicines = $db->query('SELECT * FROM medicines ORDER BY name')->fetchAll();

$commonUnits = ['units', 'mg', 'mL', 'mcg', 'g', 'IU', 'mmol'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medicines — Syringe Box Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
  <?php include __DIR__ . '/includes/nav.php'; ?>

  <div class="admin-wrap">
    <div class="page-header">
      <div>
        <h1>Medicines</h1>
        <p>Global medicine list — available across all boxes</p>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= $flashType ?>"><?= $flash ?></div>
    <?php endif; ?>

    <!-- Add medicine form -->
    <div class="card">
      <div class="card-header"><h2>Add Medicine</h2></div>
      <form method="POST" action="medicines.php" class="inline-form">
        <input type="hidden" name="act" value="create">
        <div class="inline-form-fields">
          <div class="form-group form-group--flex">
            <label for="newMedName">Name</label>
            <input type="text" id="newMedName" name="name" placeholder="e.g. Insulin" required>
          </div>
          <div class="form-group form-group--flex">
            <label for="newMedUnit">Unit</label>
            <select id="newMedUnit" name="unit">
              <?php foreach ($commonUnits as $u): ?>
                <option value="<?= $u ?>"><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group--color">
            <label for="newMedColor">Color</label>
            <input type="color" id="newMedColor" name="color" value="#3b82f6">
          </div>
          <button type="submit" class="btn-primary btn-add">Add Medicine</button>
        </div>
      </form>
    </div>

    <!-- Medicines table -->
    <?php if (empty($medicines)): ?>
      <div class="card empty-state">No medicines yet. Add one above.</div>
    <?php else: ?>
    <div class="card">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Color</th>
              <th>Name</th>
              <th>Unit</th>
              <th>Added</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($medicines as $med): ?>
            <tr>
              <td>
                <span class="color-swatch" style="background:<?= htmlspecialchars($med['color']) ?>"
                      title="<?= htmlspecialchars($med['color']) ?>"></span>
              </td>
              <td><strong><?= htmlspecialchars($med['name']) ?></strong></td>
              <td><?= htmlspecialchars($med['unit']) ?></td>
              <td class="nowrap"><?= date('M j, Y', strtotime($med['created_at'])) ?></td>
              <td class="action-col">
                <button class="btn-sm btn-outline"
                  onclick="openEditMed(<?= htmlspecialchars(json_encode($med)) ?>)">Edit</button>
                <form method="POST" action="medicines.php" style="display:inline"
                      onsubmit="return confirm('Delete this medicine? Cells using it will become empty.')">
                  <input type="hidden" name="act" value="delete">
                  <input type="hidden" name="med_id" value="<?= $med['id'] ?>">
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

  <!-- Edit Medicine Modal -->
  <div class="modal-overlay" id="editMedOverlay" onclick="closeEditMed(event)" hidden>
    <div class="modal-dialog">
      <div class="modal-dialog-header">
        <h2>Edit Medicine</h2>
        <button onclick="closeEditMedBtn()" class="modal-close">✕</button>
      </div>
      <form method="POST" action="medicines.php">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="med_id" id="editMedId">
        <div class="form-group">
          <label for="editMedName">Name</label>
          <input type="text" id="editMedName" name="name" required>
        </div>
        <div class="form-group">
          <label for="editMedUnit">Unit</label>
          <select id="editMedUnit" name="unit">
            <?php foreach ($commonUnits as $u): ?>
              <option value="<?= $u ?>"><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="editMedColor">Color</label>
          <input type="color" id="editMedColor" name="color">
        </div>
        <div class="form-actions">
          <button type="button" class="btn-secondary" onclick="closeEditMedBtn()">Cancel</button>
          <button type="submit" class="btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/admin.js"></script>
</body>
</html>
