<?php
/**
 * EduConnect — enseignant_dashboard.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Dashboard Enseignant
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

// Nb cours / étudiants
$stats_ens_dash = db_row($conn,
    "SELECT COUNT(DISTINCT me.module_id) AS nb_modules
     FROM module_enseignants me WHERE me.enseignant_id = ?",
    "i", [$uid]
);
$nb_modules_ens = (int)($stats_ens_dash["nb_modules"] ?? 0);

$nb_etudiants_ens = (int)(db_row($conn,
    "SELECT COUNT(DISTINCT e.id) AS nb
     FROM etudiants e
     JOIN classes c ON c.id = e.classe_id
     JOIN module_classes mc ON mc.classe_id = c.id
     JOIN module_enseignants me ON me.module_id = mc.module_id
     WHERE me.enseignant_id = ?",
    "i", [$uid])["nb"] ?? 0);

$notes_non_publiees = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notes n
     JOIN module_enseignants me ON me.module_id = n.module_id
     WHERE me.enseignant_id = ? AND n.publie = 0",
    "i", [$uid])["nb"] ?? 0);

// Prochains cours (emploi du temps)
$prochains_cours = db_query($conn,
    "SELECT et.date_debut, et.date_fin, et.type_creneau,
            m.nom AS module_nom, c.code AS classe_code, s.nom AS salle_nom
     FROM emplois_du_temps et
     JOIN modules m ON m.id = et.module_id
     LEFT JOIN classes c ON c.id = et.classe_id
     LEFT JOIN salles s ON s.id = et.salle_id
     WHERE et.enseignant_id = ? AND et.date_debut >= NOW()
     ORDER BY et.date_debut ASC LIMIT 5",
    "i", [$uid]
);

// Dernières notes saisies
$dernieres_saisies = db_query($conn,
    "SELECT m.nom AS module_nom, c.code AS classe_code,
            COUNT(*) AS nb_notes, MAX(n.saisie_le) AS derniere_saisie,
            SUM(CASE WHEN n.publie=1 THEN 1 ELSE 0 END) AS publiees
     FROM notes n
     JOIN modules m ON m.id = n.module_id
     JOIN module_enseignants me ON me.module_id = n.module_id
     LEFT JOIN etudiants e ON e.id = n.etudiant_id
     LEFT JOIN classes c ON c.id = e.classe_id
     WHERE me.enseignant_id = ?
     GROUP BY n.module_id, e.classe_id
     ORDER BY derniere_saisie DESC LIMIT 5",
    "i", [$uid]
);

$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id=? AND lu=0","i",[$uid])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Tableau de Bord Enseignant</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
  --teal:#0891b2;--teal-lt:#06b6d4;--teal-dim:rgba(8,145,178,.12);
  /* Teacher accent = teal */
  --accent:#0891b2;--accent-lt:#06b6d4;--accent-dim:rgba(8,145,178,.12);
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
.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--teal);color:#fff;box-shadow:0 1px 4px rgba(8,145,178,.3)}
.tab-btn i{font-size:13px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:200px}
.tb-search:focus-within{border-color:var(--teal);background:#fff}
.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}
.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}
.tb-search input::placeholder{color:var(--txt3)}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--teal);background:var(--teal-dim)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--teal),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1}
.page-panel{display:none;animation:fadeUp .3s ease both}
#page-dashboard{display:block}

/* ═══ STAT TILES ═══ */
.tiles-row{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.stat-tile{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--r-sm);color:#fff;flex:1;min-width:130px;cursor:pointer;transition:all var(--tr);position:relative;overflow:hidden}
.stat-tile:hover{transform:translateY(-2px);box-shadow:var(--sh-md);filter:brightness(1.06)}
.stat-tile::after{content:'';position:absolute;bottom:-16px;right:-16px;width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.1)}
.stat-tile-icon{width:36px;height:36px;border-radius:6px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-tile-val{font-size:22px;font-weight:700;line-height:1}
.stat-tile-lbl{font-size:11px;opacity:.9;margin-top:2px}
.tile-teal  {background:linear-gradient(135deg,var(--teal),var(--teal-lt))}
.tile-blue  {background:linear-gradient(135deg,var(--blue),var(--blue-lt))}
.tile-green {background:linear-gradient(135deg,var(--green),var(--green-lt))}
.tile-orange{background:linear-gradient(135deg,var(--orange),#f39c12)}
.tile-purple{background:linear-gradient(135deg,var(--purple),#9b59b6)}

/* ═══ LAYOUT ═══ */
.dash-grid{display:grid;grid-template-columns:1fr 340px;gap:14px;margin-bottom:14px}
.dash-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px}

/* ═══ WIDGETS ═══ */
.widget{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;transition:all var(--tr)}
.widget:hover{box-shadow:var(--sh-md)}
.widget-head{padding:13px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.widget-title{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:6px}
.widget-title i{color:var(--teal);font-size:14px}
.widget-body{padding:14px 16px}
.w-btn{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:12px;cursor:pointer;transition:all var(--tr)}
.w-btn:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}

/* ═══ TODAY'S SCHEDULE ═══ */
.schedule-item{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border)}
.schedule-item:last-child{border:none;padding-bottom:0}
.sch-time{font-size:10px;font-weight:700;color:var(--txt3);min-width:70px;flex-shrink:0;text-align:right;line-height:1.4}
.sch-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.sch-bar{width:3px;height:32px;border-radius:2px;flex-shrink:0}
.sch-info{flex:1}
.sch-name{font-size:12.5px;font-weight:600;color:var(--txt)}
.sch-meta{font-size:11px;color:var(--txt3);margin-top:1px;display:flex;align-items:center;gap:6px}
.sch-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:4px;font-size:9.5px;font-weight:700}
.sch-now{background:rgba(8,145,178,.15);border-left:2px solid var(--teal);margin:-9px -16px 0;padding:9px 16px;border-radius:0}

/* ═══ PENDING ITEMS ═══ */
.pending-item{display:flex;align-items:center;gap:11px;padding:10px 0;border-bottom:1px solid var(--border)}
.pending-item:last-child{border:none;padding-bottom:0}
.pending-ico{width:34px;height:34px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.pending-name{font-size:12.5px;font-weight:600;color:var(--txt)}
.pending-sub{font-size:11px;color:var(--txt3);margin-top:1px}
.pending-right{margin-left:auto;text-align:right;flex-shrink:0}

/* ═══ QUICK ACTIONS ═══ */
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.qa-btn{padding:12px 10px;border-radius:var(--r-sm);border:1.5px solid var(--border);background:var(--surface2);display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;transition:all var(--tr);text-align:center}
.qa-btn:hover{border-color:var(--teal);background:var(--teal-dim);transform:translateY(-2px)}
.qa-btn i{font-size:20px;color:var(--teal)}
.qa-btn span{font-size:11px;font-weight:600;color:var(--txt2)}

/* ═══ TABLE ═══ */
.table{width:100%;border-collapse:collapse}
.table th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}
.table td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}
.table tr:hover{background:var(--surface2)}
.table tr:last-child td{border:none}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-teal{background:var(--teal-dim);color:var(--teal)}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-red{background:var(--red-dim);color:var(--red)}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}
.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}
.btn-primary:hover{background:var(--teal-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--teal);color:var(--teal)}
.btn-sm{padding:4px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:12px;padding:24px;width:460px;max-width:100%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease;max-height:88vh;overflow-y:auto}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:16px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red)}
.form-field{margin-bottom:13px}
.form-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px}
.form-input{width:100%;padding:8px 11px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px;color:var(--txt);background:var(--surface);transition:border var(--tr)}
.form-input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-dim)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1200px){.dash-grid{grid-template-columns:1fr}.dash-grid-3{grid-template-columns:1fr 1fr}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.tiles-row{flex-direction:column}.dash-grid-3{grid-template-columns:1fr}}
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
  <div class="sidebar-search">
    <div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div>
  </div>
  <div class="nav-body">
    <div class="nav-section">Principal</div>
    <a href="enseignant_dashboard.php" class="nav-item active"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></a>
    <a href="enseignant_cours.php" class="nav-item"><i class="bi bi-book-fill"></i><span class="nav-item-txt">Mes Cours</span></a>
    <a href="enseignant_emploi_du_temps.php" class="nav-item"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></a>

    <div class="nav-section">Pédagogie</div>
    <a href="enseignant_notes.php" class="nav-item"><i class="bi bi-pencil-square"></i><span class="nav-item-txt">Saisie des Notes</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_etudiants.php" class="nav-item"><i class="bi bi-people-fill"></i><span class="nav-item-txt">Mes Étudiants</span></a>
    <a href="enseignant_ressources.php" class="nav-item"><i class="bi bi-folder-fill"></i><span class="nav-item-txt">Ressources</span></a>

    <div class="nav-section">Communication</div>
    <a href="enseignant_messages.php" class="nav-item"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_notifications.php" class="nav-item"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
  </div>
  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Enseignant/enseignant_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role">Enseignante — Informatique</div>
      </div>
    </div>
  
  <a href="/Views/Enseignant/enseignant_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-speedometer2"></i>Tableau de Bord</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="setView('dashboard',this)"><i class="bi bi-speedometer2"></i>Vue d'ensemble</button>
      <button class="tab-btn" onclick="setView('activity',this)"><i class="bi bi-clock-history"></i>Activité récente</button>
    </div>
    <div class="topbar-right">
      <div class="tb-search"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…" oninput="globalSearch(this.value)"></div>
      <button class="tb-icon" title="Saisir des notes" onclick="openNotes()"><i class="bi bi-pencil-square" style="color:var(--teal)"></i></button>
      <button class="tb-icon" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <div class="tb-user">
        <div class="tb-ava"><?= $u_init ?></div>
        <div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- ── DASHBOARD VIEW ── -->
    <div id="page-dashboard" class="page-panel">

      <!-- Greeting -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;animation:fadeUp .3s ease">
        <div>
          <div style="font-size:20px;font-weight:700;color:var(--txt)" id="greeting">Bonjour, Dr. Fatima Ba</div>
          <div style="font-size:12px;color:var(--txt3);margin-top:3px" id="dateLabel"></div>
        </div>
        <button class="btn btn-primary" onclick="openNotes()"><i class="bi bi-plus-circle"></i>Saisir des notes</button>
      </div>

      <!-- Stat tiles -->
      <div class="tiles-row">
        <div class="stat-tile tile-teal" onclick="window.location='enseignant_cours.php'">
          <div class="stat-tile-icon"><i class="bi bi-book-fill"></i></div>
          <div><div class="stat-tile-val">5</div><div class="stat-tile-lbl">Cours dispensés</div></div>
        </div>
        <div class="stat-tile tile-blue" onclick="window.location='enseignant_etudiants.php'">
          <div class="stat-tile-icon"><i class="bi bi-people-fill"></i></div>
          <div><div class="stat-tile-val" id="tileEtudiants">0</div><div class="stat-tile-lbl">Étudiants</div></div>
        </div>
        <div class="stat-tile tile-green" onclick="toast('📊 Voir les moyennes')">
          <div class="stat-tile-icon"><i class="bi bi-graph-up"></i></div>
          <div><div class="stat-tile-val">13.8</div><div class="stat-tile-lbl">Moyenne classe /20</div></div>
        </div>
        <div class="stat-tile tile-orange" onclick="window.location='enseignant_notes.php'">
          <div class="stat-tile-icon"><i class="bi bi-pencil-square"></i></div>
          <div><div class="stat-tile-val" id="tilePending">0</div><div class="stat-tile-lbl">Notes à saisir</div></div>
        </div>
        <div class="stat-tile tile-purple" onclick="toast('📅 Voir emploi du temps')">
          <div class="stat-tile-icon"><i class="bi bi-calendar-check"></i></div>
          <div><div class="stat-tile-val" id="tileToday">0</div><div class="stat-tile-lbl">Cours aujourd'hui</div></div>
        </div>
      </div>

      <!-- Main dash grid -->
      <div class="dash-grid">

        <!-- Left: chart + pending -->
        <div style="display:flex;flex-direction:column;gap:14px">
          <div class="widget">
            <div class="widget-head">
              <div class="widget-title"><i class="bi bi-bar-chart-fill"></i>Moyennes par cours — S1 2025-2026</div>
              <div style="display:flex;gap:4px">
                <button class="w-btn" onclick="toggleChartType()" title="Changer type"><i class="bi bi-arrow-left-right" id="chartTypeIcon"></i></button>
                <button class="w-btn" onclick="toast('📥 Export PDF')"><i class="bi bi-download"></i></button>
              </div>
            </div>
            <div class="widget-body">
              <div style="position:relative;height:220px"><canvas id="avgChart"></canvas></div>
            </div>
          </div>

          <div class="widget">
            <div class="widget-head">
              <div class="widget-title"><i class="bi bi-clock-history"></i>Notes à saisir</div>
              <a href="enseignant_notes.php" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i>Tout saisir</a>
            </div>
            <div class="widget-body" id="pendingList"></div>
          </div>
        </div>

        <!-- Right: today's schedule + quick actions -->
        <div style="display:flex;flex-direction:column;gap:14px">
          <div class="widget">
            <div class="widget-head">
              <div class="widget-title"><i class="bi bi-calendar3"></i>Emploi du temps — Aujourd'hui</div>
              <a href="enseignant_emploi_du_temps.php" class="w-btn" title="Voir tout"><i class="bi bi-box-arrow-up-right"></i></a>
            </div>
            <div class="widget-body" id="todaySchedule"></div>
          </div>

          <div class="widget">
            <div class="widget-head"><div class="widget-title"><i class="bi bi-lightning-charge-fill"></i>Actions rapides</div></div>
            <div class="widget-body">
              <div class="qa-grid">
                <button class="qa-btn" onclick="openNotes()"><i class="bi bi-pencil-square"></i><span>Saisir notes</span></button>
                <button class="qa-btn" onclick="toast('📤 Ressource partagée')"><i class="bi bi-cloud-upload"></i><span>Déposer ressource</span></button>
                <button class="qa-btn" onclick="toast('📋 Feuille de présence téléchargée')"><i class="bi bi-check2-square"></i><span>Présences</span></button>
                <button class="qa-btn" onclick="toast('✉️ Message aux étudiants envoyé')"><i class="bi bi-send-fill"></i><span>Envoyer message</span></button>
                <button class="qa-btn" onclick="toast('📊 Rapport de performance généré')"><i class="bi bi-file-earmark-bar-graph"></i><span>Rapport classe</span></button>
                <button class="qa-btn" onclick="window.location='enseignant_emploi_du_temps.php'"><i class="bi bi-calendar-plus"></i><span>Voir EDT</span></button>
              </div>
            </div>
          </div>

          <div class="widget">
            <div class="widget-head"><div class="widget-title"><i class="bi bi-people-fill"></i>Top Étudiants — S1</div></div>
            <div class="widget-body" id="topStudents"></div>
          </div>
        </div>
      </div>

      <!-- Bottom: recent messages -->
      <div class="widget">
        <div class="widget-head">
          <div class="widget-title"><i class="bi bi-chat-dots-fill"></i>Messages récents</div>
          <a href="enseignant_messages.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-right"></i>Tous les messages</a>
        </div>
        <div class="widget-body" style="padding:0">
          <table class="table">
            <thead><tr><th>Expéditeur</th><th>Objet</th><th>Date</th><th>Statut</th><th></th></tr></thead>
            <tbody id="messagesTable"></tbody>
          </table>
        </div>
      </div>

    </div><!-- /dashboard -->

    <!-- ── ACTIVITY VIEW ── -->
    <div id="page-activity" class="page-panel">
      <div style="font-size:16px;font-weight:700;color:var(--txt);margin-bottom:14px;display:flex;align-items:center;gap:8px">
        <i class="bi bi-clock-history" style="color:var(--teal)"></i>Activité récente
      </div>
      <div class="widget"><div class="widget-body" id="activityFeed"></div></div>
    </div>

  </div><!-- /content -->
</main>

<!-- NOTES MODAL -->
<div class="modal-overlay" id="notesModal" onclick="if(event.target===this)closeNotes()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-pencil-square" style="color:var(--teal)"></i>Saisie rapide de notes</div>
      <button class="modal-close" onclick="closeNotes()">✕</button>
    </div>
    <div class="form-row" style="margin-bottom:13px">
      <div class="form-field" style="margin:0">
        <div class="form-label">Cours</div>
        <select class="form-input" id="notesCours">
          <option>Bases de Données — L2 Info</option>
          <option>Bases de Données — L2 Gestion</option>
          <option>Systèmes d'Information — M1</option>
        </select>
      </div>
      <div class="form-field" style="margin:0">
        <div class="form-label">Type d'évaluation</div>
        <select class="form-input" id="notesType">
          <option>Contrôle continu</option><option>Examen partiel</option><option>TP noté</option><option>Projet</option>
        </select>
      </div>
    </div>
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:8px">Notes des étudiants</div>
    <div id="notesGrid" style="display:flex;flex-direction:column;gap:6px;max-height:300px;overflow-y:auto"></div>
    <div style="display:flex;gap:8px;margin-top:16px">
      <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closeNotes()">Annuler</button>
      <button class="btn btn-primary" style="flex:2;justify-content:center" onclick="saveNotes()"><i class="bi bi-check2"></i>Enregistrer les notes</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ══ DATA ══ */
const COURSES = <?= json_encode(array_map(fn($m)=>[
  'id'        => $m['id'],
  'code'      => $m['code'],
  'name'      => $m['nom'],
  'classes'   => $m['classes'] ?? '—',
  'students'  => (int)($m['nb_classes'] ?? 0) * 40,
  'published' => (int)($m['nb_notes_publiees'] ?? 0),
  'avg'       => $m['moy_module'] !== null ? (float)$m['moy_module'] : null,
], $mes_cours)) ?>;

const TODAY_SCHEDULE = <?= json_encode(array_map(fn($ev)=>[
  'time'    => date("H:i",strtotime($ev["date_debut"]))."–".date("H:i",strtotime($ev["date_fin"])),
  'module'  => $ev["module_nom"] ?? "—",
  'classe'  => $ev["classe_code"] ?? "—",
  'salle'   => $ev["salle_nom"] ?? "—",
  'type'    => $ev["type_creneau"] ?? "cours1",
], array_filter($prochains_cours, fn($ev)=>date("Y-m-d",strtotime($ev["date_debut"]))==date("Y-m-d")))) ?>;

const PENDING = <?= json_encode(array_map(fn($ds)=>[
  'module'  => $ds["module_nom"],
  'classe'  => $ds["classe_code"] ?? "—",
  'count'   => (int)$ds["nb_notes"] - (int)$ds["publiees"],
  'date'    => date("d/m",strtotime($ds["derniere_saisie"])),
], array_filter($dernieres_saisies, fn($ds)=>(int)$ds["publiees"] < (int)$ds["nb_notes"]))) ?>;

const TOP_STUDENTS = <?= json_encode(
  db_query($conn,
    "SELECT CONCAT(u.prenom,' ',u.nom) AS name, ROUND(AVG(n.note_finale),1) AS avg,
            c.code AS classe
     FROM notes n JOIN etudiants e ON e.id=n.etudiant_id JOIN users u ON u.id=e.user_id
     JOIN classes c ON c.id=e.classe_id
     JOIN module_enseignants me ON me.module_id=n.module_id
     WHERE me.enseignant_id=? AND n.publie=1
     GROUP BY e.id ORDER BY avg DESC LIMIT 5",
    "i",[(int)$user['id']],[])
) ?>;

const MESSAGES = [];

const ACTIVITY = <?= json_encode(array_map(fn($ds)=>[
  'action' => 'Notes saisies',
  'module' => $ds['module_nom'],
  'classe' => $ds['classe_code']??"—",
  'date'   => date('d/m',strtotime($ds['derniere_saisie'])),
  'count'  => (int)$ds['nb_notes'],
],$dernieres_saisies)) ?>;

let chartRef = null, chartType = 'bar';

/* ══ INIT ══ */
document.addEventListener('DOMContentLoaded', () => {
  setGreeting();
  animCount('tileEtudiants', COURSES.reduce((a,c)=>a+c.students,0));
  animCount('tilePending',   PENDING.length);
  animCount('tileToday',     TODAY_SCHEDULE.length);
  renderSchedule();
  renderPending();
  renderTopStudents();
  renderMessages();
  renderActivity();
  buildChart();
  buildNotesGrid();
});

function setGreeting(){
  const now = new Date();
  const h = now.getHours();
  const greet = h < 12 ? 'Bonjour' : h < 18 ? 'Bon après-midi' : 'Bonsoir';
  document.getElementById('greeting').textContent = greet + ', Dr. Fatima Ba 👋';
  document.getElementById('dateLabel').textContent = now.toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
}

function animCount(id, target){
  const el = document.getElementById(id); if(!el) return;
  const t0 = performance.now();
  (function s(now){ const t=Math.min((now-t0)/600,1); el.textContent=Math.round((1-Math.pow(1-t,3))*target); if(t<1)requestAnimationFrame(s); })(t0);
}

/* ══ SCHEDULE ══ */
function renderSchedule(){
  const typeColor = { CM:'var(--teal)', TD:'var(--blue)', TP:'var(--orange)' };
  document.getElementById('todaySchedule').innerHTML = TODAY_SCHEDULE.map(s => `
    <div class="schedule-item${s.now?' sch-now':''}">
      <div class="sch-time">${s.time}</div>
      <div class="sch-bar" style="background:${typeColor[s.type]||'var(--border2)'}"></div>
      <div class="sch-info">
        <div class="sch-name">${s.name} ${s.now?'<span class="sch-badge" style="background:var(--teal-dim);color:var(--teal);margin-left:4px"><i class="bi bi-broadcast"></i>EN COURS</span>':''}</div>
        <div class="sch-meta"><i class="bi bi-people" style="font-size:10px"></i>${s.groupe} · <i class="bi bi-geo-alt" style="font-size:10px"></i>${s.room}
          <span class="badge-teal sch-badge" style="background:${typeColor[s.type]}22;color:${typeColor[s.type]}">${s.type}</span>
        </div>
      </div>
    </div>`).join('');
}

/* ══ PENDING ══ */
function renderPending(){
  document.getElementById('pendingList').innerHTML = PENDING.map(p => `
    <div class="pending-item">
      <div class="pending-ico" style="background:var(--teal-dim);color:var(--teal)"><i class="bi bi-pencil-square"></i></div>
      <div>
        <div class="pending-name">${p.cours}</div>
        <div class="pending-sub">${p.type} · ${p.students} étudiants</div>
      </div>
      <div class="pending-right">
        <span class="badge badge-orange"><i class="bi bi-clock"></i>${p.deadline}</span>
      </div>
    </div>`).join('');
}

/* ══ TOP STUDENTS ══ */
function renderTopStudents(){
  const medals = ['🥇','🥈','🥉'];
  document.getElementById('topStudents').innerHTML = TOP_STUDENTS.map((s,i) => `
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)${i===TOP_STUDENTS.length-1?';border:none':''}">
      <span style="font-size:18px">${medals[i]}</span>
      <div style="flex:1"><div style="font-size:12.5px;font-weight:600;color:var(--txt)">${s.nom}</div><div style="font-size:10.5px;color:var(--txt3)">${s.cours}</div></div>
      <span style="font-size:15px;font-weight:700;color:var(--teal)">${s.avg}/20</span>
    </div>`).join('');
}

/* ══ MESSAGES ══ */
function renderMessages(){
  document.getElementById('messagesTable').innerHTML = MESSAGES.map(m => `
    <tr style="${m.unread?'font-weight:600':''}">
      <td><div style="display:flex;align-items:center;gap:7px">
        ${m.unread?'<span style="width:6px;height:6px;border-radius:50%;background:var(--red);flex-shrink:0"></span>':'<span style="width:6px;height:6px;flex-shrink:0"></span>'}
        ${m.from}
      </div></td>
      <td>${m.subject}</td>
      <td style="color:var(--txt3)">${m.date}</td>
      <td>${m.unread?'<span class="badge badge-teal">Nouveau</span>':'<span class="badge" style="background:var(--surface2);color:var(--txt3)">Lu</span>'}</td>
      <td><button class="btn btn-sm btn-secondary" onclick="toast('💬 Répondre à ${m.from}')"><i class="bi bi-reply"></i>Répondre</button></td>
    </tr>`).join('');
}

/* ══ ACTIVITY ══ */
function renderActivity(){
  document.getElementById('activityFeed').innerHTML = ACTIVITY.map(a => `
    <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
      <div style="width:34px;height:34px;border-radius:var(--r-sm);background:${a.color}22;color:${a.color};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"><i class="bi ${a.ico}"></i></div>
      <div style="flex:1"><div style="font-size:12.5px;color:var(--txt)">${a.text}</div><div style="font-size:10.5px;color:var(--txt3);margin-top:2px">${a.time}</div></div>
    </div>`).join('');
}

/* ══ CHART ══ */
function buildChart(){
  const ctx = document.getElementById('avgChart').getContext('2d');
  chartRef = new Chart(ctx, {
    type: chartType,
    data:{
      labels: COURSES.map(c=>c.name.split(' ')[0]+' '+c.groupe.split(' ')[0]),
      datasets:[
        {label:'Moyenne classe',data:COURSES.map(c=>c.avg),backgroundColor:COURSES.map(c=>c.color+'cc'),borderColor:COURSES.map(c=>c.color),borderWidth:2,borderRadius:chartType==='bar'?5:0,tension:.4},
        {label:'Seuil (10/20)',data:Array(COURSES.length).fill(10),type:'line',borderColor:'rgba(196,30,58,.5)',backgroundColor:'transparent',borderDash:[4,4],borderWidth:1.5,pointRadius:0},
      ]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{family:'Ubuntu',size:11},boxWidth:10}}},scales:{x:{grid:{display:false},ticks:{font:{family:'Ubuntu',size:10}}},y:{min:0,max:20,grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{family:'Ubuntu',size:10},callback:v=>v+'/20'}}}}
  });
}
function toggleChartType(){
  chartType = chartType==='bar'?'line':'bar';
  chartRef.destroy();
  buildChart();
  toast(chartType==='bar'?'📊 Vue histogramme':'📈 Vue courbe');
}

/* ══ NOTES MODAL ══ */
const MOCK_STUDENTS = [];
function buildNotesGrid(){
  document.getElementById('notesGrid').innerHTML = MOCK_STUDENTS.map((n,i)=>`
    <div style="display:flex;align-items:center;gap:10px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface2)">
      <div style="width:28px;height:28px;border-radius:6px;background:var(--teal-dim);color:var(--teal);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0">${n.split(' ').map(w=>w[0]).join('').slice(0,2)}</div>
      <span style="flex:1;font-size:12.5px;font-weight:500">${n}</span>
      <input type="number" min="0" max="20" step="0.5" placeholder="—" id="note_${i}"
        style="width:70px;padding:5px 8px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px;text-align:center"
        onfocus="this.style.borderColor='var(--teal)'" onblur="this.style.borderColor='var(--border2)'">
      <span style="font-size:11px;color:var(--txt3)">/20</span>
    </div>`).join('');
}
function openNotes(){ document.getElementById('notesModal').classList.add('open'); }
function closeNotes(){ document.getElementById('notesModal').classList.remove('open'); }
function saveNotes(){
  const cours = document.getElementById('notesCours').value;
  const type  = document.getElementById('notesType').value;
  const filled = MOCK_STUDENTS.filter((_,i)=>document.getElementById('note_'+i)?.value).length;
  if(!filled){ toast('⚠️ Veuillez saisir au moins une note'); return; }
  closeNotes();
  toast(`✅ ${filled} note(s) enregistrée(s) — ${type.split(' ')[0]}, ${cours.split('—')[0].trim()}`);
}

/* ══ VIEW / SIDEBAR ══ */
function setView(v, btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active');
  document.getElementById('page-dashboard').style.display = v==='dashboard'?'block':'none';
  document.getElementById('page-activity').style.display  = v==='activity'?'block':'none';
}
function globalSearch(q){ if(q.length>1) toast('🔍 Recherche : «'+q+'»'); }
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('collapsed'); document.getElementById('main').classList.toggle('expanded'); }
let toastT;
function toast(msg){ const el=document.getElementById('toast'); el.textContent=msg; el.classList.add('show'); clearTimeout(toastT); toastT=setTimeout(()=>el.classList.remove('show'),3200); }
</script>
</body>
</html>
