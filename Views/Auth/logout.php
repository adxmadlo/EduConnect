<?php
/**
 * EduConnect — Logout
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id   = $_SESSION['user_id']  ?? null;
$user_role = $_SESSION['role']     ?? 'unknown';

if ($user_id) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
    db_exec($conn,
        "INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, 'LOGOUT', ?)",
        'is', [(int)$user_id, $_SERVER['REMOTE_ADDR'] ?? '']
    );
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php?logout=success');
exit;
