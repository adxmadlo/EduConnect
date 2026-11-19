<?php
/**
 * EduConnect — parent_profil.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Profil Parent
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$profil_parent = db_row($conn,
    "SELECT u.numero_ucao, u.telephone, u.email, u.created_at, u.dernier_login
     FROM users u WHERE u.id=?",
    "i",[$uid]
);

// Enfant lié
$enfant_p = db_row($conn,
    "SELECT u.nom, u.prenom, u.numero_ucao, e.id AS etu_id,
            e.credits_obtenus, e.statut_academique,
            c.code AS classe_code, c.nom AS classe_nom
     FROM etudiants e JOIN users u ON u.id=e.user_id JOIN classes c ON c.id=e.classe_id
     WHERE e.parent_id=? LIMIT 1",
    "i",[$uid]
);
$enfant_nom   = $enfant_p ? h($enfant_p["prenom"]." ".$enfant_p["nom"]) : "Enfant non lié";
$enfant_init  = $enfant_p ? h(substr($enfant_p["prenom"],0,1).substr($enfant_p["nom"],0,1)) : "?";
$enfant_class = $enfant_p ? h($enfant_p["classe_code"]) : "";
$etu_id_p     = (int)($enfant_p["etu_id"] ?? 0);

// Stats enfant
$stats_enfant_p = db_row($conn,
    "SELECT ROUND(AVG(n.note_finale),2) AS moy,
            COALESCE(SUM(CASE WHEN p.statut='confirme' THEN p.montant ELSE 0 END),0) AS paye,
            COALESCE(f.montant_total,0) AS total
     FROM etudiants e
     LEFT JOIN notes n ON n.etudiant_id=e.id AND n.publie=1 AND n.annee_acad='2025-2026'
     LEFT JOIN frais_scolarite f ON f.etudiant_id=e.id AND f.annee_acad='2025-2026'
     LEFT JOIN paiements p ON p.frais_id=f.id AND p.statut='confirme'
     WHERE e.id=? GROUP BY e.id",
    "i",[$etu_id_p]
);

// Traitement POST
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["update_profil"])) {
    $tel = trim($_POST["telephone"] ?? "");
    db_exec($conn,"UPDATE users SET telephone=? WHERE id=?","si",[$tel,$uid]);
    $profil_parent["telephone"]=$tel; $profil_ok=true;
}



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
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--gold-dk:#b8892e;--gold-lt:#f0c45a;--gold-dim:rgba(212,168,67,.15);--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--gold),var(--gold-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(212,168,67,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--gold-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--gold-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}.sidebar.collapsed .nav-badge{opacity:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--gold-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--gold-dk);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer}.tb-icon:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--gold);background:var(--gold-dim)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
/* content max-width:900px centered — exact match to etudiant_profil.php */
.content{padding:18px 20px;flex:1;overflow-y:auto;max-width:900px;margin:0 auto;width:100%}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--gold-dk)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
/* profile-card — exact match to etudiant_profil.php */
.profile-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:24px;animation:fadeUp .3s ease;text-align:center;margin-bottom:20px}
.profile-avatar{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;color:#fff;margin:0 auto 16px;box-shadow:0 4px 12px rgba(212,168,67,.4)}
.profile-name{font-size:18px;font-weight:700;color:var(--txt);margin-bottom:4px}
.profile-role{font-size:12px;color:var(--txt3);margin-bottom:12px}
.profile-chips{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
.chip-gold{background:var(--gold-dim);color:var(--gold-dk)}
.chip-green{background:var(--green-dim);color:var(--green)}
.chip-blue{background:var(--blue-dim);color:var(--blue)}
.chip i{font-size:12px}
/* profile-stats — exact match */
.profile-stats{display:flex;justify-content:center;gap:24px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);flex-wrap:wrap}
.stat{text-align:center}.stat-val{font-size:18px;font-weight:700;color:var(--gold-dk)}.stat-lbl{font-size:10px;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px}
/* form-group / form-input — exact match */
.form-group{margin-bottom:16px;animation:fadeUp .3s ease}.form-label{font-size:12px;font-weight:600;color:var(--txt);margin-bottom:6px;display:block}.form-input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);background:var(--surface2);transition:all var(--tr)}.form-input:focus{outline:none;border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px var(--gold-dim)}.form-input[readonly]{cursor:default;color:var(--txt2)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
/* card — exact match */
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:18px;animation:fadeUp .3s ease;margin-bottom:20px}
.card-title{font-size:13px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px}
/* child card — special gold border */
.child-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--gold);box-shadow:var(--sh);padding:18px;animation:fadeUp .3s ease;margin-bottom:20px}
.child-card-title{font-size:13px;font-weight:600;color:var(--gold-dk);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:7px}
.child-card-title i{font-size:14px}
.child-profile{display:flex;align-items:center;gap:16px}
.child-ava{width:52px;height:52px;border-radius:12px;background:linear-gradient(135deg,var(--green),var(--green-lt));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#fff;flex-shrink:0;box-shadow:0 3px 8px rgba(39,174,96,.3)}
.child-stats{display:flex;gap:16px;margin-top:10px;flex-wrap:wrap}
.cs{text-align:center;padding:6px 12px;background:var(--surface2);border-radius:var(--r-sm);border:1px solid var(--border)}.cs-val{font-size:15px;font-weight:700;color:var(--gold-dk)}.cs-lbl{font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px}
/* btn — exact match with gold accent */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;width:100%;justify-content:center;font-family:'Ubuntu',sans-serif}.btn-primary{background:var(--gold);color:#fff;border-color:var(--gold)}.btn-primary:hover{background:var(--gold-dk);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{background:var(--surface);border-color:var(--gold);color:var(--gold-dk)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-green{background:var(--green-dim);color:var(--green)}.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-gold{background:var(--gold-dim);color:var(--gold-dk)}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);font-size:12px}.info-row:last-child{border:none}.info-label{color:var(--txt3);font-weight:500}.info-value{color:var(--txt);font-weight:600}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--gold)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.form-row{grid-template-columns:1fr}.profile-stats{flex-direction:column;gap:12px}.child-stats{flex-wrap:wrap}}
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
    <button class="nav-item <?= $current_page === 'parent_emploi_du_temps.php' ? 'active' : '' ?>" onclick="location.href='parent_emploi_du_temps.php'"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></button>
    <button class="nav-item <?= $current_page === 'parent_messages.php' ? 'active' : '' ?>" onclick="location.href='parent_messages.php'"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'parent_notifications.php' ? 'active' : '' ?>" onclick="location.href='parent_notifications.php'"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item active"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
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
    <div class="topbar-brand"><i class="bi bi-person"></i>Mon Profil</div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="location.href='parent_notifications.php'" title="Notifications"><i class="bi bi-bell-fill"></i></button>
      <div class="tb-user" onclick="toast('Profil <?= $u_full ?>')"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-person"></i><div><h1>Mon Profil</h1><p>Gérez vos informations personnelles et votre compte parent</p></div></div>
    </div>

    <!-- Profile card — exact structure as etudiant_profil.php -->
    <div class="profile-card">
      <div class="profile-avatar">OD</div>
      <div class="profile-name"><?= $u_full ?></div>
      <div class="profile-role"><?= $u_role ?></div>
      <div class="profile-chips">
        <span class="chip chip-gold"><i class="bi bi-person-hearts"></i>Parent vérifié</span>
        <span class="chip chip-green"><i class="bi bi-shield-check"></i>Compte actif</span>
        <span class="chip chip-blue"><i class="bi bi-link-45deg"></i>Marie Sow liée</span>
      </div>
      <div class="profile-stats">
        <div class="stat"><div class="stat-val">3</div><div class="stat-lbl">Paiements</div></div>
        <div class="stat"><div class="stat-val"><?= $stats_enfant_p["total"] > 0 ? round($stats_enfant_p["paye"]/$stats_enfant_p["total"]*100) : 0 ?>%</div><div class="stat-lbl">Scolarité</div></div>
        <div class="stat"><div class="stat-val">15.5</div><div class="stat-lbl">Moy. enfant</div></div>
        <div class="stat"><div class="stat-val">3/42</div><div class="stat-lbl">Rang enfant</div></div>
      </div>
    </div>

    <!-- Informations personnelles -->
    <div class="card">
      <div class="card-title">Informations Personnelles</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Prénom</label>
          <input type="text" class="form-input" value="Oumar" id="fname">
        </div>
        <div class="form-group">
          <label class="form-label">Nom</label>
          <input type="text" class="form-input" value="Diallo" id="lname">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Adresse email</label>
        <input type="email" class="form-input" value="oumar.diallo@gmail.com" id="email">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Téléphone principal</label>
          <input type="tel" class="form-input" value="+221 77 XXX XX XX" id="tel1">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone secondaire</label>
          <input type="tel" class="form-input" value="+221 76 XXX XX XX" id="tel2">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Adresse</label>
        <input type="text" class="form-input" value="Dakar, Sénégal" id="address">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Profession</label>
          <input type="text" class="form-input" value="Ingénieur" id="job">
        </div>
        <div class="form-group">
          <label class="form-label">Lien avec l'étudiant</label>
          <input type="text" class="form-input" value="Père" readonly>
        </div>
      </div>
    </div>

    <!-- Enfant lié — parent-specific -->
    <div class="child-card">
      <div class="child-card-title"><i class="bi bi-person-hearts"></i>Enfant lié au compte</div>
      <div class="child-profile">
        <div class="child-ava">MS</div>
        <div style="flex:1">
          <div style="font-size:15px;font-weight:700;color:var(--txt)">Marie Sow</div>
          <div style="font-size:12px;color:var(--txt3);margin-top:2px">Matricule #STU001 · L2 Informatique · Groupe A</div>
          <div style="display:flex;gap:6px;margin-top:7px;flex-wrap:wrap">
            <span class="badge badge-green"><i class="bi bi-check-circle-fill"></i>Inscription active</span>
            <span class="badge badge-green">Paiement à jour</span>
            <span class="badge badge-gold"><i class="bi bi-star-fill"></i>Mention Très Bien</span>
          </div>
        </div>
      </div>
      <div class="child-stats">
        <div class="cs"><div class="cs-val">15.5</div><div class="cs-lbl">Moyenne</div></div>
        <div class="cs"><div class="cs-val">3/42</div><div class="cs-lbl">Rang</div></div>
        <div class="cs"><div class="cs-val">30/30</div><div class="cs-lbl">Crédits</div></div>
        <div class="cs"><div class="cs-val">94%</div><div class="cs-lbl">Assiduité</div></div>
        <div class="cs"><div class="cs-val">75%</div><div class="cs-lbl">Scolarité</div></div>
      </div>
    </div>

    <!-- Informations du compte -->
    <div class="card">
      <div class="card-title">Informations du Compte</div>
      <div class="info-row"><span class="info-label">ID compte</span><span class="info-value" style="font-family:monospace;font-size:11px">#PAR-2025-0042</span></div>
      <div class="info-row"><span class="info-label">Date d'inscription</span><span class="info-value">15 Septembre 2025</span></div>
      <div class="info-row"><span class="info-label">Statut</span><span class="info-value"><span class="badge badge-green"><i class="bi bi-check-circle-fill"></i>Actif et vérifié</span></span></div>
      <div class="info-row"><span class="info-label">Dernière connexion</span><span class="info-value">Aujourd'hui à 09h32</span></div>
      <div class="info-row"><span class="info-label">Année académique</span><span class="info-value">2025/2026</span></div>
      <div class="info-row"><span class="info-label">Plan de paiement</span><span class="info-value">Trimestriel — 4 × 125 000 FCFA</span></div>
    </div>

    <!-- Save buttons — exact layout match -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px">
      <button class="btn btn-secondary" onclick="toast('❌ Modifications annulées')"><i class="bi bi-x-circle"></i>Annuler</button>
      <button class="btn btn-primary" onclick="saveProfile()"><i class="bi bi-check-circle"></i>Enregistrer</button>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}
function saveProfile(){
  const name=document.getElementById('fname').value+' '+document.getElementById('lname').value;
  toast('✅ Profil de '+name+' mis à jour avec succès');
}
let toastTimer;function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3200)}
</script>
</body>
</html>
