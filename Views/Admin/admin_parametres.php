<?php
/**
 * EduConnect — admin_parametres.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check(['admin','chef_admin']);
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Administrateur';
$current_page = basename(__FILE__);
$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0",
    "i", [(int)$user["id"]]
)["nb"] ?? 0);

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Paramètres Admin
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$prefs_admin = db_row($conn,
    "SELECT notif_email,notif_sms,notif_inapp,langue,theme FROM user_preferences WHERE user_id=?",
    "i",[$uid]
) ?: ["notif_email"=>1,"notif_sms"=>0,"notif_inapp"=>1,"langue"=>"fr","theme"=>"auto"];
$success_adm=""; $error_adm="";

if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $action=$_POST["action"]??"";
    if ($action==="update_prefs") {
        $ne=isset($_POST["notif_email"])?1:0; $ns=isset($_POST["notif_sms"])?1:0; $ni=isset($_POST["notif_inapp"])?1:0;
        $th=in_array($_POST["theme"]??"",[" light","dark","auto"])?$_POST["theme"]:"auto";
        $lg=in_array($_POST["langue"]??"",[" fr","en"])?$_POST["langue"]:"fr";
        db_exec($conn,
            "INSERT INTO user_preferences (user_id,notif_email,notif_sms,notif_inapp,langue,theme)
             VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE notif_email=?,notif_sms=?,notif_inapp=?,langue=?,theme=?",
            "iiiissiiiss",[$uid,$ne,$ns,$ni,$lg,$th,$ne,$ns,$ni,$lg,$th]);
        $success_adm="Préférences enregistrées.";
    } elseif ($action==="change_password") {
        $old=trim($_POST["old_password"]??""); $new=trim($_POST["new_password"]??""); $cfm=trim($_POST["confirm"]??"");
        $u_h4=db_row($conn,"SELECT password_hash FROM users WHERE id=?","i",[$uid]);
        if (!password_verify($old,$u_h4["password_hash"])) $error_adm="Mot de passe actuel incorrect.";
        elseif(strlen($new)<8) $error_adm="Trop court.";
        elseif($new!==$cfm) $error_adm="Mots de passe différents.";
        else { db_exec($conn,"UPDATE users SET password_hash=? WHERE id=?","si",[password_hash($new,PASSWORD_BCRYPT),$uid]); $success_adm="Mot de passe mis à jour."; }
    }
}



$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Paramètres Administrateur</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--blue),var(--blue-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(50,110,169,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--blue-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--blue-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--blue),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--blue),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1;overflow-y:auto;max-width:900px;margin:0 auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--blue)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:18px;animation:fadeUp .3s ease;margin-bottom:20px}
.card-title{font-size:13px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px}
.setting-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}.setting-item:last-child{border:none}
.setting-label{font-size:12px;font-weight:600;color:var(--txt)}.setting-desc{font-size:10px;color:var(--txt3);margin-top:2px}
.toggle{width:40px;height:24px;border-radius:12px;background:var(--border);position:relative;cursor:pointer;transition:background var(--tr)}.toggle.active{background:var(--blue)}.toggle-dot{width:20px;height:20px;border-radius:50%;background:#fff;position:absolute;top:2px;left:2px;transition:left var(--tr);box-shadow:var(--sh)}
.toggle.active .toggle-dot{left:18px}
.form-group{margin-bottom:16px;animation:fadeUp .3s ease}.form-label{font-size:12px;font-weight:600;color:var(--txt);margin-bottom:6px;display:block}
.form-input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);background:var(--surface2);transition:all var(--tr)}.form-input:focus{outline:none;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px var(--blue-dim)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;width:100%;justify-content:center}.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--blue)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.setting-item{flex-direction:column;align-items:flex-start;gap:8px}}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-gear-fill"></i></div>
    <div><div class="logo-text">Edu<span>Connect</span></div><div style="font-size:9px;color:rgba(255,255,255,.4)">Admin</div></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Gestion</div>
    <button class="nav-item <?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_dashboard.php'"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></button>
    <button class="nav-item <?= $current_page === 'admin_etudiants.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_etudiants.php'"><i class="bi bi-people"></i><span class="nav-item-txt">Étudiants</span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item <?= $current_page === 'admin_profil.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_profil.php'"><i class="bi bi-person"></i><span class="nav-item-txt">Profil</span></button>
    <button class="nav-item active" onclick="nav('settings',this)"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role">Administratrice</div></div></div>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-gear"></i>Paramètres</div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="toast('🔔 Vous avez 2 notifications')" title="Notifications"><i class="bi bi-bell-fill"></i></button>
      <div class="tb-user" onclick="toast('Profil Mme Bilong')"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-gear"></i><div><h1>Paramètres</h1><p>Gérez vos préférences et paramètres de sécurité</p></div></div>
    </div>

    <div class="card">
      <div class="card-title">Préférences Générales</div>
      <div class="setting-item">
        <div><div class="setting-label">Thème Sombre</div><div class="setting-desc">Activer le mode sombre</div></div>
        <div class="toggle" onclick="this.classList.toggle('active');toast('🌙 Thème mis à jour')"></div class="toggle-dot"></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Notifications Email</div><div class="setting-desc">Recevoir les mises à jour par email</div></div>
        <div class="toggle active" onclick="this.classList.toggle('active');toast('📧 Notifications mises à jour')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Notifications Système</div><div class="setting-desc">Afficher les notifications du système</div></div>
        <div class="toggle active" onclick="this.classList.toggle('active');toast('🔔 Notifications mises à jour')"><div class="toggle-dot"></div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Sécurité</div>
      <div class="form-group">
        <label class="form-label">Mot de Passe Actuel</label>
        <input type="password" class="form-input" placeholder="••••••••">
      </div>
      <div class="form-group">
        <label class="form-label">Nouveau Mot de Passe</label>
        <input type="password" class="form-input" placeholder="••••••••">
      </div>
      <div class="form-group">
        <label class="form-label">Confirmer le Mot de Passe</label>
        <input type="password" class="form-input" placeholder="••••••••">
      </div>
      <button class="btn btn-primary" onclick="toast('✅ Mot de passe mis à jour')">Changer le Mot de Passe</button>
    </div>

    <div class="card">
      <div class="card-title">Authentification à Deux Facteurs</div>
      <div class="setting-item">
        <div><div class="setting-label">2FA Activé</div><div class="setting-desc">Sécurité supplémentaire pour votre compte</div></div>
        <div class="toggle" onclick="this.classList.toggle('active');toast('🔐 2FA mis à jour')"><div class="toggle-dot"></div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Sessions Actives</div>
      <div style="font-size:12px;color:var(--txt3);line-height:1.8">
        <p>✓ Navigateur: Chrome sur Windows - Aujourd'hui 09:30</p>
        <p>✓ Navigateur: Safari sur macOS - Hier 14:20</p>
        <p>✓ Mobile: iPhone - Il y a 3 jours</p>
      </div>
      <button class="btn btn-secondary" style="margin-top:12px" onclick="toast('✅ Autres sessions fermées')">Fermer les Autres Sessions</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <button class="btn btn-secondary" onclick="toast('❌ Modifications annulées')">Annuler</button>
      <button class="btn btn-primary" onclick="toast('✅ Paramètres enregistrés')">Enregistrer</button>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}
function nav(page,btn){document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));btn.classList.add('active');toast('📋 Accès à: '+page)}
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3000)}
</script>

</body>
</html>
