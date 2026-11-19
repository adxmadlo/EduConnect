<?php
/**
 * EduConnect — parent_dashboard.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Dashboard Parent
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

// Enfant lié
$enfant = db_row($conn,
    "SELECT u.nom, u.prenom, u.numero_ucao, e.id AS etu_id, e.credits_obtenus,
            e.credits_requis, e.statut_academique,
            c.code AS classe_code, c.nom AS classe_nom
     FROM etudiants e
     JOIN users u ON u.id = e.user_id
     JOIN classes c ON c.id = e.classe_id
     WHERE e.parent_id = ? LIMIT 1",
    "i", [$uid]
);
$enfant_nom   = $enfant ? h($enfant["prenom"]." ".$enfant["nom"]) : "Enfant non lié";
$enfant_init  = $enfant ? h(substr($enfant["prenom"],0,1).substr($enfant["nom"],0,1)) : "?";
$enfant_class = $enfant ? h($enfant["classe_code"]) : "";
$etu_id       = (int)($enfant["etu_id"] ?? 0);

// Paiements
$frais = db_row($conn,
    "SELECT f.montant_total,
            COALESCE(SUM(CASE WHEN p.statut='confirme' THEN p.montant ELSE 0 END),0) AS paye
     FROM frais_scolarite f
     LEFT JOIN paiements p ON p.frais_id = f.id
     WHERE f.etudiant_id = ? AND f.annee_acad='2025-2026' GROUP BY f.id",
    "i", [$etu_id]
);
$montant_total = (float)($frais["montant_total"] ?? 0);
$montant_paye  = (float)($frais["paye"] ?? 0);
$pct_paye      = $montant_total > 0 ? round(($montant_paye/$montant_total)*100) : 0;

// Moyenne générale enfant
$moy_enfant = db_row($conn,
    "SELECT ROUND(AVG(n.note_finale),2) AS moy FROM notes n
     WHERE n.etudiant_id=? AND n.publie=1 AND n.annee_acad='2025-2026'",
    "i", [$etu_id]
)["moy"] ?? "—";

// Dernières notes
$dernieres_notes_enfant = db_query($conn,
    "SELECT m.nom AS module_nom, n.note_finale, n.semestre,
            CASE WHEN n.note_finale>=16 THEN 'TB' WHEN n.note_finale>=14 THEN 'B'
                 WHEN n.note_finale>=12 THEN 'AB' WHEN n.note_finale>=10 THEN 'P'
                 WHEN n.note_finale IS NOT NULL THEN 'F' ELSE '—' END AS mention
     FROM notes n JOIN modules m ON m.id=n.module_id
     WHERE n.etudiant_id=? AND n.publie=1
     ORDER BY n.saisie_le DESC LIMIT 5",
    "i", [$etu_id]
);

$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id=? AND lu=0","i",[$uid])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Tableau de Bord Parent</title>
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

/* SIDEBAR */
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

/* MAIN */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}

/* TOPBAR */
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
.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:200px}
.tb-search:focus-within{border-color:var(--gold);background:#fff}
.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}
.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}
.tb-search input::placeholder{color:var(--txt3)}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--gold);background:var(--gold-dim)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* CONTENT */
.content{padding:18px 20px;flex:1}
.page-panel{display:none;animation:fadeUp .3s ease both}
#page-dashboard{display:block}

/* TILES */
.tiles-row{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.stat-tile{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--r-sm);color:#fff;cursor:pointer;transition:all var(--tr);flex:1;min-width:110px;max-width:160px}
.stat-tile:hover{transform:translateY(-2px);box-shadow:var(--sh-md);filter:brightness(1.05)}
.stat-tile-icon{width:36px;height:36px;border-radius:6px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-tile-val{font-size:22px;font-weight:700;line-height:1}
.stat-tile-lbl{font-size:11px;opacity:.9;margin-top:1px}
.tile-gold  {background:linear-gradient(135deg,var(--gold),var(--gold-lt))}
.tile-green {background:linear-gradient(135deg,var(--green),var(--green-lt))}
.tile-blue  {background:linear-gradient(135deg,var(--blue),var(--blue-lt))}
.tile-red   {background:linear-gradient(135deg,var(--red),#e85b72)}
.tile-teal  {background:linear-gradient(135deg,var(--teal),#06b6d4)}

/* MINI STATS */
.mini-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin-bottom:16px}
.mini-stat{background:var(--surface);border-radius:var(--r-sm);border:1px solid var(--border);padding:10px 12px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:all var(--tr)}
.mini-stat:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.mini-stat-val{font-size:18px;font-weight:700;color:var(--gold-dk)}
.mini-stat-lbl{font-size:10px;color:var(--txt3);margin-top:1px}
.mini-stat-ico{color:var(--txt3);font-size:20px;opacity:.6}

/* WIDGETS */
.widget-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px}
.widget{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;transition:all var(--tr)}
.widget:hover{box-shadow:var(--sh-md);transform:translateY(-2px)}
.widget-head{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.widget-title{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:6px}
.widget-title i{color:var(--gold-dk);font-size:14px}
.widget-body{padding:14px 16px}
.w-btn{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:12px;cursor:pointer;transition:all var(--tr)}
.w-btn:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}

/* QR RECEIPT BANNER */
.qr-banner{background:linear-gradient(135deg,var(--green),var(--green-lt));border-radius:var(--r);padding:14px 18px;color:#fff;display:flex;align-items:center;gap:14px;margin-bottom:16px;cursor:pointer;transition:all var(--tr);box-shadow:var(--sh)}
.qr-banner:hover{filter:brightness(1.04);transform:translateY(-2px);box-shadow:var(--sh-md)}
.qr-box{width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.qr-text h3{font-size:13px;font-weight:700;margin-bottom:2px}
.qr-text p{font-size:11px;opacity:.9}

/* PAYMENT PROGRESS */
.pay-progress{margin-bottom:12px}
.pay-progress-labels{display:flex;justify-content:space-between;font-size:11px;color:var(--txt2);margin-bottom:5px}
.pay-bar{height:8px;background:var(--border);border-radius:10px;overflow:hidden}
.pay-bar-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,var(--green),var(--green-lt));transition:width 1s ease}

/* TABLE */
.table{width:100%;border-collapse:collapse}
.table th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}
.table td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}
.table tr:hover{background:var(--surface2)}
.table tr:last-child td{border:none}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-red{background:var(--red-dim);color:var(--red)}
.badge-gold{background:var(--gold-dim);color:var(--gold-dk)}

/* ALERT BANNER */
.alert-bar{border-radius:var(--r-sm);padding:9px 14px;font-size:12px;font-weight:500;display:flex;align-items:center;gap:8px;margin-bottom:14px}
.alert-bar i{font-size:15px;flex-shrink:0}
.alert-success{background:var(--green-dim);color:var(--green);border:1px solid rgba(39,174,96,.2)}
.alert-info{background:var(--blue-dim);color:var(--blue);border:1px solid rgba(50,110,169,.2)}

/* TOAST */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--gold)}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1200px){.widget-grid{grid-template-columns:1fr}.mini-stats{grid-template-columns:repeat(auto-fit,minmax(100px,1fr))}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.tiles-row{flex-direction:column}.stat-tile{max-width:none}}
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

  <div class="sidebar-search">
    <div class="search-field">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Rechercher…">
    </div>
  </div>

  <div class="nav-body">
    <div class="nav-section">Mon Espace</div>
    <button class="nav-item active" onclick="nav('dashboard',this)">
      <i class="bi bi-speedometer2"></i>
      <a href="parent_dashboard.php" class="nav-item-txt">Tableau de Bord</a>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-person-fill"></i>
      <a href="parent_etudiants.php" class="nav-item-txt">Mon Enfant</a>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
    <i class="bi bi-credit-card"></i>
    <a href="parent_paiements.php" class="nav-item-txt">Paiements</a>
    <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
      <span class="nav-item-txt">Paiements</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-graph-up"></i>
      <a href="parent_notes.php" class="nav-item-txt">Notes & Bulletins</a>
    </button>

    <div class="nav-section">Suivi</div>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-calendar3"></i>
      <a href="parent_emploi.php" class="nav-item-txt">Emploi du Temps</a>
    </button>
    <button class="nav-item <?= $current_page === 'parent_messages.php' ? 'active' : '' ?>" onclick="location.href='/Views/Parent/parent_messages.php'">
      <i class="bi bi-chat-dots"></i>
      <a href="parent_messages.php" class="nav-item-txt">Messages</a>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-bell"></i>
      <a href="parent_notifications.php" class="nav-item-txt">Notifications</a>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>

    <div class="nav-section">Compte</div>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-person"></i>
      <a href="parent_profil.php" class="nav-item-txt">Mon Profil</a>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-gear"></i>
      <a href="parent_parametres.php" class="nav-item-txt">Paramètres</a>
    </button>
  </div>

  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Parent/parent_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role">Parent · Marie Sow (L2)</div>
      </div>
    </div>
  
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- MAIN -->
<main class="main" id="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-brand">
      <i class="bi bi-person-hearts"></i>
      EduConnect Parent
    </div>

    <div class="tab-nav">
      <button class="tab-btn active" onclick="setTab('overview',this)"><i class="bi bi-speedometer2"></i>Vue d'ensemble</button>
      <button class="tab-btn" onclick="setTab('child',this)"><i class="bi bi-person-fill"></i>Mon enfant</button>
      <button class="tab-btn" onclick="setTab('activity',this)"><i class="bi bi-activity"></i>Activité récente</button>
    </div>

    <div class="topbar-right">
      <div class="tb-search">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Rechercher…">
      </div>
      <button class="tb-icon" onclick="nav('notifications',document.querySelectorAll('.nav-item')[6])" title="Notifications">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <button class="tb-icon" onclick="toast('📥 Rapport exporté')" title="Exporter">
        <i class="bi bi-download"></i>
      </button>
      <div class="tb-user" onclick="nav('profil',document.querySelectorAll('.nav-item')[8])">
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

    <!-- ══ DASHBOARD ══ -->
    <div id="page-dashboard" class="page-panel">

      <!-- Period row -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:13px;font-weight:600;color:var(--txt)">
          <i class="bi bi-calendar3" style="color:var(--gold-dk)"></i>
          Suivi de <strong style="color:var(--gold-dk)">Marie Sow</strong> — <span id="periodLabel">Cette semaine</span>
        </div>
        <div style="display:flex;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;gap:2px">
          <button class="tab-btn active" style="font-size:11px;padding:4px 10px" onclick="setPeriod('week',this,'Cette semaine')">Semaine</button>
          <button class="tab-btn" style="font-size:11px;padding:4px 10px" onclick="setPeriod('month',this,'Ce mois')">Mois</button>
          <button class="tab-btn" style="font-size:11px;padding:4px 10px" onclick="setPeriod('year',this,'Cette année')">Année</button>
        </div>
      </div>

      <!-- QR receipt banner -->
      <div class="qr-banner" onclick="toast('📄 Reçu #REC-2026-0341 téléchargé !')">
        <div class="qr-box"><i class="bi bi-qr-code"></i></div>
        <div class="qr-text">
          <h3>Paiement Q1 2026 confirmé — Reçu disponible</h3>
          <p>125 000 FCFA · Wave · Reçu #REC-2026-0341 avec QR code de vérification — Cliquez pour télécharger</p>
        </div>
        <i class="bi bi-download" style="margin-left:auto;font-size:18px;opacity:.85"></i>
      </div>

      <!-- Stat tiles -->
      <div class="tiles-row">
        <div class="stat-tile tile-gold" onclick="nav('notes',document.querySelectorAll('.nav-item')[3])">
          <div class="stat-tile-icon"><i class="bi bi-graph-up"></i></div>
          <div><div class="stat-tile-val">15.5</div><div class="stat-tile-lbl">Moyenne /20</div></div>
        </div>
        <div class="stat-tile tile-green" onclick="nav('paiements',document.querySelectorAll('.nav-item')[2])">
          <div class="stat-tile-icon"><i class="bi bi-check-circle"></i></div>
          <div><div class="stat-tile-val"><?= $pct_paye ?>%</div><div class="stat-tile-lbl">Scolarité payée</div></div>
        </div>
        <div class="stat-tile tile-blue" onclick="nav('emploi',document.querySelectorAll('.nav-item')[4])">
          <div class="stat-tile-icon"><i class="bi bi-calendar-check"></i></div>
          <div><div class="stat-tile-val"><?= count($dernieres_notes_enfant) ?></div><div class="stat-tile-lbl">Notes reçues</div></div>
        </div>
        <div class="stat-tile tile-teal">
          <div class="stat-tile-icon"><i class="bi bi-star-fill"></i></div>
          <div><div class="stat-tile-val">2</div><div class="stat-tile-lbl">Mentors actifs</div></div>
        </div>
        <div class="stat-tile tile-red">
          <div class="stat-tile-icon"><i class="bi bi-exclamation-triangle"></i></div>
          <div><div class="stat-tile-val">0</div><div class="stat-tile-lbl">Alertes</div></div>
        </div>
      </div>

      <!-- Mini stats -->
      <div class="mini-stats">
        <div class="mini-stat" onclick="nav('notes',document.querySelectorAll('.nav-item')[3])">
          <div><div class="mini-stat-val"><?= $moy_enfant !== "—" ? $moy_enfant."/20" : "—" ?></div><div class="mini-stat-lbl">Moyenne</div></div>
          <i class="bi bi-star mini-stat-ico"></i>
        </div>
        <div class="mini-stat" onclick="nav('notes',document.querySelectorAll('.nav-item')[3])">
          <div><div class="mini-stat-val">3/42</div><div class="mini-stat-lbl">Rang classe</div></div>
          <i class="bi bi-award mini-stat-ico"></i>
        </div>
        <div class="mini-stat" onclick="nav('paiements',document.querySelectorAll('.nav-item')[2])">
          <div><div class="mini-stat-val">375k</div><div class="mini-stat-lbl">FCFA payés</div></div>
          <i class="bi bi-wallet2 mini-stat-ico"></i>
        </div>
        <div class="mini-stat" onclick="nav('paiements',document.querySelectorAll('.nav-item')[2])">
          <div><div class="mini-stat-val">125k</div><div class="mini-stat-lbl">Reste dû</div></div>
          <i class="bi bi-hourglass-split mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val"><?= (int)($enfant["credits_obtenus"] ?? 0) ?></div><div class="mini-stat-lbl">Crédits validés</div></div>
          <i class="bi bi-patch-check mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">7</div><div class="mini-stat-lbl">Matières</div></div>
          <i class="bi bi-book mini-stat-ico"></i>
        </div>
      </div>

      <!-- Widgets row 1 -->
      <div class="widget-grid">
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-graph-up"></i>Notes par matière</div>
            <div style="display:flex;gap:4px">
              <div class="w-btn" onclick="nav('notes',document.querySelectorAll('.nav-item')[3])" title="Voir toutes les notes"><i class="bi bi-arrow-right"></i></div>
            </div>
          </div>
          <div class="widget-body">
            <canvas id="gradesChart" height="200"></canvas>
          </div>
        </div>

        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-credit-card"></i>Suivi des paiements</div>
            <div class="w-btn" onclick="nav('paiements',document.querySelectorAll('.nav-item')[2])"><i class="bi bi-arrow-right"></i></div>
          </div>
          <div class="widget-body">
            <div class="pay-progress">
              <div class="pay-progress-labels">
                <span>Payé : 375 000 FCFA</span>
                <span style="font-weight:700;color:var(--green)">75%</span>
              </div>
              <div class="pay-bar"><div class="pay-bar-fill" style="width:75%"></div></div>
            </div>
            <table class="table">
              <thead><tr><th>Tranche</th><th>Date</th><th>Montant</th><th>Statut</th></tr></thead>
              <tbody>
                <?php
                // Quick paiement mini-list for dashboard (use frais_id)
                $mini_hist = db_query($conn,
                    "SELECT p.montant, p.moyen, p.statut, p.created_at
                     FROM frais_scolarite f
                     JOIN paiements p ON p.frais_id = f.id
                     WHERE f.etudiant_id = ? ORDER BY p.created_at DESC LIMIT 3",
                    "i", [$etu_id]
                );
                foreach ($mini_hist as $mh):
                  $ml = match($mh["moyen"]){"wave"=>"Wave","orange_money"=>"OM","carte"=>"Carte",default=>"—"};
                  $sc = $mh["statut"]==="confirme"?"badge-green":"badge-orange";
                ?>
                <tr>
                  <td><?= h(date("d/m/Y", strtotime($mh["created_at"]))) ?></td>
                  <td><?= number_format((float)$mh["montant"],0,","," ") ?> FCFA</td>
                  <td><?= $ml ?></td>
                  <td><span class="badge <?= $sc ?>"><?= $mh["statut"]==="confirme"?"✓":"⏳" ?></span></td>
                </tr>
                <?php endforeach; if (empty($mini_hist)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--txt3)">Aucune transaction</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Widgets row 2 -->
      <div class="widget-grid">
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-calendar3"></i>Cours cette semaine</div>
            <div class="w-btn" onclick="nav('emploi',document.querySelectorAll('.nav-item')[4])"><i class="bi bi-arrow-right"></i></div>
          </div>
          <div class="widget-body">
            <table class="table">
              <thead><tr><th>Jour</th><th>Heure</th><th>Matière</th><th>Salle</th></tr></thead>
              <tbody>
                <tr><td>Lundi</td><td>08h–10h</td><td><span class="badge badge-blue">Droit Civil</span></td><td>A1</td></tr>
                <tr><td>Mardi</td><td>10h–12h</td><td><span class="badge badge-green">Informatique</span></td><td>C3</td></tr>
                <?php
    $sched_mini = db_query($conn,
        "SELECT et.date_debut, m.nom AS module_nom, s.nom AS salle_nom
         FROM emplois_du_temps et
         LEFT JOIN modules m ON m.id=et.module_id
         LEFT JOIN salles s ON s.id=et.salle_id
         WHERE et.classe_id=? AND et.date_debut>=NOW()
         ORDER BY et.date_debut LIMIT 4",
        "i",[$enfant["classe_id"]??0] ?? [$etu_id]
    ) ?? [];
    foreach ($sched_mini as $sm_row): ?>
    <tr>
      <td><?= h(date("l",strtotime($sm_row["date_debut"]))) ?></td>
      <td><?= h(date("H:i",strtotime($sm_row["date_debut"]))) ?></td>
      <td><span class="badge badge-blue"><?= h($sm_row["module_nom"]??"—") ?></span></td>
      <td><?= h($sm_row["salle_nom"]??"—") ?></td>
    </tr>
    <?php endforeach; if(empty($sched_mini)): ?>
    <tr><td colspan="4" style="text-align:center;color:var(--txt3)">Aucun cours à venir</td></tr>
    <?php endif; ?>
                <tr><td>Jeudi</td><td>14h–16h</td><td><span class="badge badge-gold">Révision Groupe</span></td><td>B2</td></tr>
                <tr><td>Vendredi</td><td>08h–10h</td><td><span class="badge badge-blue">Anglais</span></td><td>C1</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-bell"></i>Activité récente</div>
            <div class="w-btn" onclick="nav('notifications',document.querySelectorAll('.nav-item')[6])"><i class="bi bi-arrow-right"></i></div>
          </div>
          <div class="widget-body" style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;gap:10px;align-items:flex-start;padding-bottom:10px;border-bottom:1px solid var(--border)">
              <div style="width:32px;height:32px;border-radius:7px;background:var(--green-dim);display:flex;align-items:center;justify-content:center;color:var(--green);flex-shrink:0"><i class="bi bi-check-circle-fill"></i></div>
              <div style="flex:1"><div style="font-size:12px;font-weight:600">Paiement confirmé</div><div style="font-size:11px;color:var(--txt3)">125 000 FCFA · Wave · Il y a 5h</div></div>
              <span class="badge badge-green" style="flex-shrink:0">Nouveau</span>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;padding-bottom:10px;border-bottom:1px solid var(--border)">
              <div style="width:32px;height:32px;border-radius:7px;background:var(--blue-dim);display:flex;align-items:center;justify-content:center;color:var(--blue);flex-shrink:0"><i class="bi bi-file-earmark-text"></i></div>
              <div style="flex:1"><div style="font-size:12px;font-weight:600">Bulletin disponible</div><div style="font-size:11px;color:var(--txt3)">Semestre 1 2026 · Il y a 3h</div></div>
              <span class="badge badge-blue" style="flex-shrink:0">Nouveau</span>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;padding-bottom:10px;border-bottom:1px solid var(--border)">
              <div style="width:32px;height:32px;border-radius:7px;background:var(--gold-dim);display:flex;align-items:center;justify-content:center;color:var(--gold-dk);flex-shrink:0"><i class="bi bi-graph-up-arrow"></i></div>
              <div style="flex:1"><div style="font-size:12px;font-weight:600">Note publiée — Droit Civil</div><div style="font-size:11px;color:var(--txt3)">17.5/20 · Mention TB · Il y a 1h</div></div>
              <span class="badge badge-gold" style="flex-shrink:0">Nouveau</span>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start">
              <div style="width:32px;height:32px;border-radius:7px;background:var(--purple-dim);display:flex;align-items:center;justify-content:center;color:var(--purple);flex-shrink:0"><i class="bi bi-camera-video"></i></div>
              <div style="flex:1"><div style="font-size:12px;font-weight:600">Session mentorat confirmée</div><div style="font-size:11px;color:var(--txt3)">Fatou Diop · Jeudi 14h · Hier</div></div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /dashboard -->

  </div><!-- /content -->
</main>

<div class="toast" id="toast"></div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

function nav(page,btn){
  document.querySelectorAll('.page-panel').forEach(p=>p.style.display='none');
  const panel=document.getElementById('page-'+page);
  if(panel){panel.style.display='block';}
  document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  toast('📋 Navigation : '+page);
}

function setTab(tab,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

function setPeriod(p,btn,label){
  document.querySelectorAll('[onclick^="setPeriod"]').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('periodLabel').textContent=label;
}

let toastTimer;
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer=setTimeout(()=>el.classList.remove('show'),3000);
}

document.addEventListener('DOMContentLoaded',()=>{
  const ctx=document.getElementById('gradesChart').getContext('2d');
  new Chart(ctx,{
    type:'bar',
    data:{
      labels:['Droit Civil','Informatique','Maths','Économie','Anglais','Philo','Physique'],
      datasets:[{
        label:'Note /20',
        data:[17.5,16,14.5,13,18,12,15],
        backgroundColor:['#326ea9','#27ae60','#D4A843','#e67e22','#0891b2','#7C3AED','#C41E3A'],
        borderRadius:4,
        borderSkipped:false
      }]
    },
    options:{
      responsive:true,
      plugins:{legend:{display:false}},
      scales:{
        y:{beginAtZero:true,max:20,ticks:{font:{family:'Ubuntu',size:11}}},
        x:{ticks:{font:{family:'Ubuntu',size:10}}}
      }
    }
  });
});
</script>
</body>
</html>
