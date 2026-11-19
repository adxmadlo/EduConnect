<?php
/**
 * EduConnect — enseignant_emploi_du_temps.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Emploi du Temps Enseignant
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$semaine_offset = (int)($_GET["semaine"] ?? 0); // 0=actuelle, +1=suivante, -1=précédente

// Calcul bornes de la semaine (lundi-samedi)
$lundi = new DateTime();
$lundi->modify("monday this week");
if ($semaine_offset !== 0) $lundi->modify("{$semaine_offset} week");
$samedi = clone $lundi; $samedi->modify("+5 days");
$debut_str  = $lundi->format("Y-m-d 00:00:00");
$fin_str    = $samedi->format("Y-m-d 23:59:59");
$semaine_lbl= $lundi->format("d/m")." au ".$samedi->format("d/m/Y");

// Cours de la semaine
$edt_ens = db_query($conn,
    "SELECT et.id, et.date_debut, et.date_fin, et.type_creneau,
            m.nom AS module_nom, m.code AS module_code,
            c.code AS classe_code, s.nom AS salle_nom, s.batiment
     FROM emplois_du_temps et
     LEFT JOIN modules m ON m.id = et.module_id
     LEFT JOIN classes c ON c.id = et.classe_id
     LEFT JOIN salles s ON s.id = et.salle_id
     WHERE et.enseignant_id = ?
       AND et.date_debut BETWEEN ? AND ?
     ORDER BY et.date_debut",
    "iss",[$uid,$debut_str,$fin_str]
);

// Prochains examens / événements
$prochains_examens = db_query($conn,
    "SELECT et.date_debut, et.date_fin, m.nom AS module_nom, c.code AS classe_code, s.nom AS salle_nom
     FROM emplois_du_temps et
     LEFT JOIN modules m ON m.id = et.module_id
     LEFT JOIN classes c ON c.id = et.classe_id
     LEFT JOIN salles s ON s.id = et.salle_id
     WHERE et.enseignant_id = ? AND et.type_creneau IN ('examen','rattrapage')
       AND et.date_debut >= NOW()
     ORDER BY et.date_debut ASC LIMIT 5",
    "i",[$uid]
);


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
<title>EduConnect — Emploi du Temps Enseignant</title>
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

/* ═══ SIDEBAR (identical teal theme) ═══ */
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

/* ═══ MAIN ═══ */
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
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--teal);background:var(--teal-dim)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--teal),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;animation:fadeUp .3s ease}
.page-title{display:flex;align-items:center;gap:10px}
.page-title h1{font-size:22px;font-weight:700;color:var(--txt)}
.page-title i{font-size:26px;color:var(--teal)}
.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.page-actions{display:flex;gap:8px}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}
.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}
.btn-primary:hover{background:var(--teal-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--teal);color:var(--teal)}
.btn-sm{padding:4px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ WEEK NAV ═══ */
.week-nav{display:flex;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:10px 14px;margin-bottom:14px;box-shadow:var(--sh);animation:fadeUp .3s ease}
.week-arrow{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;cursor:pointer;transition:all var(--tr);flex-shrink:0}
.week-arrow:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}
.week-label{flex:1;text-align:center;font-weight:600;font-size:13px;color:var(--txt)}
.week-label small{color:var(--txt3);font-weight:400;font-size:11px;display:block;margin-top:1px}
.week-today-btn{padding:5px 14px;border-radius:var(--r-sm);background:var(--teal-dim);border:1px solid var(--teal);color:var(--teal);font-size:11px;font-weight:600;cursor:pointer;transition:all var(--tr);font-family:'Ubuntu',sans-serif}
.week-today-btn:hover{background:var(--teal);color:#fff}

/* ═══ LEGEND ═══ */
.legend{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:9px 14px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);margin-bottom:12px;box-shadow:var(--sh)}
.leg-it{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--txt2)}
.leg-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}

/* ═══ TIMETABLE GRID ═══ */
.tt-wrap{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow-x:auto;animation:fadeUp .3s .1s ease both}
.tt-grid{display:grid;grid-template-columns:54px repeat(5,1fr);min-width:700px}
.tt-corner,.tt-day-hdr{background:var(--surface2);border-bottom:2px solid var(--border)}
.tt-corner{border-right:1px solid var(--border)}
.tt-day-hdr{padding:10px 8px;text-align:center;border-right:1px solid var(--border)}
.tt-day-name{font-size:11px;font-weight:700;color:var(--txt);text-transform:uppercase;letter-spacing:.5px}
.tt-day-date{font-size:10px;color:var(--txt3);margin-top:2px}
.tt-day-hdr.today{background:var(--teal-dim);border-bottom-color:var(--teal)}
.tt-day-hdr.today .tt-day-name{color:var(--teal)}
.tt-time-lbl{display:flex;align-items:flex-start;justify-content:flex-end;padding:8px 8px 0 0;font-size:9.5px;color:var(--txt3);border-right:1px solid var(--border);border-bottom:1px solid var(--border);font-weight:600}
.tt-cell{border-right:1px solid var(--border);border-bottom:1px solid var(--border);min-height:56px;padding:4px;transition:background var(--tr)}
.tt-cell:hover{background:var(--surface2)}
.tt-break{background:repeating-linear-gradient(45deg,var(--surface2),var(--surface2) 4px,var(--surface) 4px,var(--surface) 12px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--txt3);font-style:italic;letter-spacing:.5px;grid-column:span 6;min-height:26px}

/* ═══ COURSE BLOCKS ═══ */
.cb{height:100%;min-height:48px;border-radius:5px;padding:5px 7px;display:flex;flex-direction:column;justify-content:space-between;cursor:pointer;transition:all var(--tr);position:relative;overflow:hidden;border-left:3px solid transparent}
.cb:hover{transform:scale(1.02);box-shadow:0 2px 8px rgba(0,0,0,.14)}
.cb-teal  {background:var(--teal-dim);   border-left-color:var(--teal)}
.cb-blue  {background:var(--blue-dim);   border-left-color:var(--blue)}
.cb-green {background:var(--green-dim);  border-left-color:var(--green)}
.cb-orange{background:var(--orange-dim); border-left-color:var(--orange)}
.cb-purple{background:var(--purple-dim); border-left-color:var(--purple)}
.cb-name{font-size:11px;font-weight:700;color:var(--txt);line-height:1.2}
.cb-sub{font-size:9.5px;color:var(--txt2);margin-top:2px}
.cb-foot{display:flex;align-items:center;gap:4px;margin-top:4px;flex-wrap:wrap}
.cb-chip{font-size:8.5px;font-weight:700;padding:1px 5px;border-radius:3px}

/* ═══ LIST VIEW ═══ */
.list-day{margin-bottom:16px}
.list-day-title{font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--txt3);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px}
.list-course{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);margin-bottom:6px;padding:12px 14px;display:flex;align-items:center;gap:14px;box-shadow:var(--sh);cursor:pointer;transition:all var(--tr);border-left:4px solid transparent}
.list-course:hover{box-shadow:var(--sh-md);transform:translateX(3px)}
.lc-teal  {border-left-color:var(--teal)}
.lc-blue  {border-left-color:var(--blue)}
.lc-green {border-left-color:var(--green)}
.lc-orange{border-left-color:var(--orange)}
.lc-purple{border-left-color:var(--purple)}
.lc-time{font-size:11px;font-weight:700;color:var(--txt3);min-width:100px}
.lc-icon{width:38px;height:38px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.lc-name{font-size:13px;font-weight:700;color:var(--txt);margin-bottom:2px}
.lc-meta{font-size:11px;color:var(--txt3);display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-teal{background:var(--teal-dim);color:var(--teal)}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}

/* ═══ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:12px;padding:24px;width:460px;max-width:100%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease;max-height:88vh;overflow-y:auto}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:16px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red)}
.detail-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)}
.detail-row:last-child{border:none}
.detail-row i{color:var(--teal);font-size:15px;flex-shrink:0;width:20px;text-align:center}
.detail-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);min-width:80px}
.detail-val{font-size:13px;color:var(--txt)}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start;gap:10px}}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-person-video3"></i></div>
    <div><div class="logo-text">Edu<span>Connect</span></div><div style="font-size:9px;color:rgba(255,255,255,.4)">Enseignant</div></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Principal</div>
    <a href="enseignant_dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></a>
    <a href="enseignant_cours.php" class="nav-item"><i class="bi bi-book-fill"></i><span class="nav-item-txt">Mes Cours</span></a>
    <a href="enseignant_emploi_du_temps.php" class="nav-item active"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></a>
    <div class="nav-section">Pédagogie</div>
    <a href="enseignant_notes.php" class="nav-item"><i class="bi bi-pencil-square"></i><span class="nav-item-txt">Saisie des Notes</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_etudiants.php" class="nav-item"><i class="bi bi-people-fill"></i><span class="nav-item-txt">Mes Étudiants</span></a>
    <a href="enseignant_ressources.php" class="nav-item"><i class="bi bi-folder-fill"></i><span class="nav-item-txt">Ressources</span></a>
    <div class="nav-section">Communication</div>
    <a href="enseignant_messages.php" class="nav-item"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_notifications.php" class="nav-item"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
  </div>
  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Enseignant/enseignant_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role">Enseignante — Informatique</div></div></div>
  
  <a href="/Views/Enseignant/enseignant_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-calendar3"></i>Emploi du Temps</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="setView('grid',this)"><i class="bi bi-grid-3x3-gap"></i>Grille semaine</button>
      <button class="tab-btn" onclick="setView('list',this)"><i class="bi bi-list-ul"></i>Liste</button>
    </div>
    <div class="topbar-right">
      <button class="tb-icon" title="Imprimer" onclick="toast('🖨️ Impression…')"><i class="bi bi-printer"></i></button>
      <button class="tb-icon" title="Exporter PDF" onclick="toast('📥 Export PDF téléchargé')"><i class="bi bi-download"></i></button>
      <div class="tb-user"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title">
        <i class="bi bi-calendar3"></i>
        <div><h1>Mon Emploi du Temps</h1><p id="pageSubtitle">Chargement…</p></div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="prevWeek()"><i class="bi bi-chevron-left"></i>Précédente</button>
        <button class="btn btn-secondary" onclick="goToday()"><i class="bi bi-crosshair"></i>Aujourd'hui</button>
        <button class="btn btn-secondary" onclick="nextWeek()">Suivante<i class="bi bi-chevron-right"></i></button>
        <button class="btn btn-primary" onclick="toast('📥 PDF exporté')"><i class="bi bi-download"></i>Exporter</button>
      </div>
    </div>

    <!-- Weekly stats row -->
    <div style="display:flex;gap:10px;margin-bottom:14px;animation:fadeUp .3s ease;flex-wrap:wrap">
      <div style="flex:1;min-width:110px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 14px;box-shadow:var(--sh);display:flex;align-items:center;gap:10px">
        <i class="bi bi-book-fill" style="font-size:20px;color:var(--teal)"></i>
        <div><div style="font-size:18px;font-weight:700;color:var(--txt)" id="wkCours">0</div><div style="font-size:10px;color:var(--txt3)">Cours cette semaine</div></div>
      </div>
      <div style="flex:1;min-width:110px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 14px;box-shadow:var(--sh);display:flex;align-items:center;gap:10px">
        <i class="bi bi-clock-fill" style="font-size:20px;color:var(--orange)"></i>
        <div><div style="font-size:18px;font-weight:700;color:var(--txt)" id="wkHeures">0h</div><div style="font-size:10px;color:var(--txt3)">Heures d'enseignement</div></div>
      </div>
      <div style="flex:1;min-width:110px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 14px;box-shadow:var(--sh);display:flex;align-items:center;gap:10px">
        <i class="bi bi-people-fill" style="font-size:20px;color:var(--blue)"></i>
        <div><div style="font-size:18px;font-weight:700;color:var(--txt)" id="wkEtudiants">0</div><div style="font-size:10px;color:var(--txt3)">Étudiants à encadrer</div></div>
      </div>
      <div style="flex:1;min-width:110px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 14px;box-shadow:var(--sh);display:flex;align-items:center;gap:10px">
        <i class="bi bi-geo-alt-fill" style="font-size:20px;color:var(--green)"></i>
        <div><div style="font-size:18px;font-weight:700;color:var(--txt)" id="wkSalles">0</div><div style="font-size:10px;color:var(--txt3)">Salles différentes</div></div>
      </div>
    </div>

    <!-- Week navigator -->
    <div class="week-nav">
      <button class="week-arrow" onclick="prevWeek()"><i class="bi bi-chevron-left"></i></button>
      <div class="week-label"><span id="weekLabel">…</span><small id="weekRel"></small></div>
      <button class="week-today-btn" onclick="goToday()"><i class="bi bi-crosshair"></i> Aujourd'hui</button>
      <button class="week-arrow" onclick="nextWeek()"><i class="bi bi-chevron-right"></i></button>
    </div>

    <!-- Legend -->
    <div class="legend">
      <span style="font-size:11px;font-weight:600;color:var(--txt2);margin-right:4px">Types :</span>
      <div class="leg-it"><div class="leg-dot" style="background:var(--teal)"></div>Cours Magistral</div>
      <div class="leg-it"><div class="leg-dot" style="background:var(--blue)"></div>Travaux Dirigés</div>
      <div class="leg-it"><div class="leg-dot" style="background:var(--orange)"></div>Travaux Pratiques</div>
      <div class="leg-it"><div class="leg-dot" style="background:var(--purple)"></div>Examen / Soutenance</div>
      <div class="leg-it"><div class="leg-dot" style="background:var(--green)"></div>Réunion / Admin.</div>
    </div>

    <!-- Grid view -->
    <div id="view-grid" class="tt-wrap"><div class="tt-grid" id="ttGrid"></div></div>

    <!-- List view -->
    <div id="view-list" style="display:none;animation:fadeUp .3s ease" id="listView">
      <div id="listContainer"></div>
    </div>

  </div>
</main>

<!-- Course detail modal -->
<div class="modal-overlay" id="courseModal" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title" id="modalTitle"><i class="bi bi-book-fill" style="color:var(--teal)"></i>Détail du cours</div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div id="modalBody"></div>
    <div style="display:flex;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
      <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="toast('📋 Feuille de présence téléchargée');closeModal()"><i class="bi bi-check2-square"></i>Présences</button>
      <button class="btn btn-primary" style="flex:1;justify-content:center" onclick="toast('📝 Saisie des notes ouverte');closeModal()"><i class="bi bi-pencil-square"></i>Saisir notes</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ══ DATA ══ */
const SLOTS = <?= json_encode(array_map(fn($ev)=>[
  'date'    => $ev["date_debut"],
  'fin'     => $ev["date_fin"],
  'module'  => $ev["module_nom"] ?? "—",
  'code'    => $ev["module_code"] ?? "",
  'classe'  => $ev["classe_code"] ?? "—",
  'salle'   => $ev["salle_nom"] ?? "—",
  'type'    => $ev["type_creneau"],
], $edt_ens)) ?>;
const SLOT_LABELS = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00'];
const DAYS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const COURSES = <?= json_encode(array_map(fn($m)=>["id"=>(int)$m["id"],"name"=>$m["nom"],"code"=>$m["code"]],$mes_cours ?? [])) ?>;

let weekOffset = 0, currentView = 'grid';

document.addEventListener('DOMContentLoaded', () => { renderAll(); });

function renderAll(){
  updateLabels();
  renderGrid();
  renderList();
  updateStats();
}

/* ── LABELS ── */
function updateLabels(){
  const mon = getMonday();
  const fri = new Date(mon); fri.setDate(mon.getDate()+4);
  const fmt = d => d.toLocaleDateString('fr-FR',{day:'numeric',month:'short'});
  const fmtFull = d => d.toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'});
  document.getElementById('weekLabel').textContent = fmt(mon)+' – '+fmtFull(fri);
  document.getElementById('weekRel').textContent = weekOffset===0?'Semaine actuelle':weekOffset===1?'Semaine prochaine':weekOffset===-1?'Semaine précédente':'Semaine '+(weekOffset>0?'+':'')+weekOffset;
  document.getElementById('pageSubtitle').textContent = 'Dr. Fatima Ba — Informatique · Semaine du '+fmt(mon);
}

function getMonday(){
  const d=new Date(); const day=d.getDay(); const diff=d.getDate()-day+(day===0?-6:1);
  const m=new Date(d.setDate(diff+weekOffset*7)); m.setHours(0,0,0,0); return m;
}
function prevWeek(){ weekOffset--; renderAll(); }
function nextWeek(){ weekOffset++; renderAll(); }
function goToday()  { weekOffset=0;  renderAll(); }

/* ── STATS ── */
function updateStats(){
  const coursCount = COURSES.filter(c=>c.type!=='Réunion').length;
  const hours = COURSES.reduce((a,_)=>a+2,0);
  const students = [...new Set(COURSES.filter(c=>c.students>0).map(c=>c.groupe))].reduce((a,g)=>{
    const c=COURSES.find(x=>x.groupe===g); return a+(c?c.students:0);
  },0);
  const rooms = [...new Set(COURSES.map(c=>c.room))].length;
  document.getElementById('wkCours').textContent     = coursCount;
  document.getElementById('wkHeures').textContent    = hours+'h';
  document.getElementById('wkEtudiants').textContent = students;
  document.getElementById('wkSalles').textContent    = rooms;
}

/* ── GRID ── */
function renderGrid(){
  const mon = getMonday();
  const today = new Date(); today.setHours(0,0,0,0);
  let html = '<div class="tt-corner" style="padding:10px 8px;text-align:center;font-size:9px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px">Horaire</div>';
  DAYS.forEach((d,i)=>{
    const date=new Date(mon); date.setDate(mon.getDate()+i);
    const isToday = date.getTime()===today.getTime() && weekOffset===0;
    html+=`<div class="tt-day-hdr${isToday?' today':''}"><div class="tt-day-name">${d}</div><div class="tt-day-date">${date.toLocaleDateString('fr-FR',{day:'2-digit',month:'short'})}</div></div>`;
  });
  SLOTS.forEach((slot,si)=>{
    if(si===2){ html+=`<div class="tt-break"><i class="bi bi-cup-hot" style="margin-right:5px"></i>Pause déjeuner — 12:00 à 13:00</div>`; return; }
    html+=`<div class="tt-time-lbl">${SLOT_LABELS[si]}</div>`;
    DAYS.forEach((_,di)=>{
      const c=COURSES.find(x=>x.slot===si&&x.day===di);
      const ci=COURSES.indexOf(c);
      html+=`<div class="tt-cell">`;
      if(c){
        const typeColor={CM:'var(--teal)',TD:'var(--blue)',TP:'var(--orange)',Réunion:'var(--green)',Soutenance:'var(--purple)'};
        html+=`<div class="cb cb-${c.color}" onclick="showCourse(${ci})">
          <div><div class="cb-name">${c.name}</div><div class="cb-sub"><i class="bi bi-people" style="font-size:9px"></i> ${c.groupe}</div></div>
          <div class="cb-foot">
            <span class="cb-chip" style="background:${typeColor[c.type]}22;color:${typeColor[c.type]}">${c.type}</span>
            <span class="cb-chip" style="background:var(--surface2);color:var(--txt3)"><i class="bi bi-geo-alt" style="font-size:7px"></i>${c.room}</span>
          </div>
        </div>`;
      }
      html+='</div>';
    });
  });
  document.getElementById('ttGrid').innerHTML = html;
}

/* ── LIST ── */
function renderList(){
  const typeColor = {CM:'var(--teal)',TD:'var(--blue)',TP:'var(--orange)',Réunion:'var(--green)',Soutenance:'var(--purple)'};
  const mon = getMonday();
  let html='';
  DAYS.forEach((day,di)=>{
    const dc=COURSES.filter(c=>c.day===di).sort((a,b)=>a.slot-b.slot);
    const date=new Date(mon); date.setDate(mon.getDate()+di);
    const dateStr=date.toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long'});
    html+=`<div class="list-day"><div class="list-day-title"><i class="bi bi-calendar-event" style="color:var(--teal)"></i>${dateStr.charAt(0).toUpperCase()+dateStr.slice(1)}<span class="badge badge-teal" style="margin-left:6px">${dc.length} cours</span></div>`;
    if(!dc.length) html+=`<div style="text-align:center;padding:16px;color:var(--txt3);font-size:12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh)"><i class="bi bi-calendar-x" style="font-size:22px;display:block;margin-bottom:5px;color:var(--border2)"></i>Pas de cours ce jour</div>`;
    dc.forEach(c=>{
      const ci=COURSES.indexOf(c);
      html+=`<div class="list-course lc-${c.color}" onclick="showCourse(${ci})">
        <div class="lc-time"><i class="bi bi-clock" style="margin-right:4px;color:var(--txt3)"></i>${SLOTS[c.slot]}</div>
        <div class="lc-icon" style="background:${typeColor[c.type]||'var(--teal)'}22;color:${typeColor[c.type]||'var(--teal)'}"><i class="bi bi-book-fill"></i></div>
        <div style="flex:1"><div class="lc-name">${c.name}</div><div class="lc-meta"><i class="bi bi-people"></i>${c.groupe} · <i class="bi bi-geo-alt"></i>${c.room} · <span class="badge" style="background:${typeColor[c.type]}22;color:${typeColor[c.type]};font-size:9px">${c.type}</span></div></div>
        <div style="text-align:right;flex-shrink:0"><div style="font-size:11px;font-weight:700;color:var(--txt3)">${c.students>0?c.students+' étudiants':''}</div><i class="bi bi-chevron-right" style="color:var(--txt3)"></i></div>
      </div>`;
    });
    html+='</div>';
  });
  document.getElementById('listContainer').innerHTML = html;
}

/* ── COURSE MODAL ── */
function showCourse(idx){
  const c = COURSES[idx];
  const typeColor = {CM:'var(--teal)',TD:'var(--blue)',TP:'var(--orange)',Réunion:'var(--green)',Soutenance:'var(--purple)'};
  const col = typeColor[c.type]||'var(--teal)';
  document.getElementById('modalTitle').innerHTML = `<i class="bi bi-book-fill" style="color:${col}"></i>${c.name}`;
  document.getElementById('modalBody').innerHTML = `
    <div style="background:var(--surface2);border-left:4px solid ${col};border-radius:var(--r-sm);padding:10px 12px;margin-bottom:16px;font-size:12px;color:var(--txt)">${c.desc}</div>
    <div class="detail-row"><i class="bi bi-people-fill"></i><span class="detail-lbl">Groupe</span><span class="detail-val">${c.groupe}</span></div>
    <div class="detail-row"><i class="bi bi-tag-fill"></i><span class="detail-lbl">Type</span><span class="detail-val"><span class="badge" style="background:${col}22;color:${col}">${c.type}</span></span></div>
    <div class="detail-row"><i class="bi bi-geo-alt-fill"></i><span class="detail-lbl">Salle</span><span class="detail-val">${c.room}</span></div>
    <div class="detail-row"><i class="bi bi-clock-fill"></i><span class="detail-lbl">Horaire</span><span class="detail-val">${SLOTS[c.slot]}</span></div>
    <div class="detail-row"><i class="bi bi-calendar-event-fill"></i><span class="detail-lbl">Jour</span><span class="detail-val">${DAYS[c.day]}</span></div>
    ${c.students>0?`<div class="detail-row"><i class="bi bi-person-check-fill"></i><span class="detail-lbl">Étudiants</span><span class="detail-val">${c.students} inscrits</span></div>`:''}
  `;
  document.getElementById('courseModal').classList.add('open');
}
function closeModal(){ document.getElementById('courseModal').classList.remove('open'); }

/* ── VIEW / SIDEBAR ── */
function setView(v,btn){
  currentView=v;
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active');
  document.getElementById('view-grid').style.display = v==='grid'?'block':'none';
  document.getElementById('view-list').style.display = v==='list'?'block':'none';
}
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('collapsed'); document.getElementById('main').classList.toggle('expanded'); }
let toastT;
function toast(msg){ const el=document.getElementById('toast'); el.textContent=msg; el.classList.add('show'); clearTimeout(toastT); toastT=setTimeout(()=>el.classList.remove('show'),3200); }
</script>
</body>
</html>
