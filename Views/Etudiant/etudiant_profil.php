<?php
/**
 * EduConnect — etudiant_profil.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Profil Étudiant
// ═══════════════════════════════════════════════════════════
$profil = db_row($conn,
    "SELECT u.numero_ucao, u.telephone, u.email, u.created_at, u.dernier_login,
            e.date_naissance, e.adresse, e.credits_obtenus, e.credits_requis, e.statut_academique,
            c.code AS classe_code, c.nom AS classe_nom, c.annee_etude
     FROM users u
     JOIN etudiants e ON e.user_id = u.id
     JOIN classes c ON c.id = e.classe_id
     WHERE u.id = ?",
    "i", [(int)$user["id"]]
);
$etu_id_row = db_row($conn,"SELECT id FROM etudiants WHERE user_id=?","i",[(int)$user["id"]]);
$etu_id = (int)($etu_id_row["id"] ?? 0);

// Stats académiques
$stats = db_row($conn,
    "SELECT ROUND(AVG(n.note_finale),2) AS moyenne,
            COUNT(DISTINCT n.module_id) AS nb_modules,
            SUM(CASE WHEN n.note_finale >= 10 THEN 1 ELSE 0 END) AS valides
     FROM notes n WHERE n.etudiant_id = ? AND n.publie = 1 AND n.annee_acad='2025-2026'",
    "i", [$etu_id]
);
$moyenne_gen  = $stats["moyenne"] ?? "—";
$modules_valides = (int)($stats["valides"] ?? 0);
$credits_pct  = $profil["credits_requis"] > 0
    ? round(($profil["credits_obtenus"] / $profil["credits_requis"]) * 100)
    : 0;

// Rang dans la classe
$rang_row = db_row($conn,
    "SELECT COUNT(*)+1 AS rang FROM (
        SELECT n2.etudiant_id, AVG(n2.note_finale) AS moy
        FROM notes n2
        JOIN etudiants e2 ON e2.id=n2.etudiant_id
        WHERE e2.classe_id=(SELECT classe_id FROM etudiants WHERE user_id=?) AND n2.publie=1
        GROUP BY n2.etudiant_id
        HAVING moy > COALESCE((SELECT AVG(n3.note_finale) FROM notes n3 WHERE n3.etudiant_id=? AND n3.publie=1),0)
    ) AS t",
    "ii", [(int)$user["id"], $etu_id]
);
$rang = (int)($rang_row["rang"] ?? 0);

// Notifications non lues
$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0","i",[(int)$user["id"]])["nb"] ?? 0);

// Traitement POST — mise à jour profil
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profil"])) {
    $tel   = trim($_POST["telephone"] ?? "");
    $adr   = trim($_POST["adresse"]   ?? "");
    $ddn   = trim($_POST["date_naissance"] ?? "");
    db_exec($conn,"UPDATE users SET telephone=? WHERE id=?","si",[$tel,(int)$user["id"]]);
    db_exec($conn,"UPDATE etudiants SET adresse=?, date_naissance=? WHERE user_id=?",
            "ssi",[$adr, $ddn ?: null, (int)$user["id"]]);
    $profil["telephone"] = $tel; $profil["adresse"] = $adr;
    $profil_ok = true;
}

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Profil Étudiant</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,#27ae60,#2ecc71);display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(39,174,96,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:#2ecc71;border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:#2ecc71}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,#27ae60,var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--blue),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1;overflow-y:auto;max-width:900px;margin:0 auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--blue)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.profile-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:24px;animation:fadeUp .3s ease;text-align:center;margin-bottom:20px}
.profile-avatar{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--blue-lt));display:flex;align-items:center;justify-content:center;font-size:40px;color:#fff;margin:0 auto 16px;box-shadow:0 4px 12px rgba(50,110,169,.3)}
.profile-name{font-size:18px;font-weight:700;color:var(--txt);margin-bottom:4px}
.profile-role{font-size:12px;color:var(--txt3);margin-bottom:12px}
.profile-stats{display:flex;justify-content:center;gap:24px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)}
.stat{text-align:center}.stat-val{font-size:18px;font-weight:700;color:var(--blue)}.stat-lbl{font-size:10px;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px}
.form-group{margin-bottom:16px;animation:fadeUp .3s ease}.form-label{font-size:12px;font-weight:600;color:var(--txt);margin-bottom:6px;display:block}
.form-input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);background:var(--surface2);transition:all var(--tr)}.form-input:focus{outline:none;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px var(--blue-dim)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:18px;animation:fadeUp .3s ease;margin-bottom:20px}
.card-title{font-size:13px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;width:100%;justify-content:center}.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--blue)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.form-row{grid-template-columns:1fr}.profile-stats{flex-direction:column;gap:12px}}
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
    <button class="nav-item <?= $current_page === 'etudiant_dashboard.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_dashboard.php'"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></button>
    <button class="nav-item <?= $current_page === 'etudiant_cours.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_cours.php'"><i class="bi bi-book"></i><span class="nav-item-txt">Mes Cours</span></button>
    <button class="nav-item <?= $current_page === 'etudiants_notes.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiants_notes.php'"><i class="bi bi-graph-up"></i><span class="nav-item-txt">Mes Notes</span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item active" onclick="nav('profile',this)"><i class="bi bi-person"></i><span class="nav-item-txt">Profil</span></button>
    <button class="nav-item <?= $current_page === 'etudiant_parametres.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_parametres.php'"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Etudiant/etudiant_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role"><?= $u_role ?></div></div></div>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-person"></i>Profil</div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="toast('🔔 Vous avez 2 notifications')" title="Notifications"><i class="bi bi-bell-fill"></i></button>
      <div class="tb-user" onclick="toast('Profil <?= $u_full ?>')"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-person"></i><div><h1>Mon Profil</h1><p>Gérez vos informations personnelles</p></div></div>
    </div>

    <div class="profile-card">
      <div class="profile-avatar">👨‍🎓</div>
      <div class="profile-name"><?= $u_full ?></div>
      <div class="profile-role"><?= h($profil["classe_code"] ?? "") ?> — <?= $u_role ?></div>
      <div class="profile-stats">
        <div class="stat"><div class="stat-val">15.2</div><div class="stat-lbl">Moyenne</div></div>
        <div class="stat"><div class="stat-val"><?= (int)($profil["credits_obtenus"] ?? 0) ?>/<?= (int)($profil["credits_requis"] ?? 30) ?></div><div class="stat-lbl">Crédits</div></div>
        <div class="stat"><div class="stat-val"><?= $moy_enfant !== null ? $moy_enfant."/20" : "—" ?></div><div class="stat-lbl">Moyenne</div></div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Informations Personnelles</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Prénom</label>
          <input type="text" class="form-input" value="Amadou" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Nom</label>
          <input type="text" class="form-input" value="Sow" readonly>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" value="<?= h($profil['email'] ?? $user['email']) ?>" readonly>
      </div>
      <div class="form-group">
        <label class="form-label">Numéro Matricule</label>
        <input type="text" class="form-input" value="STU-2024-0847" readonly>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Niveau</label>
          <input type="text" class="form-input" value="L2 (Licence 2)" readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Groupe</label>
          <input type="text" class="form-input" value="Groupe A" readonly>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Informations Académiques</div>
      <div style="font-size:12px;color:var(--txt3);line-height:1.8">
        <p>✓ Année académique: 2024-2025</p>
        <p>✓ Semestre actuel: 2</p>
        <p>✓ Statut: Actif</p>
        <p>✓ Mentor assigné: Dr. Dupont</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <button class="btn btn-secondary" onclick="toast('📝 Édition du profil')">Modifier</button>
      <button class="btn btn-primary" onclick="toast('✅ Profil mis à jour')">Enregistrer</button>
    </div>
  </div>
</main>

<div class="toast" id="toast"></div>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');document.getElementById('main').classList.toggle('expanded')}
function nav(page,btn){document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));btn.classList.add('active');toast('📋 Accès à: '+page)}
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3000)}
</script>

</body>
</html>
