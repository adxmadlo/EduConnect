<?php
/**
 * EduConnect — Login
 * POST → vérifie email + password_verify → $_SESSION → redirect selon rôle
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    $map = ['etudiant'=>'/Views/Etudiant/etudiant_dashboard.php','parent'=>'/Views/Parent/parent_dashboard.php',
            'enseignant'=>'/Views/Enseignant/enseignant_dashboard.php','mentor'=>'/Views/Mentor/mentor_dashboard.php',
            'admin'=>'/Views/Admin/admin_dashboard.php','chef_admin'=>'/Views/Admin/admin_dashboard.php'];
    header('Location: ' . ($map[$_SESSION['role']] ?? '/')); exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

$error = ''; $timeout = isset($_GET['timeout']); $success = isset($_GET['logout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$email || !$password) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $user = db_row($conn,
            "SELECT id, numero_ucao, nom, prenom, email, password_hash, role, statut FROM users WHERE email = ? LIMIT 1",
            's', [$email]);
        if (!$user) {
            $error = 'Email ou mot de passe incorrect.';
        } elseif ($user['statut'] === 'suspendu') {
            $error = 'Compte suspendu. Contactez l\'administration.';
        } elseif ($user['statut'] === 'en_attente') {
            $error = 'Compte en attente de validation par l\'administration.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Email ou mot de passe incorrect.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id']; $_SESSION['role'] = $user['role'];
            $_SESSION['nom'] = $user['nom']; $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['email'] = $user['email']; $_SESSION['numero_ucao'] = $user['numero_ucao'];
            $_SESSION['last_activity'] = time();
            db_exec($conn, "UPDATE users SET dernier_login = NOW() WHERE id = ?", 'i', [(int)$user['id']]);
            db_exec($conn, "INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, 'LOGIN', ?)",
                    'is', [(int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            $redirects = ['etudiant'=>'/Views/Etudiant/etudiant_dashboard.php','parent'=>'/Views/Parent/parent_dashboard.php',
                          'enseignant'=>'/Views/Enseignant/enseignant_dashboard.php','mentor'=>'/Views/Mentor/mentor_dashboard.php',
                          'admin'=>'/Views/Admin/admin_dashboard.php','chef_admin'=>'/Views/Admin/admin_dashboard.php'];
            $next = $_GET['next'] ?? '';
            header('Location: ' . (($next && str_starts_with($next, '/')) ? $next : ($redirects[$user['role']] ?? '/'))); exit;
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
<title>Connexion | EduConnect</title>
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
<div class="container" id="container">
    <!-- Sign Up -->
    <div class="form-container sign-up">
        <form action="register.php" method="POST">
            <div class="brand-logo"><i class='bx bxs-graduation'></i> EduConnect</div>
            <h1>Créer un compte</h1><p class="subtitle">Rejoignez des milliers d'étudiants</p>
            <div class="input-group"><i class='bx bx-user'></i><input type="text" name="prenom" placeholder="Prénom" required></div>
            <div class="input-group"><i class='bx bx-user'></i><input type="text" name="nom" placeholder="Nom" required></div>
            <div class="input-group"><i class='bx bx-envelope'></i><input type="email" name="email" placeholder="Email" required></div>
            <div class="input-group"><i class='bx bx-lock-alt'></i><input type="password" name="password" placeholder="Mot de passe" required></div>
            <button type="submit" class="btn-auth"><i class='bx bx-user-plus'></i> S'inscrire</button>
        </form>
    </div>
    <!-- Sign In -->
    <div class="form-container sign-in">
        <form action="login.php" method="POST">
            <div class="brand-logo"><i class='bx bxs-graduation'></i> EduConnect</div>
            <h1>Se connecter</h1>
            <p class="subtitle">
                <?php if ($timeout): ?><span style="color:#e67e22">⏱ Session expirée</span>
                <?php elseif ($success): ?><span style="color:#27ae60">✓ Déconnexion réussie</span>
                <?php else: ?>Bon retour parmi nous !<?php endif; ?>
            </p>
            <?php if ($error): ?>
            <div style="background:#fde8ec;border:1px solid #f5a0b0;color:#C41E3A;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <i class='bx bx-error-circle'></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <div class="input-group">
                <i class='bx bx-envelope'></i>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Email" required autofocus>
            </div>
            <div class="input-group"><i class='bx bx-lock-alt'></i><input type="password" name="password" placeholder="Mot de passe" required></div>
            <a href="mdp_oublie.php" class="forgot-link">Mot de passe oublié ?</a>
            <button type="submit" class="btn-auth"><i class='bx bx-log-in'></i> Se connecter</button>
        </form>
    </div>
    <!-- Toggle -->
    <div class="toggle-container"><div class="toggle">
        <div class="toggle-panel toggle-left">
            <i class='bx bxs-graduation toggle-icon'></i><h1>Bon retour !</h1>
            <p>Connectez-vous pour accéder à votre espace académique personnel.</p>
            <div class="toggle-features">
                <div class="feature"><i class='bx bx-check-circle'></i> Notes en temps réel</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Paiements Wave & Orange Money</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Mentorat personnalisé</div>
            </div>
            <button class="btn-toggle" id="login">Se connecter</button>
        </div>
        <div class="toggle-panel toggle-right">
            <i class='bx bxs-graduation toggle-icon'></i><h1>Rejoignez-nous !</h1>
            <p>Créez votre compte et transformez votre expérience dès aujourd'hui.</p>
            <div class="toggle-features">
                <div class="feature"><i class='bx bx-check-circle'></i> Salles UCAO en ligne</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Bulletins gratuits</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Notifications push</div>
            </div>
            <button class="btn-toggle" id="register">S'inscrire</button>
        </div>
    </div></div>
</div>
</div>
<script>
const c=document.getElementById('container');
document.getElementById('register').addEventListener('click',()=>c.classList.add('active'));
document.getElementById('login').addEventListener('click',()=>c.classList.remove('active'));
</script>
<script src="../../Assets/js/animations.js"></script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body></html>
