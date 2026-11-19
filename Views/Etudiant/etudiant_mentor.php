<?php
/**
 * EduConnect — etudiant_mentor.php — Version dynamique
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

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Mentorat Étudiant
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
$etu_row = db_row($conn,"SELECT id,classe_id FROM etudiants WHERE user_id=?","i",[$uid]);
$etu_id  = (int)($etu_row["id"] ?? 0);

// Mentors disponibles avec modules et stats
$mentors_disponibles = db_query($conn,
    "SELECT mt.id AS mentor_id, u.prenom, u.nom, mt.note_moyenne, mt.nb_sessions,
            mt.taux_satisfaction, mt.bio, mt.tarif,
            GROUP_CONCAT(DISTINCT m.nom ORDER BY m.nom SEPARATOR ', ') AS modules_txt
     FROM mentors mt
     JOIN users u ON u.id = mt.user_id
     LEFT JOIN mentor_modules mm ON mm.mentor_id = mt.id
     LEFT JOIN modules m ON m.id = mm.module_id
     WHERE mt.actif = 1
     GROUP BY mt.id
     ORDER BY mt.note_moyenne DESC, mt.nb_sessions DESC"
);

// Mes sessions (historique + à venir)
$mes_sessions = db_query($conn,
    "SELECT sm.id, sm.date_session, sm.heure_debut, sm.heure_fin,
            sm.type_session, sm.statut, sm.lien_visio, sm.note_etudiant,
            m.nom AS module_nom,
            CONCAT(u.prenom,' ',u.nom) AS mentor_nom
     FROM sessions_mentorat sm
     JOIN modules m ON m.id = sm.module_id
     JOIN mentors mt ON mt.id = sm.mentor_id
     JOIN users u ON u.id = mt.user_id
     WHERE sm.etudiant_id = ?
     ORDER BY sm.date_session DESC, sm.heure_debut DESC",
    "i",[$etu_id]
);

// Modules disponibles pour demande
$modules_disponibles = db_query($conn,
    "SELECT DISTINCT m.id, m.nom, m.code FROM modules m
     JOIN module_classes mc ON mc.module_id = m.id
     WHERE mc.classe_id = ?
     ORDER BY m.nom",
    "i",[$etu_row["classe_id"] ?? 0]
);

// Traitement POST — demande de session
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["demande_session"])) {
    $mentor_id_post = (int)$_POST["mentor_id"];
    $module_id_post = (int)$_POST["module_id"];
    $date_post      = trim($_POST["date_session"] ?? "");
    $heure_post     = trim($_POST["heure_debut"] ?? "08:00");
    $type_post      = in_array($_POST["type_session"]??"",[" en_ligne","presentiel"])
                      ? $_POST["type_session"] : "en_ligne";
    if ($mentor_id_post && $module_id_post && $date_post) {
        $h_fin = date("H:i",strtotime($heure_post."+90 minutes"));
        db_exec($conn,
            "INSERT INTO sessions_mentorat
             (mentor_id,etudiant_id,module_id,type_session,date_session,heure_debut,heure_fin,statut)
             VALUES (?,?,?,?,?,?,?,'demandee')",
            "iiissss",
            [$mentor_id_post,$etu_id,$module_id_post,$type_post,$date_post,$heure_post,$h_fin]
        );
        header("Location: etudiant_mentor.php?ok=1"); exit;
    }
}
// POST — noter une session terminée
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["noter_session"])) {
    $sm_id  = (int)$_POST["session_id"];
    $note_e = max(1,min(5,(int)$_POST["note_etudiant"]));
    db_exec($conn,"UPDATE sessions_mentorat SET note_etudiant=? WHERE id=? AND etudiant_id=?",
            "iii",[$note_e,$sm_id,$etu_id]);
    header("Location: etudiant_mentor.php?ok=2"); exit;
}


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
<title>EduConnect — Mentorat</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,#27ae60,#2ecc71);display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(39,174,96,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:#2ecc71;border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:#2ecc71}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,#27ae60,var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}.tab-nav::-webkit-scrollbar{display:none}.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}.tab-btn:hover{background:var(--surface);color:var(--txt)}.tab-btn.active{background:var(--blue);color:#fff;box-shadow:0 1px 4px rgba(50,110,169,.3)}.tab-btn i{font-size:13px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--blue),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1;overflow-y:auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--blue)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:20px}
.mentor-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;transition:all var(--tr);cursor:pointer}.mentor-card:hover{box-shadow:var(--sh-md);transform:translateY(-4px);border-color:var(--blue)}.mentor-header{padding:14px;background:var(--surface2);display:flex;align-items:center;gap:12px}.mentor-ava{width:48px;height:48px;border-radius:8px;background:linear-gradient(135deg,var(--blue),var(--blue-lt));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#fff;flex-shrink:0}.mentor-info h3{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:2px}.mentor-info p{font-size:11px;color:var(--txt3)}.mentor-body{padding:14px}.mentor-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border)}.stat-item{text-align:center}.stat-value{font-size:14px;font-weight:700;color:var(--blue)}.stat-label{font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px}.mentor-rating{display:flex;align-items:center;gap:4px;margin-bottom:12px;font-size:12px}.star{color:var(--gold)}.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;width:100%;justify-content:center}.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-green{background:var(--green-dim);color:var(--green)}.badge-orange{background:var(--orange-dim);color:var(--orange)}.badge-red{background:var(--red-dim);color:var(--red)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--blue)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start;gap:12px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-book-fill"></i></div>
    <div><div class="logo-text">Edu<span>Connect</span></div><div style="font-size:9px;color:rgba(255,255,255,.4)">Étudiant</div></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Académique</div>
    <button class="nav-item <?= $current_page === 'etudiant_cours.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_cours.php'"><i class="bi bi-book"></i><span class="nav-item-txt">Mes Cours</span></button>
    <button class="nav-item <?= $current_page === 'etudiants_notes.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiants_notes.php'"><i class="bi bi-graph-up"></i><span class="nav-item-txt">Mes Notes</span></button>
    <button class="nav-item active" onclick="nav('mentoring',this)"><i class="bi bi-star"></i><span class="nav-item-txt">Mentorat</span></button>
    <button class="nav-item <?= $current_page === 'etudiant_emploi_du_temps.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_emploi_du_temps.php'"><i class="bi bi-calendar"></i><span class="nav-item-txt">Emploi du temps</span></button>
    <button class="nav-item <?= $current_page === 'etudiant_paiements.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_paiements.php'"><i class="bi bi-credit-card"></i><span class="nav-item-txt">Paiements</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Etudiant/etudiant_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role"><?= $u_role ?></div></div></div>
  <a href="/Views/Etudiant/etudiant_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-star"></i>Mentorat</div>
    <div class="tab-nav" id="tabNav">
      <button class="tab-btn active" onclick="setTab('available',this)"><i class="bi bi-search"></i>Mentors disponibles</button>
      <button class="tab-btn" onclick="setTab('my-mentors',this)"><i class="bi bi-person-check"></i>Mes mentors</button>
    </div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="toast('🔔 Vous avez 1 notification')" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <div class="tb-user" onclick="toast('Profil <?= $u_full ?>')"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-star"></i><div><h1>Mentorat</h1><p>Trouvez et réservez un mentor pour votre accompagnement</p></div></div>
    </div>

    <div class="grid" id="mentorGrid">
      <div class="mentor-card">
        <div class="mentor-header">
          <div class="mentor-ava">DR</div>
          <div class="mentor-info">
            <h3>Dr. Dupont</h3>
            <p>Programmation</p>
          </div>
        </div>
        <div class="mentor-body">
          <div class="mentor-stats">
            <div class="stat-item"><div class="stat-value">8</div><div class="stat-label">Étudiants</div></div>
            <div class="stat-item"><div class="stat-value">4.8</div><div class="stat-label">Note</div></div>
          </div>
          <div class="mentor-rating"><span class="star">★★★★★</span><span style="color:var(--txt3)">4.8/5</span></div>
          <button class="btn btn-primary" onclick="toast('✅ Demande de mentorat envoyée')">Demander</button>
        </div>
      </div>

      <div class="mentor-card">
        <div class="mentor-header">
          <div class="mentor-ava" style="background:linear-gradient(135deg,#e67e22,#f39c12)">FB</div>
          <div class="mentor-info">
            <?php foreach ($mentors_disponibles as $m):
                  $stars = str_repeat("⭐", min(5, round((float)($m["note_moyenne"] ?? 0))));
                ?>
                <h3><?= h($m["prenom"]." ".$m["nom"]) ?></h3>
                <p style="font-size:12px;color:var(--txt3);margin:4px 0"><?= h($m["modules_txt"] ?? "—") ?></p>
                <div style="font-size:11px;color:var(--green)"><?= $stars ?> (<?= number_format((float)($m["note_moyenne"]??0),1) ?>)</div>
                <div style="font-size:11px;color:var(--txt3);margin-top:4px"><?= (int)($m["nb_sessions"]??0) ?> sessions — <?= h($m["tarif"]) ?></div>
                <button onclick="openDemande(<?= (int)$m['mentor_id'] ?>, '<?= h(addslashes($m['prenom']." ".$m['nom'])) ?>')"
                  style="margin-top:8px;padding:5px 12px;background:var(--blue);color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer">
                  Demander
                </button>
                <?php endforeach; ?></div></div>
          </div>
          <div class="mentor-rating"><span class="star">★★★★★</span><span style="color:var(--txt3)">4.9/5</span></div>
          <button class="btn btn-primary" onclick="toast('✅ Demande de mentorat envoyée')">Demander</button>
        </div>
      </div>

      <div class="mentor-card">
        <div class="mentor-header">
          <div class="mentor-ava" style="background:linear-gradient(135deg,#27ae60,#2ecc71)">MN</div>
          <div class="mentor-info">
            <h3>Moussa Ndiaye</h3>
            <p>Algorithmes</p>
          </div>
        </div>
        <div class="mentor-body">
          <div class="mentor-stats">
            <div class="stat-item"><div class="stat-value">5</div><div class="stat-label">Étudiants</div></div>
            <div class="stat-item"><div class="stat-value">4.7</div><div class="stat-label">Note</div></div>
          </div>
          <div class="mentor-rating"><span class="star">★★★★☆</span><span style="color:var(--txt3)">4.7/5</span></div>
          <button class="btn btn-primary" onclick="toast('✅ Demande de mentorat envoyée')">Demander</button>
        </div>
      </div>

      <div class="mentor-card">
        <div class="mentor-header">
          <div class="mentor-ava" style="background:linear-gradient(135deg,#0891b2,#06b6d4)">AK</div>
          <div class="mentor-info">
            <h3>Aissatou Kane</h3>
            <p>Développement Web</p>
          </div>
        </div>
        <div class="mentor-body">
          <div class="mentor-stats">
            <div class="stat-item"><div class="stat-value">7</div><div class="stat-label">Étudiants</div></div>
            <div class="stat-item"><div class="stat-value">4.9</div><div class="stat-label">Note</div></div>
          </div>
          <div class="mentor-rating"><span class="star">★★★★★</span><span style="color:var(--txt3)">4.9/5</span></div>
          <button class="btn btn-primary" onclick="toast('✅ Demande de mentorat envoyée')">Demander</button>
        </div>
      </div>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}
function nav(page,btn){document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));btn.classList.add('active');toast('📋 Accès à: '+page)}
function setTab(tab,btn){document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active')}
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3000)}
</script>

</body>
</html>
