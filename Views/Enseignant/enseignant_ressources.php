<?php
/**
 * EduConnect — enseignant_ressources.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Ressources Enseignant
// NOTE: Les fichiers sont stockés sur le serveur dans /uploads/ressources/
// La table ressources n'est pas dans le schéma v1 — on utilise une table étendue
// Pour l'instant on liste les modules de l'enseignant pour l'upload
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

// Modules de cet enseignant pour le filtre
$mes_modules_res = db_query($conn,
    "SELECT m.id, m.code, m.nom FROM modules m
     JOIN module_enseignants me ON me.module_id = m.id
     WHERE me.enseignant_id = ? ORDER BY m.nom",
    "i",[$uid]
);

// Stats nb modules / classes
$nb_modules_res = count($mes_modules_res);
$nb_classes_res = (int)(db_row($conn,
    "SELECT COUNT(DISTINCT mc.classe_id) AS nb FROM module_enseignants me
     JOIN module_classes mc ON mc.module_id = me.module_id
     WHERE me.enseignant_id = ?",
    "i",[$uid])["nb"] ?? 0);



$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Ressources Pédagogiques</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--gold-dim:rgba(212,168,67,.15);--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dk:#065f7e;--teal-lt:#06b6d4;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--teal),var(--teal-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(8,145,178,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--teal-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--teal-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--teal-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--teal-dk);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}.tab-nav::-webkit-scrollbar{display:none}.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}.tab-btn:hover{background:var(--surface);color:var(--txt)}.tab-btn.active{background:var(--teal);color:#fff;box-shadow:0 1px 4px rgba(8,145,178,.35)}.tab-btn i{font-size:13px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:200px}.tb-search:focus-within{border-color:var(--teal);background:#fff}.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}.tb-icon:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal-dk)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--teal);background:var(--teal-dim)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--teal),var(--teal-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1;overflow-y:auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease;flex-wrap:wrap;gap:10px}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--teal-dk)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}.page-actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}.btn-primary:hover{background:var(--teal-dk);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{border-color:var(--teal);color:var(--teal-dk)}.btn-danger{background:var(--red-dim);color:var(--red);border-color:var(--red-dim)}.btn-danger:hover{background:var(--red);color:#fff}.btn-sm{padding:5px 10px;font-size:11px}.btn i{font-size:13px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:20px}
.stat-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:14px 16px;display:flex;align-items:center;justify-content:space-between;animation:fadeUp .3s ease}.stat-label{font-size:11px;color:var(--txt3);font-weight:500;margin-bottom:2px}.stat-value{font-size:20px;font-weight:700;color:var(--txt)}.stat-icon{width:38px;height:38px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-icon.teal{background:var(--teal-dim);color:var(--teal-dk)}.stat-icon.green{background:var(--green-dim);color:var(--green)}.stat-icon.blue{background:var(--blue-dim);color:var(--blue)}.stat-icon.orange{background:var(--orange-dim);color:var(--orange)}
/* filter bar */
.filter-bar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.filter-select{padding:7px 10px;border-radius:var(--r-sm);background:var(--surface);border:1px solid var(--border);font-size:12px;color:var(--txt);font-family:'Ubuntu',sans-serif;cursor:pointer}.filter-select:focus{outline:none;border-color:var(--teal)}
/* upload zone */
.upload-zone{border:2px dashed var(--teal);border-radius:var(--r);padding:32px;text-align:center;background:var(--teal-dim);margin-bottom:20px;cursor:pointer;transition:all var(--tr);animation:fadeUp .3s ease}
.upload-zone:hover{background:rgba(8,145,178,.12);border-color:var(--teal-dk)}
.upload-icon{font-size:40px;color:var(--teal);margin-bottom:12px}
.upload-title{font-size:14px;font-weight:600;color:var(--teal-dk);margin-bottom:4px}
.upload-sub{font-size:12px;color:var(--txt3)}
/* resource cards — exact .resource-card from mentor_ressources.php, teal accent */
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:20px}
.resource-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;transition:all var(--tr)}.resource-card:hover{box-shadow:var(--sh-md);transform:translateY(-4px);border-color:var(--teal)}
.resource-header{padding:14px;background:var(--surface2);display:flex;align-items:center;justify-content:space-between}
.resource-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px}
.resource-icon.pdf{background:var(--red-dim);color:var(--red)}
.resource-icon.video{background:var(--green-dim);color:var(--green)}
.resource-icon.code{background:var(--orange-dim);color:var(--orange)}
.resource-icon.slides{background:var(--purple-dim);color:var(--purple)}
.resource-icon.doc{background:var(--blue-dim);color:var(--blue)}
.resource-icon.zip{background:var(--teal-dim);color:var(--teal-dk)}
.resource-body{padding:14px}
.resource-title{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:4px}
.resource-desc{font-size:11px;color:var(--txt3);margin-bottom:10px;line-height:1.5}
.resource-meta{display:flex;justify-content:space-between;align-items:center;font-size:10px;color:var(--txt3);padding-top:10px;border-top:1px solid var(--border)}
.resource-actions{display:flex;gap:5px;margin-top:10px}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-green{background:var(--green-dim);color:var(--green)}.badge-orange{background:var(--orange-dim);color:var(--orange)}.badge-red{background:var(--red-dim);color:var(--red)}.badge-teal{background:var(--teal-dim);color:var(--teal-dk)}.badge-purple{background:var(--purple-dim);color:var(--purple)}
/* modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}.modal.show{display:flex;animation:fadeUp .2s ease}
.modal-content{background:var(--surface);border-radius:var(--r);padding:22px;width:480px;max-width:92%;box-shadow:var(--sh-lg)}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)}.modal-title{font-size:15px;font-weight:700;color:var(--txt)}.modal-close{width:26px;height:26px;border-radius:4px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);cursor:pointer;font-size:14px;transition:all var(--tr)}.modal-close:hover{background:var(--red-dim);color:var(--red)}
.form-group{margin-bottom:12px}.form-label{font-size:12px;font-weight:600;color:var(--txt);margin-bottom:5px;display:block}
.form-input{width:100%;padding:8px 10px;border-radius:var(--r-sm);border:1px solid var(--border);font-family:'Ubuntu',sans-serif;font-size:12.5px;color:var(--txt);background:var(--surface2);transition:all var(--tr)}.form-input:focus{outline:none;border-color:var(--teal);background:#fff}
.form-select-full{width:100%;padding:8px 10px;border-radius:var(--r-sm);border:1px solid var(--border);font-family:'Ubuntu',sans-serif;font-size:12.5px;color:var(--txt);background:var(--surface2);cursor:pointer}.form-select-full:focus{outline:none;border-color:var(--teal)}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start}.grid{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-person-video3"></i></div>
    <div><div class="logo-text">Edu<span>Connect</span></div><div style="font-size:9px;color:rgba(255,255,255,.4)">Enseignant</div></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Enseignement</div>
    <button class="nav-item <?= $current_page === 'enseignant_cours.php' ? 'active' : '' ?>" onclick="location.href='enseignant_cours.php'"><i class="bi bi-book"></i><span class="nav-item-txt">Mes Cours</span></button>
    <button class="nav-item <?= $current_page === 'enseignant_notes.php' ? 'active' : '' ?>" onclick="location.href='enseignant_notes.php'"><i class="bi bi-pencil-square"></i><span class="nav-item-txt">Saisie des Notes</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item active"><i class="bi bi-folder2-open"></i><span class="nav-item-txt">Ressources</span></button>
    <button class="nav-item <?= $current_page === 'enseignant_cours.php' ? 'active' : '' ?>" onclick="location.href='/output/Views/Enseignant/enseignant_cours.php'"><i class="bi bi-book"></i><span class="nav-item-txt">Mes Cours</span></button>
    <button class="nav-item"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Mon Planning</span></button>
    <button class="nav-item"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
    <button class="nav-item"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Enseignant/enseignant_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role"><?= $u_role ?></div></div></div>
  <a href="/Views/Enseignant/enseignant_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-folder2-open"></i>Ressources Pédagogiques</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="setTab('all',this)"><i class="bi bi-list-ul"></i>Toutes</button>
      <button class="tab-btn" onclick="setTab('pdf',this)"><i class="bi bi-file-pdf"></i>Documents</button>
      <button class="tab-btn" onclick="setTab('video',this)"><i class="bi bi-play-circle"></i>Vidéos</button>
      <button class="tab-btn" onclick="setTab('exercices',this)"><i class="bi bi-pencil-square"></i>Exercices</button>
      <button class="tab-btn" onclick="setTab('slides',this)"><i class="bi bi-easel"></i>Diapositives</button>
    </div>
    <div class="topbar-right">
      <div class="tb-search"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher une ressource…" onkeyup="filterResources(this.value)"></div>
      <button class="tb-icon" onclick="openUploadModal()" title="Ajouter"><i class="bi bi-plus-circle"></i></button>
      <button class="tb-icon" title="Notifications"><i class="bi bi-bell-fill"></i></button>
      <div class="tb-user"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-folder2-open"></i><div><h1>Ressources Pédagogiques</h1><p>Partagez des documents, exercices et vidéos avec vos étudiants</p></div></div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="toast('📥 Export de toutes les ressources')"><i class="bi bi-download"></i>Exporter</button>
        <button class="btn btn-primary" onclick="openUploadModal()"><i class="bi bi-plus-circle"></i>Ajouter une ressource</button>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card"><div><div class="stat-label">Ressources total</div><div class="stat-value">12</div></div><div class="stat-icon teal"><i class="bi bi-folder2"></i></div></div>
      <div class="stat-card"><div><div class="stat-label">Téléchargements</div><div class="stat-value">248</div></div><div class="stat-icon green"><i class="bi bi-download"></i></div></div>
      <div class="stat-card"><div><div class="stat-label">Étudiants actifs</div><div class="stat-value">142</div></div><div class="stat-icon blue"><i class="bi bi-people"></i></div></div>
      <div class="stat-card"><div><div class="stat-label">Cours couverts</div><div class="stat-value">5</div></div><div class="stat-icon orange"><i class="bi bi-book"></i></div></div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <select class="filter-select" onchange="toast('Filtrage par cours : '+this.value)">
        <option value="">Tous les cours</option>
        <option value="DRT201">DRT201 — Droit Civil L2</option>
        <option value="DRT301">DRT301 — Droit Civil Approfondi L3</option>
        <option value="DRT101">DRT101 — Introduction au Droit L1</option>
        <option value="DRT203">DRT203 — Contrats &amp; Obligations</option>
        <option value="DRT205">DRT205 — Droit des Affaires</option>
      </select>
      <select class="filter-select" onchange="toast('Tri : '+this.value)">
        <option>Récents en premier</option>
        <option>Plus vus</option>
        <option>A–Z</option>
      </select>
    </div>

    <!-- Upload drop zone -->
    <div class="upload-zone" onclick="openUploadModal()">
      <div class="upload-icon"><i class="bi bi-cloud-upload"></i></div>
      <div class="upload-title">Glissez-déposez vos fichiers ici</div>
      <div class="upload-sub">ou cliquez pour parcourir · PDF, DOCX, PPTX, MP4, ZIP acceptés · max 50 MB</div>
    </div>

    <!-- Resource cards grid — exact .resource-card structure from mentor_ressources.php, teal accent -->
    <div class="grid" id="resourceGrid">

      <div class="resource-card">
        <div class="resource-header">
          <div class="resource-icon pdf"><i class="bi bi-file-pdf"></i></div>
          <span class="badge badge-red">PDF</span>
        </div>
        <div class="resource-body">
          <div class="resource-title">Cours complet — Droit Civil Semestre 2</div>
          <div class="resource-desc">Le polycopié officiel du cours DRT201, incluant jurisprudence, cas pratiques et méthodologie de dissertation juridique.</div>
          <div class="resource-actions">
            <button class="btn btn-primary btn-sm" onclick="toast('📄 Cours complet téléchargé')"><i class="bi bi-download"></i>Télécharger</button>
            <button class="btn btn-secondary btn-sm" onclick="toast('✏️ Édition du document')"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-danger btn-sm" onclick="if(confirm('Supprimer ?'))toast('🗑️ Ressource supprimée')"><i class="bi bi-trash3"></i></button>
          </div>
          <div class="resource-meta"><span><i class="bi bi-calendar3"></i> 28 Mar 2026</span><span><i class="bi bi-download"></i> 89 téléchargements</span><span><span class="badge badge-teal">DRT201</span></span></div>
        </div>
      </div>

      <div class="resource-card">
        <div class="resource-header">
          <div class="resource-icon slides"><i class="bi bi-easel"></i></div>
          <span class="badge" style="background:var(--purple-dim);color:var(--purple)">SLIDES</span>
        </div>
        <div class="resource-body">
          <div class="resource-title">Diapositives — Séance 1 à 8</div>
          <div class="resource-desc">Présentation PowerPoint des 8 premières séances du cours. Schémas, définitions clés et exemples jurisprudentiels.</div>
          <div class="resource-actions">
            <button class="btn btn-primary btn-sm" onclick="toast('📊 Diapositives téléchargées')"><i class="bi bi-download"></i>Télécharger</button>
            <button class="btn btn-secondary btn-sm" onclick="toast('✏️ Édition')"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-danger btn-sm" onclick="if(confirm('Supprimer ?'))toast('🗑️ Supprimé')"><i class="bi bi-trash3"></i></button>
          </div>
          <div class="resource-meta"><span><i class="bi bi-calendar3"></i> 20 Mar 2026</span><span><i class="bi bi-download"></i> 72 téléchargements</span><span><span class="badge badge-teal">DRT201</span></span></div>
        </div>
      </div>

      <div class="resource-card">
        <div class="resource-header">
          <div class="resource-icon video"><i class="bi bi-play-circle-fill"></i></div>
          <span class="badge badge-green">VIDÉO</span>
        </div>
        <div class="resource-body">
          <div class="resource-title">Tutoriel — Méthodologie du cas pratique</div>
          <div class="resource-desc">Vidéo de 35 minutes expliquant pas à pas comment traiter un cas pratique en Droit Civil selon la méthode attendue à l'examen.</div>
          <div class="resource-actions">
            <button class="btn btn-primary btn-sm" onclick="toast('▶️ Lecture vidéo')"><i class="bi bi-play-circle"></i>Visionner</button>
            <button class="btn btn-secondary btn-sm" onclick="toast('🔗 Lien copié')"><i class="bi bi-link-45deg"></i></button>
            <button class="btn btn-danger btn-sm" onclick="if(confirm('Supprimer ?'))toast('🗑️ Supprimé')"><i class="bi bi-trash3"></i></button>
          </div>
          <div class="resource-meta"><span><i class="bi bi-calendar3"></i> 15 Mar 2026</span><span><i class="bi bi-eye"></i> 54 vues</span><span><span class="badge badge-teal">DRT201</span></span></div>
        </div>
      </div>

      <div class="resource-card">
        <div class="resource-header">
          <div class="resource-icon code"><i class="bi bi-pencil-square"></i></div>
          <span class="badge badge-orange">EXERCICES</span>
        </div>
        <div class="resource-body">
          <div class="resource-title">Série d'exercices — Droit des Contrats</div>
          <div class="resource-desc">15 cas pratiques progressifs avec corrections détaillées. Idéal pour la préparation au contrôle continu et à l'examen final.</div>
          <div class="resource-actions">
            <button class="btn btn-primary btn-sm" onclick="toast('📝 Exercices téléchargés')"><i class="bi bi-download"></i>Télécharger</button>
            <button class="btn btn-secondary btn-sm" onclick="toast('✏️ Édition')"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-danger btn-sm" onclick="if(confirm('Supprimer ?'))toast('🗑️ Supprimé')"><i class="bi bi-trash3"></i></button>
          </div>
          <div class="resource-meta"><span><i class="bi bi-calendar3"></i> 10 Mar 2026</span><span><i class="bi bi-download"></i> 63 téléchargements</span><span><span class="badge badge-blue">DRT203</span></span></div>
        </div>
      </div>

      <div class="resource-card">
        <div class="resource-header">
          <div class="resource-icon pdf"><i class="bi bi-file-pdf"></i></div>
          <span class="badge badge-red">PDF</span>
        </div>
        <div class="resource-body">
          <div class="resource-title">Annales — 5 dernières années</div>
          <div class="resource-desc">Compilation des sujets d'examens des 5 dernières années avec corrigés types. Format identique à l'examen final du semestre.</div>
          <div class="resource-actions">
            <button class="btn btn-primary btn-sm" onclick="toast('📄 Annales téléchargées')"><i class="bi bi-download"></i>Télécharger</button>
            <button class="btn btn-secondary btn-sm" onclick="toast('✏️ Édition')"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-danger btn-sm" onclick="if(confirm('Supprimer ?'))toast('🗑️ Supprimé')"><i class="bi bi-trash3"></i></button>
          </div>
          <div class="resource-meta"><span><i class="bi bi-calendar3"></i> 5 Mar 2026</span><span><i class="bi bi-download"></i> 98 téléchargements</span><span><span class="badge badge-teal">DRT201</span></span></div>
        </div>
      </div>

      <div class="resource-card">
        <div class="resource-header">
          <div class="resource-icon zip"><i class="bi bi-file-earmark-zip"></i></div>
          <span class="badge badge-teal">PACK</span>
        </div>
        <div class="resource-body">
          <div class="resource-title">Pack complet — Introduction au Droit L1</div>
          <div class="resource-desc">Tous les supports du cours DRT101 : polycopié, diapositives et exercices réunis en un seul archive ZIP téléchargeable.</div>
          <div class="resource-actions">
            <button class="btn btn-primary btn-sm" onclick="toast('📦 Pack complet téléchargé')"><i class="bi bi-download"></i>Télécharger</button>
            <button class="btn btn-secondary btn-sm" onclick="toast('✏️ Édition')"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-danger btn-sm" onclick="if(confirm('Supprimer ?'))toast('🗑️ Supprimé')"><i class="bi bi-trash3"></i></button>
          </div>
          <div class="resource-meta"><span><i class="bi bi-calendar3"></i> 1 Mar 2026</span><span><i class="bi bi-download"></i> 45 téléchargements</span><span><span class="badge badge-blue">DRT101</span></span></div>
        </div>
      </div>

    </div>
  </div>
</main>

<!-- Upload modal -->
<div class="modal" id="uploadModal">
  <div class="modal-content">
    <div class="modal-header">
      <div class="modal-title" style="display:flex;align-items:center;gap:7px"><i class="bi bi-cloud-upload" style="color:var(--teal-dk)"></i>Ajouter une ressource</div>
      <button class="modal-close" onclick="closeModal()"><i class="bi bi-x"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Titre de la ressource *</label>
      <input type="text" class="form-input" placeholder="Ex : Cours complet — Chapitre 3" id="resTitle">
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <input type="text" class="form-input" placeholder="Brève description du contenu…" id="resDesc">
    </div>
    <div class="form-group">
      <label class="form-label">Cours associé *</label>
      <select class="form-select-full">
        <option value="">Sélectionner un cours…</option>
        <option>DRT201 — Droit Civil L2</option>
        <option>DRT301 — Droit Civil Approfondi L3</option>
        <option>DRT101 — Introduction au Droit L1</option>
        <option>DRT203 — Contrats &amp; Obligations</option>
        <option>DRT205 — Droit des Affaires</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Type de ressource *</label>
      <select class="form-select-full">
        <option>PDF — Document</option>
        <option>SLIDES — Diapositives</option>
        <option>VIDÉO — Tutoriel</option>
        <option>EXERCICES — Cas pratiques</option>
        <option>PACK — Archive ZIP</option>
        <option>LIEN — URL externe</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Fichier</label>
      <div style="border:2px dashed var(--teal);border-radius:var(--r-sm);padding:20px;text-align:center;cursor:pointer;background:var(--teal-dim)" onclick="toast('📂 Sélecteur de fichier ouvert')">
        <i class="bi bi-cloud-upload" style="font-size:24px;color:var(--teal);display:block;margin-bottom:6px"></i>
        <div style="font-size:12px;color:var(--teal-dk);font-weight:600">Cliquez pour choisir un fichier</div>
        <div style="font-size:11px;color:var(--txt3);margin-top:2px">PDF, DOCX, PPTX, MP4, ZIP · max 50 MB</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">Annuler</button>
      <button class="btn btn-primary" onclick="addResource()"><i class="bi bi-cloud-upload"></i>Publier la ressource</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}
function setTab(tab,btn){document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active')}
function filterResources(val){document.querySelectorAll('.resource-card').forEach(c=>{c.style.display=c.textContent.toLowerCase().includes(val.toLowerCase())?'':'none'})}
function openUploadModal(){document.getElementById('uploadModal').classList.add('show')}
function closeModal(){document.getElementById('uploadModal').classList.remove('show')}
function addResource(){
  const title=document.getElementById('resTitle').value.trim();
  if(!title){toast('⚠️ Veuillez saisir un titre');return;}
  closeModal();
  toast('✅ Ressource "'+title+'" publiée — Les étudiants peuvent la télécharger');
}
let toastTimer;function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3200)}
</script>
</body>
</html>
