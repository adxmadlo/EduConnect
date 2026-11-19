<?php
/**
 * EduConnect — enseignant_parametres.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('enseignant');
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Enseignant';
$current_page = basename(__FILE__);

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Paramètres Enseignant
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$prefs = db_row($conn,
    "SELECT notif_email,notif_sms,notif_inapp,langue,theme FROM user_preferences WHERE user_id=?",
    "i",[$uid]
) ?: ["notif_email"=>1,"notif_sms"=>0,"notif_inapp"=>1,"langue"=>"fr","theme"=>"auto"];
$success_msg=""; $error_msg="";

if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $action = $_POST["action"] ?? "";
    if ($action==="update_prefs") {
        $ne=isset($_POST["notif_email"])?1:0; $ns=isset($_POST["notif_sms"])?1:0;
        $ni=isset($_POST["notif_inapp"])?1:0;
        $th=in_array($_POST["theme"]??"",[" light","dark","auto"])?$_POST["theme"]:"auto";
        $lg=in_array($_POST["langue"]??"",[" fr","en"])?$_POST["langue"]:"fr";
        db_exec($conn,
            "INSERT INTO user_preferences (user_id,notif_email,notif_sms,notif_inapp,langue,theme)
             VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE notif_email=?,notif_sms=?,notif_inapp=?,langue=?,theme=?",
            "iiiissiiiss",[$uid,$ne,$ns,$ni,$lg,$th,$ne,$ns,$ni,$lg,$th]);
        $success_msg="Préférences enregistrées.";
        $prefs=["notif_email"=>$ne,"notif_sms"=>$ns,"notif_inapp"=>$ni,"langue"=>$lg,"theme"=>$th];
    } elseif ($action==="change_password") {
        $old=trim($_POST["old_password"]??""); $new=trim($_POST["new_password"]??""); $cfm=trim($_POST["confirm"]??"");
        $u_hash=db_row($conn,"SELECT password_hash FROM users WHERE id=?","i",[$uid]);
        if (!password_verify($old,$u_hash["password_hash"])) $error_msg="Mot de passe actuel incorrect.";
        elseif (strlen($new)<8) $error_msg="Mot de passe trop court.";
        elseif ($new!==$cfm) $error_msg="Les mots de passe ne correspondent pas.";
        else { db_exec($conn,"UPDATE users SET password_hash=? WHERE id=?","si",[password_hash($new,PASSWORD_BCRYPT),$uid]); $success_msg="Mot de passe mis à jour."; }
    }
}


// ── Notifications non lues (badge sidebar) ─────────────────
$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0",
    "i", [(int)$user["id"]]
)["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Paramètres Enseignant</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
  --teal:#0891b2;--teal-lt:#06b6d4;--teal-dim:rgba(8,145,178,.12);
  --bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;
  --border:#e0e3e8;--border2:#d1d5db;
  --txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;
  --sidebar-w:240px;--topbar-h:56px;
  --r:8px;--r-sm:5px;
  --sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);
  --tr:.2s ease;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{font-size:14px}
body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}
button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ═══ SIDEBAR ═══ */
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:54px}
.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--teal),var(--teal-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(8,145,178,.5)}
.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}
.logo-text span{color:#7dd3fc}
.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}
.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}
.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}
.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}
.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}
.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}
.search-field input::placeholder{color:rgba(255,255,255,.4)}
.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}
.nav-body::-webkit-scrollbar{display:none}
.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .nav-section{opacity:0}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--teal-lt);border-radius:0 2px 2px 0}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--teal-lt)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--teal),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}

/* ═══ MAIN / TOPBAR ═══ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}
.topbar-brand{font-size:13px;font-weight:600;color:var(--teal);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--teal);background:var(--teal-dim)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--teal),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1;overflow-y:auto}
.inner{max-width:860px;margin:0 auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease}
.page-title{display:flex;align-items:center;gap:10px}
.page-title h1{font-size:22px;font-weight:700;color:var(--txt)}
.page-title i{font-size:26px;color:var(--teal)}
.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}

/* ═══ SECTION TABS (left rail + content) ═══ */
.settings-layout{display:grid;grid-template-columns:200px 1fr;gap:18px;align-items:start}
.settings-nav{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:8px;position:sticky;top:calc(var(--topbar-h) + 14px)}
.sn-item{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;color:var(--txt2);cursor:pointer;transition:all var(--tr);width:100%;text-align:left}
.sn-item:hover{background:var(--surface2);color:var(--txt)}
.sn-item.active{background:var(--teal-dim);color:var(--teal);font-weight:600}
.sn-item i{font-size:15px;flex-shrink:0}
.sn-separator{height:1px;background:var(--border);margin:6px 0}

/* ═══ CARDS ═══ */
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);margin-bottom:14px;animation:fadeUp .3s ease;overflow:hidden}
.card-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.card-head-icon{width:32px;height:32px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.card-head-title{font-size:13px;font-weight:700;color:var(--txt)}
.card-head-sub{font-size:11px;color:var(--txt3);margin-top:1px}
.card-body{padding:16px 18px}

/* ═══ PROFILE PHOTO ═══ */
.profile-zone{display:flex;align-items:center;gap:18px;margin-bottom:18px;padding:14px;background:var(--surface2);border-radius:var(--r-sm);border:1px solid var(--border)}
.profile-ava{width:72px;height:72px;border-radius:14px;background:linear-gradient(135deg,var(--teal),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:24px;color:#fff;flex-shrink:0;position:relative}
.profile-ava-edit{position:absolute;bottom:-4px;right:-4px;width:22px;height:22px;border-radius:50%;background:var(--teal);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background var(--tr)}
.profile-ava-edit:hover{background:var(--teal-lt)}
.profile-ava-edit i{font-size:11px;color:#fff}
.profile-info{flex:1}
.profile-name{font-size:16px;font-weight:700;color:var(--txt)}
.profile-role{font-size:12px;color:var(--txt3);margin-top:2px}
.profile-dept{font-size:12px;font-weight:600;color:var(--teal);margin-top:4px;display:flex;align-items:center;gap:5px}

/* ═══ FORM ELEMENTS ═══ */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-group{margin-bottom:14px}
.form-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px;display:block;display:flex;align-items:center;gap:5px}
.form-label i{color:var(--teal);font-size:12px}
.form-input{width:100%;padding:9px 12px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px;color:var(--txt);background:var(--surface);transition:all var(--tr)}
.form-input:focus{outline:none;border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px var(--teal-dim)}
.form-input:disabled{background:var(--surface2);color:var(--txt3);cursor:not-allowed}
.form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%239ca3af'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
.form-hint{font-size:10.5px;color:var(--txt3);margin-top:4px}

/* ═══ TOGGLE SWITCH ═══ */
.setting-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.setting-item:last-child{border:none;padding-bottom:0}
.setting-left{flex:1;padding-right:16px}
.setting-label{font-size:13px;font-weight:600;color:var(--txt)}
.setting-desc{font-size:11px;color:var(--txt3);margin-top:2px;line-height:1.4}
.toggle{width:42px;height:24px;border-radius:12px;background:var(--border);position:relative;cursor:pointer;transition:background .25s ease;flex-shrink:0}
.toggle.on{background:var(--teal)}
.toggle-dot{width:20px;height:20px;border-radius:50%;background:#fff;position:absolute;top:2px;left:2px;transition:left .25s ease;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.toggle.on .toggle-dot{left:20px}

/* ═══ AVAILABILITY GRID ═══ */
.avail-grid{display:grid;grid-template-columns:80px repeat(5,1fr);gap:4px;margin-top:8px}
.avail-header{font-size:10px;font-weight:700;text-align:center;color:var(--txt3);padding:5px 2px;text-transform:uppercase;letter-spacing:.5px}
.avail-time{font-size:10px;font-weight:600;color:var(--txt3);display:flex;align-items:center;justify-content:flex-end;padding-right:6px}
.avail-cell{height:30px;border-radius:4px;cursor:pointer;border:1.5px solid var(--border);background:var(--surface2);transition:all var(--tr);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:600}
.avail-cell:hover{border-color:var(--teal);background:var(--teal-dim)}
.avail-cell.busy{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}
.avail-cell.class{background:rgba(50,110,169,.15);border-color:var(--blue);color:var(--blue)}
.avail-legend{display:flex;gap:12px;margin-top:8px;flex-wrap:wrap}
.avail-leg-it{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--txt2)}
.avail-leg-dot{width:12px;height:12px;border-radius:3px;flex-shrink:0}

/* ═══ DANGER ZONE ═══ */
.danger-item{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:var(--r-sm);border:1px solid var(--border);margin-bottom:8px;background:var(--surface2)}
.danger-item:last-child{margin:0}
.danger-label{font-size:12.5px;font-weight:600;color:var(--txt)}
.danger-desc{font-size:11px;color:var(--txt3);margin-top:2px}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}
.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}
.btn-primary:hover{background:var(--teal-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--teal);color:var(--teal)}
.btn-danger{background:var(--red-dim);color:var(--red);border-color:var(--red)}
.btn-danger:hover{background:var(--red);color:#fff}
.btn-full{width:100%;justify-content:center}
.btn-sm{padding:5px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ SAVE BAR ═══ */
.save-bar{display:flex;gap:10px;margin-top:4px;padding:14px 18px;background:var(--surface2);border-radius:var(--r-sm);border:1px solid var(--border);align-items:center}
.save-hint{flex:1;font-size:11px;color:var(--txt3)}
.save-hint i{color:var(--teal);margin-right:4px}

/* ═══ PASSWORD STRENGTH ═══ */
.pwd-strength{margin-top:6px}
.pwd-bars{display:flex;gap:3px;margin-bottom:3px}
.pwd-bar{height:3px;flex:1;border-radius:10px;background:var(--border);transition:background .3s}
.pwd-label{font-size:10px;color:var(--txt3)}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:900px){.settings-layout{grid-template-columns:1fr}.settings-nav{position:static;display:flex;flex-wrap:wrap;gap:4px;padding:8px}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.form-row{grid-template-columns:1fr}.avail-grid{grid-template-columns:60px repeat(5,1fr)}}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-person-video3"></i></div>
    <div>
      <div class="logo-text">Edu<span>Connect</span></div>
      <div style="font-size:9px;color:rgba(255,255,255,.4)">Enseignant</div>
    </div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Principal</div>
    <a href="enseignant_dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></a>
    <a href="enseignant_cours.php" class="nav-item"><i class="bi bi-book-fill"></i><span class="nav-item-txt">Mes Cours</span></a>
    <a href="enseignant_emploi_du_temps.php" class="nav-item"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></a>
    <div class="nav-section">Pédagogie</div>
    <a href="enseignant_notes.php" class="nav-item"><i class="bi bi-pencil-square"></i><span class="nav-item-txt">Saisie des Notes</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_etudiants.php" class="nav-item"><i class="bi bi-people-fill"></i><span class="nav-item-txt">Mes Étudiants</span></a>
    <a href="enseignant_ressources.php" class="nav-item"><i class="bi bi-folder-fill"></i><span class="nav-item-txt">Ressources</span></a>
    <div class="nav-section">Communication</div>
    <a href="enseignant_messages.php" class="nav-item"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_notifications.php" class="nav-item"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <div class="nav-section">Compte</div>
    <a href="enseignant_profil.php" class="nav-item"><i class="bi bi-person-circle"></i><span class="nav-item-txt">Mon Profil</span></a>
    <a href="enseignant_parametres.php" class="nav-item active"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></a>
  </div>
  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Enseignant/enseignant_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role">Enseignante — Informatique</div>
      </div>
    </div>
  
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-gear"></i>Paramètres</div>
    <div class="topbar-right">
      <button class="tb-icon" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <div class="tb-user">
        <div class="tb-ava"><?= $u_init ?></div>
        <div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="inner">

      <div class="page-header">
        <div class="page-title">
          <i class="bi bi-gear"></i>
          <div><h1>Paramètres</h1><p>Gérez votre profil, disponibilités et préférences d'enseignement</p></div>
        </div>
      </div>

      <div class="settings-layout">

        <!-- Left nav rail -->
        <nav class="settings-nav">
          <button class="sn-item active" onclick="showSection('profile',this)"><i class="bi bi-person-fill"></i>Profil</button>
          <button class="sn-item" onclick="showSection('pedagogy',this)"><i class="bi bi-mortarboard"></i>Enseignement</button>
          <button class="sn-item" onclick="showSection('availability',this)"><i class="bi bi-calendar3"></i>Disponibilités</button>
          <button class="sn-item" onclick="showSection('notifications',this)"><i class="bi bi-bell"></i>Notifications</button>
          <button class="sn-item" onclick="showSection('display',this)"><i class="bi bi-palette"></i>Affichage</button>
          <div class="sn-separator"></div>
          <button class="sn-item" onclick="showSection('security',this)"><i class="bi bi-shield-lock"></i>Sécurité</button>
          <button class="sn-item" onclick="showSection('danger',this)" style="color:var(--red)"><i class="bi bi-exclamation-triangle"></i>Zone critique</button>
        </nav>

        <!-- Right content panels -->
        <div id="sectionsContainer">

          <!-- ── PROFILE ── -->
          <div id="sec-profile">
            <div class="card">
              <div class="card-head">
                <div class="card-head-icon" style="background:var(--teal-dim);color:var(--teal)"><i class="bi bi-person-fill"></i></div>
                <div><div class="card-head-title">Informations personnelles</div><div class="card-head-sub">Vos données professionnelles visibles par les étudiants</div></div>
              </div>
              <div class="card-body">
                <div class="profile-zone">
                  <div class="profile-ava">
                    FB
                    <div class="profile-ava-edit" onclick="toast('📷 Modification de la photo')"><i class="bi bi-camera-fill"></i></div>
                  </div>
                  <div class="profile-info">
                    <div class="profile-name"><?= $u_full ?></div>
                    <div class="profile-role"><?= $u_role ?></div>
                    <div class="profile-dept"><i class="bi bi-building"></i>Département Informatique — UCAO Saint-Michel</div>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-person"></i>Prénom</label>
                    <input class="form-input" type="text" value="Fatima">
                  </div>
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-person"></i>Nom</label>
                    <input class="form-input" type="text" value="Ba">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-award"></i>Titre académique</label>
                    <select class="form-input form-select">
                      <option>Dr.</option><option>Pr.</option><option>M.</option><option>Mme</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-briefcase"></i>Grade</label>
                    <select class="form-input form-select">
                      <option selected>Maître de Conférences</option>
                      <option>Professeur Titulaire</option>
                      <option>Assistant</option>
                      <option>Vacataire</option>
                    </select>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-envelope"></i>Email professionnel</label>
                    <input class="form-input" type="email" value="fatima.ba@ucao.sn">
                  </div>
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-phone"></i>Téléphone</label>
                    <input class="form-input" type="tel" value="+221 77 123 45 67">
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label"><i class="bi bi-building"></i>Département / Spécialité</label>
                  <input class="form-input" type="text" value="Informatique — Bases de données & Systèmes d'Information">
                </div>
                <div class="form-group">
                  <label class="form-label"><i class="bi bi-card-text"></i>Biographie courte</label>
                  <textarea class="form-input" rows="3" style="resize:vertical">Docteure en Informatique spécialisée en bases de données relationnelles et systèmes d'information. 8 ans d'expérience d'enseignement à l'UCAO.</textarea>
                  <div class="form-hint">Visible par les étudiants sur votre fiche enseignant.</div>
                </div>
                <div class="form-row">
                  <div class="form-group" style="margin:0">
                    <label class="form-label"><i class="bi bi-geo-alt"></i>Bureau</label>
                    <input class="form-input" type="text" value="Bâtiment B — Bureau 204">
                  </div>
                  <div class="form-group" style="margin:0">
                    <label class="form-label"><i class="bi bi-clock"></i>Heures de permanence</label>
                    <input class="form-input" type="text" value="Mer 10h–12h, Jeu 14h–16h">
                  </div>
                </div>
              </div>
            </div>
            <div class="save-bar">
              <span class="save-hint"><i class="bi bi-info-circle"></i>Les modifications sont visibles immédiatement dans l'annuaire.</span>
              <button class="btn btn-secondary btn-sm" onclick="toast('❌ Modifications annulées')">Annuler</button>
              <button class="btn btn-primary btn-sm" onclick="toast('✅ Profil mis à jour')"><i class="bi bi-check2"></i>Enregistrer</button>
            </div>
          </div>

          <!-- ── PEDAGOGY ── -->
          <div id="sec-pedagogy" style="display:none">
            <div class="card">
              <div class="card-head">
                <div class="card-head-icon" style="background:var(--blue-dim);color:var(--blue)"><i class="bi bi-mortarboard"></i></div>
                <div><div class="card-head-title">Préférences pédagogiques</div><div class="card-head-sub">Paramètres liés à votre activité d'enseignement</div></div>
              </div>
              <div class="card-body">
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-bar-chart"></i>Échelle de notation par défaut</label>
                    <select class="form-input form-select">
                      <option selected>Sur 20 points</option>
                      <option>Sur 100 points</option>
                      <option>Lettres (A–F)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-translate"></i>Langue d'enseignement</label>
                    <select class="form-input form-select">
                      <option selected>Français</option>
                      <option>Anglais</option>
                      <option>Français / Anglais</option>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label"><i class="bi bi-file-earmark-text"></i>Format des ressources pédagogiques</label>
                  <select class="form-input form-select">
                    <option selected>PDF uniquement</option>
                    <option>PDF + Vidéo</option>
                    <option>Tous formats acceptés</option>
                  </select>
                  <div class="form-hint">Détermine quels types de fichiers vous pouvez déposer sur la plateforme.</div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Publication automatique des ressources</div><div class="setting-desc">Les fichiers déposés sont visibles immédiatement par les étudiants inscrits</div></div>
                  <div class="toggle on" onclick="tog(this,'📚 Publication auto mise à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Autoriser les questions en ligne</div><div class="setting-desc">Les étudiants peuvent poser des questions sur les ressources directement sur la plateforme</div></div>
                  <div class="toggle on" onclick="tog(this,'💬 Questions en ligne mises à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Afficher la moyenne de la classe aux étudiants</div><div class="setting-desc">Chaque étudiant voit la moyenne de sa classe sur son tableau de bord</div></div>
                  <div class="toggle" onclick="tog(this,'📊 Affichage moyenne mis à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Signalement automatique des absences répétées</div><div class="setting-desc">Alerter l'administration si un étudiant dépasse 3 absences consécutives</div></div>
                  <div class="toggle on" onclick="tog(this,'⚠️ Signalement absences mis à jour')"><div class="toggle-dot"></div></div>
                </div>
              </div>
            </div>
            <div class="save-bar">
              <span class="save-hint"><i class="bi bi-info-circle"></i>Les préférences pédagogiques s'appliquent à tous vos cours.</span>
              <button class="btn btn-secondary btn-sm" onclick="toast('❌ Annulé')">Annuler</button>
              <button class="btn btn-primary btn-sm" onclick="toast('✅ Préférences enregistrées')"><i class="bi bi-check2"></i>Enregistrer</button>
            </div>
          </div>

          <!-- ── AVAILABILITY ── -->
          <div id="sec-availability" style="display:none">
            <div class="card">
              <div class="card-head">
                <div class="card-head-icon" style="background:var(--teal-dim);color:var(--teal)"><i class="bi bi-calendar3"></i></div>
                <div><div class="card-head-title">Disponibilités & permanences</div><div class="card-head-sub">Indiquez vos créneaux libres pour les étudiants</div></div>
              </div>
              <div class="card-body">
                <div style="font-size:12px;color:var(--txt2);margin-bottom:12px;line-height:1.5">Cliquez sur les cases pour basculer entre <span style="color:var(--teal);font-weight:600">Disponible</span>, <span style="color:var(--blue);font-weight:600">Cours programmé</span> et libre. Les étudiants verront vos permanences dans l'annuaire.</div>

                <div class="avail-grid" id="availGrid"></div>

                <div class="avail-legend">
                  <div class="avail-leg-it"><div class="avail-leg-dot" style="background:var(--teal-dim);border:1.5px solid var(--teal)"></div>Disponible (permanence)</div>
                  <div class="avail-leg-it"><div class="avail-leg-dot" style="background:rgba(50,110,169,.15);border:1.5px solid var(--blue)"></div>Cours programmé</div>
                  <div class="avail-leg-it"><div class="avail-leg-dot" style="background:var(--surface2);border:1.5px solid var(--border)"></div>Non disponible</div>
                </div>

                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
                  <div class="form-group" style="margin-bottom:10px">
                    <label class="form-label"><i class="bi bi-chat-text"></i>Message de disponibilité (affiché aux étudiants)</label>
                    <textarea class="form-input" rows="2" style="resize:vertical">Pour toute question urgente hors permanence, merci de passer par la messagerie EduConnect. Je réponds sous 48h.</textarea>
                  </div>
                  <div class="setting-item" style="padding-top:0">
                    <div class="setting-left"><div class="setting-label">Accepter les prises de rendez-vous en ligne</div><div class="setting-desc">Les étudiants peuvent réserver un créneau de permanence directement sur EduConnect</div></div>
                    <div class="toggle on" onclick="tog(this,'📅 Réservations RDV mises à jour')"><div class="toggle-dot"></div></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="save-bar">
              <span class="save-hint"><i class="bi bi-info-circle"></i>Vos disponibilités sont publiées dans l'annuaire de l'université.</span>
              <button class="btn btn-secondary btn-sm" onclick="toast('❌ Annulé')">Annuler</button>
              <button class="btn btn-primary btn-sm" onclick="toast('✅ Disponibilités mises à jour')"><i class="bi bi-check2"></i>Enregistrer</button>
            </div>
          </div>

          <!-- ── NOTIFICATIONS ── -->
          <div id="sec-notifications" style="display:none">
            <div class="card">
              <div class="card-head">
                <div class="card-head-icon" style="background:var(--orange-dim);color:var(--orange)"><i class="bi bi-bell-fill"></i></div>
                <div><div class="card-head-title">Notifications académiques</div><div class="card-head-sub">Choisissez les alertes que vous souhaitez recevoir</div></div>
              </div>
              <div class="card-body">
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Rappel avant chaque cours</div><div class="setting-desc">30 minutes avant le début de chaque session</div></div>
                  <div class="toggle on" onclick="tog(this,'⏰ Rappels cours mis à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Délai de saisie des notes expirant</div><div class="setting-desc">Alerte 48h avant la date limite de remise des notes</div></div>
                  <div class="toggle on" onclick="tog(this,'📝 Alertes notes mises à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Nouveau message d'un étudiant</div><div class="setting-desc">Notification dès qu'un étudiant vous envoie un message</div></div>
                  <div class="toggle on" onclick="tog(this,'💬 Notifications messages mises à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Absences répétées signalées</div><div class="setting-desc">Quand un étudiant dépasse 3 absences consécutives dans votre cours</div></div>
                  <div class="toggle on" onclick="tog(this,'🚨 Alertes absences mises à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Modification de l'emploi du temps</div><div class="setting-desc">Si un cours est déplacé ou annulé par l'administration</div></div>
                  <div class="toggle on" onclick="tog(this,'📅 Alertes EDT mises à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Rapport mensuel d'activité</div><div class="setting-desc">Résumé mensuel : heures dispensées, moyennes, taux de présence</div></div>
                  <div class="toggle" onclick="tog(this,'📊 Rapport mensuel mis à jour')"><div class="toggle-dot"></div></div>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card-head">
                <div class="card-head-icon" style="background:var(--blue-dim);color:var(--blue)"><i class="bi bi-send-fill"></i></div>
                <div><div class="card-head-title">Canaux de notification</div><div class="card-head-sub">Comment voulez-vous recevoir vos alertes ?</div></div>
              </div>
              <div class="card-body">
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Notifications in-app</div><div class="setting-desc">Dans la barre de notifications EduConnect</div></div>
                  <div class="toggle on" onclick="tog(this,'🔔 In-app mis à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Email professionnel</div><div class="setting-desc">Envoi vers fatima.ba@ucao.sn</div></div>
                  <div class="toggle on" onclick="tog(this,'📧 Email mis à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">SMS</div><div class="setting-desc">Uniquement pour les alertes urgentes (délai de notes, annulation cours)</div></div>
                  <div class="toggle" onclick="tog(this,'📱 SMS mis à jour')"><div class="toggle-dot"></div></div>
                </div>
              </div>
            </div>
            <div class="save-bar">
              <span class="save-hint"><i class="bi bi-info-circle"></i>Modifications appliquées immédiatement.</span>
              <button class="btn btn-primary btn-sm" onclick="toast('✅ Notifications enregistrées')"><i class="bi bi-check2"></i>Enregistrer</button>
            </div>
          </div>

          <!-- ── DISPLAY ── -->
          <div id="sec-display" style="display:none">
            <div class="card">
              <div class="card-head">
                <div class="card-head-icon" style="background:var(--purple-dim);color:var(--purple)"><i class="bi bi-palette-fill"></i></div>
                <div><div class="card-head-title">Thème et affichage</div><div class="card-head-sub">Personnalisez l'interface selon vos préférences</div></div>
              </div>
              <div class="card-body">
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Mode sombre</div><div class="setting-desc">Activer l'interface sombre — repose les yeux en soirée</div></div>
                  <div class="toggle" onclick="tog(this,'🌙 Mode sombre activé')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Sidebar réduite par défaut</div><div class="setting-desc">La barre latérale est compacte au chargement des pages</div></div>
                  <div class="toggle" onclick="tog(this,'📐 Sidebar mise à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="setting-item">
                  <div class="setting-left"><div class="setting-label">Animations de l'interface</div><div class="setting-desc">Transitions et effets visuels sur les composants</div></div>
                  <div class="toggle on" onclick="tog(this,'✨ Animations mises à jour')"><div class="toggle-dot"></div></div>
                </div>
                <div class="form-group" style="margin-top:14px;margin-bottom:0">
                  <label class="form-label"><i class="bi bi-translate"></i>Langue de l'interface</label>
                  <select class="form-input form-select">
                    <option selected>Français</option>
                    <option>English</option>
                    <option>Wolof (bêta)</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="save-bar">
              <span class="save-hint"><i class="bi bi-info-circle"></i>Le changement de langue recharge la page.</span>
              <button class="btn btn-secondary btn-sm" onclick="toast('❌ Annulé')">Annuler</button>
              <button class="btn btn-primary btn-sm" onclick="toast('✅ Affichage mis à jour')"><i class="bi bi-check2"></i>Appliquer</button>
            </div>
          </div>

          <!-- ── SECURITY ── -->
          <div id="sec-security" style="display:none">
            <div class="card">
              <div class="card-head">
                <div class="card-head-icon" style="background:var(--green-dim);color:var(--green)"><i class="bi bi-shield-check"></i></div>
                <div><div class="card-head-title">Sécurité du compte</div><div class="card-head-sub">Protégez votre accès EduConnect</div></div>
              </div>
              <div class="card-body">
                <div style="background:var(--green-dim);border-left:3px solid var(--green);border-radius:var(--r-sm);padding:10px 12px;margin-bottom:16px;font-size:12px;color:#1a5e34">
                  <i class="bi bi-shield-check"></i> <strong>Compte sécurisé.</strong> Dernière connexion : Aujourd'hui à 08:14 depuis Dakar, Sénégal.
                </div>
                <div class="form-group">
                  <label class="form-label"><i class="bi bi-lock"></i>Mot de passe actuel</label>
                  <input class="form-input" type="password" id="pwdCurrent" placeholder="••••••••" oninput="checkPwdStrength()">
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-lock-fill"></i>Nouveau mot de passe</label>
                    <input class="form-input" type="password" id="pwdNew" placeholder="Min. 8 caractères" oninput="checkPwdStrength()">
                    <div class="pwd-strength" id="pwdStrength" style="display:none">
                      <div class="pwd-bars"><div class="pwd-bar" id="pb1"></div><div class="pwd-bar" id="pb2"></div><div class="pwd-bar" id="pb3"></div><div class="pwd-bar" id="pb4"></div></div>
                      <div class="pwd-label" id="pwdLabel"></div>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label"><i class="bi bi-lock-fill"></i>Confirmer le mot de passe</label>
                    <input class="form-input" type="password" id="pwdConfirm" placeholder="Répéter le mot de passe">
                  </div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="changePwd()"><i class="bi bi-shield-check"></i>Changer le mot de passe</button>

                <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border)">
                  <div style="font-size:12px;font-weight:700;color:var(--txt);margin-bottom:10px;display:flex;align-items:center;gap:6px"><i class="bi bi-phone" style="color:var(--teal)"></i>Authentification à deux facteurs</div>
                  <div class="setting-item" style="padding-top:0">
                    <div class="setting-left"><div class="setting-label">Activer l'A2F par SMS</div><div class="setting-desc">Un code à 6 chiffres vous sera envoyé à chaque connexion</div></div>
                    <div class="toggle" onclick="tog(this,'🔐 A2F mise à jour')"><div class="toggle-dot"></div></div>
                  </div>
                </div>

                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                  <div style="font-size:12px;font-weight:700;color:var(--txt);margin-bottom:10px;display:flex;align-items:center;gap:6px"><i class="bi bi-clock-history" style="color:var(--teal)"></i>Sessions actives</div>
                  <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 12px;display:flex;align-items:center;justify-content:space-between">
                    <div><div style="font-size:12.5px;font-weight:600">Chrome — Dakar, Sénégal</div><div style="font-size:11px;color:var(--txt3)">Session actuelle · Aujourd'hui 08:14</div></div>
                    <span style="font-size:10px;font-weight:700;padding:3px 8px;background:var(--green-dim);color:var(--green);border-radius:4px">Actuelle</span>
                  </div>
                  <button class="btn btn-sm" style="margin-top:8px;color:var(--red);background:var(--red-dim);border:1px solid var(--red)" onclick="toast('🚪 Toutes les autres sessions ont été déconnectées')">
                    <i class="bi bi-box-arrow-right"></i>Déconnecter toutes les autres sessions
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- ── DANGER ZONE ── -->
          <div id="sec-danger" style="display:none">
            <div class="card" style="border-color:rgba(196,30,58,.3)">
              <div class="card-head" style="background:var(--red-dim)">
                <div class="card-head-icon" style="background:var(--red-dim);color:var(--red)"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div><div class="card-head-title" style="color:var(--red)">Zone critique</div><div class="card-head-sub">Actions irréversibles — procédez avec précaution</div></div>
              </div>
              <div class="card-body">
                <div class="danger-item">
                  <div><div class="danger-label">Exporter mes données</div><div class="danger-desc">Télécharger l'intégralité de vos données (cours, notes, ressources) au format JSON</div></div>
                  <button class="btn btn-sm btn-secondary" onclick="toast('📦 Export de vos données lancé')"><i class="bi bi-download"></i>Exporter</button>
                </div>
                <div class="danger-item">
                  <div><div class="danger-label">Archiver mes cours</div><div class="danger-desc">Archiver tous les cours du semestre en cours (irréversible avant validation admin)</div></div>
                  <button class="btn btn-sm" style="color:var(--orange);background:var(--orange-dim);border:1px solid var(--orange)" onclick="toast('📦 Archivage demandé — en attente de validation')"><i class="bi bi-archive"></i>Archiver</button>
                </div>
                <div class="danger-item">
                  <div><div class="danger-label">Supprimer mon compte</div><div class="danger-desc">Suppression définitive de votre compte et de toutes vos données. Cette action est irréversible et nécessite la validation de la direction.</div></div>
                  <button class="btn btn-sm btn-danger" onclick="toast('⚠️ Demande transmise à l\'administration pour validation')"><i class="bi bi-trash3"></i>Demander la suppression</button>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /sectionsContainer -->
      </div><!-- /settings-layout -->
    </div><!-- /inner -->
  </div><!-- /content -->
</main>

<div class="toast" id="toast"></div>

<script>
/* ══ SECTION NAV ══ */
function showSection(id, btn){
  document.querySelectorAll('[id^="sec-"]').forEach(el=>el.style.display='none');
  document.getElementById('sec-'+id).style.display = '';
  document.querySelectorAll('.sn-item').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

/* ══ AVAILABILITY GRID ══ */
const AVAIL_TIMES = ['08:00','09:00','10:00','11:15','14:00','15:00','16:00','17:00','18:00','19:00'];
const AVAIL_DAYS = ['Lun','Mar','Mer','Jeu','Ven','Sam'];
// state: 0=free, 1=available(perm), 2=class
const AVAIL_STATE = [];  // Disponibilités — gérées via mentor_disponibilites

function buildAvailGrid(){
  const grid = document.getElementById('availGrid');
  let html = '<div></div>';
  AVAIL_DAYS.forEach(d=>{ html+=`<div class="avail-header">${d}</div>`; });
  AVAIL_TIMES.forEach((t,ti)=>{
    html+=`<div class="avail-time">${t}</div>`;
    AVAIL_DAYS.forEach((_,di)=>{
      const s = AVAIL_STATE[ti][di];
      const cls = s===1?'busy':s===2?'class':'';
      const lbl = s===1?'✓':s===2?'Cours':'';
      html+=`<div class="avail-cell ${cls}" onclick="cycleAvail(${ti},${di})" title="${t} — ${AVAIL_DAYS[di]}">${lbl}</div>`;
    });
  });
  grid.innerHTML = html;
}

function cycleAvail(ti,di){
  AVAIL_STATE[ti][di] = (AVAIL_STATE[ti][di]+1) % 3;
  buildAvailGrid();
}

/* ══ TOGGLE ══ */
function tog(el, msg){
  el.classList.toggle('on');
  toast(msg);
}

/* ══ PASSWORD STRENGTH ══ */
function checkPwdStrength(){
  const pwd = document.getElementById('pwdNew').value;
  const s = document.getElementById('pwdStrength');
  if(!pwd){ s.style.display='none'; return; }
  s.style.display='block';
  let score = 0;
  if(pwd.length>=8)  score++;
  if(/[A-Z]/.test(pwd)) score++;
  if(/[0-9]/.test(pwd)) score++;
  if(/[^A-Za-z0-9]/.test(pwd)) score++;
  const colors = ['#C41E3A','#e67e22','#D4A843','#27ae60'];
  const labels = ['Très faible','Faible','Moyen','Fort'];
  [1,2,3,4].forEach(i=>{
    const bar = document.getElementById('pb'+i);
    bar.style.background = i<=score ? colors[score-1] : 'var(--border)';
  });
  document.getElementById('pwdLabel').textContent = labels[score-1]||'';
  document.getElementById('pwdLabel').style.color = colors[score-1]||'var(--txt3)';
}

function changePwd(){
  const cur  = document.getElementById('pwdCurrent').value;
  const nw   = document.getElementById('pwdNew').value;
  const conf = document.getElementById('pwdConfirm').value;
  if(!cur||!nw||!conf){ toast('⚠️ Veuillez remplir tous les champs'); return; }
  if(nw!==conf){ toast('⚠️ Les mots de passe ne correspondent pas'); return; }
  if(nw.length<8){ toast('⚠️ Minimum 8 caractères requis'); return; }
  toast('✅ Mot de passe mis à jour avec succès');
  document.getElementById('pwdCurrent').value='';
  document.getElementById('pwdNew').value='';
  document.getElementById('pwdConfirm').value='';
  document.getElementById('pwdStrength').style.display='none';
}

/* ══ SIDEBAR ══ */
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

/* ══ TOAST ══ */
let toastT;
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg; el.classList.add('show');
  clearTimeout(toastT); toastT=setTimeout(()=>el.classList.remove('show'),3200);
}

/* ══ INIT ══ */
document.addEventListener('DOMContentLoaded', buildAvailGrid);
</script>
</body>
</html>
