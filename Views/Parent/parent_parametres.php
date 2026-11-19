<?php
/**
 * EduConnect — parent_parametres.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('parent');
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Parent / Tuteur';
$current_page = basename(__FILE__);

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Paramètres Parent
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$prefs_parent = db_row($conn,
    "SELECT notif_email,notif_sms,notif_inapp,langue,theme FROM user_preferences WHERE user_id=?",
    "i",[$uid]
) ?: ["notif_email"=>1,"notif_sms"=>1,"notif_inapp"=>1,"langue"=>"fr","theme"=>"auto"];
$success_msg_p=""; $error_msg_p="";

if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $action=$_POST["action"]??"";
    if ($action==="update_prefs") {
        $ne=isset($_POST["notif_email"])?1:0; $ns=isset($_POST["notif_sms"])?1:0; $ni=isset($_POST["notif_inapp"])?1:0;
        $th=in_array($_POST["theme"]??"",[" light","dark","auto"])?$_POST["theme"]:"auto";
        $lg=in_array($_POST["langue"]??"",[" fr","en"])?$_POST["langue"]:"fr";
        $plan=in_array($_POST["plan"]??"",[" mensuel","trimestriel","semestriel","annuel"])?$_POST["plan"]:"trimestriel";
        $moyen=in_array($_POST["moyen"]??"",[" wave","orange_money","carte"])?$_POST["moyen"]:"wave";
        db_exec($conn,
            "INSERT INTO user_preferences (user_id,notif_email,notif_sms,notif_inapp,langue,theme)
             VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE notif_email=?,notif_sms=?,notif_inapp=?,langue=?,theme=?",
            "iiiissiiiss",[$uid,$ne,$ns,$ni,$lg,$th,$ne,$ns,$ni,$lg,$th]);
        $success_msg_p="Préférences enregistrées.";
    } elseif ($action==="change_password") {
        $old=trim($_POST["old_password"]??""); $new=trim($_POST["new_password"]??""); $cfm=trim($_POST["confirm"]??"");
        $u_h2=db_row($conn,"SELECT password_hash FROM users WHERE id=?","i",[$uid]);
        if (!password_verify($old,$u_h2["password_hash"])) $error_msg_p="Mot de passe actuel incorrect.";
        elseif (strlen($new)<8) $error_msg_p="Mot de passe trop court.";
        elseif ($new!==$cfm) $error_msg_p="Mots de passe différents.";
        else { db_exec($conn,"UPDATE users SET password_hash=? WHERE id=?","si",[password_hash($new,PASSWORD_BCRYPT),$uid]); $success_msg_p="Mot de passe mis à jour."; }
    }
}



$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Paramètres Parent</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--gold-dk:#b8892e;--gold-lt:#f0c45a;--gold-dim:rgba(212,168,67,.15);--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--gold),var(--gold-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(212,168,67,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--gold-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--gold-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}.sidebar.collapsed .nav-badge{opacity:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--gold-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--gold-dk);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer}.tb-icon:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--gold);background:var(--gold-dim)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
/* content max-width:900px centered — exactly like etudiant_parametres.php */
.content{padding:18px 20px;flex:1;overflow-y:auto;max-width:900px;margin:0 auto;width:100%}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease;flex-wrap:wrap;gap:10px}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--gold-dk)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
/* card — exact match to etudiant_parametres.php */
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:18px;animation:fadeUp .3s ease;margin-bottom:20px}
.card-title{font-size:13px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:7px}.card-title i{color:var(--gold-dk);font-size:14px}
/* setting-item — exact match */
.setting-item{display:flex;align-items:center;justify-content:space-between;padding:13px 0;border-bottom:1px solid var(--border)}.setting-item:last-child{border:none}.setting-label{font-size:13px;font-weight:600;color:var(--txt)}.setting-desc{font-size:11px;color:var(--txt3);margin-top:2px}
/* toggle — exact match to etudiant_parametres.php */
.toggle{width:40px;height:24px;border-radius:12px;background:var(--border2);position:relative;cursor:pointer;transition:background var(--tr);flex-shrink:0}.toggle.active{background:var(--gold)}.toggle-dot{width:20px;height:20px;border-radius:50%;background:#fff;position:absolute;top:2px;left:2px;transition:left var(--tr);box-shadow:var(--sh)}.toggle.active .toggle-dot{left:18px}
/* form — exact match */
.form-group{margin-bottom:16px;animation:fadeUp .3s ease}.form-label{font-size:12px;font-weight:600;color:var(--txt);margin-bottom:6px;display:block}.form-input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);background:var(--surface2);transition:all var(--tr)}.form-input:focus{outline:none;border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px var(--gold-dim)}.form-select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);background:var(--surface2);cursor:pointer}.form-select:focus{outline:none;border-color:var(--gold)}
/* btn — exact match, gold accent */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--r-sm);font-size:12.5px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif;justify-content:center}.btn-primary{background:var(--gold);color:#fff;border-color:var(--gold)}.btn-primary:hover{background:var(--gold-dk);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{background:var(--surface);border-color:var(--gold);color:var(--gold-dk)}.btn-danger{background:var(--red-dim);color:var(--red);border-color:var(--red-dim)}.btn-danger:hover{background:var(--red);color:#fff}
.linked-child{display:flex;align-items:center;gap:14px;padding:14px;background:var(--gold-dim);border-radius:var(--r-sm);border:1px solid rgba(212,168,67,.3)}.child-ava{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,var(--green),var(--green-lt));display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#fff;flex-shrink:0}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-green{background:var(--green-dim);color:var(--green)}.badge-gold{background:var(--gold-dim);color:var(--gold-dk)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--gold)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.setting-item{flex-direction:column;align-items:flex-start;gap:8px}}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-person-hearts"></i></div>
    <div><div class="logo-text">Edu<span>Connect</span></div><div style="font-size:9px;color:rgba(255,255,255,.4)">Parent / Tuteur</div></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Mon Espace</div>
    <button class="nav-item <?= $current_page === 'parent_dashboard.php' ? 'active' : '' ?>" onclick="location.href='parent_dashboard.php'"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></button>
    <button class="nav-item <?= $current_page === 'parent_etudiants.php' ? 'active' : '' ?>" onclick="location.href='parent_etudiants.php'"><i class="bi bi-person-fill"></i><span class="nav-item-txt">Mon Enfant</span></button>
    <button class="nav-item <?= $current_page === 'parent_paiements.php' ? 'active' : '' ?>" onclick="location.href='parent_paiements.php'"><i class="bi bi-credit-card"></i><span class="nav-item-txt">Paiements</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'parent_notes.php' ? 'active' : '' ?>" onclick="location.href='parent_notes.php'"><i class="bi bi-graph-up"></i><span class="nav-item-txt">Notes &amp; Bulletins</span></button>
    <div class="nav-section">Suivi</div>
    <button class="nav-item <?= $current_page === 'parent_emploi_du_temps.php' ? 'active' : '' ?>" onclick="location.href='parent_emploi_du_temps.php'"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></button>
    <button class="nav-item <?= $current_page === 'parent_messages.php' ? 'active' : '' ?>" onclick="location.href='parent_messages.php'"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'parent_notifications.php' ? 'active' : '' ?>" onclick="location.href='parent_notifications.php'"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item <?= $current_page === 'parent_profil.php' ? 'active' : '' ?>" onclick="location.href='parent_profil.php'"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
    <button class="nav-item active"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Parent/parent_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role">Parent · Marie Sow (L2)</div></div></div>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-gear"></i>Paramètres</div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="location.href='parent_notifications.php'"><i class="bi bi-bell-fill"></i></button>
      <div class="tb-user" onclick="location.href='parent_profil.php'"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title">
        <i class="bi bi-gear"></i>
        <div><h1>Paramètres</h1><p>Gérez vos préférences, notifications et sécurité</p></div>
      </div>
    </div>

    <!-- Linked child — parent-specific section -->
    <div class="card">
      <div class="card-title"><i class="bi bi-person-lines-fill"></i>Enfant lié au compte</div>
      <div class="linked-child">
        <div class="child-ava">MS</div>
        <div style="flex:1">
          <div style="font-size:13px;font-weight:700;color:var(--txt)">Marie Sow</div>
          <div style="font-size:11px;color:var(--txt3);margin-top:2px">Matricule #STU001 · L2 Informatique · UCAO Saint-Michel</div>
          <div style="display:flex;gap:6px;margin-top:6px">
            <span class="badge badge-green"><i class="bi bi-check-circle-fill"></i>Actif</span>
            <span class="badge badge-green">Paiement à jour</span>
            <span class="badge badge-gold">Mention TB</span>
          </div>
        </div>
        <button class="btn btn-secondary" style="flex-shrink:0" onclick="toast('📧 Demande de liaison envoyée à l\'administration')"><i class="bi bi-link-45deg"></i>Gérer</button>
      </div>
    </div>

    <!-- Notifications académiques -->
    <div class="card">
      <div class="card-title"><i class="bi bi-bell-fill"></i>Notifications Académiques</div>
      <div class="setting-item">
        <div><div class="setting-label">Notes publiées</div><div class="setting-desc">Recevoir une alerte dès qu'une note est disponible pour Marie</div></div>
        <div class="toggle active" onclick="toggle(this,'Alertes notes')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Rappels de cours</div><div class="setting-desc">Rappels avant chaque cours pour suivre la présence</div></div>
        <div class="toggle active" onclick="toggle(this,'Rappels cours')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Alertes de paiement</div><div class="setting-desc">Rappels 7 jours, 3 jours et 1 jour avant chaque échéance</div></div>
        <div class="toggle active" onclick="toggle(this,'Alertes paiement')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Examens et contrôles</div><div class="setting-desc">Rappels avant les évaluations programmées de Marie</div></div>
        <div class="toggle active" onclick="toggle(this,'Rappels examens')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Sessions de mentorat</div><div class="setting-desc">Confirmation et rappels des sessions avec les mentors</div></div>
        <div class="toggle active" onclick="toggle(this,'Notifications mentorat')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Bulletins disponibles</div><div class="setting-desc">Notification dès qu'un nouveau bulletin est publié</div></div>
        <div class="toggle active" onclick="toggle(this,'Bulletins disponibles')"><div class="toggle-dot"></div></div>
      </div>
    </div>

    <!-- Préférences de communication -->
    <div class="card">
      <div class="card-title"><i class="bi bi-chat-dots-fill"></i>Préférences de Communication</div>
      <div class="setting-item">
        <div><div class="setting-label">Notifications par email</div><div class="setting-desc">oumar.diallo@gmail.com</div></div>
        <div class="toggle active" onclick="toggle(this,'Email')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Notifications par SMS</div><div class="setting-desc">+221 77 XXX XX XX — alertes critiques uniquement</div></div>
        <div class="toggle active" onclick="toggle(this,'SMS')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Notifications push (in-app)</div><div class="setting-desc">Alertes en temps réel dans l'application</div></div>
        <div class="toggle active" onclick="toggle(this,'Push')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Résumé hebdomadaire</div><div class="setting-desc">Récapitulatif de la semaine académique de Marie chaque lundi</div></div>
        <div class="toggle" onclick="toggle(this,'Résumé hebdo')"><div class="toggle-dot"></div></div>
      </div>
    </div>

    <!-- Plan de paiement préféré -->
    <div class="card">
      <div class="card-title"><i class="bi bi-credit-card-fill"></i>Préférences de Paiement</div>
      <div class="form-group">
        <label class="form-label">Plan de paiement préféré</label>
        <select class="form-select" onchange="toast('💳 Plan de paiement mis à jour')">
          <option value="trimestre" selected>Trimestriel — 4 versements de 125 000 FCFA</option>
          <option value="semestre">Semestriel — 2 versements de 250 000 FCFA</option>
          <option value="annuel">Annuel — 1 versement de 500 000 FCFA</option>
          <option value="mensuel">Mensuel — 12 versements de ~41 700 FCFA</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Méthode de paiement préférée</label>
        <select class="form-select" onchange="toast('📱 Méthode de paiement mise à jour')">
          <option value="wave" selected>Wave (Sénégal)</option>
          <option value="om">Orange Money</option>
          <option value="carte">Carte bancaire (Visa / Mastercard)</option>
        </select>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Rappels de paiement automatiques</div><div class="setting-desc">7 jours, 3 jours et 1 jour avant chaque échéance</div></div>
        <div class="toggle active" onclick="toggle(this,'Rappels auto')"><div class="toggle-dot"></div></div>
      </div>
    </div>

    <!-- Sécurité — exact same structure as etudiant_parametres.php -->
    <div class="card">
      <div class="card-title"><i class="bi bi-shield-lock-fill"></i>Sécurité</div>
      <div class="form-group">
        <label class="form-label">Mot de Passe Actuel</label>
        <input type="password" class="form-input" placeholder="••••••••">
      </div>
      <div class="form-group">
        <label class="form-label">Nouveau Mot de Passe</label>
        <input type="password" class="form-input" id="newPwd" placeholder="••••••••" oninput="checkPwd(this.value)">
        <div id="pwdStrength" style="margin-top:6px;display:none">
          <div style="display:flex;gap:4px;margin-bottom:3px">
            <div class="pwd-bar" id="pb1" style="flex:1;height:4px;border-radius:10px;background:var(--border)"></div>
            <div class="pwd-bar" id="pb2" style="flex:1;height:4px;border-radius:10px;background:var(--border)"></div>
            <div class="pwd-bar" id="pb3" style="flex:1;height:4px;border-radius:10px;background:var(--border)"></div>
            <div class="pwd-bar" id="pb4" style="flex:1;height:4px;border-radius:10px;background:var(--border)"></div>
          </div>
          <div id="pwdLabel" style="font-size:11px;color:var(--txt3)">Entrez un mot de passe</div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirmer le Mot de Passe</label>
        <input type="password" class="form-input" placeholder="••••••••">
      </div>
      <button class="btn btn-primary" style="width:100%" onclick="toast('✅ Mot de passe mis à jour avec succès')">
        <i class="bi bi-shield-check"></i>Changer le Mot de Passe
      </button>
    </div>

    <!-- Thème et affichage -->
    <div class="card">
      <div class="card-title"><i class="bi bi-palette-fill"></i>Thème &amp; Affichage</div>
      <div class="setting-item">
        <div><div class="setting-label">Thème sombre</div><div class="setting-desc">Activer le mode sombre (bêta)</div></div>
        <div class="toggle" onclick="toggle(this,'Thème sombre')"><div class="toggle-dot"></div></div>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Langue de l'interface</div><div class="setting-desc">Français</div></div>
        <select class="form-select" style="width:auto;padding:5px 10px" onchange="toast('🌍 Langue mise à jour')">
          <option selected>Français</option>
          <option>English</option>
          <option>Wolof</option>
        </select>
      </div>
      <div class="setting-item">
        <div><div class="setting-label">Afficher les montants en</div><div class="setting-desc">FCFA par défaut</div></div>
        <select class="form-select" style="width:auto;padding:5px 10px" onchange="toast('💱 Devise mise à jour')">
          <option selected>FCFA (XOF)</option>
          <option>EUR €</option>
          <option>USD $</option>
        </select>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="card" style="border-color:var(--red-dim)">
      <div class="card-title" style="color:var(--red)"><i class="bi bi-exclamation-triangle-fill"></i>Zone Dangereuse</div>
      <div class="setting-item">
        <div><div class="setting-label">Supprimer mon compte</div><div class="setting-desc">Cette action est irréversible. Toutes vos données seront effacées.</div></div>
        <button class="btn btn-danger" onclick="if(confirm('Êtes-vous certain ? Cette action est irréversible.'))toast('🗑️ Demande de suppression envoyée')"><i class="bi bi-trash3"></i>Supprimer</button>
      </div>
    </div>

    <!-- Save buttons — same layout as etudiant_parametres.php -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px">
      <button class="btn btn-secondary" onclick="toast('❌ Modifications annulées')"><i class="bi bi-x-circle"></i>Annuler</button>
      <button class="btn btn-primary" onclick="toast('✅ Paramètres enregistrés avec succès')"><i class="bi bi-check-circle"></i>Enregistrer</button>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}

function toggle(el,label){
  el.classList.toggle('active');
  const on=el.classList.contains('active');
  toast((on?'✅ ':'🔕 ')+label+' '+(on?'activé':'désactivé'));
}

function checkPwd(val){
  const wrap=document.getElementById('pwdStrength');
  const label=document.getElementById('pwdLabel');
  const bars=['pb1','pb2','pb3','pb4'];
  bars.forEach(id=>document.getElementById(id).style.background='var(--border)');
  if(!val){wrap.style.display='none';return;}
  wrap.style.display='block';
  const hasLen=val.length>=8,hasUp=/[A-Z]/.test(val),hasNum=/[0-9]/.test(val),hasSpec=/[^A-Za-z0-9]/.test(val);
  const score=[hasLen,hasUp,hasNum,hasSpec].filter(Boolean).length;
  const colors=['var(--red)','var(--orange)','var(--blue)','var(--green)'];
  const labels=['Très faible','Faible','Bon','Très fort ✓'];
  for(let i=0;i<score;i++) document.getElementById(bars[i]).style.background=colors[score-1];
  label.textContent=labels[score-1]||'Entrez un mot de passe';
  label.style.color=colors[score-1]||'var(--txt3)';
}

let toastTimer;function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3200)}
</script>
</body>
</html>
