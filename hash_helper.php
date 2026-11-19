<?php
/**
 * EduConnect — Générateur de hash bcrypt
 * Outil de démo uniquement — SUPPRIMER en production
 * Usage : http://localhost/educonnect/hash_helper.php
 */

// Bloquer en production
if (isset($_SERVER['HTTP_HOST']) && !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
    http_response_code(403); die('Accès refusé en production.');
}

$password = trim($_POST['password'] ?? 'EduConnect2026!');
$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $password) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Hash Helper — EduConnect</title>
<style>
body{font-family:monospace;padding:40px;background:#1a1a2e;color:#a8c8e8}
input{padding:10px;width:400px;font-size:16px;border-radius:6px;border:1px solid #326ea9;background:#0d1117;color:#fff;font-family:monospace}
button{padding:10px 20px;background:#326ea9;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;margin-left:8px}
.hash{background:#0d1117;border:1px solid #27ae60;padding:14px;border-radius:6px;margin-top:16px;word-break:break-all;color:#2ecc71;font-size:14px}
.sql-tip{margin-top:20px;color:#9ca3af;font-size:12px}
</style>
</head>
<body>
<h2>🔑 Générateur Hash Bcrypt — EduConnect</h2>
<p>⚠️ Outil démo uniquement — supprimer avant mise en production</p>
<form method="POST">
    <input type="text" name="password" value="<?= htmlspecialchars($password, ENT_QUOTES) ?>" placeholder="Mot de passe...">
    <button type="submit">Générer</button>
</form>
<?php if ($hash): ?>
<div class="hash"><?= htmlspecialchars($hash) ?></div>
<div class="sql-tip">
    Remplacer dans educonnect_final.sql :<br>
    <code>sed 's/\$2y\$12\$HASH_[A-Z0-9]*/<?= htmlspecialchars(addslashes($hash)) ?>/g' educonnect_final.sql > educonnect_ready.sql</code>
</div>
<?php endif; ?>
</body>
</html>
