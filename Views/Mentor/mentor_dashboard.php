<?php
/**
 * EduConnect — mentor_dashboard.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('mentor');
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Mentor';
$current_page = basename(__FILE__);

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Dashboard Mentor
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$mentor = db_row($conn,
    "SELECT id, note_moyenne, nb_sessions, nb_etudiants, taux_satisfaction, actif
     FROM mentors WHERE user_id = ?",
    "i", [$uid]
);
$mentor_id = (int)($mentor["id"] ?? 0);

// Sessions à venir
$sessions_mentor_venir = db_query($conn,
    "SELECT sm.id, sm.date_session, sm.heure_debut, sm.heure_fin, sm.type_session,
            m.nom AS module_nom, CONCAT(u.prenom,' ',u.nom) AS etudiant_nom
     FROM sessions_mentorat sm
     JOIN modules m ON m.id = sm.module_id
     JOIN etudiants e ON e.id = sm.etudiant_id
     JOIN users u ON u.id = e.user_id
     WHERE sm.mentor_id = ? AND sm.statut = 'confirmee' AND sm.date_session >= CURDATE()
     ORDER BY sm.date_session ASC LIMIT 5",
    "i", [$mentor_id]
);

// Demandes en attente
$demandes_attente = db_query($conn,
    "SELECT sm.id, sm.date_session, sm.heure_debut, m.nom AS module_nom,
            CONCAT(u.prenom,' ',u.nom) AS etudiant_nom
     FROM sessions_mentorat sm
     JOIN modules m ON m.id = sm.module_id
     JOIN etudiants e ON e.id = sm.etudiant_id
     JOIN users u ON u.id = e.user_id
     WHERE sm.mentor_id = ? AND sm.statut = 'demandee'
     ORDER BY sm.created_at ASC",
    "i", [$mentor_id]
);

// Modules enseignés par ce mentor
$modules_mentor = db_query($conn,
    "SELECT m.nom, mm.note_obtenue FROM mentor_modules mm
     JOIN modules m ON m.id = mm.module_id WHERE mm.mentor_id = ?",
    "i", [$mentor_id]
);

// Traitement POST — accepter/refuser demande
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $sm_id = (int)($_POST["session_id"] ?? 0);
    $act   = $_POST["action"] ?? "";
    if ($sm_id && in_array($act,["accepter","refuser"])) {
        $new_st = $act === "accepter" ? "confirmee" : "annulee";
        db_exec($conn,"UPDATE sessions_mentorat SET statut=? WHERE id=? AND mentor_id=?",
                "sii",[$new_st,$sm_id,$mentor_id]);
        header("Location: mentor_dashboard.php?ok=1"); exit;
    }
}

$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id=? AND lu=0","i",[$uid])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>


<?php /** EduConnect — Mentor Dashboard v1 — GLPI-inspired layout */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Mentor Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
  --teal:#0891b2;--teal-dim:rgba(8,145,178,.12);
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
.sidebar{
  width:var(--sidebar-w);background:var(--blue-dk);
  position:fixed;height:100vh;z-index:400;
  display:flex;flex-direction:column;
  transition:width var(--tr);overflow:hidden;
}
.sidebar.collapsed{width:54px}

.logo-zone{
  height:var(--topbar-h);padding:0 14px;
  display:flex;align-items:center;gap:10px;
  border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0;
}
.logo-mark{
  width:32px;height:32px;border-radius:7px;
  background:var(--blue-lt);display:flex;align-items:center;
  justify-content:center;font-size:17px;color:#fff;flex-shrink:0;
  box-shadow:0 2px 8px rgba(50,110,169,.5);
}
.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}
.logo-text span{color:#a8c8e8}
.sidebar-toggle{
  margin-left:auto;width:24px;height:24px;border-radius:5px;
  display:flex;align-items:center;justify-content:center;
  color:rgba(255,255,255,.5);font-size:14px;
  transition:all var(--tr);flex-shrink:0;cursor:pointer;
}
.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}

.sidebar-search{
  padding:10px 10px 6px;flex-shrink:0;
  overflow:hidden;transition:all var(--tr);
}
.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}
.search-field{
  display:flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:var(--r-sm);
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);
}
.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}
.search-field input{
  background:none;border:none;outline:none;color:#fff;
  font-family:'Ubuntu',sans-serif;font-size:12px;width:100%;
}
.search-field input::placeholder{color:rgba(255,255,255,.4)}

.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}
.nav-body::-webkit-scrollbar{display:none}
.nav-section{
  font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;
  color:rgba(255,255,255,.3);padding:10px 8px 4px;
  white-space:nowrap;overflow:hidden;transition:opacity var(--tr);
}
.sidebar.collapsed .nav-section{opacity:0}
.nav-item{
  display:flex;align-items:center;gap:9px;
  padding:8px 10px;border-radius:var(--r-sm);
  color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;
  transition:all var(--tr);margin-bottom:1px;
  width:100%;text-align:left;cursor:pointer;white-space:nowrap;
  position:relative;overflow:hidden;
}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}
.nav-item.active::before{
  content:'';position:absolute;left:0;top:20%;bottom:20%;
  width:3px;background:var(--blue-lt);border-radius:0 2px 2px 0;
}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--blue-lt)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{
  background:var(--red);font-size:9px;font-weight:700;
  padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;
  transition:opacity var(--tr);
}
.sidebar.collapsed .nav-badge{opacity:0}

.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{
  display:flex;align-items:center;gap:9px;padding:8px 10px;
  border-radius:var(--r-sm);background:rgba(255,255,255,.07);
  cursor:pointer;transition:background var(--tr);overflow:hidden;
}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{
  width:30px;height:30px;border-radius:7px;
  background:linear-gradient(135deg,var(--blue),var(--blue-dk));
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:11px;color:#fff;flex-shrink:0;
}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}

/* ═══ MAIN ═══ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}

/* ═══ TOPBAR ═══ */
.topbar{
  height:var(--topbar-h);background:var(--surface);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 20px;gap:12px;
  position:sticky;top:0;z-index:200;
  box-shadow:var(--sh);
}
.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}

.tab-nav{
  display:flex;align-items:center;gap:1px;
  background:var(--surface2);border:1px solid var(--border);
  border-radius:var(--r-sm);padding:3px;
  overflow-x:auto;scrollbar-width:none;
}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{
  display:flex;align-items:center;gap:5px;
  padding:5px 12px;border-radius:4px;
  font-size:12px;font-weight:500;color:var(--txt2);
  white-space:nowrap;transition:all var(--tr);cursor:pointer;
}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--blue);color:#fff;box-shadow:0 1px 4px rgba(50,110,169,.3)}
.tab-btn i{font-size:13px}

.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-search{
  display:flex;align-items:center;gap:7px;
  padding:6px 12px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  min-width:200px;
}
.tb-search:focus-within{border-color:var(--blue);background:#fff}
.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}
.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}
.tb-search input::placeholder{color:var(--txt3)}
.tb-icon{
  width:32px;height:32px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;
  position:relative;
}
.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{
  display:flex;align-items:center;gap:7px;
  padding:4px 10px 4px 4px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  cursor:pointer;transition:all var(--tr);
}
.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}
.tb-ava{
  width:28px;height:28px;border-radius:6px;
  background:linear-gradient(135deg,var(--blue),var(--blue-dk));
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:11px;color:#fff;flex-shrink:0;
}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1}
.page-panel{display:none;animation:fadeUp .3s ease both}
#page-dashboard{display:block}

/* ═══ STAT TILES ═══ */
.tiles-row{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.stat-tile{
  display:flex;align-items:center;gap:10px;
  padding:10px 14px;border-radius:var(--r-sm);
  color:#fff;min-width:120px;cursor:pointer;
  transition:all var(--tr);position:relative;overflow:hidden;
  flex:1;min-width:110px;max-width:160px;
}
.stat-tile:hover{transform:translateY(-2px);box-shadow:var(--sh-md);filter:brightness(1.05)}
.stat-tile-icon{
  width:36px;height:36px;border-radius:6px;
  background:rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;flex-shrink:0;
}
.stat-tile-val{font-size:22px;font-weight:700;line-height:1}
.stat-tile-lbl{font-size:11px;opacity:.9;margin-top:1px}

.tile-blue  {background:linear-gradient(135deg,var(--blue),var(--blue-lt))}
.tile-green {background:linear-gradient(135deg,var(--green),var(--green-lt))}
.tile-orange{background:linear-gradient(135deg,var(--orange),#f39c12)}
.tile-purple{background:linear-gradient(135deg,var(--purple),#9b59b6)}
.tile-teal  {background:linear-gradient(135deg,var(--teal),#06b6d4)}

/* ═══ WIDGETS ═══ */
.widget-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px}
.widget{
  background:var(--surface);border-radius:var(--r);
  border:1px solid var(--border);box-shadow:var(--sh);
  overflow:hidden;transition:all var(--tr);
}
.widget:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.widget-head{
  padding:14px 16px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.widget-title{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:6px}
.widget-title i{color:var(--blue);font-size:14px}
.widget-body{padding:14px 16px}
.widget-actions{display:flex;gap:4px}
.w-btn{
  width:28px;height:28px;border-radius:5px;
  background:var(--surface2);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  color:var(--txt2);font-size:12px;cursor:pointer;
  transition:all var(--tr);
}
.w-btn:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}

/* ═══ MINI STATS ═══ */
.mini-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin-bottom:16px}
.mini-stat{
  background:var(--surface);border-radius:var(--r-sm);
  border:1px solid var(--border);padding:10px 12px;
  display:flex;align-items:center;justify-content:space-between;
  cursor:pointer;transition:all var(--tr);
}
.mini-stat:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.mini-stat-val{font-size:18px;font-weight:700;color:var(--blue)}
.mini-stat-lbl{font-size:10px;color:var(--txt3);margin-top:1px}
.mini-stat-ico{color:var(--txt3);font-size:20px;opacity:.6}

/* ═══ TABLES ═══ */
.table{width:100%;border-collapse:collapse}
.table th{
  padding:10px 12px;text-align:left;font-size:11px;font-weight:700;
  color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;
  border-bottom:2px solid var(--border);background:var(--surface2);
}
.table td{
  padding:10px 12px;border-bottom:1px solid var(--border);
  font-size:12px;color:var(--txt2);
}
.table tr:hover{background:var(--surface2)}
.table tr:last-child td{border:none}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-red{background:var(--red-dim);color:var(--red)}

/* ═══ TOAST ═══ */
.toast{
  position:fixed;bottom:20px;right:20px;
  background:var(--txt);color:#fff;
  padding:12px 16px;border-radius:var(--r-sm);
  font-size:12.5px;font-weight:500;
  box-shadow:var(--sh-lg);
  transform:translateY(100px);opacity:0;
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  z-index:9999;border-left:3px solid var(--blue);
}
.toast.show{transform:translateY(0);opacity:1}

/* ═══ RESPONSIVE ═══ */
@media(max-width:1200px){
  .widget-grid{grid-template-columns:1fr}
  .mini-stats{grid-template-columns:repeat(auto-fit,minmax(100px,1fr))}
}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .main{margin-left:0}
  .content{padding:12px 14px}
  .tiles-row{flex-direction:column}
  .stat-tile{max-width:none}
}
</style>
</head>
<body>

<!-- ════════════════════ SIDEBAR ════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-star-fill"></i></div>
    <div>
      <div class="logo-text">Edu<span>Connect</span></div>
      <div style="font-size:9px;color:rgba(255,255,255,.4)">Mentor</div>
    </div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>

  <div class="sidebar-search">
    <div class="search-field">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Rechercher…">
    </div>
  </div>

  <div class="nav-body">
    <div class="nav-section">Menu Principal</div>

    <button class="nav-item active" onclick="nav('dashboard',this)">
      <i class="bi bi-speedometer2"></i>
      <span class="nav-item-txt">Tableau de Bord</span>
    </button>
    <button class="nav-item <?= $current_page === 'mentor_etudiants.php' ? 'active' : '' ?>" onclick="location.href='/Views/Mentor/mentor_etudiants.php'">
      <i class="bi bi-people-fill"></i>
      <span class="nav-item-txt">Mes Étudiants</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>
    <button class="nav-item <?= $current_page === 'mentor_sessions.php' ? 'active' : '' ?>" onclick="location.href='/Views/Mentor/mentor_sessions.php'">
      <i class="bi bi-calendar-check"></i>
      <span class="nav-item-txt">Sessions</span>
    </button>
    <button class="nav-item <?= $current_page === 'mentor_messages.php' ? 'active' : '' ?>" onclick="location.href='/Views/Mentor/mentor_messages.php'">
      <i class="bi bi-chat-dots"></i>
      <span class="nav-item-txt">Messages</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>

    <div class="nav-section">Académique</div>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-graph-up"></i>
      <span class="nav-item-txt">Progression</span>
    </button>
    <button class="nav-item <?= $current_page === 'mentor_ressources.php' ? 'active' : '' ?>" onclick="location.href='/Views/Mentor/mentor_ressources.php'">
      <i class="bi bi-book"></i>
      <span class="nav-item-txt">Ressources</span>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-bell"></i>
      <span class="nav-item-txt">Notifications</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>
  </div>

  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Mentor/mentor_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role">Mentor Excellence</div>
      </div>
    </div>
  
  <a href="/Views/Mentor/mentor_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- ════════════════════ MAIN ════════════════════ -->
<main class="main" id="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-brand">
      <i class="bi bi-star-fill"></i>
      EduConnect Mentor
    </div>

    <div class="tab-nav" id="tabNav">
      <button class="tab-btn active" onclick="setTab('overview',this)"><i class="bi bi-speedometer2"></i>Vue d'ensemble</button>
      <button class="tab-btn" onclick="setTab('personal',this)"><i class="bi bi-person"></i>Vue personnelle</button>
      <button class="tab-btn" onclick="setTab('students',this)"><i class="bi bi-people"></i>Mes étudiants</button>
    </div>

    <div class="topbar-right">
      <div class="tb-search">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Rechercher…">
      </div>
      <button class="tb-icon" onclick="toast('🔔 Vous avez 2 notifications')" title="Notifications">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <button class="tb-icon" onclick="toast('📥 Export lancé')" title="Exporter">
        <i class="bi bi-download"></i>
      </button>
      <div class="tb-user" onclick="toast('Profil Mentor')">
        <div class="tb-ava"><?= $u_init ?></div>
        <div>
          <div class="tb-uname"><?= $u_full ?></div>
          <div class="tb-uentity"><?= $u_role ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <!-- ══════════════ DASHBOARD ══════════════ -->
    <div id="page-dashboard" class="page-panel">

      <!-- Period selector -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:13px;font-weight:600;color:var(--txt)">
          <i class="bi bi-calendar3" style="color:var(--blue)"></i>
          Tableau de bord — <span id="periodLabel">Cette semaine</span>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
          <div style="display:flex;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;gap:2px">
            <button class="tab-btn active" style="font-size:11px;padding:4px 10px" onclick="setPeriod('week',this,'Cette semaine')">Semaine</button>
            <button class="tab-btn" style="font-size:11px;padding:4px 10px" onclick="setPeriod('month',this,'Ce mois')">Mois</button>
            <button class="tab-btn" style="font-size:11px;padding:4px 10px" onclick="setPeriod('year',this,'Cette année')">Année</button>
          </div>
        </div>
      </div>

      <!-- Stat Tiles -->
      <div class="tiles-row">
        <div class="stat-tile tile-blue">
          <div class="stat-tile-icon"><i class="bi bi-people-fill"></i></div>
          <div><div class="stat-tile-val"><?= (int)($mentor["nb_sessions"]??0) ?></div><div class="stat-tile-lbl">Étudiants suivis</div></div>
        </div>
        <div class="stat-tile tile-green">
          <div class="stat-tile-icon"><i class="bi bi-calendar-check"></i></div>
          <div><div class="stat-tile-val"><?= (int)($mentor["nb_etudiants"]??0) ?></div><div class="stat-tile-lbl">Sessions complétées</div></div>
        </div>
        <div class="stat-tile tile-orange">
          <div class="stat-tile-icon"><i class="bi bi-graph-up"></i></div>
          <div><div class="stat-tile-val">+18%</div><div class="stat-tile-lbl">Progression moyenne</div></div>
        </div>
        <div class="stat-tile tile-purple">
          <div class="stat-tile-icon"><i class="bi bi-star-fill"></i></div>
          <div><div class="stat-tile-val">4.8</div><div class="stat-tile-lbl">Évaluation</div></div>
        </div>
        <div class="stat-tile tile-teal">
          <div class="stat-tile-icon"><i class="bi bi-chat-dots"></i></div>
          <div><div class="stat-tile-val"><?= count($demandes_attente) ?></div><div class="stat-tile-lbl">Messages non lus</div></div>
        </div>
      </div>

      <!-- Mini stats -->
      <div class="mini-stats">
        <div class="mini-stat">
          <div><div class="mini-stat-val">12</div><div class="mini-stat-lbl">Étudiants</div></div>
          <i class="bi bi-people mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">8</div><div class="mini-stat-lbl">Actifs cette semaine</div></div>
          <i class="bi bi-activity mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">4</div><div class="mini-stat-lbl">Excellents progrès</div></div>
          <i class="bi bi-arrow-up mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">2</div><div class="mini-stat-lbl">Nécessitent aide</div></div>
          <i class="bi bi-exclamation-circle mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">24</div><div class="mini-stat-lbl">Heures mentorat</div></div>
          <i class="bi bi-clock mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">92%</div><div class="mini-stat-lbl">Taux satisfaction</div></div>
          <i class="bi bi-hand-thumbs-up mini-stat-ico"></i>
        </div>
      </div>

      <!-- Widgets -->
      <div class="widget-grid">
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-graph-up"></i>Progression des étudiants</div>
            <div class="widget-actions">
              <div class="w-btn" onclick="toast('📊 Graphique mis à jour')"><i class="bi bi-arrow-repeat"></i></div>
            </div>
          </div>
          <div class="widget-body">
            <canvas id="progressChart" height="200"></canvas>
          </div>
        </div>

        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-calendar-check"></i>Sessions cette semaine</div>
            <div class="widget-actions">
              <div class="w-btn" onclick="toast('➕ Ajouter session')"><i class="bi bi-plus"></i></div>
            </div>
          </div>
          <div class="widget-body">
            <table class="table">
              <thead>
                <tr>
                  <th>Étudiant</th>
                  <th>Date</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <!-- données chargées depuis $sessions_mentor_venir ou $demandes_attente -->
                </tbody>
            </table>
          </div>
        </div>

        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-chat-dots"></i>Messages récents</div>
            <div class="widget-actions">
              <div class="w-btn" onclick="toast('✉️ Voir tous les messages')"><i class="bi bi-arrow-right"></i></div>
            </div>
          </div>
          <div class="widget-body" style="max-height:250px;overflow-y:auto">
            <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:flex-start">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--blue-dim);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--blue);flex-shrink:0">MS</div>
              <div style="flex:1;min-width:0">
                <div style="font-size:12px;font-weight:600;color:var(--txt)">Marie Sow</div>
                <div style="font-size:11px;color:var(--txt3);margin-top:2px">Merci pour votre aide hier !</div>
                <div style="font-size:10px;color:var(--txt3);margin-top:3px">Il y a 2h</div>
              </div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:flex-start">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--green-dim);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:var(--green);flex-shrink:0">AD</div>
              <div style="flex:1;min-width:0">
                <div style="font-size:12px;font-weight:600;color:var(--txt)">Ahmed Diallo</div>
                <div style="font-size:11px;color:var(--txt3);margin-top:2px">Peux-tu m'expliquer l'exercice 5 ?</div>
                <div style="font-size:10px;color:var(--txt3);margin-top:3px">Il y a 4h</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

  </div>

</main>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

function nav(page,btn){
  document.querySelectorAll('.page-panel').forEach(p=>p.style.display='none');
  document.getElementById('page-'+page).style.display='block';
  document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

function setTab(tab,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  toast('📊 Vue: '+tab);
}

function setPeriod(period,btn,label){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('periodLabel').textContent=label;
}

function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;
  el.classList.add('show');
  setTimeout(()=>el.classList.remove('show'),3000);
}

document.addEventListener('DOMContentLoaded',()=>{
  const ctx=document.getElementById('progressChart').getContext('2d');
  new Chart(ctx,{
    type:'line',
    data:{
      labels:['Sem 1','Sem 2','Sem 3','Sem 4','Sem 5','Sem 6'],
      datasets:[
        {label:'Marie Sow',data: <?= json_encode(array_map(fn($r)=>(float)$r['note_moy'],array_slice($evol_notes,0,6))) ?>,borderColor:'#27ae60',backgroundColor:'rgba(39,174,96,.1)',tension:.4,fill:true},
        {label:'Ahmed Diallo',data: <?= json_encode(array_map(fn($r)=>(int)$r['nb_sessions'],array_slice($evol_notes,0,6))) ?>,borderColor:'#e67e22',backgroundColor:'rgba(230,126,34,.1)',tension:.4,fill:true},
        {label:'Fatima Ba',data: <?= json_encode(array_map(fn($r)=>(float)$r['note_moy'],array_slice($evol_notes,0,6))) ?>,borderColor:'#326ea9',backgroundColor:'rgba(50,110,169,.1)',tension:.4,fill:true}
      ]
    },
    options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,max:100}}}
  });
});
</script>

</body>
</html>
