<?php
/**
 * EduConnect — parent_emploi_du_temps.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Emploi du Temps Enfant (Parent)
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$enfant_edt = db_row($conn,
    "SELECT e.id AS etu_id, e.classe_id, u.prenom, u.nom
     FROM etudiants e JOIN users u ON u.id=e.user_id
     WHERE e.parent_id=? LIMIT 1",
    "i",[$uid]
);
$enfant_nom   = $enfant_edt ? h($enfant_edt["prenom"]." ".$enfant_edt["nom"]) : "Enfant non lié";
$enfant_init  = $enfant_edt ? h(substr($enfant_edt["prenom"],0,1).substr($enfant_edt["nom"],0,1)) : "?";
$enfant_class = "";
$classe_id_edt = (int)($enfant_edt["classe_id"] ?? 0);

$semaine_offset_p = (int)($_GET["semaine"] ?? 0);
$lundi_p = new DateTime(); $lundi_p->modify("monday this week");
if ($semaine_offset_p!==0) $lundi_p->modify("{$semaine_offset_p} week");
$samedi_p = clone $lundi_p; $samedi_p->modify("+5 days");
$debut_p  = $lundi_p->format("Y-m-d 00:00:00");
$fin_p    = $samedi_p->format("Y-m-d 23:59:59");
$semaine_lbl_p = $lundi_p->format("d/m")." au ".$samedi_p->format("d/m/Y");

// EDT de la classe de l'enfant
$edt_enfant = db_query($conn,
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
    "iss",[$classe_id_edt,$debut_p,$fin_p]
);

// Prochains examens de la classe
$prochains_ex_p = db_query($conn,
    "SELECT et.date_debut, et.date_fin, m.nom AS module_nom, s.nom AS salle_nom, et.type_creneau
     FROM emplois_du_temps et
     LEFT JOIN modules m ON m.id=et.module_id
     LEFT JOIN salles s ON s.id=et.salle_id
     WHERE et.classe_id=? AND et.type_creneau IN ('examen','rattrapage') AND et.date_debut>=NOW()
     ORDER BY et.date_debut LIMIT 5",
    "i",[$classe_id_edt]
);



$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Emploi du Temps</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--gold-dk:#b8892e;--gold-lt:#f0c45a;--gold-dim:rgba(212,168,67,.15);--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--gold),var(--gold-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(212,168,67,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--gold-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--gold-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}.sidebar.collapsed .nav-badge{opacity:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--gold-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--gold-dk);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}.tab-nav::-webkit-scrollbar{display:none}.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}.tab-btn:hover{background:var(--surface);color:var(--txt)}.tab-btn.active{background:var(--gold);color:#fff;box-shadow:0 1px 4px rgba(212,168,67,.4)}.tab-btn i{font-size:13px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:180px}.tb-search:focus-within{border-color:var(--gold);background:#fff}.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}.tb-search input::placeholder{color:var(--txt3)}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}.tb-icon:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--gold);background:var(--gold-dim)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease;flex-wrap:wrap;gap:10px}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--gold-dk)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}.page-actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}.btn-primary{background:var(--gold);color:#fff;border-color:var(--gold)}.btn-primary:hover{background:var(--gold-dk);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{border-color:var(--gold);color:var(--gold-dk)}.btn-sm{padding:5px 10px;font-size:11px}.btn i{font-size:13px}
/* Week nav bar */
.week-nav{display:flex;align-items:center;justify-content:space-between;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 16px;margin-bottom:16px;box-shadow:var(--sh);animation:fadeUp .3s ease}
.week-label{font-size:13px;font-weight:700;color:var(--txt)}
.week-sub{font-size:11px;color:var(--txt3);margin-top:1px}
.week-arrows{display:flex;gap:6px}
.week-arrow{width:30px;height:30px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);cursor:pointer;transition:all var(--tr)}
.week-arrow:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}
/* Legend */
.legend{display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap}
.legend-item{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--txt2)}
.legend-dot{width:10px;height:10px;border-radius:2px;flex-shrink:0}
/* Timetable grid — same concept as admin_emploi_du_temps */
.tt-wrap{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;margin-bottom:20px}
.tt-grid{display:grid;grid-template-columns:56px repeat(5,1fr);border-collapse:collapse}
.tt-head{background:var(--blue-dk);color:#fff;padding:8px 6px;text-align:center;font-size:11px;font-weight:700;border-right:1px solid rgba(255,255,255,.1)}
.tt-head:first-child{border-right:1px solid var(--border);background:var(--surface2);color:var(--txt3)}
.tt-time{padding:0 6px;display:flex;align-items:flex-start;justify-content:flex-end;padding-top:8px;font-size:10px;color:var(--txt3);border-right:1px solid var(--border);border-bottom:1px solid var(--border);background:var(--surface2)}
.tt-cell{border-right:1px solid var(--border);border-bottom:1px solid var(--border);min-height:58px;padding:4px;position:relative}
.tt-cell:last-child{border-right:none}
.tt-slot{border-radius:5px;padding:5px 7px;height:100%;cursor:pointer;transition:all var(--tr);min-height:50px}
.tt-slot:hover{filter:brightness(.96);transform:scale(1.01)}
.tt-slot.empty{background:transparent}
.tt-slot.cours{background:var(--blue-dim);border:1px solid rgba(50,110,169,.2)}
.tt-slot.mentorat{background:var(--green-dim);border:1px solid rgba(39,174,96,.2)}
.tt-slot.revision{background:var(--gold-dim);border:1px solid rgba(212,168,67,.25)}
.tt-slot.examen{background:var(--red-dim);border:1px solid rgba(196,30,58,.2)}
.tt-slot.pause{background:var(--surface2);border:1px dashed var(--border)}
.slot-name{font-size:11px;font-weight:700;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.slot-name.cours{color:var(--blue)}
.slot-name.mentorat{color:var(--green)}
.slot-name.revision{color:var(--gold-dk)}
.slot-name.examen{color:var(--red)}
.slot-room{font-size:9px;color:var(--txt3);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* Table — reused from admin/etudiant style */
.table-container{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;margin-bottom:20px}.table{width:100%;border-collapse:collapse}.table th{padding:12px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}.table td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}.table tr:hover{background:var(--surface2)}.table tr:last-child td{border:none}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-green{background:var(--green-dim);color:var(--green)}.badge-orange{background:var(--orange-dim);color:var(--orange)}.badge-red{background:var(--red-dim);color:var(--red)}.badge-gold{background:var(--gold-dim);color:var(--gold-dk)}.badge-purple{background:var(--purple-dim);color:var(--purple)}
/* Today highlight */
.today-col{background:rgba(212,168,67,.04)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--gold)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:1100px){.tt-grid{grid-template-columns:48px repeat(5,1fr)}.slot-room{display:none}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start}.tt-wrap{overflow-x:auto}.tt-grid{min-width:560px}}
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
    <button class="nav-item active"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></button>
    <button class="nav-item <?= $current_page === 'parent_messages.php' ? 'active' : '' ?>" onclick="location.href='parent_messages.php'"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'parent_notifications.php' ? 'active' : '' ?>" onclick="location.href='parent_notifications.php'"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item <?= $current_page === 'parent_profil.php' ? 'active' : '' ?>" onclick="location.href='parent_profil.php'"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
    <button class="nav-item <?= $current_page === 'parent_parametres.php' ? 'active' : '' ?>" onclick="location.href='parent_parametres.php'"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Parent/parent_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role">Parent · Marie Sow (L2)</div></div></div>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-calendar3"></i>Emploi du Temps</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="setTab('semaine',this)"><i class="bi bi-calendar-week"></i>Semaine</button>
      <button class="tab-btn" onclick="setTab('liste',this)"><i class="bi bi-list-ul"></i>Liste des cours</button>
      <button class="tab-btn" onclick="setTab('evenements',this)"><i class="bi bi-calendar-event"></i>Événements</button>
    </div>
    <div class="topbar-right">
      <div class="tb-search"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher un cours…" onkeyup="filterTable(this.value)"></div>
      <button class="tb-icon" onclick="location.href='parent_notifications.php'" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <button class="tb-icon" onclick="toast('📥 Emploi du temps exporté en PDF')" title="Exporter"><i class="bi bi-download"></i></button>
      <div class="tb-user" onclick="location.href='parent_profil.php'"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title">
        <i class="bi bi-calendar3"></i>
        <div><h1>Emploi du Temps de Marie Sow</h1><p>L2 Informatique · UCAO Saint-Michel · 2025/2026</p></div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="toast('📅 Ajout au calendrier (iCal)')"><i class="bi bi-calendar-plus"></i>Synchroniser</button>
        <button class="btn btn-primary" onclick="toast('📥 PDF téléchargé')"><i class="bi bi-download"></i>Exporter PDF</button>
      </div>
    </div>

    <!-- Week navigator -->
    <div class="week-nav">
      <div class="week-arrows">
        <div class="week-arrow" onclick="changeWeek(-1)"><i class="bi bi-chevron-left"></i></div>
      </div>
      <div style="text-align:center">
        <div class="week-label" id="weekLabel">Semaine du 31 Mars au 4 Avril 2026</div>
        <div class="week-sub">Semestre 2 — Semaine 12/18</div>
      </div>
      <div class="week-arrows">
        <button class="btn btn-secondary btn-sm" onclick="goToday()">Aujourd'hui</button>
        <div class="week-arrow" onclick="changeWeek(1)"><i class="bi bi-chevron-right"></i></div>
      </div>
    </div>

    <!-- Legend -->
    <div class="legend">
      <div class="legend-item"><div class="legend-dot" style="background:var(--blue-dim);border:1px solid rgba(50,110,169,.3)"></div>Cours</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--green-dim);border:1px solid rgba(39,174,96,.3)"></div>Mentorat</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--gold-dim);border:1px solid rgba(212,168,67,.3)"></div>Révision</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--red-dim);border:1px solid rgba(196,30,58,.3)"></div>Examen</div>
      <div class="legend-item"><div class="legend-dot" style="background:var(--surface2);border:1px dashed var(--border)"></div>Libre</div>
    </div>

    <!-- Timetable grid -->
    <div class="tt-wrap">
      <div class="tt-grid">
              <?php foreach ($edt_enfant as $ev_tt):
                $day_num = (int)date("N", strtotime($ev_tt["date_debut"]));
                $tc_class = in_array($ev_tt["type_creneau"],["examen","rattrapage"]) ? "examen" : ($ev_tt["type_creneau"]==="revision" ? "revision" : "cours");
                $slot_label = h($ev_tt["module_nom"]??"—");
                $slot_time = h(date("H:i",strtotime($ev_tt["date_debut"])))."-".h(date("H:i",strtotime($ev_tt["date_fin"])));
              ?>
              <div class="tt-slot <?= $tc_class ?>"
                   onclick="showSlot('<?= addslashes($slot_label) ?>','<?= h(addslashes($ev_tt['prof_nom']??"—")) ?>','<?= h($ev_tt['salle_nom']??"—") ?>','<?= $slot_time ?>')">
                <div class="slot-name <?= $tc_class ?>"><?= $slot_label ?></div>
                <div class="slot-room"><?= h($ev_tt["salle_nom"]??"") ?></div>
              </div>
              <?php endforeach; ?>
              </div></div></div>
        <div class="tt-cell today-col"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot cours" onclick="showSlot('<?= h(addslashes($ev_tt['module_nom']?? '—')) ?>','<?= h(addslashes($ev_tt['prof_nom']??'—')) ?>','<?= h($ev_tt['salle_nom']??'—') ?>','<?= h(date('H:i',strtotime($ev_tt['date_debut']))).'-'.h(date('H:i',strtotime($ev_tt['date_fin']))) ?>')"><div class="slot-name cours">Économie</div><div class="slot-room">B2 · Prof. Niane</div></div></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot cours" onclick="showSlot('<?= h(addslashes($ev_tt['module_nom']?? '—')) ?>','<?= h(addslashes($ev_tt['prof_nom']??'—')) ?>','<?= h($ev_tt['salle_nom']??'—') ?>','<?= h(date('H:i',strtotime($ev_tt['date_debut']))).'-'.h(date('H:i',strtotime($ev_tt['date_fin']))) ?>')"><div class="slot-name cours">Anglais</div><div class="slot-room">C1 · Prof. Kane</div></div></div>

        <!-- 10h–12h -->
        <div class="tt-time">10h–12h</div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell today-col"><div class="tt-slot cours" onclick="showSlot('<?= h(addslashes($ev_tt['module_nom']?? '—')) ?>','<?= h(addslashes($ev_tt['prof_nom']??'—')) ?>','<?= h($ev_tt['salle_nom']??'—') ?>','<?= h(date('H:i',strtotime($ev_tt['date_debut']))).'-'.h(date('H:i',strtotime($ev_tt['date_fin']))) ?>')"><div class="slot-name cours">Informatique</div><div class="slot-room">C3 · Prof. Ndiaye</div></div></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot cours" onclick="showSlot('Droit Civil','Prof. Martin','Salle A1','Jeu 10h–12h')"><div class="slot-name cours">Droit Civil</div><div class="slot-room">A1 · Prof. Martin</div></div></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>

        <!-- 12h–14h (pause) -->
        <div class="tt-time">12h–14h</div>
        <div class="tt-cell"><div class="tt-slot pause" style="display:flex;align-items:center;justify-content:center"><span style="font-size:10px;color:var(--txt3)">Pause</span></div></div>
        <div class="tt-cell today-col"><div class="tt-slot pause" style="display:flex;align-items:center;justify-content:center"><span style="font-size:10px;color:var(--txt3)">Pause</span></div></div>
        <div class="tt-cell"><div class="tt-slot pause" style="display:flex;align-items:center;justify-content:center"><span style="font-size:10px;color:var(--txt3)">Pause</span></div></div>
        <div class="tt-cell"><div class="tt-slot pause" style="display:flex;align-items:center;justify-content:center"><span style="font-size:10px;color:var(--txt3)">Pause</span></div></div>
        <div class="tt-cell"><div class="tt-slot pause" style="display:flex;align-items:center;justify-content:center"><span style="font-size:10px;color:var(--txt3)">Pause</span></div></div>

        <!-- 14h–16h -->
        <div class="tt-time">14h–16h</div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell today-col"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot revision" onclick="showSlot('Révision Groupe','Marie + 4 camarades','Salle B2','Jeu 14h–16h')"><div class="slot-name revision">Révision Groupe</div><div class="slot-room">B2 · Groupe</div></div></div>
        <div class="tt-cell"><div class="tt-slot examen" onclick="showSlot('Contrôle — Mathématiques','Prof. Sall','Amphi B','Ven 14h–16h')"><div class="slot-name examen">Contrôle Maths</div><div class="slot-room">Amphi B · Prof. Sall</div></div></div>

        <!-- 16h–18h -->
        <div class="tt-time">16h–18h</div>
        <div class="tt-cell"><div class="tt-slot cours" onclick="showSlot('Physique','Prof. Ba','Salle B1','Lun 16h–18h')"><div class="slot-name cours">Physique</div><div class="slot-room">B1 · Prof. Ba</div></div></div>
        <div class="tt-cell today-col"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot cours" onclick="showSlot('Philosophie','Prof. Diop','Salle A3','Mer 16h–18h')"><div class="slot-name cours">Philosophie</div><div class="slot-room">A3 · Prof. Diop</div></div></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>

        <!-- 18h–20h -->
        <div class="tt-time">18h–20h</div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell today-col"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><?php foreach ($sessions_mentorat_enfant as $sm_tt):
          if ($sm_tt["statut"]==="confirmee" && $sm_tt["date_session"] >= date("Y-m-d")): ?>
        <div class="tt-slot mentorat" onclick="showSlot('Mentorat — <?= addslashes(h($sm_tt['module_nom'])) ?>','<?= addslashes(h($sm_tt['mentor_nom'])) ?>','Visioconférence','<?= h(date('D',strtotime($sm_tt['date_session']))).' '.h($sm_tt['heure_debut']).'–'.h($sm_tt['heure_fin']) ?>')">
          <div class="slot-name mentorat">Mentorat ⭐</div>
          <div class="slot-room">Visio · <?= h($sm_tt['mentor_nom']) ?></div>
        </div>
        <?php endif; endforeach; ?></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
        <div class="tt-cell"><div class="tt-slot empty"></div></div>
      </div>
    </div>

    <!-- List of courses table — same pattern as admin_emploi_du_temps -->
    <div class="table-container" id="coursesTable">
      <table class="table">
        <thead>
          <tr><th>Matière</th><th>Professeur</th><th>Jour</th><th>Horaire</th><th>Salle</th><th>Type</th><th>Statut</th></tr>
        </thead>
        <tbody id="tableBody">
          <?php foreach ($edt_enfant as $ev_r):
            $tc_r=in_array($ev_r["type_creneau"],["examen","rattrapage"])?"badge-red":(($ev_r["type_creneau"]==="revision")?"badge-orange":"badge-blue");
          ?>
          <tr>
            <td><strong><?= h($ev_r["module_nom"]??"—") ?></strong></td>
            <td><?= h($ev_r["prof_nom"]??"—") ?></td>
            <td><?= h(date("l",strtotime($ev_r["date_debut"]))) ?></td>
            <td><?= h(date("H:i",strtotime($ev_r["date_debut"])))."–".h(date("H:i",strtotime($ev_r["date_fin"]))) ?></td>
            <td><?= h($ev_r["salle_nom"]??"—") ?></td>
            <td><span class="badge badge-blue">Cours</span></td>
            <td><span class="badge badge-green">Actif</span></td>
          </tr>
          <?php endforeach; if (empty($edt_enfant)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--txt3);padding:14px">Aucun cours cette semaine</td></tr>
          <?php endif; ?>
          <tr><td><strong>Informatique</strong></td><td>Prof. Ndiaye</td><td>Mardi</td><td>10h–12h</td><td>Salle C3</td><td><span class="badge badge-blue">Cours</span></td><td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Actif</span></td></tr>
          <tr><td><strong>Mathématiques</strong></td><td>Prof. Sall</td><td>Vendredi</td><td>14h–16h</td><td>Amphi B</td><td><span class="badge badge-red">Examen</span></td><td><span class="badge badge-orange"><i class="bi bi-hourglass-split"></i>Vendredi</span></td></tr>
          <tr><td><strong>Économie</strong></td><td>Prof. Niane</td><td>Mercredi</td><td>08h–10h</td><td>Salle B2</td><td><span class="badge badge-blue">Cours</span></td><td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Actif</span></td></tr>
          <tr><td><strong>Anglais</strong></td><td>Prof. Kane</td><td>Vendredi</td><td>08h–10h</td><td>Salle C1</td><td><span class="badge badge-blue">Cours</span></td><td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Actif</span></td></tr>
          <tr><td><strong>Philosophie</strong></td><td>Prof. Diop</td><td>Mercredi</td><td>16h–18h</td><td>Salle A3</td><td><span class="badge badge-blue">Cours</span></td><td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Actif</span></td></tr>
          <tr><td><strong>Physique</strong></td><td>Prof. Ba</td><td>Lundi</td><td>16h–18h</td><td>Salle B1</td><td><span class="badge badge-blue">Cours</span></td><td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Actif</span></td></tr>
          <tr><td><strong>Révision Groupe</strong></td><td>Marie + camarades</td><td>Jeudi</td><td>14h–16h</td><td>Salle B2</td><td><span class="badge badge-gold">Révision</span></td><td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Confirmé</span></td></tr>
          <?php foreach ($sessions_mentorat_enfant as $sm_row):
            if ($sm_row["statut"]==="confirmee"): ?>
          <tr>
            <td><strong>Mentorat — <?= h($sm_row['module_nom']) ?></strong></td>
            <td><?= h($sm_row['mentor_nom']) ?></td>
            <td><?= h(date('l', strtotime($sm_row['date_session']))) ?></td>
            <td><?= h($sm_row['heure_debut']) ?>–<?= h($sm_row['heure_fin']) ?></td>
            <td>Visioconférence</td>
            <td><span class="badge" style="background:var(--green-dim);color:var(--green)">Mentorat</span></td>
            <td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Confirmé</span></td>
          </tr>
          <?php endif; endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Upcoming events -->
    <div class="table-container">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:6px">
        <i class="bi bi-calendar-event" style="color:var(--gold-dk)"></i>Événements à venir
      </div>
      <table class="table">
        <thead><tr><th>Date &amp; Heure</th><th>Événement</th><th>Matière</th><th>Lieu</th><th>Type</th></tr></thead>
        <tbody>
                <?php if (empty($edt_enfant)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--txt3);padding:20px">Aucun cours cette semaine</td></tr>
                <?php else: foreach ($edt_enfant as $ev_p):
                  $type_lbl = ["cours1"=>"Cours","cours2"=>"Cours","cours3"=>"Cours","revision"=>"Révision","examen"=>"Examen","rattrapage"=>"Rattrapage"][$ev_p["type_creneau"]] ?? "Cours";
                  $type_col = in_array($ev_p["type_creneau"],["examen","rattrapage"]) ? "badge-red" : (($ev_p["type_creneau"]==="revision")?"badge-orange":"badge-blue");
                ?>
                <tr>
                  <td><?= h(date("l", strtotime($ev_p["date_debut"]))) ?></td>
                  <td><?= h(date("H:i",strtotime($ev_p["date_debut"]))) ?>–<?= h(date("H:i",strtotime($ev_p["date_fin"]))) ?></td>
                  <td><?= h($ev_p["module_nom"] ?? "—") ?></td>
                  <td><?= h($ev_p["salle_nom"] ?? "—") ?> <small><?= h($ev_p["batiment"] ?? "") ?></small></td>
                  <td><?= h($ev_p["prof_nom"] ?? "—") ?></td>
                  <td><span class="badge <?= $type_col ?>"><?= $type_lbl ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
      </table>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}

function setTab(tab,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

const weeks=['<?= $semaine_lbl_p ?>','Semaine du 31 Mars au 4 Avril 2026','Semaine du 7 au 11 Avril 2026','Semaine du 14 au 18 Avril 2026'];
let wi=1;
function changeWeek(d){
  wi=Math.max(0,Math.min(weeks.length-1,wi+d));
  document.getElementById('weekLabel').textContent=weeks[wi];
  toast('📅 '+weeks[wi]);
}
function goToday(){wi=1;document.getElementById('weekLabel').textContent=weeks[1];toast('📅 Retour à la semaine actuelle')}

function showSlot(name,who,room,when){
  toast('📍 '+name+' · '+who+' · '+room+' · '+when);
}

function filterTable(val){
  const rows=document.querySelectorAll('#tableBody tr');
  rows.forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(val.toLowerCase())?'':' none'});
}

let toastTimer;
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3200)}
</script>
</body>
</html>
