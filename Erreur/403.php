<?php /** EduConnect — 403 Forbidden Error */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 — Accès Refusé</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:linear-gradient(135deg,var(--bg),var(--surface2));color:var(--txt);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.error-container{background:var(--surface);border-radius:var(--r);box-shadow:var(--sh-lg);padding:40px;text-align:center;max-width:500px;animation:fadeUp .5s ease}
.error-icon{font-size:80px;margin-bottom:20px;animation:shake 0.5s infinite}
.error-code{font-size:72px;font-weight:700;color:var(--red);margin-bottom:10px;letter-spacing:2px}
.error-title{font-size:28px;font-weight:700;color:var(--txt);margin-bottom:10px}
.error-desc{font-size:14px;color:var(--txt2);line-height:1.6;margin-bottom:30px}
.error-details{background:var(--red-dim);border-left:3px solid var(--red);padding:12px;border-radius:var(--r-sm);margin-bottom:30px;text-align:left;font-size:12px;color:var(--txt3)}
.error-details strong{color:var(--txt);display:block;margin-bottom:4px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:var(--r-sm);font-size:13px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;text-decoration:none;margin:0 6px}
.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-2px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.btn-group{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-10px)}75%{transform:translateX(10px)}}
@media(max-width:600px){.error-container{padding:30px}.error-code{font-size:48px}.error-title{font-size:22px}.btn-group{flex-direction:column}.btn{width:100%;margin:6px 0}}
</style>
</head>
<body>

<div class="error-container">
  <div class="error-icon"><i class="bi bi-lock-fill"></i></div>
  <div class="error-code">403</div>
  <h1 class="error-title">Accès Refusé</h1>
  <p class="error-desc">Vous n'avez pas les permissions nécessaires pour accéder à cette ressource.</p>
  
  <div class="error-details">
    <strong>Raison:</strong>
    Votre rôle utilisateur n'a pas accès à cette page. Contactez l'administrateur si vous pensez que c'est une erreur.
  </div>

  <div class="btn-group">
    <a href="../index.php" class="btn btn-primary"><i class="bi bi-house-fill"></i>Accueil</a>
    <a href="../Views/Auth/login.php" class="btn btn-secondary"><i class="bi bi-box-arrow-in-right"></i>Connexion</a>
  </div>
</div>

</body>
</html>
