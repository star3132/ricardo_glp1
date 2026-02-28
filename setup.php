<?php
// ============================================================
// First-run setup helper
// Creates/updates the admin account.
// DELETE THIS FILE after setup is complete!
// ============================================================
require_once __DIR__ . '/includes/db.php';

$message = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if ($username === '' || $password === '') {
        $message = 'Username and password are required.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = db()->prepare(
            'INSERT INTO admins (username, password_hash) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
        );
        $stmt->execute([$username, $hash]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup — Syringe Box Manager</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .card { background: #fff; border-radius: 12px; padding: 2rem; width: 100%; max-width: 400px; box-shadow: 0 4px 24px rgba(0,0,0,.1); }
    h1 { margin: 0 0 .25rem; font-size: 1.5rem; }
    p { color: #64748b; margin: 0 0 1.5rem; }
    label { display: block; font-size: .875rem; font-weight: 500; margin-bottom: .25rem; }
    input { width: 100%; box-sizing: border-box; padding: .6rem .75rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 1rem; margin-bottom: 1rem; }
    button { width: 100%; padding: .75rem; background: #3b82f6; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
    .err { background: #fee2e2; color: #b91c1c; padding: .75rem; border-radius: 8px; margin-bottom: 1rem; font-size: .875rem; }
    .success { background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; }
    .warn { background: #fef9c3; border: 1px solid #fde047; color: #713f12; padding: .75rem; border-radius: 8px; margin-bottom: 1rem; font-size: .875rem; }
  </style>
</head>
<body>
  <div class="card">
    <?php if ($done): ?>
      <h1>Setup Complete!</h1>
      <div class="success">
        <p style="margin:0 0 .5rem;">Admin account created successfully.</p>
        <strong>⚠️ Delete this file (setup.php) now for security!</strong>
      </div>
      <br>
      <a href="admin/login.php"><button type="button">Go to Admin Login</button></a>
    <?php else: ?>
      <h1>First-Run Setup</h1>
      <p>Create your admin account</p>
      <div class="warn">⚠️ Delete this file after setup is complete.</div>
      <?php if ($message): ?><div class="err"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="POST">
        <label for="u">Username</label>
        <input type="text" id="u" name="username" value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>" autocomplete="username">
        <label for="p">Password</label>
        <input type="password" id="p" name="password" autocomplete="new-password">
        <label for="c">Confirm Password</label>
        <input type="password" id="c" name="confirm" autocomplete="new-password">
        <button type="submit">Create Admin Account</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
