<?php
/**
 * EduConnect — etudiant_emploi_du_temps.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('etudiant');
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Étudiant';
$current_page = basename(__FILE__);

// ── Notifications non lues (badge sidebar) ─────────────────
$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0",
    "i", [(int)$user["id"]]
)["nb"] ?? 0);
// ── Emploi du temps étudiant (semaine courante) ──────────────
$etu_row_edt = db_row($conn,"SELECT e.id, e.classe_id FROM etudiants e WHERE e.user_id=?","i",[(int)$user["id"]]);
$classe_id_edt2 = (int)($etu_row_edt["classe_id"] ?? 0);
$semaine_offset_edt = (int)($_GET["semaine"] ?? 0);
$lundi_edt = new DateTime(); $lundi_edt->modify("monday this week");
if ($semaine_offset_edt!==0) $lundi_edt->modify("{$semaine_offset_edt} week");
$samedi_edt = clone $lundi_edt; $samedi_edt->modify("+5 days");
$debut_edt = $lundi_edt->format("Y-m-d 00:00:00");
$fin_edt   = $samedi_edt->format("Y-m-d 23:59:59");
$semaine_lbl_edt = $lundi_edt->format("d/m")." au ".$samedi_edt->format("d/m/Y");

$edt_etudiant = db_query($conn,
    "SELECT et.date_debut, et.date_fin, et.type_creneau,
            m.nom AS module_nom, m.code AS module_code,
            s.nom AS salle_nom, s.batiment,
            COALESCE(CONCAT(u.prenom,' ',u.nom),'—') AS prof_nom
     FROM emplois_du_temps et
     LEFT JOIN modules m ON m.id=et.module_id
     LEFT JOIN salles s ON s.id=et.salle_id
     LEFT JOIN users u ON u.id=et.enseignant_id
     WHERE et.classe_id=? AND et.date_debut BETWEEN ? AND ?
     ORDER BY et.date_debut",
    "iss",[$classe_id_edt2,$debut_edt,$fin_edt]
);
$prochains_ex_edt = db_query($conn,
    "SELECT et.date_debut, m.nom AS module_nom, s.nom AS salle_nom, et.type_creneau
     FROM emplois_du_temps et
     LEFT JOIN modules m ON m.id=et.module_id
     LEFT JOIN salles s ON s.id=et.salle_id
     WHERE et.classe_id=? AND et.type_creneau IN ('examen','rattrapage') AND et.date_debut>=NOW()
     ORDER BY et.date_debut LIMIT 5",
    "i",[$classe_id_edt2]
);


$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Mon Emploi du Temps</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════
   TOKENS — identiques à tout le projet
═══════════════════════════════════════════ */
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
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:54px}
.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--green),var(--green-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(39,174,96,.5)}
.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}
.logo-text span{color:#a8c8e8}
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
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--green);border-radius:0 2px 2px 0}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--green)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--green),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}

/* ═══ MAIN / TOPBAR ═══ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}
.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}
.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--blue);color:#fff;box-shadow:0 1px 4px rgba(50,110,169,.3)}
.tab-btn i{font-size:13px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:180px}
.tb-search:focus-within{border-color:var(--blue);background:#fff}
.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}
.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}
.tb-search input::placeholder{color:var(--txt3)}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--green),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;animation:fadeUp .3s ease}
.page-title{display:flex;align-items:center;gap:10px}
.page-title h1{font-size:22px;font-weight:700;color:var(--txt)}
.page-title i{font-size:26px;color:var(--blue)}
.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.page-actions{display:flex;gap:8px;align-items:center}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent}
.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}
.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.btn-sm{padding:5px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-red{background:var(--red-dim);color:var(--red)}
.badge-purple{background:var(--purple-dim);color:var(--purple)}
.badge-teal{background:var(--teal-dim);color:var(--teal)}

/* ═══ WEEK NAVIGATOR ═══ */
.week-nav{
  display:flex;align-items:center;gap:10px;
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  padding:10px 14px;margin-bottom:16px;
  box-shadow:var(--sh);animation:fadeUp .3s ease;
}
.week-nav-arrow{
  width:32px;height:32px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  color:var(--txt2);font-size:15px;cursor:pointer;
  transition:all var(--tr);flex-shrink:0;
}
.week-nav-arrow:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}
.week-label{flex:1;text-align:center;font-weight:600;font-size:13px;color:var(--txt)}
.week-label small{color:var(--txt3);font-weight:400;font-size:11px;display:block;margin-top:2px}
.week-today{
  padding:6px 14px;border-radius:var(--r-sm);
  background:var(--blue-dim);border:1px solid var(--blue);
  color:var(--blue);font-size:11px;font-weight:600;cursor:pointer;
  transition:all var(--tr);
}
.week-today:hover{background:var(--blue);color:#fff}

/* ═══ LEGEND ═══ */
.legend{
  display:flex;align-items:center;gap:14px;flex-wrap:wrap;
  padding:10px 14px;background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);margin-bottom:14px;box-shadow:var(--sh);
}
.legend-item{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--txt2)}
.legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}

/* ═══ TIMETABLE GRID ═══ */
.tt-wrap{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);box-shadow:var(--sh);
  overflow-x:auto;animation:fadeUp .3s .1s ease both;
}
.tt-grid{
  display:grid;
  grid-template-columns:54px repeat(5,1fr);
  min-width:700px;
}

/* Day headers */
.tt-corner{background:var(--surface2);border-bottom:2px solid var(--border);border-right:1px solid var(--border)}
.tt-day-hdr{
  background:var(--surface2);border-bottom:2px solid var(--border);
  border-right:1px solid var(--border);padding:10px 8px;
  text-align:center;position:sticky;top:0;z-index:10;
}
.tt-day-name{font-size:11px;font-weight:700;color:var(--txt);text-transform:uppercase;letter-spacing:.5px}
.tt-day-date{font-size:10px;color:var(--txt3);margin-top:2px}
.tt-day-hdr.today{background:var(--blue-dim);border-bottom-color:var(--blue)}
.tt-day-hdr.today .tt-day-name{color:var(--blue)}
.tt-day-hdr.today .tt-day-date{color:var(--blue)}

/* Time labels */
.tt-time-lbl{
  display:flex;align-items:flex-start;justify-content:flex-end;
  padding:8px 8px 0 0;font-size:10px;color:var(--txt3);
  border-right:1px solid var(--border);border-bottom:1px solid var(--border);
  font-weight:600;
}

/* Slots */
.tt-slot{
  border-right:1px solid var(--border);
  border-bottom:1px solid var(--border);
  min-height:52px;padding:4px;
  position:relative;transition:background var(--tr);
}
.tt-slot:hover{background:var(--surface2)}

/* Course block inside a slot */
.course-block{
  height:100%;min-height:44px;
  border-radius:5px;padding:5px 8px;
  display:flex;flex-direction:column;justify-content:space-between;
  cursor:pointer;transition:all var(--tr);position:relative;overflow:hidden;
  border-left:3px solid transparent;
}
.course-block::after{content:'';position:absolute;inset:0;background:rgba(255,255,255,0);transition:background var(--tr)}
.course-block:hover::after{background:rgba(255,255,255,.12)}
.course-block:hover{transform:scale(1.02);box-shadow:0 2px 8px rgba(0,0,0,.15)}

.cb-blue  {background:var(--blue-dim);   border-left-color:var(--blue);}
.cb-green {background:var(--green-dim);  border-left-color:var(--green);}
.cb-orange{background:var(--orange-dim); border-left-color:var(--orange);}
.cb-purple{background:var(--purple-dim); border-left-color:var(--purple);}
.cb-teal  {background:var(--teal-dim);   border-left-color:var(--teal);}
.cb-red   {background:var(--red-dim);    border-left-color:var(--red);}

.cb-name  {font-size:11px;font-weight:700;color:var(--txt);line-height:1.2}
.cb-prof  {font-size:9.5px;color:var(--txt2);margin-top:2px}
.cb-meta  {display:flex;align-items:center;gap:5px;margin-top:4px;flex-wrap:wrap}
.cb-room  {font-size:9px;font-weight:600;padding:1px 5px;border-radius:3px;background:rgba(0,0,0,.07);color:var(--txt2)}
.cb-type  {font-size:9px;font-weight:600;padding:1px 5px;border-radius:3px}

/* Empty slot hint */
.slot-free{
  height:100%;min-height:44px;
  border-radius:5px;border:1.5px dashed var(--border2);
  display:flex;align-items:center;justify-content:center;
  color:var(--txt3);font-size:11px;cursor:default;
  transition:all var(--tr);
}
.slot-free:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-xlt)}

/* Break row */
.tt-break{
  background:repeating-linear-gradient(
    45deg,var(--surface2),var(--surface2) 4px,var(--surface) 4px,var(--surface) 12px
  );
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:10px;color:var(--txt3);letter-spacing:.5px;
  font-style:italic;grid-column:span 6;min-height:28px;
}

/* ═══ STATS ROW ═══ */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px;animation:fadeUp .3s ease}
.stat-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);padding:12px 14px;
  display:flex;align-items:center;gap:12px;
  box-shadow:var(--sh);transition:all var(--tr);cursor:default;
}
.stat-card:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.stat-icon{
  width:38px;height:38px;border-radius:var(--r-sm);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;flex-shrink:0;
}
.stat-val{font-size:20px;font-weight:700;color:var(--txt);line-height:1}
.stat-lbl{font-size:10px;color:var(--txt3);margin-top:2px}

/* ═══ LIST VIEW ═══ */
.list-view{animation:fadeUp .3s ease}
.list-day-group{margin-bottom:16px}
.list-day-title{
  font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;
  color:var(--txt3);margin-bottom:8px;padding-bottom:6px;
  border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px;
}
.list-day-title i{color:var(--blue);font-size:13px}
.list-course{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);margin-bottom:6px;padding:12px 14px;
  display:flex;align-items:center;gap:14px;
  box-shadow:var(--sh);transition:all var(--tr);cursor:pointer;
  border-left:4px solid transparent;
}
.list-course:hover{box-shadow:var(--sh-md);transform:translateX(3px)}
.list-course.lc-blue  {border-left-color:var(--blue)}
.list-course.lc-green {border-left-color:var(--green)}
.list-course.lc-orange{border-left-color:var(--orange)}
.list-course.lc-purple{border-left-color:var(--purple)}
.list-course.lc-teal  {border-left-color:var(--teal)}
.list-course.lc-red   {border-left-color:var(--red)}
.lc-time{font-size:11px;font-weight:700;color:var(--txt3);min-width:100px;flex-shrink:0}
.lc-icon{width:36px;height:36px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.lc-name{font-size:13px;font-weight:700;color:var(--txt);margin-bottom:2px}
.lc-details{font-size:11px;color:var(--txt3);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.lc-details i{font-size:11px}
.lc-right{margin-left:auto;text-align:right;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;gap:4px}

/* ═══ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:12px;padding:24px;width:440px;max-width:95vw;box-shadow:var(--sh-lg);animation:fadeUp .25s ease}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:16px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red)}
.modal-field{margin-bottom:12px}
.modal-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:4px}
.modal-value{font-size:13px;color:var(--txt);display:flex;align-items:center;gap:6px}
.modal-value i{color:var(--blue);font-size:14px}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--green)}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}
  .main{margin-left:0}
  .content{padding:12px 14px}
  .stats-row{grid-template-columns:1fr 1fr}
  .page-header{flex-direction:column;align-items:flex-start;gap:10px}
}
</style>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
</head>
<body>

<!-- ════════════════════ SIDEBAR ════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-mortarboard-fill"></i></div>
    <div>
      <div class="logo-text">Edu<span>Connect</span></div>
      <div style="font-size:9px;color:rgba(255,255,255,.4)">Étudiant</div>
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
    <a href="etudiant_dashboard.php" class="nav-item">
      <i class="bi bi-speedometer2"></i>
      <span class="nav-item-txt">Tableau de Bord</span>
    </a>
    <a href="etudiant_cours.php" class="nav-item">
      <i class="bi bi-book"></i>
      <span class="nav-item-txt">Mes Cours</span>
    </a>
    <a href="etudiants_notes.php" class="nav-item">
      <i class="bi bi-graph-up"></i>
      <span class="nav-item-txt">Mes Notes</span>
    </a>
    <a href="etudiant_mentoring.php" class="nav-item">
      <i class="bi bi-star"></i>
      <span class="nav-item-txt">Mentorat</span>
    </a>

    <div class="nav-section">Académique</div>
    <a href="etudiant_emploi_du_temps.php" class="nav-item active">
      <i class="bi bi-calendar3"></i>
      <span class="nav-item-txt">Emploi du temps</span>
    </a>
    <a href="etudiant_reservation_salles.php" class="nav-item">
      <i class="bi bi-building"></i>
      <span class="nav-item-txt">Réserver une salle</span>
    </a>
    <a href="etudiant_paiements.php" class="nav-item">
      <i class="bi bi-credit-card"></i>
      <span class="nav-item-txt">Paiements</span>
    </a>
    <a href="etudiant_messages.php" class="nav-item">
      <i class="bi bi-chat-dots"></i>
      <span class="nav-item-txt">Messages</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
    <a href="etudiant_notifications.php" class="nav-item">
      <i class="bi bi-bell"></i>
      <span class="nav-item-txt">Notifications</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Etudiant/etudiant_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role"><?= $u_role ?></div>
      </div>
    </div>
  
  <a href="/Views/Etudiant/etudiant_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
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
      <i class="bi bi-calendar3"></i>
      Mon Emploi du Temps
    </div>

    <div class="tab-nav" id="tabNav">
      <button class="tab-btn active" onclick="setView('grid',this)">
        <i class="bi bi-grid-3x3-gap"></i>Grille semaine
      </button>
      <button class="tab-btn" onclick="setView('list',this)">
        <i class="bi bi-list-ul"></i>Liste
      </button>
    </div>

    <div class="topbar-right">
      <div class="tb-search">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Rechercher un cours…" id="searchInput" oninput="filterCourses()">
      </div>
      <button class="tb-icon" title="Notifications" onclick="toast('🔔 3 rappels de cours cette semaine')">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <button class="tb-icon" title="Imprimer / Exporter" onclick="toast('🖨️ Impression de l\'emploi du temps…')">
        <i class="bi bi-printer"></i>
      </button>
      <div class="tb-user" onclick="toast('👤 Profil — Marie Sow')">
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

    <!-- Page header -->
    <div class="page-header">
      <div class="page-title">
        <i class="bi bi-calendar3"></i>
        <div>
          <h1 id="pageTitle">Emploi du Temps</h1>
          <p id="pageSubtitle">L2 Informatique — Semestre 1, 2025–2026</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="prevWeek()"><i class="bi bi-chevron-left"></i>Précédente</button>
        <button class="btn btn-secondary" onclick="goToday()"><i class="bi bi-calendar-check"></i>Aujourd'hui</button>
        <button class="btn btn-secondary" onclick="nextWeek()">Suivante<i class="bi bi-chevron-right"></i></button>
        <button class="btn btn-primary" onclick="toast('📥 Export PDF téléchargé')"><i class="bi bi-download"></i>Exporter</button>
      </div>
    </div>

    <!-- Weekly stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-dim);color:var(--blue)"><i class="bi bi-book-fill"></i></div>
        <div><div class="stat-val" id="statCours">0</div><div class="stat-lbl">Cours cette semaine</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--orange-dim);color:var(--orange)"><i class="bi bi-clock-fill"></i></div>
        <div><div class="stat-val" id="statHeures">0h</div><div class="stat-lbl">Heures au total</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-dim);color:var(--green)"><i class="bi bi-calendar-check-fill"></i></div>
        <div><div class="stat-val" id="statProchain">–</div><div class="stat-lbl">Prochain cours</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--purple-dim);color:var(--purple)"><i class="bi bi-person-fill"></i></div>
        <div><div class="stat-val" id="statProfs">0</div><div class="stat-lbl">Enseignants</div></div>
      </div>
    </div>

    <!-- Week navigator -->
    <div class="week-nav">
      <button class="week-nav-arrow" onclick="prevWeek()"><i class="bi bi-chevron-left"></i></button>
      <div class="week-label">
        <span id="weekRangeLabel">…</span>
        <small id="weekRelLabel"></small>
      </div>
      <button class="week-today" onclick="goToday()"><i class="bi bi-crosshair"></i> Aujourd'hui</button>
      <button class="week-nav-arrow" onclick="nextWeek()"><i class="bi bi-chevron-right"></i></button>
    </div>

    <!-- Legend -->
    <div class="legend">
      <span style="font-size:11px;font-weight:600;color:var(--txt2);margin-right:4px">Légende :</span>
      <div class="legend-item"><div class="legend-dot" style="background:var(--blue)"></div>Cours magistral</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--green)"></div>Travaux Dirigés</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--orange)"></div>Travaux Pratiques</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--purple)"></div>Projet / Soutenance</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--teal)"></div>Examen</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--red)"></div>Annulé</div>
    </div>

    <!-- Grid view -->
    <div id="view-grid" class="tt-wrap">
      <div class="tt-grid" id="ttGrid"></div>
    </div>

    <!-- List view (hidden initially) -->
    <div id="view-list" class="list-view" style="display:none">
      <div id="listContainer"></div>
    </div>

  </div><!-- /content -->
</main>

<!-- Course detail modal -->
<div class="modal-overlay" id="courseModal" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title" id="modalTitle"><i class="bi bi-book-fill" style="color:var(--blue)"></i>Détail du cours</div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div id="modalBody"></div>
    <div style="display:flex;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
      <button class="btn btn-secondary" style="flex:1" onclick="toast('📅 Rappel ajouté à votre calendrier');closeModal()">
        <i class="bi bi-bell"></i>Rappel
      </button>
      <button class="btn btn-primary" style="flex:1" onclick="toast('📥 Ressources du cours téléchargées');closeModal()">
        <i class="bi bi-download"></i>Ressources
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ════════════════════════════════════════
   DATA — Emploi du temps L2 Informatique
════════════════════════════════════════ */
const SLOTS = <?= json_encode(array_map(fn($ev)=>[
  'date'   => $ev["date_debut"],
  'fin'    => $ev["date_fin"],
  'module' => $ev["module_nom"] ?? "—",
  'code'   => $ev["module_code"] ?? "",
  'salle'  => $ev["salle_nom"] ?? "—",
  'prof'   => $ev["prof_nom"] ?? "—",
  'type'   => $ev["type_creneau"],
  'day'    => (int)date("N",strtotime($ev["date_debut"])),
], $edt_etudiant)) ?>;

// slot index → display hour label
const SLOT_LABELS = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00'];

// day index 0=Lun … 4=Ven
// Each course: {slot, day, name, prof, room, type, color, coeff}
const COURSES = [
  // Lundi
  { slot:0, day:0, name:'Algorithmique Avancée', prof:'Dr. Amadou Diallo', room:'B202', type:'CM', color:'blue',   coeff:3, desc:'Algorithmes de tri et de graphes — Chapitre 4 : Parcours BFS/DFS' },
  { slot:3, day:0, name:'Algorithmique Avancée', prof:'Dr. Amadou Diallo', room:'B202', type:'TD', color:'green',  coeff:3, desc:'Feuille d\'exercices n°4 — Complexité et récursivité' },
  // Mardi
  { slot:0, day:1, name:'Bases de Données',      prof:'Mme Fatima Ba',    room:'C303', type:'CM', color:'blue',   coeff:2, desc:'Modèle relationnel — Normalisation 3NF / BCNF' },
  { slot:3, day:1, name:'Bases de Données',      prof:'Mme Fatima Ba',    room:'A101', type:'TP', color:'orange', coeff:2, desc:'TP PostgreSQL n°3 — Requêtes imbriquées et jointures' },
  { slot:4, day:1, name:'Réseaux Informatiques', prof:'Dr. Seydou Ndiaye', room:'B202', type:'CM', color:'blue',  coeff:2, desc:'TCP/IP — Couche transport et protocoles UDP/TCP' },
  // Mercredi
  { slot:1, day:2, name:'Systèmes d\'Exploitation', prof:'M. Ibrahima Sow', room:'C303', type:'CM', color:'blue', coeff:2, desc:'Gestion de la mémoire — Pagination et segmentation' },
  { slot:3, day:2, name:'Systèmes d\'Exploitation', prof:'M. Ibrahima Sow', room:'A101', type:'TP', color:'orange',coeff:2, desc:'TP Linux n°5 — Gestion des processus et threads' },
  { slot:4, day:2, name:'Anglais Technique',     prof:'Mme Claire Martin', room:'D404', type:'TD', color:'teal',  coeff:1, desc:'Technical writing & oral presentation skills — Unit 6' },
  // Jeudi
  { slot:0, day:3, name:'Réseaux Informatiques', prof:'Dr. Seydou Ndiaye', room:'A101', type:'TP', color:'orange',coeff:2, desc:'TP Wireshark n°2 — Analyse de trames réseau' },
  { slot:3, day:3, name:'Mathématiques Discrètes',prof:'Prof. Modou Fall',  room:'C303', type:'CM', color:'blue', coeff:3, desc:'Théorie des graphes — Arbres couvrants et algorithme de Kruskal' },
  { slot:4, day:3, name:'Mathématiques Discrètes',prof:'Prof. Modou Fall',  room:'D404', type:'TD', color:'green',coeff:3, desc:'Exercices sur les relations d\'ordre et les treillis' },
  // Vendredi
  { slot:1, day:4, name:'Projet Développement',  prof:'Mme Fatima Ba',    room:'B202', type:'Projet',color:'purple',coeff:4,desc:'Sprint 3 — Présentation de l\'avancement du projet web EduConnect' },
  { slot:3, day:4, name:'Mathématiques Discrètes',prof:'Prof. Modou Fall',  room:'C303', type:'TD', color:'green', coeff:3, desc:'Préparation examen — Révision générale des chapitres 1–5' },
];

const DAYS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const TYPE_LABELS = { CM:'Cours',TD:'TD',TP:'TP',Projet:'Projet',Examen:'Examen' };

/* ════ STATE ════ */
let weekOffset = 0;
let currentView = 'grid';

/* ════ INIT ════ */
document.addEventListener('DOMContentLoaded', () => {
  renderAll();
});

function renderAll(){
  updateWeekLabel();
  renderGrid();
  renderList();
  renderStats();
}

/* ════ WEEK NAVIGATION ════ */
function getMonday(offset){
  const d = new Date();
  const day = d.getDay(); // 0=sun
  const diff = d.getDate() - day + (day === 0 ? -6 : 1); // monday
  const mon = new Date(d.setDate(diff + offset * 7));
  mon.setHours(0,0,0,0);
  return mon;
}

function updateWeekLabel(){
  const mon = getMonday(weekOffset);
  const fri = new Date(mon); fri.setDate(mon.getDate() + 4);
  const fmtShort = d => d.toLocaleDateString('fr-FR',{day:'numeric',month:'short'});
  const fmtFull  = d => d.toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'});
  document.getElementById('weekRangeLabel').textContent =
    fmtShort(mon) + ' – ' + fmtFull(fri);
  const rel = weekOffset === 0 ? 'Semaine actuelle'
    : weekOffset === 1 ? 'Semaine prochaine'
    : weekOffset === -1 ? 'Semaine précédente'
    : `Semaine ${weekOffset > 0 ? '+':''}${weekOffset}`;
  document.getElementById('weekRelLabel').textContent = rel;
}

function prevWeek(){ weekOffset--; renderAll(); }
function nextWeek(){ weekOffset++; renderAll(); }
function goToday(){ weekOffset = 0; renderAll(); }

/* ════ STATS ════ */
function renderStats(){
  const count = COURSES.length;
  const hours = COURSES.reduce((a,c)=>{
    const [s,e] = SLOTS[c.slot].split('–').map(t=>{ const [h,m]=t.split(':'); return parseInt(h)+parseInt(m)/60; });
    return a + (e - s);
  }, 0);
  const profs = [...new Set(COURSES.map(c=>c.prof))].length;

  animCount('statCours', count);
  document.getElementById('statHeures').textContent = hours + 'h';
  document.getElementById('statProchain').textContent = 'Algo';
  animCount('statProfs', profs);
}

function animCount(id, target){
  const el = document.getElementById(id); if(!el) return;
  const t0 = performance.now();
  (function step(now){
    const t = Math.min((now-t0)/600, 1);
    el.textContent = Math.round((1-Math.pow(1-t,3))*target);
    if(t<1) requestAnimationFrame(step);
  })(t0);
}

/* ════ GRID VIEW ════ */
function renderGrid(){
  const mon = getMonday(weekOffset);
  const today = new Date(); today.setHours(0,0,0,0);
  const grid = document.getElementById('ttGrid');
  let html = '';

  // Corner
  html += '<div class="tt-corner" style="padding:10px 8px;text-align:center;font-size:9px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px">Horaire</div>';

  // Day headers
  DAYS.forEach((day,di) => {
    const date = new Date(mon); date.setDate(mon.getDate() + di);
    const isToday = date.getTime() === today.getTime() && weekOffset === 0;
    const dateStr = date.toLocaleDateString('fr-FR',{day:'2-digit',month:'short'});
    html += `<div class="tt-day-hdr${isToday?' today':''}">
      <div class="tt-day-name">${day}</div>
      <div class="tt-day-date">${dateStr}</div>
    </div>`;
  });

  // Rows
  SLOTS.forEach((slot, si) => {
    const isBreak = si === 2; // 12:00–13:00

    if(isBreak){
      html += `<div class="tt-break"><i class="bi bi-cup-hot" style="margin-right:5px"></i>Pause déjeuner — 12:00 à 13:00</div>`;
      return;
    }

    // Time label
    html += `<div class="tt-time-lbl">${SLOT_LABELS[si]}</div>`;

    // Day cells
    DAYS.forEach((_,di) => {
      const course = COURSES.find(c => c.slot === si && c.day === di);
      if(course){
        html += `<div class="tt-slot">
          <div class="course-block cb-${course.color}" onclick="showCourse(${COURSES.indexOf(course)})">
            <div>
              <div class="cb-name">${course.name}</div>
              <div class="cb-prof"><i class="bi bi-person" style="font-size:9px"></i> ${course.prof}</div>
            </div>
            <div class="cb-meta">
              <span class="cb-room"><i class="bi bi-geo-alt" style="font-size:8px"></i> ${course.room}</span>
              <span class="cb-type badge badge-${course.color==='blue'?'blue':course.color==='green'?'green':course.color==='orange'?'orange':course.color==='purple'?'blue':course.color==='teal'?'teal':'red'}" style="font-size:8px">${course.type}</span>
            </div>
          </div>
        </div>`;
      } else {
        html += `<div class="tt-slot"><div class="slot-free"></div></div>`;
      }
    });
  });

  grid.innerHTML = html;
}

/* ════ LIST VIEW ════ */
function renderList(){
  const mon = getMonday(weekOffset);
  let html = '';

  DAYS.forEach((day,di) => {
    const dayCourses = COURSES.filter(c=>c.day===di).sort((a,b)=>a.slot-b.slot);
    const date = new Date(mon); date.setDate(mon.getDate() + di);
    const dateStr = date.toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long'});
    const emoji = dayCourses.length === 0 ? '' : '';

    html += `<div class="list-day-group">
      <div class="list-day-title">
        <i class="bi bi-calendar-event"></i>
        ${dateStr.charAt(0).toUpperCase()+dateStr.slice(1)}
        <span class="badge badge-blue" style="margin-left:6px">${dayCourses.length} cours</span>
      </div>`;

    if(dayCourses.length === 0){
      html += `<div style="text-align:center;padding:20px;color:var(--txt3);font-size:12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh)">
        <i class="bi bi-calendar-x" style="font-size:22px;display:block;margin-bottom:6px;color:var(--border2)"></i>
        Pas de cours ce jour
      </div>`;
    } else {
      dayCourses.forEach(c => {
        const ci = COURSES.indexOf(c);
        const iconMap = {blue:'bi-book-fill',green:'bi-pencil-fill',orange:'bi-terminal-fill',purple:'bi-kanban-fill',teal:'bi-journal-check',red:'bi-x-circle-fill'};
        const bgMap   = {blue:'var(--blue-dim)',green:'var(--green-dim)',orange:'var(--orange-dim)',purple:'var(--purple-dim)',teal:'var(--teal-dim)',red:'var(--red-dim)'};
        const colMap  = {blue:'var(--blue)',green:'var(--green)',orange:'var(--orange)',purple:'var(--purple)',teal:'var(--teal)',red:'var(--red)'};
        html += `<div class="list-course lc-${c.color}" onclick="showCourse(${ci})">
          <div class="lc-time"><i class="bi bi-clock" style="margin-right:4px;color:var(--txt3)"></i>${SLOTS[c.slot]}</div>
          <div class="lc-icon" style="background:${bgMap[c.color]};color:${colMap[c.color]}"><i class="bi ${iconMap[c.color]}"></i></div>
          <div style="flex:1">
            <div class="lc-name">${c.name}</div>
            <div class="lc-details">
              <span><i class="bi bi-person"></i> ${c.prof}</span>
              <span><i class="bi bi-geo-alt"></i> Salle ${c.room}</span>
              <span class="badge badge-${c.color==='blue'?'blue':c.color==='green'?'green':c.color==='orange'?'orange':'blue'}">${c.type}</span>
            </div>
          </div>
          <div class="lc-right">
            <span style="font-size:10px;font-weight:600;color:var(--txt3)">Coeff. ${c.coeff}</span>
            <i class="bi bi-chevron-right" style="color:var(--txt3);font-size:13px"></i>
          </div>
        </div>`;
      });
    }
    html += '</div>';
  });

  document.getElementById('listContainer').innerHTML = html;
}

/* ════ VIEW TOGGLE ════ */
function setView(v, btn){
  currentView = v;
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('view-grid').style.display = v==='grid' ? 'block' : 'none';
  document.getElementById('view-list').style.display = v==='list' ? 'block' : 'none';
}

/* ════ COURSE MODAL ════ */
function showCourse(idx){
  const c = COURSES[idx];
  const typeLabels = {CM:'Cours Magistral',TD:'Travaux Dirigés',TP:'Travaux Pratiques',Projet:'Projet / Soutenance',Examen:'Examen'};
  const colMap = {blue:'var(--blue)',green:'var(--green)',orange:'var(--orange)',purple:'var(--purple)',teal:'var(--teal)',red:'var(--red)'};

  document.getElementById('modalTitle').innerHTML =
    `<i class="bi bi-book-fill" style="color:${colMap[c.color]}"></i>${c.name}`;

  document.getElementById('modalBody').innerHTML = `
    <div style="background:var(--surface2);border-radius:var(--r-sm);padding:12px;margin-bottom:14px;border-left:4px solid ${colMap[c.color]}">
      <div style="font-size:11px;color:var(--txt3);margin-bottom:4px">Résumé du cours</div>
      <div style="font-size:12px;color:var(--txt)">${c.desc}</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="modal-field">
        <div class="modal-label">Enseignant</div>
        <div class="modal-value"><i class="bi bi-person-fill"></i>${c.prof}</div>
      </div>
      <div class="modal-field">
        <div class="modal-label">Type</div>
        <div class="modal-value"><i class="bi bi-tag-fill"></i>${typeLabels[c.type]||c.type}</div>
      </div>
      <div class="modal-field">
        <div class="modal-label">Salle</div>
        <div class="modal-value"><i class="bi bi-geo-alt-fill"></i>Salle ${c.room}</div>
      </div>
      <div class="modal-field">
        <div class="modal-label">Horaire</div>
        <div class="modal-value"><i class="bi bi-clock-fill"></i>${SLOTS[c.slot]}</div>
      </div>
      <div class="modal-field">
        <div class="modal-label">Jour</div>
        <div class="modal-value"><i class="bi bi-calendar-event-fill"></i>${DAYS[c.day]}</div>
      </div>
      <div class="modal-field">
        <div class="modal-label">Coefficient</div>
        <div class="modal-value"><i class="bi bi-award-fill"></i>${c.coeff}</div>
      </div>
    </div>
  `;
  document.getElementById('courseModal').classList.add('open');
}
function closeModal(){ document.getElementById('courseModal').classList.remove('open'); }

/* ════ SEARCH ════ */
function filterCourses(){
  const q = document.getElementById('searchInput').value.toLowerCase();
  if(currentView === 'grid'){
    document.querySelectorAll('.course-block').forEach(el => {
      const txt = el.textContent.toLowerCase();
      el.closest('.tt-slot').style.opacity = (!q || txt.includes(q)) ? '1' : '.2';
    });
  } else {
    document.querySelectorAll('.list-course').forEach(el => {
      const txt = el.textContent.toLowerCase();
      el.style.display = (!q || txt.includes(q)) ? '' : 'none';
    });
  }
}

/* ════ SIDEBAR ════ */
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

/* ════ TOAST ════ */
function toast(msg){
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  setTimeout(()=>el.classList.remove('show'), 3000);
}
</script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body>
</html>
