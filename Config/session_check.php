<?php
/**
 * EduConnect — Vérification Session + Timeout
 * À inclure EN PREMIÈRE LIGNE de chaque page protégée
 *
 * Usage :
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
 *   session_role_check('etudiant'); // ou 'enseignant', 'admin', etc.
 *
 * Variables disponibles après inclusion :
 *   $_SESSION['user_id']    → INT
 *   $_SESSION['role']       → STRING
 *   $_SESSION['nom']        → STRING
 *   $_SESSION['prenom']     → STRING
 *   $_SESSION['email']      → STRING
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SESSION_TIMEOUT', 900); // 15 minutes

// ── Timeout inactivité ──────────────────────────────────────
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: /Views/Auth/login.php?timeout=1&next=' . $redirect);
        exit;
    }
}
$_SESSION['last_activity'] = time();

// ── Non connecté ─────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: /Views/Auth/login.php?next=' . $redirect);
    exit;
}

/**
 * Vérifie le rôle — appeler après inclusion
 * @param string|array $roles  Rôle(s) autorisé(s) ex: 'etudiant' ou ['admin','chef_admin']
 */
function session_role_check(string|array $roles): void {
    $allowed = (array)$roles;
    if (!in_array($_SESSION['role'] ?? '', $allowed, true)) {
        http_response_code(403);
        header('Location: /Erreur/403.php');
        exit;
    }
}

/**
 * Retourne les données de l'utilisateur connecté (depuis $_SESSION)
 */
function current_user(): array {
    return [
        'id'     => $_SESSION['user_id']  ?? 0,
        'role'   => $_SESSION['role']     ?? '',
        'nom'    => $_SESSION['nom']      ?? '',
        'prenom' => $_SESSION['prenom']   ?? '',
        'email'  => $_SESSION['email']    ?? '',
        'numero' => $_SESSION['numero_ucao'] ?? '',
        'initiales' => strtoupper(
            substr($_SESSION['prenom'] ?? 'U', 0, 1) .
            substr($_SESSION['nom']    ?? 'U', 0, 1)
        ),
    ];
}
