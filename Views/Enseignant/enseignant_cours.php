<?php
/**
 * EduConnect — enseignant_cours.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Cours Enseignant
// ═══════════════════════════════════════════════════════════
$mes_cours = db_query($conn,
    "SELECT m.id, m.code, m.nom, m.type_eval, m.semestre, m.annee_etude, m.credits,
            GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') AS classes,
            COUNT(DISTINCT mc.classe_id) AS nb_classes,
            (SELECT COUNT(DISTINCT n.etudiant_id) FROM notes n WHERE n.module_id=m.id AND n.publie=1) AS nb_notes_publiees,
            (SELECT ROUND(AVG(n2.note_finale),1) FROM notes n2 WHERE n2.module_id=m.id AND n2.publie=1) AS moy_module
     FROM modules m
     JOIN module_enseignants me ON me.module_id = m.id
     LEFT JOIN module_classes mc ON mc.module_id = m.id
     LEFT JOIN classes c ON c.id = mc.classe_id
     WHERE me.enseignant_id = ?
     GROUP BY m.id
     ORDER BY m.annee_etude, m.semestre, m.nom",
    "i", [(int)$user["id"]]
);

$stats_cours = [
    "nb_cours"     => count($mes_cours),
    "nb_etudiants" => (int)(db_row($conn,
        "SELECT COUNT(DISTINCT mc.classe_id * 40) AS nb
         FROM module_enseignants me
         JOIN module_classes mc ON mc.module_id = me.module_id
         WHERE me.enseignant_id = ?", "i", [(int)$user["id"]])["nb"] ?? 0),
    "notes_attente" => (int)(db_row($conn,
        "SELECT COUNT(*) AS nb FROM notes n
         JOIN module_enseignants me ON me.module_id = n.module_id
         WHERE me.enseignant_id = ? AND n.publie = 0",
        "i", [(int)$user["id"]])["nb"] ?? 0),
];

// Nombre total étudiants encadrés
$nb_etudiants_total = (int)(db_row($conn,
    "SELECT COUNT(DISTINCT e.id) AS nb
     FROM etudiants e
     JOIN classes c ON c.id = e.classe_id
     JOIN module_classes mc ON mc.classe_id = c.id
     JOIN module_enseignants me ON me.module_id = mc.module_id
     WHERE me.enseignant_id = ?",
    "i", [(int)$user["id"]])["nb"] ?? 0);

$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id=? AND lu=0","i",[(int)$user["id"]])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Mes Cours</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--gold-dim:rgba(212,168,67,.12);
  --purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
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
.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--teal);color:#fff;box-shadow:0 1px 4px rgba(8,145,178,.3)}
.tab-btn i{font-size:13px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:200px;transition:all var(--tr)}
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
.content{padding:18px 20px;flex:1;overflow-y:auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;animation:fadeUp .3s ease}
.page-title{display:flex;align-items:center;gap:10px}
.page-title h1{font-size:22px;font-weight:700;color:var(--txt)}
.page-title i{font-size:26px;color:var(--teal)}
.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.page-actions{display:flex;gap:8px;align-items:center}

/* ═══ SUMMARY TILES ═══ */
.sum-row{display:flex;gap:10px;margin-bottom:16px;animation:fadeUp .3s ease;flex-wrap:wrap}
.sum-tile{flex:1;min-width:110px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 14px;display:flex;align-items:center;gap:10px;box-shadow:var(--sh);transition:all var(--tr);cursor:default}
.sum-tile:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.sum-icon{width:36px;height:36px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.sum-val{font-size:20px;font-weight:700;color:var(--txt);line-height:1}
.sum-lbl{font-size:10px;color:var(--txt3);margin-top:2px}

/* ═══ COURSE CARDS ═══ */
.courses-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;animation:fadeUp .3s .1s ease both}

.course-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;transition:all var(--tr);cursor:pointer}
.course-card:hover{box-shadow:var(--sh-md);transform:translateY(-4px);border-color:var(--teal)}
.course-card.hidden{display:none}

/* Header banner — colour per course */
.cc-header{padding:16px;color:#fff;position:relative;overflow:hidden}
.cc-header::after{content:'';position:absolute;bottom:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.1)}
.cc-code{font-size:10px;font-weight:700;opacity:.85;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:4px}
.cc-name{font-size:15px;font-weight:700;line-height:1.2;margin-bottom:2px}
.cc-groupe{font-size:11px;opacity:.8;display:flex;align-items:center;gap:4px}
.cc-type-badge{position:absolute;top:14px;right:14px;padding:3px 9px;border-radius:20px;font-size:9.5px;font-weight:700;background:rgba(255,255,255,.22);backdrop-filter:blur(4px)}

/* Body */
.cc-body{padding:14px}

/* Key stats inside card — 4 in a row */
.cc-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.cc-stat{text-align:center;padding:6px 4px;border-radius:var(--r-sm);background:var(--surface2)}
.cc-stat-val{font-size:14px;font-weight:700;color:var(--txt);line-height:1}
.cc-stat-lbl{font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px}

/* Progression bar */
.cc-prog-label{display:flex;justify-content:space-between;font-size:10.5px;color:var(--txt3);margin-bottom:4px}
.cc-prog-label span:last-child{font-weight:600;color:var(--txt2)}
.prog-track{height:5px;background:var(--border);border-radius:10px;overflow:hidden;margin-bottom:12px}
.prog-fill{height:100%;border-radius:10px;transition:width .6s cubic-bezier(.4,0,.2,1)}

/* Footer */
.cc-footer{display:flex;align-items:center;justify-content:space-between}
.cc-next{font-size:11px;color:var(--txt3);display:flex;align-items:center;gap:4px}
.cc-next i{color:var(--teal)}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-teal  {background:var(--teal-dim);  color:var(--teal)}
.badge-blue  {background:var(--blue-dim);  color:var(--blue)}
.badge-green {background:var(--green-dim); color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-purple{background:var(--purple-dim);color:var(--purple)}
.badge-gold  {background:var(--gold-dim);  color:var(--gold)}
.badge-red   {background:var(--red-dim);   color:var(--red)}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}
.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}
.btn-primary:hover{background:var(--teal-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--teal);color:var(--teal)}
.btn-sm{padding:4px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ EMPTY STATE ═══ */
.empty-state{text-align:center;padding:60px 20px;color:var(--txt3);animation:fadeUp .3s ease}
.empty-state i{font-size:48px;display:block;margin-bottom:12px;color:var(--border2)}
.empty-state p{font-size:14px;font-weight:600;color:var(--txt2);margin-bottom:4px}
.empty-state small{font-size:12px}

/* ═══ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:14px;width:580px;max-width:100%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease;max-height:90vh;overflow-y:auto}
.modal-banner{padding:20px 24px;color:#fff;position:relative;overflow:hidden;border-radius:14px 14px 0 0}
.modal-banner::after{content:'';position:absolute;bottom:-40px;right:-40px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.08)}
.modal-banner-code{font-size:10px;font-weight:700;opacity:.8;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px}
.modal-banner-name{font-size:18px;font-weight:700;margin-bottom:3px}
.modal-banner-sub{font-size:12px;opacity:.8}
.modal-close-btn{position:absolute;top:14px;right:14px;width:28px;height:28px;border-radius:6px;background:rgba(255,255,255,.2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;transition:background var(--tr)}
.modal-close-btn:hover{background:rgba(255,255,255,.35)}
.modal-body{padding:20px 24px}

/* Modal stats row */
.modal-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.modal-stat{text-align:center;padding:10px 6px;background:var(--surface2);border-radius:var(--r-sm);border:1px solid var(--border)}
.modal-stat-val{font-size:18px;font-weight:700;color:var(--txt)}
.modal-stat-lbl{font-size:9.5px;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px}

/* Modal chart */
.modal-section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--txt3);margin-bottom:10px;display:flex;align-items:center;gap:5px}
.modal-section-title i{color:var(--teal)}

/* Student list in modal */
.modal-student-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)}
.modal-student-row:last-child{border:none}
.ms-ava{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:10px;color:#fff;flex-shrink:0}
.ms-name{flex:1;font-size:12.5px;font-weight:500;color:var(--txt)}
.ms-note{font-size:13px;font-weight:700}
.ms-bar-wrap{width:60px;height:4px;background:var(--border);border-radius:10px;overflow:hidden}
.ms-bar-fill{height:100%;border-radius:10px}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1100px){.courses-grid{grid-template-columns:1fr 1fr}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.courses-grid{grid-template-columns:1fr}.page-header{flex-direction:column;align-items:flex-start;gap:10px}.sum-row{flex-wrap:wrap}.sum-tile{min-width:calc(50% - 5px)}}
</style>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
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
    <a href="enseignant_cours.php" class="nav-item active"><i class="bi bi-book-fill"></i><span class="nav-item-txt">Mes Cours</span></a>
    <a href="enseignant_emploi_du_temps.php" class="nav-item"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></a>
    <div class="nav-section">Pédagogie</div>
    <a href="enseignant_notes.php" class="nav-item"><i class="bi bi-pencil-square"></i><span class="nav-item-txt">Saisie des Notes</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_etudiants.php" class="nav-item"><i class="bi bi-people-fill"></i><span class="nav-item-txt">Mes Étudiants</span></a>
    <a href="enseignant_ressources.php" class="nav-item"><i class="bi bi-folder-fill"></i><span class="nav-item-txt">Ressources</span></a>
    <div class="nav-section">Communication</div>
    <a href="enseignant_messages.php" class="nav-item"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_notifications.php" class="nav-item"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <div class="nav-section">Compte</div>
    <a href="enseignant_parametres.php" class="nav-item"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></a>
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
    <div class="topbar-brand"><i class="bi bi-book-fill"></i>Mes Cours</div>
    <div class="tab-nav" id="filterTabs">
      <button class="tab-btn active" onclick="filterCourses('all',this)"><i class="bi bi-list-ul"></i>Tous <span id="countAll" style="margin-left:2px;opacity:.7"></span></button>
      <button class="tab-btn" onclick="filterCourses('active',this)"><i class="bi bi-play-circle"></i>En cours</button>
      <button class="tab-btn" onclick="filterCourses('pending',this)"><i class="bi bi-pencil-square"></i>Notes à saisir</button>
      <button class="tab-btn" onclick="filterCourses('done',this)"><i class="bi bi-check-circle"></i>Terminés</button>
    </div>
    <div class="topbar-right">
      <div class="tb-search">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Rechercher un cours…" id="searchInput" oninput="searchCourses(this.value)">
      </div>
      <button class="tb-icon" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <button class="tb-icon" title="Exporter" onclick="toast('📥 Export PDF généré')"><i class="bi bi-download"></i></button>
      <div class="tb-user">
        <div class="tb-ava"><?= $u_init ?></div>
        <div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- Header -->
    <div class="page-header">
      <div class="page-title">
        <i class="bi bi-book-fill"></i>
        <div>
          <h1>Mes Cours</h1>
          <p id="pageSubtitle">Année 2025–2026 — Semestre 1 & 2</p>
        </div>
      </div>
      <div class="page-actions">
        <span id="resultCount" style="font-size:12px;color:var(--txt3);padding:6px 10px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm)"></span>
      </div>
    </div>

    <!-- Summary tiles -->
    <div class="sum-row">
      <div class="sum-tile">
        <div class="sum-icon" style="background:var(--teal-dim);color:var(--teal)"><i class="bi bi-book-fill"></i></div>
        <div><div class="sum-val" id="sumTotal">5</div><div class="sum-lbl">Cours dispensés</div></div>
      </div>
      <div class="sum-tile">
        <div class="sum-icon" style="background:var(--blue-dim);color:var(--blue)"><i class="bi bi-people-fill"></i></div>
        <div><div class="sum-val" id="sumStudents">191</div><div class="sum-lbl">Étudiants total</div></div>
      </div>
      <div class="sum-tile">
        <div class="sum-icon" style="background:var(--green-dim);color:var(--green)"><i class="bi bi-graph-up"></i></div>
        <div><div class="sum-val" id="sumAvg">13.6</div><div class="sum-lbl">Moyenne générale</div></div>
      </div>
      <div class="sum-tile">
        <div class="sum-icon" style="background:var(--orange-dim);color:var(--orange)"><i class="bi bi-pencil-square"></i></div>
        <div><div class="sum-val" id="sumPending">3</div><div class="sum-lbl">Notes à saisir</div></div>
      </div>
      <div class="sum-tile">
        <div class="sum-icon" style="background:var(--purple-dim);color:var(--purple)"><i class="bi bi-calendar-check"></i></div>
        <div><div class="sum-val" id="sumHours">10h</div><div class="sum-lbl">Heures / semaine</div></div>
      </div>
    </div>

    <!-- Cards grid -->
    <div class="courses-grid" id="coursesGrid"></div>

    <!-- Empty state -->
    <div class="empty-state" id="emptyState" style="display:none">
      <i class="bi bi-search"></i>
      <p>Aucun cours trouvé</p>
      <small>Essayez un autre terme de recherche ou filtre</small>
    </div>

  </div>
</main>

<!-- ═══ COURSE DETAIL MODAL ═══ -->
<div class="modal-overlay" id="courseModal" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="modal-banner" id="modalBanner">
      <button class="modal-close-btn" onclick="closeModal()">✕</button>
      <div class="modal-banner-code" id="mCode"></div>
      <div class="modal-banner-name" id="mName"></div>
      <div class="modal-banner-sub"  id="mSub"></div>
    </div>
    <div class="modal-body">
      <div class="modal-stats" id="mStats"></div>

      <div class="modal-section-title"><i class="bi bi-bar-chart-fill"></i>Répartition des notes</div>
      <div style="position:relative;height:160px;margin-bottom:18px"><canvas id="distChart"></canvas></div>

      <div class="modal-section-title"><i class="bi bi-people-fill"></i>Aperçu des étudiants</div>
      <div id="mStudents" style="max-height:200px;overflow-y:auto;margin-bottom:18px"></div>

      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="toast('📋 Feuille de présence téléchargée');closeModal()">
          <i class="bi bi-check2-square"></i>Présences
        </button>
        <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="toast('📤 Ressources déposées');closeModal()">
          <i class="bi bi-cloud-upload"></i>Ressources
        </button>
        <button class="btn btn-primary" style="flex:1;justify-content:center" onclick="toast('📝 Saisie des notes ouverte');closeModal()">
          <i class="bi bi-pencil-square"></i>Saisir notes
        </button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ══════════════════════════════════
   DATA
══════════════════════════════════ */
const COURSES = <?= json_encode(array_map(fn($m)=>[
  'id'        => (int)$m['id'],
  'code'      => $m['code'],
  'name'      => $m['nom'],
  'type'      => $m['type_eval'],
  'semester'  => (int)$m['semestre'],
  'classes'   => $m['classes'] ?? '—',
  'students'  => (int)($m['nb_classes']??0)*40,
  'avg'       => $m['moy_module'] !== null ? (float)$m['moy_module'] : null,
  'published' => (int)($m['nb_notes_publiees']??0),
], $mes_cours)) ?>;

let currentFilter = 'all';
let currentSearch = '';
let distChartRef  = null;

/* ══ INIT ══ */
document.addEventListener('DOMContentLoaded', () => {
  renderCards();
  document.getElementById('countAll').textContent = '('+COURSES.length+')';
});

/* ══ RENDER CARDS ══ */
function renderCards(){
  const grid = document.getElementById('coursesGrid');
  const empty = document.getElementById('emptyState');
  const q = currentSearch.toLowerCase();

  const visible = COURSES.filter(c => {
    const matchFilter =
      currentFilter === 'all'     ||
      (currentFilter === 'active'  && c.status === 'active') ||
      (currentFilter === 'pending' && c.pending) ||
      (currentFilter === 'done'    && c.status === 'done');
    const matchSearch = !q || c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q) || c.groupe.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  document.getElementById('resultCount').textContent = visible.length + ' cours affiché' + (visible.length>1?'s':'');

  if(!visible.length){
    grid.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  grid.innerHTML = visible.map(c => {
    const ci = COURSES.indexOf(c);
    const avgColor = c.avg >= 14 ? '#27ae60' : c.avg >= 12 ? '#326ea9' : c.avg >= 10 ? '#e67e22' : '#C41E3A';
    const attColor = c.attendance >= 90 ? '#27ae60' : c.attendance >= 75 ? '#e67e22' : '#C41E3A';
    const statusBadge = c.status === 'done'
      ? '<span class="badge badge-green"><i class="bi bi-check-circle"></i>Terminé</span>'
      : c.pending
        ? '<span class="badge badge-orange"><i class="bi bi-pencil-square"></i>Notes à saisir</span>'
        : '<span class="badge badge-teal"><i class="bi bi-play-circle"></i>En cours</span>';
    const typeColor = c.type.includes('Projet') ? 'badge-purple' : c.type.includes('TP') ? 'badge-teal' : 'badge-blue';

    return `<div class="course-card" onclick="showCourse(${ci})">
      <div class="cc-header" style="background:${c.gradient}">
        <div class="cc-code">${c.code}</div>
        <div class="cc-name">${c.name}</div>
        <div class="cc-groupe"><i class="bi bi-people"></i>${c.groupe}</div>
        <span class="cc-type-badge">${c.type}</span>
      </div>
      <div class="cc-body">
        <div class="cc-stats">
          <div class="cc-stat">
            <div class="cc-stat-val" style="color:${avgColor}">${c.avg}</div>
            <div class="cc-stat-lbl">Moy.</div>
          </div>
          <div class="cc-stat">
            <div class="cc-stat-val">${c.students}</div>
            <div class="cc-stat-lbl">Étudiants</div>
          </div>
          <div class="cc-stat">
            <div class="cc-stat-val" style="color:${attColor}">${c.attendance}%</div>
            <div class="cc-stat-lbl">Présence</div>
          </div>
          <div class="cc-stat">
            <div class="cc-stat-val">${c.hours}</div>
            <div class="cc-stat-lbl">Semaine</div>
          </div>
        </div>
        <div class="cc-prog-label">
          <span>Avancement du semestre</span>
          <span>${c.progress}%</span>
        </div>
        <div class="prog-track">
          <div class="prog-fill" style="width:${c.progress}%;background:${c.gradient}"></div>
        </div>
        <div class="cc-footer">
          ${statusBadge}
          <div class="cc-next"><i class="bi bi-calendar-event"></i>${c.nextSession.split('—')[0].trim()}</div>
        </div>
      </div>
    </div>`;
  }).join('');
}

/* ══ FILTERS & SEARCH ══ */
function filterCourses(filter, btn){
  currentFilter = filter;
  document.querySelectorAll('#filterTabs .tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderCards();
}
function searchCourses(q){
  currentSearch = q;
  renderCards();
}

/* ══ COURSE MODAL ══ */
function showCourse(idx){
  const c = COURSES[idx];
  const avgColor = c.avg>=14?'#27ae60':c.avg>=12?'#326ea9':c.avg>=10?'#e67e22':'#C41E3A';

  // Banner
  document.getElementById('modalBanner').style.background = c.gradient;
  document.getElementById('mCode').textContent = c.code + ' · ' + c.semester;
  document.getElementById('mName').textContent = c.name;
  document.getElementById('mSub').textContent  = c.groupe + ' · ' + c.type + ' · ' + c.hours;

  // Stats
  document.getElementById('mStats').innerHTML = `
    <div class="modal-stat"><div class="modal-stat-val" style="color:${avgColor}">${c.avg}/20</div><div class="modal-stat-lbl">Moy. classe</div></div>
    <div class="modal-stat"><div class="modal-stat-val">${c.students}</div><div class="modal-stat-lbl">Étudiants</div></div>
    <div class="modal-stat"><div class="modal-stat-val" style="color:${c.attendance>=90?'#27ae60':c.attendance>=75?'#e67e22':'#C41E3A'}">${c.attendance}%</div><div class="modal-stat-lbl">Présence</div></div>
    <div class="modal-stat"><div class="modal-stat-val">${c.progress}%</div><div class="modal-stat-lbl">Avancement</div></div>`;

  // Distribution chart
  if(distChartRef) distChartRef.destroy();
  const ctx = document.getElementById('distChart').getContext('2d');
  const distColors = ['#C41E3A','#e67e22','#D4A843','#326ea9','#0891b2','#27ae60'];
  distChartRef = new Chart(ctx, {
    type:'bar',
    data:{
      labels:['<8','8–10','10–12','12–14','14–16','≥16'],
      datasets:[{
        label:'Étudiants',
        data:c.distribution,
        backgroundColor:distColors.map(col=>col+'cc'),
        borderColor:distColors,
        borderWidth:1.5,
        borderRadius:5
      }]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>' '+ctx.raw+' étudiant'+(ctx.raw>1?'s':'')}}},
      scales:{
        x:{grid:{display:false},ticks:{font:{family:'Ubuntu',size:10}}},
        y:{beginAtZero:true,ticks:{font:{family:'Ubuntu',size:10},stepSize:1},grid:{color:'rgba(0,0,0,.04)'}}
      }
    }
  });

  // Student list
  document.getElementById('mStudents').innerHTML = c.students_list.map(s => {
    const nc = s.avg>=16?'#27ae60':s.avg>=14?'#326ea9':s.avg>=12?'#e67e22':'#C41E3A';
    const ini = s.name.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    return `<div class="modal-student-row">
      <div class="ms-ava" style="background:${s.color}">${ini}</div>
      <div class="ms-name">${s.name}</div>
      <div class="ms-bar-wrap"><div class="ms-bar-fill" style="width:${(s.avg/20*100).toFixed(0)}%;background:${nc}"></div></div>
      <div class="ms-note" style="color:${nc}">${s.avg}</div>
    </div>`;
  }).join('') + `<div style="text-align:center;padding:8px;font-size:11px;color:var(--txt3)">… et ${c.students - c.students_list.length} autres étudiants</div>`;

  document.getElementById('courseModal').classList.add('open');
}
function closeModal(){
  document.getElementById('courseModal').classList.remove('open');
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
</script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body>
</html>
