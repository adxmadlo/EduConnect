<?php
/**
 * EduConnect — admin_notes.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check(['admin','chef_admin']);
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Administrateur';
$current_page = basename(__FILE__);

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Notes Admin
// ═══════════════════════════════════════════════════════════
$sel_classe_adm  = (int)($_GET["classe"]  ?? 0);
$sel_module_adm  = (int)($_GET["module"]  ?? 0);
$sel_semestre_adm= (int)($_GET["sem"]     ?? 1);
$annee_acad_adm  = "2025-2026";

$all_classes_adm = db_query($conn,"SELECT id,code,nom FROM classes ORDER BY annee_etude,code");
$all_modules_adm = db_query($conn,
    "SELECT m.id, m.code, m.nom, m.type_eval, m.semestre FROM modules m
     WHERE m.semestre = ? AND m.annee_etude IN (1,2) ORDER BY m.nom",
    "i", [$sel_semestre_adm]
);

$notes_admin_list = [];
if ($sel_classe_adm && $sel_module_adm) {
    $notes_admin_list = db_query($conn,
        "SELECT u.nom, u.prenom, u.numero_ucao,
                n.note_devoir, n.note_examen, n.note_finale, n.publie,
                CASE WHEN n.note_finale >= 16 THEN 'TB'
                     WHEN n.note_finale >= 14 THEN 'B'
                     WHEN n.note_finale >= 12 THEN 'AB'
                     WHEN n.note_finale >= 10 THEN 'P'
                     WHEN n.note_finale IS NOT NULL THEN 'F'
                     ELSE '—' END AS mention
         FROM etudiants e
         JOIN users u ON u.id = e.user_id
         LEFT JOIN notes n ON n.etudiant_id = e.id AND n.module_id = ? AND n.semestre = ? AND n.annee_acad = ?
         WHERE e.classe_id = ?
         ORDER BY u.nom, u.prenom",
        "iisi", [$sel_module_adm, $sel_semestre_adm, $annee_acad_adm, $sel_classe_adm]
    );
}

$stats_notes_adm = [
    "publiees"    => db_row($conn,"SELECT COUNT(*) AS nb FROM notes WHERE publie=1")["nb"] ?? 0,
    "non_publiees"=> db_row($conn,"SELECT COUNT(*) AS nb FROM notes WHERE publie=0")["nb"] ?? 0,
];

$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id=? AND lu=0","i",[(int)$user["id"]])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Gestion des Notes</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:var(--red);display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(196,30,58,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--blue-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--blue-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}.sidebar.collapsed .nav-badge{opacity:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--red),#8b0f22);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}.tab-nav::-webkit-scrollbar{display:none}.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}.tab-btn:hover{background:var(--surface);color:var(--txt)}.tab-btn.active{background:var(--blue);color:#fff;box-shadow:0 1px 4px rgba(50,110,169,.3)}.tab-btn i{font-size:13px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:200px}.tb-search:focus-within{border-color:var(--blue);background:#fff}.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}.tb-search input::placeholder{color:var(--txt3)}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--blue),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--blue)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}.page-actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent}.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}.btn-sm{padding:5px 10px;font-size:11px}.btn i{font-size:13px}
.table-container{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease}.table{width:100%;border-collapse:collapse}.table th{padding:12px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}.table td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}.table tr:hover{background:var(--surface2)}.table tr:last-child td{border:none}.table-actions{display:flex;gap:4px}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-green{background:var(--green-dim);color:var(--green)}.badge-orange{background:var(--orange-dim);color:var(--orange)}.badge-red{background:var(--red-dim);color:var(--red)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--blue)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start;gap:12px}.page-actions{width:100%}}
</style>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-mortarboard-fill"></i></div>
    <div><div class="logo-text">Edu<span>Connect</span></div><div style="font-size:9px;color:rgba(255,255,255,.4)">Admin</div></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Gestion</div>
    <button class="nav-item <?= $current_page === 'admin_etudiants.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_etudiants.php'"><i class="bi bi-people-fill"></i><span class="nav-item-txt">Étudiants</span></button>
    <button class="nav-item <?= $current_page === 'admin_paiements.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_paiements.php'"><i class="bi bi-credit-card"></i><span class="nav-item-txt">Paiements</span></button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'"><i class="bi bi-star"></i><span class="nav-item-txt">Mentorat</span></button>
    <button class="nav-item <?= $current_page === 'admin_salles.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_salles.php'"><i class="bi bi-building"></i><span class="nav-item-txt">Salles</span></button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'"><i class="bi bi-calendar"></i><span class="nav-item-txt">Emplois du temps</span></button>
    <button class="nav-item active" onclick="nav('grades',this)"><i class="bi bi-graph-up"></i><span class="nav-item-txt">Notes</span></button>
    <button class="nav-item <?= $current_page === 'admin_notifications.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_notifications.php'"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span></button>
    <button class="nav-item <?= $current_page === 'admin_utilisateurs.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_utilisateurs.php'"><i class="bi bi-shield"></i><span class="nav-item-txt">Utilisateurs</span></button>
    <button class="nav-item <?= $current_page === 'admin_rapports.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_rapports.php'"><i class="bi bi-bar-chart"></i><span class="nav-item-txt">Rapports</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Admin/admin_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role"><?= $u_role ?></div></div></div>
  <a href="/Views/Admin/admin_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-graph-up"></i>EduConnect Notes</div>
    <div class="tab-nav" id="tabNav">
      <button class="tab-btn active" onclick="setTab('list',this)"><i class="bi bi-list-ul"></i>Notes</button>
      <button class="tab-btn" onclick="setTab('bulletins',this)"><i class="bi bi-file-text"></i>Bulletins</button>
    </div>
    <div class="topbar-right">
      <div class="tb-search"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…" id="searchInput" onkeyup="filterTable()"></div>
      <button class="tb-icon" onclick="toast('🔔 Vous avez 2 notifications')" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <button class="tb-icon" onclick="exportData()" title="Exporter"><i class="bi bi-download"></i></button>
      <div class="tb-user" onclick="toast('Profil <?= $u_full ?>')"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-graph-up"></i><div><h1>Gestion des Notes</h1><p>Saisie et gestion des notes et bulletins</p></div></div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="exportData()"><i class="bi bi-download"></i>Exporter</button>
        <button class="btn btn-primary" onclick="toast('➕ Ajouter note')"><i class="bi bi-plus-circle"></i>Ajouter note</button>
      </div>
    </div>

    <div class="table-container">
      <table class="table">
        <thead>
          <tr><th>Étudiant</th><th>Cours</th><th>Note</th><th>Moyenne</th><th>Statut</th><th>Bulletin</th><th>Actions</th></tr>
        </thead>
        <tbody id="tableBody">
<?php if (!$sel_classe_adm||!$sel_module_adm): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--txt3);padding:20px">Sélectionnez une classe et un module</td></tr>
              <?php elseif (empty($notes_admin_list)): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--txt3);padding:20px">Aucune note trouvée</td></tr>
              <?php else: foreach ($notes_admin_list as $nl):
                $mc=match($nl["mention"]){"TB"=>"badge-green","B"=>"badge-green","AB"=>"badge-blue","P"=>"badge-orange","F"=>"badge-red",default=>""};
              ?>
              <tr>
                <td><?= h($nl["numero_ucao"]) ?></td>
                <td><?= h($nl["prenom"]." ".$nl["nom"]) ?></td>
                <td><?= $nl["note_devoir"]!==null?h($nl["note_devoir"]):"—" ?></td>
                <td><?= $nl["note_examen"]!==null?h($nl["note_examen"]):"—" ?></td>
                <td style="font-weight:700"><?= $nl["note_finale"]!==null?h($nl["note_finale"])."/20":"—" ?></td>
                <td><?php if($nl["mention"]!=="—"): ?><span class="badge <?= $mc ?>"><?= h($nl["mention"]) ?></span><?php if($nl["publie"]): ?><span style="color:var(--green);font-size:10px;margin-left:4px">✓</span><?php else: ?><span style="color:var(--orange);font-size:10px;margin-left:4px">⏳</span><?php endif; ?><?php else: ?><span style="color:var(--txt3);font-size:11px">—</span><?php endif; ?></td>
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
function nav(page,btn){document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));btn.classList.add('active');toast('📋 Accès à: '+page)}
function setTab(tab,btn){document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active')}
function editGrade(id){toast('✏️ Édition de la note #'+id)}
function viewBulletin(id){toast('📄 Affichage du bulletin #'+id)}
function filterTable(){const searchValue=document.getElementById('searchInput').value.toLowerCase();const rows=document.querySelectorAll('#tableBody tr');rows.forEach(row=>{const text=row.textContent.toLowerCase();row.style.display=text.includes(searchValue)?'':'none'})}
function exportData(){toast('📥 Export en cours…')}
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3000)}
</script>

<script src="../../Assets/js/educonnect-legacy.js"></script>
</body>
</html>
