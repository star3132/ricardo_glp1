<nav class="admin-nav">
  <div class="nav-brand">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="18" height="18" rx="2"/>
      <path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>
    </svg>
    <span>SyringeBox</span>
  </div>
  <div class="nav-links">
    <a href="dashboard.php"  class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php'  ? 'active' : '' ?>">Dashboard</a>
    <a href="boxes.php"      class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'boxes.php'      ? 'active' : '' ?>">Boxes</a>
    <a href="medicines.php"  class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'medicines.php'  ? 'active' : '' ?>">Medicines</a>
  </div>
  <div class="nav-right">
    <span class="nav-user"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?></span>
    <a href="logout.php" class="nav-logout">Sign Out</a>
  </div>
</nav>
