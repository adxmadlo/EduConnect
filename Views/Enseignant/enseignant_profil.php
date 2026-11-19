<?php
/**
 * EduConnect — enseignant_profil.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Profil Enseignant
// ═══════════════════════════════════════════════════════════
$profil_ens = db_row($conn,
    "SELECT u.telephone, u.numero_ucao, u.created_at, u.email,
            e.grade, e.departement, e.bureau
     FROM users u
     LEFT JOIN enseignants e ON e.user_id = u.id
     WHERE u.id = ?",
    "i", [(int)$user["id"]]
);

// Modules enseignés
$mes_modules_prof = db_query($conn,
    "SELECT m.code, m.nom, m.semestre, m.annee_etude,
            COUNT(DISTINCT mc.classe_id) AS nb_classes
     FROM modules m
     JOIN module_enseignants me ON me.module_id = m.id
     LEFT JOIN module_classes mc ON mc.module_id = m.id
     WHERE me.enseignant_id = ?
     GROUP BY m.id
     ORDER BY m.annee_etude, m.semestre, m.nom",
    "i", [(int)$user["id"]]
);

// Stats globales
$stats_ens = db_row($conn,
    "SELECT COUNT(DISTINCT n.etudiant_id) AS nb_etudiants,
            COUNT(DISTINCT n.module_id)   AS nb_modules,
            ROUND(AVG(n.note_finale),1)   AS moy_globale
     FROM notes n
     JOIN module_enseignants me ON me.module_id = n.module_id
     WHERE me.enseignant_id = ? AND n.publie = 1",
    "i", [(int)$user["id"]]
);

// Traitement POST — mise à jour profil
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profil"])) {
    $tel    = trim($_POST["telephone"] ?? "");
    $grade  = trim($_POST["grade"]     ?? "");
    $bureau = trim($_POST["bureau"]    ?? "");
    db_exec($conn,"UPDATE users SET telephone=? WHERE id=?","si",[$tel,(int)$user["id"]]);
    db_exec($conn,"UPDATE enseignants SET grade=?, bureau=? WHERE user_id=?","ssi",[$grade,$bureau,(int)$user["id"]]);
    $profil_ens["telephone"]=$tel; $profil_ens["grade"]=$grade; $profil_ens["bureau"]=$bureau;
    $profil_ok = true;
}

$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0","i",[(int)$user["id"]])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Mon Profil</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--gold-dim:rgba(212,168,67,.12);--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dk:#065f7e;--teal-lt:#06b6d4;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--teal),var(--teal-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(8,145,178,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--teal-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--teal-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--teal-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--teal-dk);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer}.tb-icon:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal-dk)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--teal);background:var(--teal-dim)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--teal),var(--teal-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
/* content max-width:900px centered — exact match to mentor_profil.php */
.content{padding:18px 20px;flex:1;overflow-y:auto;max-width:900px;margin:0 auto;width:100%}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--teal-dk)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
/* profile-card — exact match to mentor_profil.php, teal gradient */
.profile-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:24px;animation:fadeUp .3s ease;text-align:center;margin-bottom:20px}
.profile-avatar{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--teal-lt));display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;color:#fff;margin:0 auto 16px;box-shadow:0 4px 12px rgba(8,145,178,.35)}
.profile-name{font-size:18px;font-weight:700;color:var(--txt);margin-bottom:4px}
.profile-role{font-size:12px;color:var(--txt3);margin-bottom:10px}
.profile-chips{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
.chip-teal{background:var(--teal-dim);color:var(--teal-dk)}.chip-green{background:var(--green-dim);color:var(--green)}.chip-blue{background:var(--blue-dim);color:var(--blue)}
.chip i{font-size:12px}
/* profile-stats — exact match */
.profile-stats{display:flex;justify-content:center;gap:24px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);flex-wrap:wrap}
.stat{text-align:center}.stat-val{font-size:18px;font-weight:700;color:var(--teal-dk)}.stat-lbl{font-size:10px;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px}
/* form — exact match to mentor_profil.php */
.form-group{margin-bottom:16px;animation:fadeUp .3s ease}.form-label{font-size:12px;font-weight:600;color:var(--txt);margin-bottom:6px;display:block}
.form-input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);background:var(--surface2);transition:all var(--tr)}.form-input:focus{outline:none;border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px var(--teal-dim)}.form-input[readonly]{color:var(--txt2)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
/* card — exact match */
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:18px;animation:fadeUp .3s ease;margin-bottom:20px}
.card-title{font-size:13px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);font-size:12px}.info-row:last-child{border:none}.info-label{color:var(--txt3);font-weight:500}.info-value{color:var(--txt);font-weight:600}
.course-tag{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;background:var(--teal-dim);color:var(--teal-dk);margin:2px}
/* btn — exact match with teal */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;width:100%;justify-content:center;font-family:'Ubuntu',sans-serif}.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}.btn-primary:hover{background:var(--teal-dk);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{border-color:var(--teal);color:var(--teal-dk)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-teal{background:var(--teal-dim);color:var(--teal-dk)}.badge-green{background:var(--green-dim);color:var(--green)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.form-row{grid-template-columns:1fr}.profile-stats{flex-direction:column;gap:12px}}
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
    <button class="nav-item <?= $current_page === 'enseignant_ressources.php' ? 'active' : '' ?>" onclick="location.href='enseignant_ressources.php'"><i class="bi bi-folder2-open"></i><span class="nav-item-txt">Ressources</span></button>
    <div class="nav-section">Communication</div>
    <button class="nav-item"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Mon Planning</span></button>
    <button class="nav-item"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'enseignant_notifications.php' ? 'active' : '' ?>" onclick="location.href='enseignant_notifications.php'"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item active"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
    <button class="nav-item <?= $current_page === 'enseignant_parametres.php' ? 'active' : '' ?>" onclick="location.href='enseignant_parametres.php'"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Enseignant/enseignant_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role"><?= $u_role ?></div></div></div>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-person"></i>Mon Profil</div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="location.href='enseignant_notifications.php'" title="Notifications"><i class="bi bi-bell-fill"></i></button>
      <div class="tb-user"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-person"></i><div><h1>Mon Profil</h1><p>Gérez vos informations professionnelles et académiques</p></div></div>
    </div>

    <!-- Profile card — exact structure as mentor_profil.php, teal gradient -->
    <div class="profile-card">
      <div class="profile-avatar">PM</div>
      <div class="profile-name"><?= $u_full ?></div>
      <div class="profile-role"><?= $u_role ?></div>
      <div class="profile-chips">
        <span class="chip chip-teal"><i class="bi bi-person-video3"></i>Enseignant titulaire</span>
        <span class="chip chip-green"><i class="bi bi-shield-check"></i>Compte vérifié</span>
        <span class="chip chip-blue"><i class="bi bi-book"></i>5 cours actifs</span>
      </div>
      <div class="profile-stats">
        <div class="stat"><div class="stat-val">198</div><div class="stat-lbl">Étudiants</div></div>
        <div class="stat"><div class="stat-val">5</div><div class="stat-lbl">Cours actifs</div></div>
        <div class="stat"><div class="stat-val">64h</div><div class="stat-lbl">Ce semestre</div></div>
        <div class="stat"><div class="stat-val">15.2</div><div class="stat-lbl">Moy. promo</div></div>
      </div>
    </div>

    <!-- Informations personnelles -->
    <div class="card">
      <div class="card-title">Informations Personnelles</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Prénom</label>
          <input type="text" class="form-input" value="Jean-Pierre" id="fname">
        </div>
        <div class="form-group">
          <label class="form-label">Nom</label>
          <input type="text" class="form-input" value="Martin" id="lname">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email institutionnel</label>
        <input type="email" class="form-input" value="jp.martin@ucao.edu.sn" id="email">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="tel" class="form-input" value="+221 77 XXX XX XX">
        </div>
        <div class="form-group">
          <label class="form-label">Bureau</label>
          <input type="text" class="form-input" value="Bâtiment A — Bureau 214">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Spécialité principale</label>
        <input type="text" class="form-input" value="Droit Civil — Droit des Obligations">
      </div>
      <div class="form-group">
        <label class="form-label">Biographie professionnelle</label>
        <textarea class="form-input" rows="3" style="resize:vertical">Maître de Conférences en Droit Civil à l'UCAO Saint-Michel depuis 2018. Spécialisé en droit des obligations et jurisprudence africaine. Auteur de 3 publications dans des revues juridiques internationales.</textarea>
      </div>
    </div>

    <!-- Informations académiques -->
    <div class="card">
      <div class="card-title">Informations Académiques</div>
      <div class="info-row"><span class="info-label">Matricule enseignant</span><span class="info-value" style="font-family:monospace;font-size:11px">#ENS-2018-0042</span></div>
      <div class="info-row"><span class="info-label">Grade</span><span class="info-value">Maître de Conférences</span></div>
      <div class="info-row"><span class="info-label">Département</span><span class="info-value">Faculté de Droit</span></div>
      <div class="info-row"><span class="info-label">Statut</span><span class="info-value"><span class="badge badge-green"><i class="bi bi-check-circle-fill"></i>Titulaire actif</span></span></div>
      <div class="info-row"><span class="info-label">Année académique</span><span class="info-value">2025/2026 — Semestre 2</span></div>
      <div class="info-row"><span class="info-label">Heures hebdomadaires</span><span class="info-value">8h de cours / semaine</span></div>
    </div>

    <!-- Cours enseignés -->
    <div class="card">
      <div class="card-title">Cours Enseignés ce Semestre</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">
        <span class="course-tag"><i class="bi bi-book"></i>DRT201 — Droit Civil L2</span>
        <span class="course-tag"><i class="bi bi-book"></i>DRT301 — Droit Civil Approfondi L3</span>
        <span class="course-tag"><i class="bi bi-book"></i>DRT101 — Introduction au Droit L1</span>
        <span class="course-tag"><i class="bi bi-book"></i>DRT203 — Contrats &amp; Obligations L2</span>
        <span class="course-tag"><i class="bi bi-book"></i>DRT205 — Droit des Affaires L2</span>
      </div>
      <div class="info-row"><span class="info-label">Étudiants encadrés</span><span class="info-value">198 étudiants</span></div>
      <div class="info-row"><span class="info-label">Moyenne globale promotions</span><span class="info-value" style="color:var(--teal-dk);font-weight:700">15.2 / 20</span></div>
      <div class="info-row"><span class="info-label">Ressources partagées</span><span class="info-value">12 documents · 248 téléchargements</span></div>
    </div>

    <!-- Save buttons — exact match -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px">
      <button class="btn btn-secondary" onclick="toast('❌ Modifications annulées')"><i class="bi bi-x-circle"></i>Annuler</button>
      <button class="btn btn-primary" onclick="saveProfile()"><i class="bi bi-check-circle"></i>Enregistrer</button>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}
function saveProfile(){toast('✅ Profil mis à jour avec succès')}
let toastTimer;function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3200)}
</script>
</body>
</html>
