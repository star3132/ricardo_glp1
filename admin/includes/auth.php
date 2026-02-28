<?php
// ============================================================
// Admin session guard — require_once this at the top of every
// admin page (after session_start())
// ============================================================
require_once dirname(__DIR__, 2) . '/config.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/admin') . '/admin/login.php');
    exit;
}

// Regenerate session ID periodically to reduce fixation risk
if (empty($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 300) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}
