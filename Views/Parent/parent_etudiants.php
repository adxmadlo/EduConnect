<?php
/**
 * EduConnect — parent_etudiants.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Mon Enfant (Parent)
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

// Enfant lié
$enfant = db_row($conn,
    "SELECT u.id AS etu_user_id, u.nom, u.prenom, u.numero_ucao, u.email, u.telephone,
            e.id AS etu_id, e.credits_obtenus, e.credits_requis, e.statut_academique,
            e.date_naissance, e.adresse,
            c.code AS classe_code, c.nom AS classe_nom, c.annee_etude
     FROM etudiants e
     JOIN users u ON u.id = e.user_id
     JOIN classes c ON c.id = e.classe_id
     WHERE e.parent_id = ? LIMIT 1",
    "i",[$uid]
);
$enfant_nom   = $enfant ? h($enfant["prenom"]." ".$enfant["nom"]) : "Enfant non lié";
$enfant_init  = $enfant ? h(substr($enfant["prenom"],0,1).substr($enfant["nom"],0,1)) : "?";
$enfant_class = $enfant ? h($enfant["classe_code"]) : "";
$etu_id       = (int)($enfant["etu_id"] ?? 0);

// Notes S1 et S2
$notes_enfant = db_query($conn,
    "SELECT m.nom AS module_nom, m.type_eval, n.note_devoir, n.note_examen, n.note_finale,
            n.semestre, m.credits, m.coefficient,
            CASE WHEN n.note_finale>=16 THEN 'TB' WHEN n.note_finale>=14 THEN 'B'
                 WHEN n.note_finale>=12 THEN 'AB' WHEN n.note_finale>=10 THEN 'P'
                 WHEN n.note_finale IS NOT NULL THEN 'F' ELSE '—' END AS mention,
            COALESCE(u.prenom,'') AS prof_prenom, COALESCE(u.nom,'') AS prof_nom
     FROM notes n
     JOIN modules m ON m.id = n.module_id
     LEFT JOIN module_enseignants me ON me.module_id = m.id AND me.est_principal = 1
     LEFT JOIN users u ON u.id = me.enseignant_id
     WHERE n.etudiant_id = ? AND n.publie = 1 AND n.annee_acad = '2025-2026'
     ORDER BY n.semestre, m.nom",
    "i",[$etu_id]
);

// Calcul moyennes par semestre
$notes_s1 = array_filter($notes_enfant, fn($n)=>$n["semestre"]==1);
$notes_s2 = array_filter($notes_enfant, fn($n)=>$n["semestre"]==2);
$calc_moy_w = function($rows){
    $sp=0;$sc=0; foreach($rows as $r){ $sp+=(float)($r["note_finale"]??0)*(int)$r["coefficient"]; $sc+=(int)$r["coefficient"]; }
    return $sc>0?round($sp/$sc,2):null;
};
$moy_enfant_s1 = $calc_moy_w($notes_s1);
$moy_enfant_s2 = $calc_moy_w($notes_s2);

// Présences (données depuis EDT si disponible)
$sessions_mentorat_enfant = db_query($conn,
    "SELECT sm.date_session, sm.heure_debut, sm.statut, m.nom AS module_nom,
            CONCAT(u.prenom,' ',u.nom) AS mentor_nom
     FROM sessions_mentorat sm
     JOIN modules m ON m.id=sm.module_id
     JOIN mentors mt ON mt.id=sm.mentor_id
     JOIN users u ON u.id=mt.user_id
     WHERE sm.etudiant_id=?
     ORDER BY sm.date_session DESC LIMIT 10",
    "i",[$etu_id]
);

// Rang dans la classe
$rang_e = db_row($conn,
    "SELECT COUNT(*)+1 AS rang FROM (
        SELECT e2.id, AVG(n2.note_finale) AS moy
        FROM etudiants e2 JOIN notes n2 ON n2.etudiant_id=e2.id
        WHERE e2.classe_id=(SELECT classe_id FROM etudiants WHERE id=?) AND n2.publie=1
        GROUP BY e2.id
        HAVING moy > COALESCE((SELECT AVG(n3.note_finale) FROM notes n3 WHERE n3.etudiant_id=? AND n3.publie=1),0)
    ) t",
    "ii",[$etu_id,$etu_id]
)["rang"] ?? 0;

$classe_size_e = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM etudiants WHERE classe_id=(SELECT classe_id FROM etudiants WHERE id=?)","i",[$etu_id])["nb"]??0);



$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Mon Enfant</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--gold-dk:#b8892e;--gold-lt:#f0c45a;--gold-dim:rgba(212,168,67,.15);
  --purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
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

/* === SIDEBAR === */
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:54px}
.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--gold),var(--gold-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(212,168,67,.5)}
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
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--gold-lt);border-radius:0 2px 2px 0}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--gold-lt)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--gold-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}

/* === MAIN === */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}
.topbar-brand{font-size:13px;font-weight:600;color:var(--gold-dk);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}
.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--gold);color:#fff;box-shadow:0 1px 4px rgba(212,168,67,.4)}
.tab-btn i{font-size:13px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--gold);background:var(--gold-dim)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1}

/* === PROFILE HERO === */
.profile-hero{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:20px 24px;margin-bottom:16px;animation:fadeUp .3s ease;display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap}
.profile-avatar{width:72px;height:72px;border-radius:14px;background:linear-gradient(135deg,var(--green),var(--green-lt));display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:#fff;flex-shrink:0;box-shadow:0 4px 12px rgba(39,174,96,.3)}
.profile-info{flex:1;min-width:0}
.profile-info h1{font-size:20px;font-weight:700;color:var(--txt);margin-bottom:3px}
.profile-info p{font-size:12px;color:var(--txt3);margin-bottom:8px}
.profile-chips{display:flex;gap:7px;flex-wrap:wrap}
.chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
.chip-blue{background:var(--blue-dim);color:var(--blue)}
.chip-green{background:var(--green-dim);color:var(--green)}
.chip-gold{background:var(--gold-dim);color:var(--gold-dk)}
.chip i{font-size:12px}
.profile-stats{display:flex;gap:16px;flex-wrap:wrap;margin-left:auto}
.pstat{text-align:center;padding:8px 14px;background:var(--surface2);border-radius:var(--r-sm);border:1px solid var(--border);min-width:70px}
.pstat-val{font-size:20px;font-weight:700;color:var(--gold-dk)}
.pstat-lbl{font-size:10px;color:var(--txt3);margin-top:1px}

/* === LAYOUT GRIDS === */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.two-col-a{display:grid;grid-template-columns:1.3fr 1fr;gap:16px;margin-bottom:16px}
.three-col{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}

/* === WIDGETS === */
.widget{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;transition:all var(--tr);animation:fadeUp .3s ease}
.widget:hover{box-shadow:var(--sh-md)}
.widget-head{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.widget-title{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:6px}
.widget-title i{color:var(--gold-dk);font-size:14px}
.widget-body{padding:14px 16px}
.w-btn{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:12px;cursor:pointer;transition:all var(--tr)}
.w-btn:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}

/* === STAT CARDS === */
.stat-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}
.stat-card{background:var(--surface);border-radius:var(--r-sm);border:1px solid var(--border);box-shadow:var(--sh);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;transition:all var(--tr);animation:fadeUp .3s ease}
.stat-card:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.stat-label{font-size:11px;color:var(--txt3);font-weight:500;margin-bottom:3px}
.stat-value{font-size:20px;font-weight:700;color:var(--txt)}
.stat-icon{width:38px;height:38px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-icon.gold{background:var(--gold-dim);color:var(--gold-dk)}
.stat-icon.green{background:var(--green-dim);color:var(--green)}
.stat-icon.blue{background:var(--blue-dim);color:var(--blue)}
.stat-icon.orange{background:var(--orange-dim);color:var(--orange)}
.stat-icon.red{background:var(--red-dim);color:var(--red)}

/* === TABLE === */
.table-wrap{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;margin-bottom:16px}
.table{width:100%;border-collapse:collapse}
.table th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}
.table td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}
.table tr:hover{background:var(--surface2)}
.table tr:last-child td{border:none}

/* === NOTE BAR === */
.note-bar{height:6px;background:var(--border);border-radius:10px;overflow:hidden;margin-top:3px}
.note-fill{height:100%;border-radius:10px}

/* === BADGES === */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-red{background:var(--red-dim);color:var(--red)}
.badge-gold{background:var(--gold-dim);color:var(--gold-dk)}
.badge-purple{background:var(--purple-dim);color:var(--purple)}

/* === TIMELINE === */
.timeline{position:relative;padding-left:20px}
.timeline::before{content:'';position:absolute;left:6px;top:6px;bottom:6px;width:2px;background:linear-gradient(to bottom,var(--gold),rgba(212,168,67,.2));border-radius:10px}
.tl-item{position:relative;padding:0 0 14px 18px}
.tl-item:last-child{padding-bottom:0}
.tl-dot{position:absolute;left:-14px;top:4px;width:10px;height:10px;border-radius:50%;border:2px solid var(--surface);flex-shrink:0}
.tl-dot.ok{background:var(--green)}
.tl-dot.warn{background:var(--orange)}
.tl-dot.info{background:var(--blue)}
.tl-dot.mentor{background:var(--purple)}
.tl-time{font-size:10px;color:var(--txt3);font-weight:600;margin-bottom:2px}
.tl-title{font-size:12.5px;font-weight:600;color:var(--txt)}
.tl-meta{font-size:11px;color:var(--txt3);margin-top:1px}

/* === MENTOR CARDS === */
.mentor-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
.mentor-item:last-child{border:none;padding-bottom:0}
.mentor-ava{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.mentor-name{font-size:13px;font-weight:600;color:var(--txt)}
.mentor-mod{font-size:11px;color:var(--txt3)}
.mentor-stars{font-size:11px;color:var(--gold);margin-top:1px}

/* === ABSENCES === */
.absence-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)}
.absence-row:last-child{border:none}
.absence-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.absence-dot.justified{background:var(--orange)}
.absence-dot.unjustified{background:var(--red)}

/* === PROGRESS RING === */
.ring-wrap{display:flex;flex-direction:column;align-items:center;gap:6px;padding:8px 0}
.ring-svg{transform:rotate(-90deg)}
.ring-track{fill:none;stroke:var(--border);stroke-width:8}
.ring-fill{fill:none;stroke-width:8;stroke-linecap:round;transition:stroke-dashoffset 1s ease}
.ring-pct{font-size:22px;font-weight:700;color:var(--gold-dk)}
.ring-sub{font-size:11px;color:var(--txt3)}

/* === TOAST === */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--gold)}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1200px){.two-col,.two-col-a,.three-col{grid-template-columns:1fr}.stat-cards{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.profile-hero{flex-direction:column}.profile-stats{margin-left:0}.stat-cards{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-person-hearts"></i></div>
    <div>
      <div class="logo-text">Edu<span>Connect</span></div>
      <div style="font-size:9px;color:rgba(255,255,255,.4)">Parent / Tuteur</div>
    </div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Mon Espace</div>
    <button class="nav-item <?= $current_page === 'parent_dashboard.php' ? 'active' : '' ?>" onclick="location.href='parent_dashboard.php'"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></button>
    <button class="nav-item active"><i class="bi bi-person-fill"></i><span class="nav-item-txt">Mon Enfant</span></button>
    <button class="nav-item <?= $current_page === 'parent_paiements.php' ? 'active' : '' ?>" onclick="location.href='parent_paiements.php'"><i class="bi bi-credit-card"></i><span class="nav-item-txt">Paiements</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'parent_notes.php' ? 'active' : '' ?>" onclick="location.href='parent_notes.php'"><i class="bi bi-graph-up"></i><span class="nav-item-txt">Notes & Bulletins</span></button>
    <div class="nav-section">Suivi</div>
    <button class="nav-item <?= $current_page === 'parent_emploi_du_temps.php' ? 'active' : '' ?>" onclick="location.href='parent_emploi_du_temps.php'"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></button>
    <button class="nav-item <?= $current_page === 'parent_messages.php' ? 'active' : '' ?>" onclick="location.href='parent_messages.php'"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'parent_notifications.php' ? 'active' : '' ?>" onclick="location.href='parent_notifications.php'"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item <?= $current_page === 'parent_profil.php' ? 'active' : '' ?>" onclick="location.href='parent_profil.php'"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
    <button class="nav-item <?= $current_page === 'parent_parametres.php' ? 'active' : '' ?>" onclick="location.href='parent_parametres.php'"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Parent/parent_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role">Parent · Marie Sow (L2)</div></div>
    </div>
  
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- MAIN -->
<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-person-fill"></i> Fiche de mon enfant</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="showTab('overview',this)"><i class="bi bi-person-lines-fill"></i>Vue générale</button>
      <button class="tab-btn" onclick="showTab('notes',this)"><i class="bi bi-graph-up"></i>Notes</button>
      <button class="tab-btn" onclick="showTab('paiements',this)"><i class="bi bi-credit-card"></i>Paiements</button>
      <button class="tab-btn" onclick="showTab('presences',this)"><i class="bi bi-calendar-check"></i>Présences</button>
      <button class="tab-btn" onclick="showTab('mentorat',this)"><i class="bi bi-star"></i>Mentorat</button>
    </div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="toast('📥 Fiche complète exportée en PDF')" title="Exporter"><i class="bi bi-download"></i></button>
      <button class="tb-icon" onclick="location.href='parent_notifications.php'" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <div class="tb-user" onclick="location.href='parent_profil.php'">
        <div class="tb-ava"><?= $u_init ?></div>
        <div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- ══ PROFILE HERO ══ -->
    <div class="profile-hero">
      <div class="profile-avatar">MS</div>
      <div class="profile-info">
        <h1>Marie Sow</h1>
        <p>Matricule : #STU001 · marie.sow@ucao.edu · UCAO Saint-Michel</p>
        <div class="profile-chips">
          <span class="chip chip-blue"><i class="bi bi-mortarboard-fill"></i> L2 Informatique</span>
          <span class="chip chip-green"><i class="bi bi-check-circle-fill"></i> Statut actif</span>
          <span class="chip chip-green"><i class="bi bi-credit-card-fill"></i> Paiement à jour</span>
          <span class="chip chip-gold"><i class="bi bi-star-fill"></i> Mention TB — Semestre 1</span>
        </div>
      </div>
      <div class="profile-stats">
        <div class="pstat"><div class="pstat-val">15.5</div><div class="pstat-lbl">Moyenne /20</div></div>
        <div class="pstat"><div class="pstat-val">3/42</div><div class="pstat-lbl">Rang classe</div></div>
        <div class="pstat"><div class="pstat-val">30/30</div><div class="pstat-lbl">Crédits</div></div>
        <div class="pstat"><div class="pstat-val">75%</div><div class="pstat-lbl">Scolarité</div></div>
      </div>
    </div>

    <!-- ══ TABS CONTENT ══ -->
    <div id="tab-overview">
      <div class="stat-cards">
        <div class="stat-card"><div><div class="stat-label">Moyenne générale</div><div class="stat-value">15.5/20</div></div><div class="stat-icon gold"><i class="bi bi-graph-up"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Meilleure note</div><div class="stat-value">18/20</div></div><div class="stat-icon green"><i class="bi bi-star-fill"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Crédits validés</div><div class="stat-value">30/30</div></div><div class="stat-icon blue"><i class="bi bi-patch-check"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Présences</div><div class="stat-value">94%</div></div><div class="stat-icon green"><i class="bi bi-calendar-check"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Sessions mentorat</div><div class="stat-value">5</div></div><div class="stat-icon blue"><i class="bi bi-people"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Rang de classe</div><div class="stat-value">3/42</div></div><div class="stat-icon gold"><i class="bi bi-award"></i></div></div>
      </div>

      <div class="two-col-a">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-graph-up"></i>Progression des notes par matière</div></div>
          <div class="widget-body"><canvas id="radarChart" height="260"></canvas></div>
        </div>
        <div>
          <div class="widget" style="margin-bottom:12px">
            <div class="widget-head"><div class="widget-title"><i class="bi bi-activity"></i>Activité récente</div></div>
            <div class="widget-body">
              <div class="timeline">
                <div class="tl-item"><div class="tl-dot ok"></div><div class="tl-time">Aujourd'hui 14h</div><div class="tl-title">Cours Informatique</div><div class="tl-meta">Salle C3 · Prof. Ndiaye</div></div>
                <div class="tl-item"><div class="tl-dot ok"></div><div class="tl-time">Aujourd'hui 09h</div><div class="tl-title">Note Droit Civil publiée</div><div class="tl-meta">17.5/20 — Mention TB</div></div>
                <div class="tl-item"><div class="tl-dot mentor"></div><div class="tl-time">Hier 18h</div><div class="tl-title">Session mentorat en ligne</div><div class="tl-meta">Fatou Diop — Droit Civil</div></div>
                <div class="tl-item"><div class="tl-dot info"></div><div class="tl-time">Il y a 2 jours</div><div class="tl-title">Réservation salle confirmée</div><div class="tl-meta">Salle B2 · Révision groupe</div></div>
                <div class="tl-item"><div class="tl-dot ok"></div><div class="tl-time">15 Jan 2026</div><div class="tl-title">Paiement confirmé</div><div class="tl-meta">125 000 FCFA · Wave · #REC-2026-0341</div></div>
              </div>
            </div>
          </div>
          <div class="widget">
            <div class="widget-head"><div class="widget-title"><i class="bi bi-person-badge"></i>Informations académiques</div></div>
            <div class="widget-body" style="display:flex;flex-direction:column;gap:8px">
              <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)"><span style="font-size:12px;color:var(--txt3)">Matricule</span><span style="font-size:12px;font-weight:600">#STU001</span></div>
              <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)"><span style="font-size:12px;color:var(--txt3)">Niveau</span><span class="badge badge-blue">L2 Informatique</span></div>
              <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)"><span style="font-size:12px;color:var(--txt3)">Année académique</span><span style="font-size:12px;font-weight:600">2025/2026</span></div>
              <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)"><span style="font-size:12px;color:var(--txt3)">Semestre en cours</span><span style="font-size:12px;font-weight:600">Semestre 2</span></div>
              <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)"><span style="font-size:12px;color:var(--txt3)">Statut inscription</span><span class="badge badge-green"><i class="bi bi-check-circle"></i> Actif</span></div>
              <div style="display:flex;justify-content:space-between;padding:7px 0"><span style="font-size:12px;color:var(--txt3)">Paiement frais</span><span class="badge badge-green"><i class="bi bi-check-circle"></i> À jour</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="tab-notes" style="display:none">
      <div class="two-col">
        <div class="table-wrap">
          <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:6px">
            <i class="bi bi-list-ol" style="color:var(--gold-dk)"></i>Relevé de notes — Semestre 1
          </div>
          <table class="table">
            <thead><tr><th>Matière</th><th>Note /20</th><th>Progression</th><th>Coeff.</th><th>Mention</th></tr></thead>
            <tbody>
                <?php
                $hist_pe = db_query($conn,
                    "SELECT p.recu_numero, p.montant, p.moyen, p.statut, p.created_at
                     FROM frais_scolarite f JOIN paiements p ON p.frais_id=f.id
                     WHERE f.etudiant_id=? ORDER BY p.created_at DESC LIMIT 5",
                    "i",[$etu_id]
                );
                foreach ($hist_pe as $hp):
                  $sc=match($hp["statut"]){"confirme"=>"badge-green","en_attente"=>"badge-orange","echec"=>"badge-red",default=>"badge-orange"};
                ?>
                <tr>
                  <td><?= h($hp["recu_numero"]) ?></td>
                  <td><?= h(date("d/m/Y",strtotime($hp["created_at"]))) ?></td>
                  <td><?= number_format((float)$hp["montant"],0,","," ") ?> FCFA</td>
                  <td><?= ucfirst(str_replace("_"," ",$hp["moyen"])) ?></td>
                  <td><span class="badge <?= $sc ?>"><?= ucfirst($hp["statut"]) ?></span></td>
                </tr>
                <?php endforeach; if(empty($hist_pe)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--txt3);padding:14px">Aucun paiement</td></tr>
                <?php endif; ?>
              </tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="tab-presences" style="display:none">
      <div class="stat-cards">
        <div class="stat-card"><div><div class="stat-label">Présences totales</div><div class="stat-value">94%</div></div><div class="stat-icon green"><i class="bi bi-check-circle"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Absences justifiées</div><div class="stat-value">2</div></div><div class="stat-icon orange"><i class="bi bi-exclamation-circle"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Absences injustifiées</div><div class="stat-value">1</div></div><div class="stat-icon red"><i class="bi bi-x-circle"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Retards</div><div class="stat-value">3</div></div><div class="stat-icon gold"><i class="bi bi-clock-history"></i></div></div>
      </div>
      <div class="two-col">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-calendar-check"></i>Détail des absences</div></div>
          <div class="widget-body">
            <div class="absence-row"><div class="absence-dot justified"></div><div style="flex:1"><div style="font-size:12.5px;font-weight:600">Économie — 18 Jan 2026</div><div style="font-size:11px;color:var(--txt3)">Justifiée — Certificat médical</div></div><span class="badge badge-orange">Justifiée</span></div>
            <div class="absence-row"><div class="absence-dot justified"></div><div style="flex:1"><div style="font-size:12.5px;font-weight:600">Philosophie — 25 Fév 2026</div><div style="font-size:11px;color:var(--txt3)">Justifiée — Événement familial</div></div><span class="badge badge-orange">Justifiée</span></div>
            <div class="absence-row"><div class="absence-dot unjustified"></div><div style="flex:1"><div style="font-size:12.5px;font-weight:600">Mathématiques — 5 Mar 2026</div><div style="font-size:11px;color:var(--txt3)">Non justifiée</div></div><span class="badge badge-red">Injustifiée</span></div>
          </div>
        </div>
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-bar-chart"></i>Présences par matière</div></div>
          <div class="widget-body"><canvas id="presenceChart" height="260"></canvas></div>
        </div>
      </div>
    </div>

    <div id="tab-mentorat" style="display:none">
      <div class="stat-cards">
        <div class="stat-card"><div><div class="stat-label">Mentors actifs</div><div class="stat-value">2</div></div><div class="stat-icon blue"><i class="bi bi-people"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Sessions complétées</div><div class="stat-value">5</div></div><div class="stat-icon green"><i class="bi bi-check2-all"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Sessions à venir</div><div class="stat-value">2</div></div><div class="stat-icon gold"><i class="bi bi-calendar-event"></i></div></div>
        <div class="stat-card"><div><div class="stat-label">Progrès moyen</div><div class="stat-value">+1.5</div></div><div class="stat-icon green"><i class="bi bi-graph-up-arrow"></i></div></div>
      </div>
      <div class="two-col">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-people-fill"></i>Mes mentors</div></div>
          <div class="widget-body">
            <div class="mentor-item"><div class="mentor-ava" style="background:#326ea9">FD</div><div style="flex:1"><div class="mentor-name">Fatou Diop</div><div class="mentor-mod">Droit Civil</div><div class="mentor-stars">★★★★★ (4.9)</div></div><span class="badge badge-green">Disponible</span></div>
            <div class="mentor-item"><div class="mentor-ava" style="background:#27ae60">KS</div><div style="flex:1"><div class="mentor-name">Kofi Seck</div><div class="mentor-mod">Informatique</div><div class="mentor-stars">★★★★☆ (4.7)</div></div><span class="badge badge-orange">En session</span></div>
          </div>
        </div>
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-calendar2-check"></i>Sessions récentes</div></div>
          <div class="widget-body">
            <table class="table">
              <thead><tr><th>Date</th><th>Mentor</th><th>Module</th><th>Type</th><th>Statut</th></tr></thead>
              <tbody>
                <?php foreach ($sessions_mentorat_enfant as $sm_p):
                  $stc=match($sm_p["statut"]){"confirmee"=>"badge-green","demandee"=>"badge-orange","terminee"=>"badge-blue","annulee"=>"badge-red",default=>"badge-gray"};
                ?>
                <tr>
                  <td><?= h($sm_p["mentor_nom"]) ?></td>
                  <td><?= h($sm_p["module_nom"]) ?></td>
                  <td><?= h(date("d/m/Y",strtotime($sm_p["date_session"]))) ?> <?= h($sm_p["heure_debut"]) ?></td>
                  <td><span class="badge <?= $stc ?>"><?= ucfirst($sm_p["statut"]) ?></span></td>
                </tr>
                <?php endforeach; if(empty($sessions_mentorat_enfant)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--txt3);padding:14px">Aucune session</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</main>

<div class="toast" id="toast"></div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

function showTab(name, btn) {
  ['overview','notes','paiements','presences','mentorat'].forEach(t => {
    const el = document.getElementById('tab-'+t);
    if (el) el.style.display = 'none';
  });
  const el = document.getElementById('tab-'+name);
  if (el) { el.style.display = 'block'; el.style.animation = 'fadeUp .3s ease both'; }
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (name === 'notes' && !window.radarBuilt) { buildNotesRadar(); window.radarBuilt = true; }
  if (name === 'presences' && !window.presenceBuilt) { buildPresenceChart(); window.presenceBuilt = true; }
}

let toastTimer;
function toast(msg){
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

document.addEventListener('DOMContentLoaded', () => {
  // Overview radar
  new Chart(document.getElementById('radarChart').getContext('2d'), {
    type: 'radar',
    data: {
      labels: ['Droit','Info','Maths','Éco','Anglais','Philo','Physique'],
      datasets: [{
        label: 'Notes', data: [17.5,16,14.5,13,18,12,15],
        backgroundColor: 'rgba(212,168,67,.12)', borderColor: '#D4A843',
        borderWidth: 2, pointBackgroundColor: '#b8892e', pointRadius: 4
      }]
    },
    options: { responsive:true, scales:{ r:{ min:0, max:20, ticks:{stepSize:5,font:{family:'Ubuntu',size:11}}, pointLabels:{font:{family:'Ubuntu',size:11}} } }, plugins:{ legend:{display:false} } }
  });
});

function buildNotesRadar() {
  new Chart(document.getElementById('notesRadar').getContext('2d'), {
    type: 'radar',
    data: {
      labels: ['Droit','Info','Maths','Éco','Anglais','Philo','Physique'],
      datasets: [{
        label: 'Semestre 1', data: [17.5,16,14.5,13,18,12,15],
        backgroundColor: 'rgba(50,110,169,.12)', borderColor: '#326ea9',
        borderWidth: 2, pointBackgroundColor: '#D4A843', pointRadius: 4
      }]
    },
    options: { responsive:true, scales:{ r:{ min:0, max:20, ticks:{stepSize:5,font:{family:'Ubuntu',size:11}}, pointLabels:{font:{family:'Ubuntu',size:11}} } }, plugins:{ legend:{display:false} } }
  });
}

function buildPresenceChart() {
  new Chart(document.getElementById('presenceChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['Droit','Info','Maths','Éco','Anglais','Philo','Physique'],
      datasets: [
        { label: 'Présent', data: <?= json_encode(array_map(fn($n)=>(float)($n['note_finale']??0),array_slice($notes_enfant,0,7))) ?>, backgroundColor: 'rgba(39,174,96,.7)', borderRadius: 4 },
        { label: 'Absent',  data: <?= json_encode(array_fill(0,min(7,count($notes_enfant)),10)) ?>,       backgroundColor: 'rgba(196,30,58,.5)', borderRadius: 4 }
      ]
    },
    options: {
      responsive: true, indexAxis: 'y',
      plugins: { legend: { position:'bottom', labels:{ font:{family:'Ubuntu',size:11} } } },
      scales: { x: { beginAtZero:true, max:100, ticks:{ font:{family:'Ubuntu',size:11}, callback:v=>v+'%' } }, y: { ticks:{ font:{family:'Ubuntu',size:11} } } }
    }
  });
}
</script>
</body>
</html>
