<?php
/**
 * EduConnect — Inscription (étudiants uniquement via ce formulaire)
 * Les enseignants et admins sont créés par l'administration.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom    = trim($_POST['prenom']    ?? '');
    $nom       = trim($_POST['nom']       ?? '');
    $email     = trim($_POST['email']     ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $password  = trim($_POST['password']  ?? '');
    $confirm   = trim($_POST['confirm']   ?? '');

    if (!$prenom || !$nom || !$email || !$password) {
        $error = 'Tous les champs obligatoires doivent être remplis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // Vérifier si l'email existe déjà
        $existing = db_row($conn, "SELECT id FROM users WHERE email = ? LIMIT 1", 's', [$email]);
        if ($existing) {
            $error = 'Cette adresse email est déjà utilisée.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            // Générer numero_ucao automatique
            $year  = date('Y');
            $count = db_row($conn, "SELECT COUNT(*) AS nb FROM users WHERE role='etudiant'", '', []);
            $num   = str_pad(($count['nb'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
            $numero_ucao = "UCAO-{$year}-{$num}";

            $new_id = db_exec($conn,
                "INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut)
                 VALUES (?, ?, ?, ?, ?, ?, 'etudiant', 'en_attente')",
                'ssssss', [$numero_ucao, $nom, $prenom, $email, $telephone, $hash]
            );

            if ($new_id) {
                // Créer préférences par défaut
                db_exec($conn,
                    "INSERT INTO user_preferences (user_id) VALUES (?)",
                    'i', [$new_id]
                );
                $success = "Compte créé avec succès ! Votre matricule est <strong>{$numero_ucao}</strong>. L'administration validera votre compte sous 24h.";
            } else {
                $error = 'Erreur lors de la création du compte. Réessayez.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="../../Assets/css/login.css">
<title>Inscription | EduConnect</title>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
</head>
<body>
<nav>
    <a href="../../index.php" class="logo"><i class='bx bxs-graduation'></i>Edu<span>Connect</span></a>
    <div class="links">
        <a href="../../index.php">Accueil</a><a href="../../services.php">Services</a>
        <a href="../../apropos.php">À Propos</a><a href="../../contact.php">Contact</a>
    </div>
    <div class="login">
        <button class="signup"><a href="login.php">Connexion</a></button>
        <button><a href="register.php">S'inscrire</a></button>
    </div>
</nav>
<div class="auth-wrapper">
<div class="container active" id="container">
    <!-- Formulaire inscription étendu -->
    <div class="form-container sign-up" style="width:100%;transform:none">
        <form action="register.php" method="POST">
            <div class="brand-logo"><i class='bx bxs-graduation'></i> EduConnect</div>
            <h1>Créer un compte étudiant</h1>
            <p class="subtitle">Remplissez le formulaire — l'administration validera sous 24h</p>

            <?php if ($error): ?>
            <div style="background:#fde8ec;border:1px solid #f5a0b0;color:#C41E3A;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <i class='bx bx-error-circle'></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div style="background:#e8f5e9;border:1px solid #a5d6a7;color:#1b5e20;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:12px">
                <i class='bx bx-check-circle'></i> <?= $success ?>
                <br><br><a href="login.php" style="color:#0891b2;font-weight:600">→ Aller à la connexion</a>
            </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <div class="input-group">
                <i class='bx bx-user'></i>
                <input type="text" name="prenom" placeholder="Prénom *"
                       value="<?= htmlspecialchars($_POST['prenom'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="input-group">
                <i class='bx bx-user'></i>
                <input type="text" name="nom" placeholder="Nom de famille *"
                       value="<?= htmlspecialchars($_POST['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="input-group">
                <i class='bx bx-envelope'></i>
                <input type="email" name="email" placeholder="Email UCAO (ex: prenom.nom@etud.ucao.sn) *"
                       value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="input-group">
                <i class='bx bx-phone'></i>
                <input type="tel" name="telephone" placeholder="Téléphone (+221...)"
                       value="<?= htmlspecialchars($_POST['telephone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="input-group">
                <i class='bx bx-lock-alt'></i>
                <input type="password" name="password" placeholder="Mot de passe (min. 8 caractères) *" required>
            </div>
            <div class="input-group">
                <i class='bx bx-lock-open-alt'></i>
                <input type="password" name="confirm" placeholder="Confirmer le mot de passe *" required>
            </div>
            <button type="submit" class="btn-auth"><i class='bx bx-user-plus'></i> Créer mon compte</button>
            <p style="margin-top:12px;font-size:12px;text-align:center">
                Déjà inscrit ? <a href="login.php" style="color:#0891b2;font-weight:600">Se connecter</a>
            </p>
            <?php endif; ?>
        </form>
    </div>
    <!-- Toggle panel vide pour garder le layout -->
    <div class="toggle-container">
        <div class="toggle">
            <div class="toggle-panel toggle-left">
                <i class='bx bxs-graduation toggle-icon'></i>
                <h1>Déjà membre ?</h1>
                <p>Connectez-vous pour accéder à votre espace EduConnect.</p>
                <button class="btn-toggle" onclick="window.location='login.php'">Se connecter</button>
            </div>
            <div class="toggle-panel toggle-right"></div>
        </div>
    </div>
</div>
</div>
<script src="../../Assets/js/animations.js"></script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body></html>
