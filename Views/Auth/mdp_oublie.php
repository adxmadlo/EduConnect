<?php
/**
 * EduConnect — Mot de passe oublié
 * Étape 1 : email → générer token (6 chiffres) valable 15min
 * Étape 2 : saisir le code reçu
 * Étape 3 : nouveau mot de passe
 * NOTE : l'envoi email réel se fait via API (SendGrid/Resend) — pour l'instant log en session
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

$step     = (int)($_SESSION['reset_step'] ?? 1);
$error    = '';
$success  = '';

// ── ÉTAPE 1 : Envoi de l'email ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'send_code') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } else {
            $user = db_row($conn, "SELECT id, nom, prenom, email FROM users WHERE email = ? AND statut = 'actif' LIMIT 1", 's', [$email]);
            if (!$user) {
                // Sécurité : ne pas révéler si l'email existe
                $success = "Si cet email est enregistré, un code a été envoyé.";
                $step = 2;
                $_SESSION['reset_email']   = $email;
                $_SESSION['reset_code']    = '000000'; // code factice pour email inexistant
                $_SESSION['reset_expires'] = time() + 900;
                $_SESSION['reset_step']    = 2;
            } else {
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['reset_email']   = $email;
                $_SESSION['reset_code']    = $code;
                $_SESSION['reset_expires'] = time() + 900;
                $_SESSION['reset_user_id'] = (int)$user['id'];
                $_SESSION['reset_step']    = 2;
                $step = 2;
                // TODO : envoyer le vrai email via Resend/SendGrid.
                // Le code n'est journalisé qu'en debug local explicite : jamais en production.
                if (defined('EDUCONNECT_DEBUG_RESET') && EDUCONNECT_DEBUG_RESET === true) {
                    error_log("[EduConnect] Code reset (debug) pour {$email} : {$code} (valable 15min)");
                }
                $success = "Code envoyé à {$email}. Vérifiez vos emails.";
            }
        }
    }

    elseif ($_POST['action'] === 'verify_code') {
        $code_saisi = trim($_POST['code'] ?? '');
        if (time() > ($_SESSION['reset_expires'] ?? 0)) {
            $error = 'Code expiré. Recommencez.';
            session_unset(); $step = 1;
        } elseif ($code_saisi !== ($_SESSION['reset_code'] ?? '')) {
            $error = 'Code incorrect. Réessayez.';
            $step = 2;
        } else {
            $_SESSION['reset_step'] = 3;
            $step = 3;
            $success = 'Code validé ! Choisissez votre nouveau mot de passe.';
        }
    }

    elseif ($_POST['action'] === 'new_password') {
        $pwd     = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm']  ?? '');
        $uid     = $_SESSION['reset_user_id'] ?? 0;
        if (!$uid || time() > ($_SESSION['reset_expires'] ?? 0)) {
            $error = 'Session expirée. Recommencez.'; $step = 1;
            unset($_SESSION['reset_step'],$_SESSION['reset_code'],$_SESSION['reset_email'],$_SESSION['reset_user_id']);
        } elseif (strlen($pwd) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.'; $step = 3;
        } elseif ($pwd !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.'; $step = 3;
        } else {
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            db_exec($conn, "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", 'si', [$hash, $uid]);
            db_exec($conn, "INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, 'PASSWORD_RESET', ?)",
                    'is', [$uid, $_SERVER['REMOTE_ADDR'] ?? '']);
            unset($_SESSION['reset_step'],$_SESSION['reset_code'],$_SESSION['reset_email'],
                  $_SESSION['reset_user_id'],$_SESSION['reset_expires']);
            header('Location: login.php?reset=success'); exit;
        }
    }
}
$step = (int)($_SESSION['reset_step'] ?? 1);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="../../Assets/css/login.css">
<title>Mot de passe oublié | EduConnect</title>
<style>
.reset-wrapper{flex:1;display:flex;align-items:center;justify-content:center;padding:60px 20px;background:var(--bg-light)}
.reset-card{background:var(--bg-white);border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.1);width:480px;max-width:100%;overflow:hidden;animation:slideUp .45s cubic-bezier(.22,1,.36,1) both}
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.reset-stripe{height:4px;background:linear-gradient(90deg,var(--primary),var(--primary-dark))}
.reset-body{padding:48px 44px 40px;display:flex;flex-direction:column;align-items:center;text-align:center}
.reset-icon{font-size:52px;margin-bottom:16px}
.reset-title{font-size:24px;font-weight:700;color:var(--text-dark);margin-bottom:8px}
.reset-sub{font-size:14px;color:var(--text-muted);margin-bottom:24px}
.steps{display:flex;gap:8px;margin-bottom:28px}
.step-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;transition:.3s}
.step-dot.active{background:var(--primary);color:#fff}
.step-dot.done{background:#27ae60;color:#fff}
.step-dot.inactive{background:var(--border);color:var(--text-muted)}
.step-line{flex:1;height:2px;background:var(--border);align-self:center;border-radius:2px}
.step-line.done{background:#27ae60}
.reset-input{width:100%;padding:12px 16px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;font-family:inherit;transition:.25s;background:var(--bg-light);margin-bottom:14px}
.reset-input:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(50,110,169,.1)}
.otp-wrap{display:flex;gap:10px;justify-content:center;margin-bottom:14px}
.otp-box{width:52px;height:60px;border:1.5px solid var(--border);border-radius:10px;font-size:22px;font-weight:700;text-align:center;background:var(--bg-light);transition:.25s}
.otp-box:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(50,110,169,.1)}
.btn-reset{width:100%;padding:13px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:.25s;font-family:inherit;margin-top:6px}
.btn-reset:hover{opacity:.9;transform:translateY(-1px)}
.pwd-strength{height:4px;background:var(--border);border-radius:10px;overflow:hidden;margin-bottom:14px}
.pwd-bar{height:100%;width:0;border-radius:10px;transition:width .4s,background .4s}
.alert{width:100%;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px;text-align:left}
.alert-err{background:#fde8ec;border:1px solid #f5a0b0;color:#C41E3A}
.alert-ok{background:#e8f5e9;border:1px solid #a5d6a7;color:#1b5e20}
.back-link{font-size:13px;color:var(--primary);margin-top:16px;cursor:pointer}
</style>
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

<div class="reset-wrapper">
<div class="reset-card">
    <div class="reset-stripe"></div>
    <div class="reset-body">

        <!-- Indicateur d'étapes -->
        <div class="steps">
            <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : 'inactive' ?>">
                <?= $step > 1 ? '✓' : '1' ?>
            </div>
            <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
            <div class="step-dot <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : 'inactive') ?>">
                <?= $step > 2 ? '✓' : '2' ?>
            </div>
            <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
            <div class="step-dot <?= $step === 3 ? 'active' : 'inactive' ?>">3</div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-err"><i class='bx bx-error-circle'></i><?= h($error) ?></div>
        <?php elseif ($success): ?>
        <div class="alert alert-ok"><i class='bx bx-check-circle'></i><?= h($success) ?></div>
        <?php endif; ?>

        <!-- ÉTAPE 1 : Email -->
        <?php if ($step === 1): ?>
        <div class="reset-icon">🔑</div>
        <div class="reset-title">Mot de passe oublié ?</div>
        <div class="reset-sub">Entrez votre email UCAO — un code à 6 chiffres vous sera envoyé.</div>
        <form method="POST" style="width:100%">
            <input type="hidden" name="action" value="send_code">
            <input class="reset-input" type="email" name="email"
                   placeholder="votre.email@etud.ucao.sn" required autofocus
                   value="<?= h($_POST['email'] ?? '') ?>">
            <button class="btn-reset" type="submit"><i class='bx bx-send'></i> Envoyer le code</button>
        </form>
        <a class="back-link" href="login.php">← Retour à la connexion</a>

        <!-- ÉTAPE 2 : Code OTP -->
        <?php elseif ($step === 2): ?>
        <div class="reset-icon">📩</div>
        <div class="reset-title">Vérification</div>
        <div class="reset-sub">
            Entrez le code à 6 chiffres envoyé à<br>
            <strong><?= h($_SESSION['reset_email'] ?? '') ?></strong>
        </div>
        <form method="POST" style="width:100%" id="otpForm">
            <input type="hidden" name="action" value="verify_code">
            <input type="hidden" name="code" id="codeHidden">
            <div class="otp-wrap" id="otpWrap">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
            </div>
            <button class="btn-reset" type="submit" id="btnVerify" disabled>
                <i class='bx bx-check-shield'></i> Vérifier le code
            </button>
        </form>
        <div style="margin-top:14px;font-size:13px;color:var(--text-muted)">
            Code expiré ? <a href="mdp_oublie.php" style="color:var(--primary);font-weight:600;cursor:pointer"
            onclick="<?php session_unset(); ?>">Renvoyer</a>
            &nbsp;|&nbsp; <a href="login.php" class="back-link" style="display:inline">← Retour</a>
        </div>

        <!-- ÉTAPE 3 : Nouveau mot de passe -->
        <?php elseif ($step === 3): ?>
        <div class="reset-icon">🔒</div>
        <div class="reset-title">Nouveau mot de passe</div>
        <div class="reset-sub">Choisissez un mot de passe sécurisé (min. 8 caractères).</div>
        <form method="POST" style="width:100%">
            <input type="hidden" name="action" value="new_password">
            <input class="reset-input" type="password" name="password" id="pwdNew"
                   placeholder="Nouveau mot de passe" required oninput="checkStrength(this.value)">
            <div class="pwd-strength"><div class="pwd-bar" id="pwdBar"></div></div>
            <input class="reset-input" type="password" name="confirm"
                   placeholder="Confirmer le mot de passe" required>
            <button class="btn-reset" type="submit">
                <i class='bx bx-lock-open-alt'></i> Réinitialiser le mot de passe
            </button>
        </form>
        <?php endif; ?>

    </div>
</div>
</div>

<script>
// ── OTP auto-advance ─────────────────────────────────────────
(function(){
    const boxes = document.querySelectorAll('.otp-box');
    const hidden = document.getElementById('codeHidden');
    const btn    = document.getElementById('btnVerify');
    if (!boxes.length) return;
    boxes[0].focus();
    boxes.forEach((box, i) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g,'').slice(-1);
            if (box.value && i < 5) boxes[i+1].focus();
            const code = [...boxes].map(b=>b.value).join('');
            hidden.value = code;
            btn.disabled = code.length < 6;
        });
        box.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !box.value && i > 0) boxes[i-1].focus();
        });
        box.addEventListener('paste', e => {
            const data = e.clipboardData.getData('text').replace(/\D/g,'').slice(0,6);
            [...data].forEach((c,j) => { if (boxes[j]) boxes[j].value = c; });
            hidden.value = data;
            btn.disabled = data.length < 6;
            if (data.length === 6) btn.focus();
            e.preventDefault();
        });
    });
})();

// ── Indicateur force mot de passe ───────────────────────────
function checkStrength(v) {
    const bar = document.getElementById('pwdBar');
    if (!bar) return;
    const s = (v.length >= 8 ? 1 : 0) + (/[A-Z]/.test(v)?1:0) + (/[0-9]/.test(v)?1:0) + (/[^A-Za-z0-9]/.test(v)?1:0);
    const colors = ['#C41E3A','#e67e22','#f0c45a','#27ae60'];
    bar.style.width = (s*25)+'%';
    bar.style.background = colors[s-1]||'#e0e0e0';
}
</script>
<script src="../../Assets/js/animations.js"></script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body></html>
