<?php
/**
 * EduConnect — Connexion Base de Données
 * Inclure en tête de chaque page protégée APRÈS session_check.php
 * Usage : require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // Changer en production
define('DB_NAME', 'educonnect');
define('DB_PORT', 3306);         // TOUJOURS 3306 avec XAMPP

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    error_log('[EduConnect] Connexion BDD échouée : ' . mysqli_connect_error());
    http_response_code(503);
    // En prod : rediriger vers page erreur
    // header('Location: /Erreur/503.php'); exit;
    die(json_encode(['error' => 'Service indisponible']));
}

mysqli_set_charset($conn, 'utf8mb4');

/**
 * Requête préparée — helper sécurisé
 * Exemple : $rows = db_query($conn, "SELECT * FROM users WHERE email=? AND role=?", "ss", [$email, $role]);
 */
function db_query(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('[EduConnect] db_query prepare error: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
        return [];
    }
    if ($types && $params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) return [];
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Requête préparée — retourne 1 seule ligne
 */
function db_row(mysqli $conn, string $sql, string $types = '', array $params = []): ?array {
    $rows = db_query($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * INSERT/UPDATE/DELETE — retourne le nb de lignes affectées ou l'ID inséré
 */
function db_exec(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('[EduConnect] db_exec prepare error: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
        return 0;
    }
    if ($types && $params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    $insert_id = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    return $insert_id ?: $affected;
}

/**
 * Sécuriser l'output HTML — toujours utiliser sur les données BDD
 */
function h(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
