<?php
/**
 * EduConnect — admin_dashboard.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Admin Dashboard
// ═══════════════════════════════════════════════════════════

// Statistiques globales
$stats_admin = db_row($conn,
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role='etudiant' AND statut='actif') AS nb_etudiants,
        (SELECT COUNT(*) FROM users WHERE role='enseignant' AND statut='actif') AS nb_enseignants,
        (SELECT COUNT(*) FROM users WHERE role='mentor' AND statut='actif') AS nb_mentors,
        (SELECT COUNT(*) FROM users WHERE role='parent' AND statut='actif') AS nb_parents,
        (SELECT COUNT(*) FROM paiements WHERE statut='confirme') AS nb_paiements,
        (SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut='confirme') AS total_recouvrement,
        (SELECT COUNT(*) FROM paiements WHERE statut='en_attente') AS paiements_en_attente,
        (SELECT COUNT(*) FROM sessions_mentorat WHERE statut='confirmee' AND date_session >= CURDATE()) AS sessions_a_venir,
        (SELECT COUNT(*) FROM reservations WHERE statut='en_attente') AS reservations_attente,
        (SELECT COUNT(*) FROM notifications WHERE lu=0) AS notifs_globales"
);

// Derniers paiements
$derniers_paiements = db_query($conn,
    "SELECT p.recu_numero, p.montant, p.moyen, p.statut, p.created_at,
            CONCAT(u.prenom,' ',u.nom) AS payeur_nom, u.email
     FROM paiements p JOIN users u ON u.id = p.payeur_id
     ORDER BY p.created_at DESC LIMIT 8"
);

// Derniers utilisateurs inscrits
$derniers_users = db_query($conn,
    "SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, email, role, statut, created_at
     FROM users ORDER BY created_at DESC LIMIT 8"
);

// Notes non publiées en attente
$notes_attente = db_row($conn,
    "SELECT COUNT(*) AS nb FROM notes WHERE publie = 0"
)["nb"] ?? 0;

// Taux de recouvrement
$total_scolarite = db_row($conn, "SELECT COALESCE(SUM(montant_total),0) AS total FROM frais_scolarite")["total"] ?? 1;
$taux_recouvrement = $total_scolarite > 0
    ? round(($stats_admin["total_recouvrement"] / $total_scolarite) * 100, 1)
    : 0;

$nb_notifs = (int)($stats_admin["notifs_globales"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Super Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
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
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

/* ═══ SIDEBAR ═══ */
.sidebar{
  width:var(--sidebar-w);background:var(--blue-dk);
  position:fixed;height:100vh;z-index:400;
  display:flex;flex-direction:column;
  transition:width var(--tr);overflow:hidden;
}
.sidebar.collapsed{width:54px}

/* Logo */
.logo-zone{
  height:var(--topbar-h);padding:0 14px;
  display:flex;align-items:center;gap:10px;
  border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0;
}
.logo-mark{
  width:32px;height:32px;border-radius:7px;
  background:var(--red);display:flex;align-items:center;
  justify-content:center;font-size:17px;color:#fff;flex-shrink:0;
  box-shadow:0 2px 8px rgba(196,30,58,.5);
}
.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}
.logo-text span{color:#a8c8e8}
.sidebar-toggle{
  margin-left:auto;width:24px;height:24px;border-radius:5px;
  display:flex;align-items:center;justify-content:center;
  color:rgba(255,255,255,.5);font-size:14px;
  transition:all var(--tr);flex-shrink:0;cursor:pointer;
}
.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}

/* Search */
.sidebar-search{
  padding:10px 10px 6px;flex-shrink:0;
  overflow:hidden;transition:all var(--tr);
}
.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}
.search-field{
  display:flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:var(--r-sm);
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);
}
.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}
.search-field input{
  background:none;border:none;outline:none;color:#fff;
  font-family:'Ubuntu',sans-serif;font-size:12px;width:100%;
}
.search-field input::placeholder{color:rgba(255,255,255,.4)}

/* Nav */
.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}
.nav-body::-webkit-scrollbar{display:none}
.nav-section{
  font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;
  color:rgba(255,255,255,.3);padding:10px 8px 4px;
  white-space:nowrap;overflow:hidden;transition:opacity var(--tr);
}
.sidebar.collapsed .nav-section{opacity:0}
.nav-item{
  display:flex;align-items:center;gap:9px;
  padding:8px 10px;border-radius:var(--r-sm);
  color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;
  transition:all var(--tr);margin-bottom:1px;
  width:100%;text-align:left;cursor:pointer;white-space:nowrap;
  position:relative;overflow:hidden;
}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}
.nav-item.active::before{
  content:'';position:absolute;left:0;top:20%;bottom:20%;
  width:3px;background:var(--blue-lt);border-radius:0 2px 2px 0;
}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--blue-lt)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.sidebar.collapsed .nav-section-divider{opacity:0}
.nav-badge{
  background:var(--red);font-size:9px;font-weight:700;
  padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;
  transition:opacity var(--tr);
}
.sidebar.collapsed .nav-badge{opacity:0}

/* User foot */
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{
  display:flex;align-items:center;gap:9px;padding:8px 10px;
  border-radius:var(--r-sm);background:rgba(255,255,255,.07);
  cursor:pointer;transition:background var(--tr);overflow:hidden;
}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{
  width:30px;height:30px;border-radius:7px;
  background:linear-gradient(135deg,var(--red),#8b0f22);
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:11px;color:#fff;flex-shrink:0;
}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.user-badge{
  margin-left:auto;padding:2px 6px;border-radius:3px;
  background:var(--red);color:#fff;font-size:9px;font-weight:700;
  flex-shrink:0;white-space:nowrap;
}

/* ═══ MAIN ═══ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}

/* ═══ TOPBAR ═══ */
.topbar{
  height:var(--topbar-h);background:var(--surface);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 20px;gap:12px;
  position:sticky;top:0;z-index:200;
  box-shadow:var(--sh);
}
.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}

/* GLPI-style horizontal tabs */
.tab-nav{
  display:flex;align-items:center;gap:1px;
  background:var(--surface2);border:1px solid var(--border);
  border-radius:var(--r-sm);padding:3px;
  overflow-x:auto;scrollbar-width:none;
}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{
  display:flex;align-items:center;gap:5px;
  padding:5px 12px;border-radius:4px;
  font-size:12px;font-weight:500;color:var(--txt2);
  white-space:nowrap;transition:all var(--tr);cursor:pointer;
}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--blue);color:#fff;box-shadow:0 1px 4px rgba(50,110,169,.3)}
.tab-btn i{font-size:13px}

.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-search{
  display:flex;align-items:center;gap:7px;
  padding:6px 12px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  min-width:200px;
}
.tb-search:focus-within{border-color:var(--blue);background:#fff}
.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}
.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}
.tb-search input::placeholder{color:var(--txt3)}
.tb-icon{
  width:32px;height:32px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;
  position:relative;
}
.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{
  display:flex;align-items:center;gap:7px;
  padding:4px 10px 4px 4px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  cursor:pointer;transition:all var(--tr);
}
.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}
.tb-ava{
  width:28px;height:28px;border-radius:6px;
  background:linear-gradient(135deg,var(--blue),var(--blue-dk));
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:11px;color:#fff;flex-shrink:0;
}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1}
.page-panel{display:none;animation:fadeUp .3s ease both}
#page-dashboard{display:block}

/* ═══ GLPI TILE STATS ═══ */
.tiles-row{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.stat-tile{
  display:flex;align-items:center;gap:10px;
  padding:10px 14px;border-radius:var(--r-sm);
  color:#fff;min-width:120px;cursor:pointer;
  transition:all var(--tr);position:relative;overflow:hidden;
  flex:1;min-width:110px;max-width:160px;
}
.stat-tile:hover{transform:translateY(-2px);box-shadow:var(--sh-md);filter:brightness(1.05)}
.stat-tile-icon{
  width:36px;height:36px;border-radius:6px;
  background:rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;flex-shrink:0;
}
.stat-tile-val{font-size:22px;font-weight:700;line-height:1}
.stat-tile-lbl{font-size:11px;opacity:.9;margin-top:1px}

/* GLPI colors */
.tile-blue  {background:linear-gradient(135deg,var(--blue),var(--blue-lt))}
.tile-red   {background:linear-gradient(135deg,var(--red),var(--red-lt))}
.tile-green {background:linear-gradient(135deg,var(--green),var(--green-lt))}
.tile-orange{background:linear-gradient(135deg,var(--orange),#f39c12)}
.tile-purple{background:linear-gradient(135deg,var(--purple),#9b59b6)}
.tile-teal  {background:linear-gradient(135deg,var(--teal),#06b6d4)}
.tile-gold  {background:linear-gradient(135deg,#b8860b,var(--gold))}
.tile-dk    {background:linear-gradient(135deg,#374151,#4b5563)}

/* ═══ MINI STAT GRID (GLPI bottom row) ═══ */
.mini-stats{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px}
.mini-stat{
  display:flex;align-items:center;gap:8px;
  padding:8px 12px;border-radius:var(--r-sm);
  background:var(--surface);border:1px solid var(--border);
  box-shadow:var(--sh);cursor:pointer;
  transition:all var(--tr);flex:1;min-width:100px;
}
.mini-stat:hover{border-color:var(--blue);background:var(--blue-xlt);transform:translateY(-1px)}
.mini-stat-val{font-size:18px;font-weight:700;color:var(--blue)}
.mini-stat-lbl{font-size:11px;color:var(--txt2);white-space:nowrap}
.mini-stat-ico{font-size:16px;color:var(--txt3);margin-left:auto}

/* ═══ WIDGET GRID (GLPI two-column layout) ═══ */
.widget-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.widget-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
.widget-grid-asymm{display:grid;grid-template-columns:1.5fr 1fr;gap:12px;margin-bottom:16px}
.widget-full{margin-bottom:12px}

/* ═══ WIDGET CARD ═══ */
.widget{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);box-shadow:var(--sh);
  overflow:hidden;transition:all var(--tr);
}
.widget:hover{box-shadow:var(--sh-md);border-color:var(--border2)}
.widget-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 14px;border-bottom:1px solid var(--border);
  background:var(--surface2);
}
.widget-title{
  font-size:12.5px;font-weight:600;color:var(--txt);
  display:flex;align-items:center;gap:6px;
}
.widget-title i{color:var(--blue);font-size:15px}
.widget-actions{display:flex;gap:4px}
.w-btn{
  width:26px;height:26px;border-radius:4px;
  display:flex;align-items:center;justify-content:center;
  color:var(--txt3);font-size:13px;transition:all var(--tr);cursor:pointer;
}
.w-btn:hover{background:var(--blue-xlt);color:var(--blue)}
.widget-body{padding:12px 14px}
.widget-body.np{padding:0}

/* ═══ DATA TABLE (GLPI style) ═══ */
.glpi-table{width:100%;border-collapse:collapse;font-size:12px}
.glpi-table th{
  text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.7px;color:var(--txt3);padding:6px 10px;
  border-bottom:2px solid var(--border);background:var(--surface2);
}
.glpi-table td{padding:7px 10px;border-bottom:1px solid var(--border);color:var(--txt)}
.glpi-table tr:hover td{background:var(--blue-xlt)}
.glpi-table tr:last-child td{border:none}
.glpi-table .td-num{font-weight:700;color:var(--blue);font-size:15px;text-align:right}

/* ═══ PROGRESS BAR ═══ */
.pbar{height:5px;background:var(--border);border-radius:10px;overflow:hidden;margin-top:4px}
.pbar-fill{height:100%;border-radius:10px;transition:width .7s ease}

/* ═══ BADGE ═══ */
.badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-red  {background:var(--red-dim);color:var(--red)}
.badge-blue {background:var(--blue-dim);color:var(--blue)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-purple{background:var(--purple-dim);color:var(--purple)}
.badge-teal {background:var(--teal-dim);color:var(--teal)}

/* ═══ ACTIVITY ITEM ═══ */
.act-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)}
.act-row:last-child{border:none;padding-bottom:0}
.act-ico{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px}
.act-title{font-size:12px;font-weight:600;color:var(--txt)}.act-meta{font-size:11px;color:var(--txt3);margin-top:1px}
.act-time{font-size:11px;color:var(--txt2);white-space:nowrap}.act-stat{font-size:10px;margin-top:2px}
.s-ok{color:var(--green)}.s-pend{color:var(--orange)}.s-new{color:var(--blue)}

/* ═══ NOTIF ═══ */
.notif-row{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);transition:background var(--tr);cursor:pointer}
.notif-row:hover{background:var(--surface2)}.notif-row:last-child{border:none}
.notif-row.unread{background:#fef9f0;border-left:3px solid var(--orange)}
.notif-ico{font-size:20px;flex-shrink:0;margin-top:2px}.notif-title{font-size:12.5px;font-weight:600;color:var(--txt)}
.notif-body{font-size:11.5px;color:var(--txt2);margin-top:2px;line-height:1.5}.notif-time{font-size:10px;color:var(--txt3);margin-top:3px}
.notif-dot{width:7px;height:7px;border-radius:50%;background:var(--orange);flex-shrink:0;margin-top:4px}

/* ═══ USER TABLE ═══ */
.avatar-sm{width:28px;height:28px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff}

/* ═══ SECTION HEADER ═══ */
.sec-hdr{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.sec-title{font-size:16px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:7px}
.sec-title i{color:var(--blue);font-size:18px}
.sec-sub{font-size:11.5px;color:var(--txt3);margin-top:2px}

/* ═══ ACTION BTN ═══ */
.btn-primary{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r-sm);background:var(--blue);color:#fff;font-size:12px;font-weight:600;font-family:'Ubuntu',sans-serif;transition:all var(--tr);cursor:pointer}
.btn-primary:hover{background:var(--blue-dk);transform:translateY(-1px);box-shadow:0 4px 12px rgba(50,110,169,.3)}
.btn-primary i{font-size:14px}
.btn-outline{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:var(--r-sm);border:1px solid var(--border);color:var(--txt2);font-size:12px;font-weight:500;font-family:'Ubuntu',sans-serif;transition:all var(--tr);cursor:pointer}
.btn-outline:hover{border-color:var(--blue);color:var(--blue);background:var(--blue-xlt)}
.btn-danger{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:var(--r-sm);background:var(--red-dim);color:var(--red);font-size:11.5px;font-weight:600;font-family:'Ubuntu',sans-serif;transition:all var(--tr);cursor:pointer}
.btn-danger:hover{background:var(--red);color:#fff}
.btn-sm{padding:4px 10px;font-size:11px}

/* ═══ HEATMAP ═══ */
.heatmap{display:grid;grid-template-columns:28px repeat(7,1fr);gap:3px}
.hm-lbl{font-size:9px;color:var(--txt3);display:flex;align-items:center;justify-content:flex-end;padding-right:3px}
.hm-hdr{font-size:9px;color:var(--txt3);text-align:center}
.hm-cell{aspect-ratio:1;border-radius:4px;cursor:pointer;position:relative;transition:transform .12s}
.hm-cell:hover{transform:scale(1.15);z-index:5}
.hm-num{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:7.5px;font-weight:700}

/* ═══ RING ═══ */
.ring-wrap{display:flex;flex-direction:column;align-items:center;gap:6px;padding:10px 0}
.ring-svg{transform:rotate(-90deg)}.ring-track{fill:none;stroke:var(--border);stroke-width:9}
.ring-fill{fill:none;stroke-width:9;stroke-linecap:round;transition:stroke-dashoffset .9s cubic-bezier(.4,0,.2,1)}
.ring-label{font-size:26px;font-weight:700;color:var(--txt)}.ring-sub{font-size:11px;color:var(--txt3)}

/* ═══ MENTOR GRID ═══ */
.mentor-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.mentor-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:14px;text-align:center;cursor:pointer;transition:all var(--tr)}
.mentor-card:hover{border-color:var(--blue);background:var(--blue-xlt);transform:translateY(-3px);box-shadow:var(--sh-md)}
.m-ava{width:46px;height:46px;border-radius:50%;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;font-weight:700}
.m-name{font-size:12px;font-weight:700;color:var(--txt);margin-bottom:2px}.m-mod{font-size:10.5px;color:var(--txt3);margin-bottom:6px}
.m-stars{font-size:11px;color:var(--gold);margin-bottom:5px}
.m-tag{display:inline-block;padding:2px 8px;border-radius:20px;font-size:9.5px;font-weight:600;background:var(--green-dim);color:var(--green)}
.m-cnt{font-size:10px;color:var(--txt3);margin-top:4px}

/* ═══ TIMETABLE ═══ */
.tt-grid{display:grid;grid-template-columns:58px repeat(5,1fr);gap:2px;font-size:11px}
.tt-day{background:var(--blue);color:#fff;padding:6px 4px;border-radius:5px;text-align:center;font-size:10.5px;font-weight:700}
.tt-time{color:var(--txt3);font-size:10px;display:flex;align-items:flex-start;justify-content:flex-end;padding-right:6px;padding-top:4px}
.tt-slot{min-height:44px;border-radius:5px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--txt3);position:relative;overflow:hidden;cursor:pointer;transition:all var(--tr);text-align:center;padding:3px}
.tt-slot:hover{border-color:var(--blue);background:var(--blue-xlt)}
.tt-slot.booked{background:var(--red-dim);border-color:rgba(196,30,58,.25);color:var(--red);font-weight:600}
.tt-slot.booked::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--red)}
.tt-slot.mine{background:var(--green-dim);border-color:rgba(39,174,96,.25);color:var(--green);font-weight:600}
.tt-slot.mine::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--green)}

/* ═══ PAYMENT ═══ */
.pay-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)}
.pay-row:last-child{border:none}
.pay-ico{width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.pay-name{font-size:12.5px;font-weight:600;color:var(--txt)}.pay-plan{font-size:10.5px;color:var(--txt3)}
.pay-bar{height:4px;background:var(--border);border-radius:10px;overflow:hidden;margin-top:3px}
.pay-fill{height:100%;border-radius:10px}

/* ═══ GRADES ═══ */
.grade-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)}
.grade-row:last-child{border:none}
.g-name{font-size:12.5px;font-weight:600;color:var(--txt)}.g-coeff{font-size:10.5px;color:var(--txt3)}
.g-val{font-size:17px;font-weight:700}

/* ═══ SETTINGS ═══ */
.settings-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.setting-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:16px;cursor:pointer;transition:all var(--tr)}
.setting-card:hover{border-color:var(--blue);box-shadow:var(--sh-md);transform:translateY(-2px)}
.setting-ico{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:10px}
.setting-title{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:4px}
.setting-desc{font-size:11px;color:var(--txt3);line-height:1.5}

/* ═══ REPORT ═══ */
.report-row{display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid var(--border);transition:background var(--tr);cursor:pointer}
.report-row:hover{background:var(--surface2)}
.report-row:last-child{border:none}
.report-icon{width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.report-name{font-size:12.5px;font-weight:600;color:var(--txt)}.report-desc{font-size:10.5px;color:var(--txt3);margin-top:1px}

/* ═══ TOOLTIP ═══ */
#hmTip{position:fixed;background:rgba(26,26,46,.95);color:#fff;padding:5px 10px;border-radius:6px;font-size:11px;display:none;z-index:9999;pointer-events:none;white-space:nowrap}

/* ═══ TOAST ═══ */
.toast-msg{position:fixed;bottom:20px;right:20px;background:var(--blue-dk);color:#fff;padding:10px 16px;border-radius:var(--r);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(60px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--blue-lt);max-width:300px}
.toast-msg.show{transform:translateY(0);opacity:1}

/* ═══ RESPONSIVE ═══ */
@media(max-width:1200px){.widget-grid{grid-template-columns:1fr}.mentor-grid{grid-template-columns:repeat(3,1fr)}.settings-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.tiles-row{overflow-x:auto;flex-wrap:nowrap}}
</style>
</head>
<body>

<!-- ════════════════════ SIDEBAR ════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-mortarboard-fill"></i></div>
    <div class="logo-text">Edu<span>Connect</span></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()" title="Réduire">
      <i class="bi bi-layout-sidebar-reverse" id="sidebarIcon"></i>
    </button>
  </div>

  <div class="sidebar-search">
    <div class="search-field">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Chercher dans le menu…">
    </div>
  </div>

  <div class="nav-body">
    <div class="nav-section">Tableau de bord</div>
    <button class="nav-item active" onclick="nav('dashboard',this)">
      <i class="bi bi-grid-1x2-fill"></i><span class="nav-item-txt">Vue d'ensemble</span>
    </button>
    <button class="nav-item <?= $current_page === 'admin_rapports.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_rapports.php'">
      <i class="bi bi-bar-chart-fill"></i><span class="nav-item-txt">Rapports</span>
    </button>

    <div class="nav-section">Gestion académique</div>
    <button class="nav-item <?= $current_page === 'admin_etudiants.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_etudiants.php'">
      <i class="bi bi-people-fill"></i><span class="nav-item-txt">Étudiants</span>
    </button>
    <button class="nav-item <?= $current_page === 'admin_notes.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_notes.php'">
      <i class="bi bi-journal-richtext"></i><span class="nav-item-txt">Notes & Bulletins</span>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-calendar3-fill"></i><span class="nav-item-txt">Emplois du Temps</span>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-star-fill"></i><span class="nav-item-txt">Mentorat</span>
    </button>

    <div class="nav-section">Administration</div>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-credit-card-2-front-fill"></i><span class="nav-item-txt">Paiements</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>
    <button class="nav-item <?= $current_page === 'admin_salles.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_salles.php'">
      <i class="bi bi-building-fill"></i><span class="nav-item-txt">Réservations</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>
    <button class="nav-item <?= $current_page === 'admin_utilisateurs.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_utilisateurs.php'">
      <i class="bi bi-person-gear"></i><span class="nav-item-txt">Utilisateurs</span>
    </button>
    <button class="nav-item <?= $current_page === 'admin_notifications.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_notifications.php'">
      <i class="bi bi-bell-fill"></i><span class="nav-item-txt">Notifications</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </button>

    <div class="nav-section">Système</div>
    <button class="nav-item <?= $current_page === 'admin_parametres.php' ? 'active' : '' ?>" onclick="location.href='/Views/Admin/admin_parametres.php'">
      <i class="bi bi-gear-fill"></i><span class="nav-item-txt">Paramètres</span>
    </button>
    <button class="nav-item <?= $current_page === '#' ? 'active' : '' ?>" onclick="location.href='#'">
      <i class="bi bi-shield-check"></i><span class="nav-item-txt">Audit & Sécurité</span>
    </button>
  </div>

  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Admin/admin_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role"><?= $u_role ?></div>
      </div>
      <span class="user-badge">SA</span>
    </div>
  
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
      <i class="bi bi-mortarboard-fill"></i>
      EduConnect
    </div>

    <!-- GLPI-style tabs -->
    <div class="tab-nav" id="tabNav">
      <button class="tab-btn active" onclick="setTab('overview',this)"><i class="bi bi-speedometer2"></i>Vue d'ensemble</button>
      <button class="tab-btn" onclick="setTab('personal',this)"><i class="bi bi-person"></i>Vue personnelle</button>
      <button class="tab-btn" onclick="setTab('group',this)"><i class="bi bi-people"></i>Vue groupe</button>
      <button class="tab-btn" onclick="setTab('global',this)"><i class="bi bi-globe"></i>Vue globale</button>
      <button class="tab-btn" onclick="setTab('all',this)"><i class="bi bi-list-ul"></i>Tout</button>
    </div>

    <div class="topbar-right">
      <div class="tb-search">
        <i class="bi bi-search"></i>
        <input type="text" placeholder="Rechercher…">
      </div>
      <button class="tb-icon" onclick="toast('🌙 Mode sombre bientôt disponible')" title="Thème">
        <i class="bi bi-moon"></i>
      </button>
      <button class="tb-icon" onclick="nav('notifs',document.querySelectorAll('.nav-item')[9])" title="Notifications">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <button class="tb-icon" onclick="toast('📥 Export lancé')" title="Exporter">
        <i class="bi bi-download"></i>
      </button>
      <div class="tb-user" onclick="toast('Profil <?= $u_full ?>')">
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

    <!-- ══════════════ DASHBOARD ══════════════ -->
    <div id="page-dashboard" class="page-panel">

      <!-- Period selector -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:13px;font-weight:600;color:var(--txt)">
          <i class="bi bi-calendar3" style="color:var(--blue)"></i>
          Tableau de bord — <span id="periodLabel">Cette semaine</span>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
          <div style="display:flex;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;gap:2px">
            <button class="tab-btn active" style="font-size:11px;padding:4px 10px" onclick="setPeriod('week',this,'Cette semaine')">Semaine</button>
            <button class="tab-btn" style="font-size:11px;padding:4px 10px" onclick="setPeriod('month',this,'Ce mois')">Mois</button>
            <button class="tab-btn" style="font-size:11px;padding:4px 10px" onclick="setPeriod('year',this,'Cette année')">Année</button>
          </div>
          <button class="btn-outline btn-sm" onclick="toast('📥 Export rapport')"><i class="bi bi-download"></i>Exporter</button>
        </div>
      </div>

      <!-- GLPI-style colored tiles -->
      <div class="tiles-row">
        <div class="stat-tile tile-blue" onclick="nav('students',document.querySelectorAll('.nav-item')[4])">
          <div class="stat-tile-icon"><i class="bi bi-people-fill"></i></div>
          <div><div class="stat-tile-val" id="kv0">0</div><div class="stat-tile-lbl">Étudiants actifs</div></div>
        </div>
        <div class="stat-tile tile-green" onclick="nav('payment',document.querySelectorAll('.nav-item')[8])">
          <div class="stat-tile-icon"><i class="bi bi-check-circle-fill"></i></div>
          <div><div class="stat-tile-val" id="kv1">0</div><div class="stat-tile-lbl">Paiements reçus</div></div>
        </div>
        <div class="stat-tile tile-red" onclick="nav('payment',document.querySelectorAll('.nav-item')[8])">
          <div class="stat-tile-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
          <div><div class="stat-tile-val"><?= (int)($stats_admin["paiements_en_attente"] ?? 0) ?></div><div class="stat-tile-lbl">Paiements en attente</div></div>
        </div>
        <div class="stat-tile tile-orange" onclick="nav('mentoring',document.querySelectorAll('.nav-item')[7])">
          <div class="stat-tile-icon"><i class="bi bi-star-fill"></i></div>
          <div><div class="stat-tile-val" id="kv2">0</div><div class="stat-tile-lbl">Sessions mentorat</div></div>
        </div>
        <div class="stat-tile tile-teal" onclick="nav('rooms',document.querySelectorAll('.nav-item')[9])">
          <div class="stat-tile-icon"><i class="bi bi-building-fill"></i></div>
          <div><div class="stat-tile-val" id="kv3">0%</div><div class="stat-tile-lbl">Taux occupation</div></div>
        </div>
        <div class="stat-tile tile-purple" onclick="nav('notifs',document.querySelectorAll('.nav-item')[9])">
          <div class="stat-tile-icon"><i class="bi bi-bell-fill"></i></div>
          <div><div class="stat-tile-val">5</div><div class="stat-tile-lbl">Nouvelles notifs</div></div>
        </div>
      </div>

      <!-- Mini stats row (GLPI bottom row style) -->
      <div class="mini-stats">
        <div class="mini-stat" onclick="nav('students',document.querySelectorAll('.nav-item')[4])">
          <div><div class="mini-stat-val"><?= (int)(($stats_admin["nb_etudiants"]??0)+($stats_admin["nb_enseignants"]??0)+($stats_admin["nb_mentors"]??0)+($stats_admin["nb_parents"]??0)) ?></div><div class="mini-stat-lbl">Utilisateurs</div></div>
          <i class="bi bi-people mini-stat-ico"></i>
        </div>
        <div class="mini-stat" onclick="nav('users',document.querySelectorAll('.nav-item')[10])">
          <div><div class="mini-stat-val"><?= (int)($stats_admin["nb_mentors"] ?? 0) ?></div><div class="mini-stat-lbl">Mentors</div></div>
          <i class="bi bi-star mini-stat-ico"></i>
        </div>
        <div class="mini-stat" onclick="nav('rooms',document.querySelectorAll('.nav-item')[9])">
          <div><div class="mini-stat-val"><?= db_row($conn,"SELECT COUNT(*) AS nb FROM salles WHERE actif=1","","")["nb"]??0 ?></div><div class="mini-stat-lbl">Salles actives</div></div>
          <i class="bi bi-building mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">12</div><div class="mini-stat-lbl">Cours actifs</div></div>
          <i class="bi bi-book mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">8</div><div class="mini-stat-lbl">Profils</div></div>
          <i class="bi bi-shield mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">4</div><div class="mini-stat-lbl">Entités</div></div>
          <i class="bi bi-diagram-3 mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">1</div><div class="mini-stat-lbl">Ticket ouvert</div></div>
          <i class="bi bi-ticket mini-stat-ico"></i>
        </div>
        <div class="mini-stat">
          <div><div class="mini-stat-val">0</div><div class="mini-stat-lbl">Problèmes</div></div>
          <i class="bi bi-bug mini-stat-ico"></i>
        </div>
      </div>

      <!-- Widget grid top row -->
      <div class="widget-grid-asymm">
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-bar-chart-line-fill"></i>Réservations & Paiements</div>
            <div class="widget-actions">
              <div class="w-btn" onclick="switchChart('bar',this)"><i class="bi bi-bar-chart-fill"></i></div>
              <div class="w-btn" onclick="switchChart('line',this)"><i class="bi bi-graph-up"></i></div>
              <div class="w-btn" onclick="switchChart('area',this)"><i class="bi bi-water"></i></div>
              <div class="w-btn" onclick="toast('📥 Export graphique')"><i class="bi bi-download"></i></div>
            </div>
          </div>
          <div class="widget-body"><canvas id="mainChart" height="180"></canvas></div>
        </div>
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-pie-chart-fill"></i>Statuts des paiements — <span id="chartMonthLabel">2026-03</span></div>
            <div class="widget-actions">
              <div class="w-btn" onclick="toast('ℹ️ Voir tous les paiements')"><i class="bi bi-clock-history"></i></div>
            </div>
          </div>
          <div class="widget-body" style="display:flex;flex-direction:column;align-items:center;gap:10px">
            <canvas id="donutChart" height="140" style="max-width:140px"></canvas>
            <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
              <span style="font-size:10.5px;color:var(--txt2);display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:var(--green);display:inline-block"></span>Payé</span>
              <span style="font-size:10.5px;color:var(--txt2);display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:var(--orange);display:inline-block"></span>En cours</span>
              <span style="font-size:10.5px;color:var(--txt2);display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:var(--red);display:inline-block"></span>En retard</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Widget grid middle row -->
      <div class="widget-grid">
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-building-fill"></i>Salles — Top occupation</div>
            <div class="widget-actions"><div class="w-btn" onclick="nav('rooms',document.querySelectorAll('.nav-item')[9])"><i class="bi bi-arrow-right"></i></div></div>
          </div>
          <div class="widget-body np">
            <table class="glpi-table">
              <thead><tr><th>#</th><th>Salle</th><th>Type</th><th>Occ.</th><th style="text-align:right">%</th></tr></thead>
              <tbody id="roomTbl"></tbody>
            </table>
          </div>
        </div>
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-grid-3x3-gap-fill"></i>Carte thermique — créneaux</div>
            <div class="widget-actions"><div class="w-btn" onclick="toast('ℹ️ Vue complète')"><i class="bi bi-fullscreen"></i></div></div>
          </div>
          <div class="widget-body" style="overflow-x:auto"><div id="heatmap"></div></div>
        </div>
      </div>

      <!-- Widget bottom row -->
      <div class="widget-grid">
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-lightning-charge-fill"></i>Activité récente</div>
            <div class="widget-actions"><div class="w-btn" onclick="nav('notifs',document.querySelectorAll('.nav-item')[10])"><i class="bi bi-arrow-right"></i></div></div>
          </div>
          <div class="widget-body" id="recentAct"></div>
        </div>
        <div class="widget">
          <div class="widget-head">
            <div class="widget-title"><i class="bi bi-people-fill"></i>Top demandeurs de mentorat</div>
            <div class="widget-actions"><div class="w-btn" onclick="nav('mentoring',document.querySelectorAll('.nav-item')[7])"><i class="bi bi-arrow-right"></i></div></div>
          </div>
          <div class="widget-body np">
            <table class="glpi-table">
              <thead><tr><th>Étudiant</th><th>Module</th><th style="text-align:right">Sessions</th></tr></thead>
              <tbody>
                <?php
                $top_stu_adm = db_query($conn,
                    "SELECT CONCAT(u.prenom,' ',u.nom) AS name,
                            ROUND(AVG(n.note_finale),1) AS avg,
                            m.nom AS best_module,
                            c.code AS classe
                     FROM notes n
                     JOIN etudiants e ON e.id=n.etudiant_id
                     JOIN users u ON u.id=e.user_id
                     JOIN modules m ON m.id=n.module_id
                     JOIN classes c ON c.id=e.classe_id
                     WHERE n.publie=1 AND n.annee_acad='2025-2026'
                     GROUP BY e.id ORDER BY avg DESC LIMIT 5","",[]
                );
                $colors=['#326ea9','#27ae60','#7C3AED','#e67e22','#0891b2'];
                foreach ($top_stu_adm as $idx=>$stu):
                  $init = strtoupper(substr(explode(' ',$stu['name'])[0],0,1).substr(explode(' ',$stu['name'])[1]??'X',0,1));
                  $col  = $colors[$idx % 5];
                ?>
                <tr>
                  <td><div style="display:flex;align-items:center;gap:7px">
                    <span class="avatar-sm" style="background:<?= $col ?>"><?= $init ?></span>
                    <?= h($stu['name']) ?>
                  </div></td>
                  <td><?= h($stu['best_module']) ?></td>
                  <td class="td-num"><?= h($stu['avg']) ?></td>
                </tr>
                <?php endforeach; if(empty($top_stu_adm)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--txt3);padding:12px">Aucune note publiée</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div><!-- /dashboard -->

    <!-- ══════════════ STUDENTS ══════════════ -->
    <div id="page-students" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-people-fill"></i>Gestion des Étudiants</div><div class="sec-sub">Inscrits, profils, accès — UCAO Saint-Michel</div></div>
        <div style="display:flex;gap:8px">
          <button class="btn-outline" onclick="toast('📥 Export CSV')"><i class="bi bi-download"></i>Exporter</button>
          <button class="btn-primary" onclick="toast('➕ Nouvel étudiant ajouté')"><i class="bi bi-person-plus-fill"></i>Ajouter</button>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px">
        <div class="stat-tile tile-blue" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-people-fill"></i></div><div><div class="stat-tile-val"><?= (int)(($stats_admin["nb_etudiants"]??0)+($stats_admin["nb_enseignants"]??0)+($stats_admin["nb_mentors"]??0)+($stats_admin["nb_parents"]??0)) ?></div><div class="stat-tile-lbl">Total inscrits</div></div></div>
        <div class="stat-tile tile-green" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-check-circle"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["nb_paiements"]??0) ?></div><div class="stat-tile-lbl">Actifs</div></div></div>
        <div class="stat-tile tile-orange" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-clock"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["sessions_a_venir"]??0) ?></div><div class="stat-tile-lbl">Dossiers incomplets</div></div></div>
        <div class="stat-tile tile-red" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-x-circle"></i></div><div><div class="stat-tile-val">7</div><div class="stat-tile-lbl">Suspendus</div></div></div>
      </div>
      <div class="widget widget-full">
        <div class="widget-head">
          <div class="widget-title"><i class="bi bi-table"></i>Liste des étudiants</div>
          <div style="display:flex;gap:8px;align-items:center">
            <div class="tb-search" style="min-width:160px"><i class="bi bi-search"></i><input type="text" placeholder="Filtrer…"></div>
            <select style="font-size:12px;padding:5px 10px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;color:var(--txt);background:var(--surface)"><option>Tous les niveaux</option><option>L1</option><option>L2</option><option>M1</option><option>M2</option></select>
          </div>
        </div>
        <div class="widget-body np">
          <table class="glpi-table">
            <thead><tr><th>Étudiant</th><th>Niveau</th><th>Filière</th><th>Statut paiement</th><th>Moyenne</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody id="studentTable"></tbody>
          </table>
        </div>
      </div>
    </div><!-- /students -->

    <!-- ══════════════ PAYMENT ══════════════ -->
    <div id="page-payment" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-credit-card-2-front-fill"></i>Gestion des Paiements</div><div class="sec-sub">Wave · Orange Money · Carte · Virement</div></div>
        <div style="display:flex;gap:8px">
          <button class="btn-outline" onclick="toast('📥 Export reçus PDF')"><i class="bi bi-file-pdf"></i>Reçus PDF</button>
          <button class="btn-primary" onclick="toast('➕ Paiement enregistré')"><i class="bi bi-plus-circle-fill"></i>Enregistrer</button>
        </div>
      </div>
      <div class="widget-grid" style="grid-template-columns:1fr 1.6fr">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-pie-chart-fill"></i>Recouvrement annuel</div></div>
          <div class="widget-body">
            <div class="ring-wrap">
              <svg width="120" height="120" class="ring-svg"><circle class="ring-track" cx="60" cy="60" r="50"/><circle class="ring-fill" id="ringFill" cx="60" cy="60" r="50" stroke="#27ae60" stroke-dasharray="314" stroke-dashoffset="314"/></svg>
              <div class="ring-label" id="ringPct"><?= $taux_recouvrement ?>%</div><div class="ring-sub">des frais recouvrés</div>
              <div style="display:flex;gap:8px;margin-top:6px">
                <span class="badge badge-green">✓ Payé: <span id="paidAmt">0</span></span>
                <span class="badge badge-red">⏳ Reste: <span id="pendAmt">0</span></span>
              </div>
            </div>
          </div>
        </div>
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-list-check"></i>Suivi des paiements</div></div>
          <div class="widget-body" id="payList" style="padding-top:6px"></div>
        </div>
      </div>
      <div class="widget-grid">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-phone-fill"></i>Moyens de paiement</div></div>
          <div class="widget-body"><canvas id="payMethodChart" height="190"></canvas></div>
        </div>
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-bar-chart-fill"></i>Paiements par mois</div></div>
          <div class="widget-body"><canvas id="payMonthChart" height="190"></canvas></div>
        </div>
      </div>
    </div><!-- /payment -->

    <!-- ══════════════ MENTORING ══════════════ -->
    <div id="page-mentoring" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-star-fill"></i>Système de Mentorat</div><div class="sec-sub">Mise en relation élèves ↔ mentors — mention Très Bien</div></div>
        <button class="btn-primary" onclick="toast('Session créée !')"><i class="bi bi-plus-circle-fill"></i>Nouvelle session</button>
      </div>
      <div class="tiles-row" style="margin-bottom:14px">
        <div class="stat-tile tile-green" style="max-width:200px"><div class="stat-tile-icon"><i class="bi bi-star-fill"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["nb_enseignants"]??0) ?></div><div class="stat-tile-lbl">Mentors actifs</div></div></div>
        <div class="stat-tile tile-purple" style="max-width:200px"><div class="stat-tile-icon"><i class="bi bi-mortarboard-fill"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["reservations_attente"]??0) ?></div><div class="stat-tile-lbl">Étudiants accompagnés</div></div></div>
        <div class="stat-tile tile-gold" style="max-width:200px"><div class="stat-tile-icon"><i class="bi bi-camera-video-fill"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["nb_sessions_terminees"]??0) ?></div><div class="stat-tile-lbl">Sessions réalisées</div></div></div>
        <div class="stat-tile tile-teal" style="max-width:200px"><div class="stat-tile-icon"><i class="bi bi-clock-fill"></i></div><div><div class="stat-tile-val">8</div><div class="stat-tile-lbl">Sessions en attente</div></div></div>
      </div>
      <div class="widget widget-full" style="margin-bottom:14px">
        <div class="widget-head"><div class="widget-title"><i class="bi bi-search"></i>Mentors disponibles</div><div class="widget-actions"><div class="w-btn" onclick="toast('Filtres avancés')"><i class="bi bi-funnel-fill"></i></div></div></div>
        <div class="widget-body"><div class="mentor-grid" id="mentorsGrid"></div></div>
      </div>
    </div><!-- /mentoring -->

    <!-- ══════════════ ROOMS ══════════════ -->
    <div id="page-rooms" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-building-fill"></i>Réservation de Salles</div><div class="sec-sub">36 espaces — Disponibilité en temps réel</div></div>
        <button class="btn-primary" onclick="toast('Réservation créée !')"><i class="bi bi-plus-circle-fill"></i>Nouvelle réservation</button>
      </div>
      <div class="widget-grid">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-bar-chart-fill"></i>Occupation des salles</div></div>
          <div class="widget-body"><canvas id="roomsChart" height="220"></canvas></div>
        </div>
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-grid-3x3-gap-fill"></i>Heatmap — semaine</div></div>
          <div class="widget-body" id="roomsHeatFull" style="overflow-x:auto"></div>
        </div>
      </div>
      <div class="widget widget-full">
        <div class="widget-head"><div class="widget-title"><i class="bi bi-table"></i>Réservations récentes</div></div>
        <div class="widget-body np">
          <table class="glpi-table">
            <thead><tr><th>Salle</th><th>Demandeur</th><th>Date/Heure</th><th>Motif</th><th>Capacité</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody id="reservationTable"></tbody>
          </table>
        </div>
      </div>
    </div><!-- /rooms -->

    <!-- ══════════════ TIMETABLE ══════════════ -->
    <div id="page-timetable" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-calendar-week-fill"></i>Emplois du Temps</div><div class="sec-sub">Semaine du 24 au 28 Mars 2026 — Administration Mme Bilong</div></div>
        <div style="display:flex;gap:8px">
          <div style="display:flex;gap:6px;align-items:center">
            <span class="badge badge-green"><i class="bi bi-circle-fill"></i> Disponible</span>
            <span class="badge badge-red"><i class="bi bi-circle-fill"></i> Réservé</span>
            <span class="badge badge-teal"><i class="bi bi-star-fill"></i> Ma réservation</span>
          </div>
          <button class="btn-primary" onclick="toast('📄 Export PDF')"><i class="bi bi-file-pdf"></i>PDF</button>
        </div>
      </div>
      <div class="widget widget-full">
        <div class="widget-head"><div class="widget-title"><i class="bi bi-calendar3-fill"></i>Planning hebdomadaire</div></div>
        <div class="widget-body"><div id="timetable" style="overflow-x:auto"></div></div>
      </div>
    </div><!-- /timetable -->

    <!-- ══════════════ GRADES ══════════════ -->
    <div id="page-grades" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-journal-richtext"></i>Notes & Bulletins</div><div class="sec-sub">Impression libre — économise les 5 000 FCFA de réémission</div></div>
        <div style="display:flex;gap:8px">
          <button class="btn-outline" onclick="toast('📊 Export notes Excel')"><i class="bi bi-file-excel"></i>Excel</button>
          <button class="btn-primary" onclick="toast('📄 Bulletin PDF téléchargé')"><i class="bi bi-printer-fill"></i>Imprimer bulletin</button>
        </div>
      </div>
      <div class="widget-grid" style="grid-template-columns:1.3fr 1fr">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-radar"></i>Performance par module</div></div>
          <div class="widget-body"><canvas id="gradesChart" height="260"></canvas></div>
        </div>
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-list-ol"></i>Notes — Semestre 1</div></div>
          <div class="widget-body" id="gradesList"></div>
        </div>
      </div>
    </div><!-- /grades -->

    <!-- ══════════════ USERS ══════════════ -->
    <div id="page-users" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-person-gear"></i>Gestion des Utilisateurs</div><div class="sec-sub">Comptes, rôles, permissions — Super Admin</div></div>
        <div style="display:flex;gap:8px">
          <button class="btn-outline" onclick="toast('📥 Export liste')"><i class="bi bi-download"></i>Exporter</button>
          <button class="btn-primary" onclick="toast('➕ Compte créé')"><i class="bi bi-person-plus-fill"></i>Créer un compte</button>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px">
        <div class="stat-tile tile-blue" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-people-fill"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["nb_etudiants"] ?? 0) ?></div><div class="stat-tile-lbl">Étudiants</div></div></div>
        <div class="stat-tile tile-teal" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-person-badge-fill"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["nb_enseignants"] ?? 0) ?></div><div class="stat-tile-lbl">Enseignants</div></div></div>
        <div class="stat-tile tile-orange" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-person-gear"></i></div><div><div class="stat-tile-val">5</div><div class="stat-tile-lbl">Admins</div></div></div>
        <div class="stat-tile tile-red" style="max-width:100%"><div class="stat-tile-icon"><i class="bi bi-person-x-fill"></i></div><div><div class="stat-tile-val">3</div><div class="stat-tile-lbl">Suspendus</div></div></div>
      </div>
      <div class="widget widget-full">
        <div class="widget-head">
          <div class="widget-title"><i class="bi bi-table"></i>Tous les utilisateurs</div>
          <div style="display:flex;gap:8px;align-items:center">
            <div class="tb-search" style="min-width:180px"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div>
            <select style="font-size:12px;padding:5px 10px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;color:var(--txt);background:var(--surface)"><option>Tous les rôles</option><option>Étudiant</option><option>Enseignant</option><option>Admin</option><option>Super Admin</option></select>
          </div>
        </div>
        <div class="widget-body np">
          <table class="glpi-table">
            <thead><tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Entité</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody id="userTable"></tbody>
          </table>
        </div>
      </div>
    </div><!-- /users -->

    <!-- ══════════════ NOTIFS ══════════════ -->
    <div id="page-notifs" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div class="sec-title"><i class="bi bi-bell-fill"></i>Notifications</div>
        <div style="display:flex;gap:8px">
          <button class="btn-outline" onclick="toast('✉️ Nouvelle notification envoyée')"><i class="bi bi-send-fill"></i>Envoyer à tous</button>
          <button class="btn-primary" onclick="markAllRead()"><i class="bi bi-check2-all"></i>Tout marquer lu</button>
        </div>
      </div>
      <div class="widget widget-full">
        <div class="widget-body np" id="notifsList"></div>
      </div>
    </div><!-- /notifs -->

    <!-- ══════════════ REPORTS ══════════════ -->
    <div id="page-reports" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-bar-chart-fill"></i>Rapports & Analyses</div><div class="sec-sub">Tableaux de bord analytiques — Super Admin</div></div>
        <button class="btn-primary" onclick="toast('📊 Rapport personnalisé généré')"><i class="bi bi-plus-circle-fill"></i>Nouveau rapport</button>
      </div>
      <div class="widget-grid" style="margin-bottom:14px">
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-graph-up-arrow"></i>Taux de réussite par filière</div></div>
          <div class="widget-body"><canvas id="successChart" height="200"></canvas></div>
        </div>
        <div class="widget">
          <div class="widget-head"><div class="widget-title"><i class="bi bi-cash-coin"></i>Revenus mensuels</div></div>
          <div class="widget-body"><canvas id="revenueChart" height="200"></canvas></div>
        </div>
      </div>
      <div class="widget widget-full">
        <div class="widget-head"><div class="widget-title"><i class="bi bi-file-earmark-bar-graph"></i>Rapports disponibles</div></div>
        <div class="widget-body np" id="reportsList"></div>
      </div>
    </div><!-- /reports -->

    <!-- ══════════════ SETTINGS ══════════════ -->
    <div id="page-settings" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-gear-fill"></i>Paramètres système</div><div class="sec-sub">Configuration globale — Super Admin uniquement</div></div>
        <button class="btn-primary" onclick="toast('✅ Paramètres sauvegardés')"><i class="bi bi-check-circle-fill"></i>Sauvegarder</button>
      </div>
      <div class="settings-grid" id="settingsGrid"></div>
    </div><!-- /settings -->

    <!-- ══════════════ AUDIT ══════════════ -->
    <div id="page-audit" class="page-panel" style="display:none">
      <div class="sec-hdr">
        <div><div class="sec-title"><i class="bi bi-shield-check"></i>Audit & Sécurité</div><div class="sec-sub">Journal d'activité, connexions, anomalies</div></div>
        <button class="btn-primary" onclick="toast('📥 Export journal audit')"><i class="bi bi-download"></i>Exporter journal</button>
      </div>
      <div class="tiles-row" style="margin-bottom:14px">
        <div class="stat-tile tile-green" style="max-width:180px"><div class="stat-tile-icon"><i class="bi bi-shield-check"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["nb_paiements"]??0) ?></div><div class="stat-tile-lbl">Connexions aujourd'hui</div></div></div>
        <div class="stat-tile tile-orange" style="max-width:180px"><div class="stat-tile-icon"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="stat-tile-val">3</div><div class="stat-tile-lbl">Tentatives échouées</div></div></div>
        <div class="stat-tile tile-red" style="max-width:180px"><div class="stat-tile-icon"><i class="bi bi-bug-fill"></i></div><div><div class="stat-tile-val">0</div><div class="stat-tile-lbl">Anomalies détectées</div></div></div>
        <div class="stat-tile tile-blue" style="max-width:180px"><div class="stat-tile-icon"><i class="bi bi-key-fill"></i></div><div><div class="stat-tile-val"><?= (int)($stats_admin["nb_sessions_a_venir"]??0) ?></div><div class="stat-tile-lbl">Sessions actives</div></div></div>
      </div>
      <div class="widget widget-full">
        <div class="widget-head"><div class="widget-title"><i class="bi bi-clock-history"></i>Journal d'activité récent</div></div>
        <div class="widget-body np">
          <table class="glpi-table">
            <thead><tr><th>Date/Heure</th><th>Utilisateur</th><th>Action</th><th>IP</th><th>Statut</th></tr></thead>
            <tbody id="auditTable"></tbody>
          </table>
        </div>
      </div>
    </div><!-- /audit -->

  </div><!-- /content -->
</main>

<div id="hmTip"></div>
<div class="toast-msg" id="toast"></div>
<script>
/* ══════════════════ DATA ══════════════════ */
const PERIODS={
  week:{labels:['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'],reserv:[8,14,11,17,13,6,2],pays:[5,9,8,12,10,4,1],students:287,payments:49,mentoring:36,occ:58},
  month:{labels:['S1','S2','S3','S4'],reserv:[52,68,59,74],pays:[38,52,47,61],students:312,payments:198,mentoring:124,occ:67},
  year:{labels:['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'],reserv:[180,165,210,240,270,225,100,80,255,290,245,215],pays:[130,120,160,180,200,170,75,60,190,220,185,160],students:487,payments:1650,mentoring:748,occ:71}
};
const ROOMS_TOP = <?= json_encode(
  db_query($conn,
    "SELECT s.nom, s.type, s.capacite, COUNT(r.id) AS nb
     FROM salles s LEFT JOIN reservations r ON r.salle_id=s.id
     AND r.statut='confirmee' AND r.date_debut>=DATE_SUB(NOW(),INTERVAL 7 DAY)
     WHERE s.actif=1 GROUP BY s.id ORDER BY nb DESC LIMIT 5","",[])
) ?>;
const HM_HOURS = ['08h','09h','10h','11h','14h','15h','16h','17h','18h','19h'];
const HM_DATA = <?= json_encode(
  array_fill(0, 6, array_fill(0, 10, 0))
) ?>;  // matrice salles×créneaux — peuplée via EDT
const RECENTS=<?= json_encode(array_map(fn($p)=>["ico"=>"💳","title"=>"Paiement reçu","meta"=>h($p["payeur_nom"])." — ".number_format((float)$p["montant"],0,","," ")." FCFA","time"=>date("H:i",strtotime($p["created_at"])),"status"=>$p["statut"]==="confirme"?"ok":"pend","label"=>$p["statut"]==="confirme"?"Confirmé":"En attente"],$derniers_paiements)) ?>;
const MENTORS = <?= json_encode(
  db_query($conn,
    "SELECT CONCAT(u.prenom,' ',u.nom) AS name,
            m.note_moyenne AS avg, m.nb_sessions AS sessions,
            m.nb_etudiants AS students, m.actif AS active
     FROM mentors m JOIN users u ON u.id=m.user_id
     WHERE m.actif=1 ORDER BY m.note_moyenne DESC LIMIT 5","",[])
) ?>;
const PAYS = <?= json_encode(
  db_query($conn,
    "SELECT DATE_FORMAT(created_at,'%b') AS mois,
            SUM(CASE WHEN statut='confirme' THEN montant ELSE 0 END) AS confirme,
            COUNT(CASE WHEN statut='en_attente' THEN 1 END) AS attente
     FROM paiements
     WHERE created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at,'%Y-%m')
     ORDER BY DATE_FORMAT(created_at,'%Y-%m') ASC","",[])
) ?>;
const GRADES = <?= json_encode(array_map(fn($cl)=>[
  "classe"=>$cl["classe"],"moy"=>(float)($cl["moy_classe"]??0)
], $moyennes_classes)) ?>;
const NOTIFS = <?= json_encode(array_map(fn($na)=>["title"=>$na["titre"],"type"=>$na["type"],"unread"=>!$na["lu"]],$notifications_admin)) ?>;
const TT_DATA={rows:['08h–10h','10h–12h','12h–14h','14h–16h','16h–18h','18h–20h'],days:['Lundi 24','Mardi 25','Mercredi 26','Jeudi 27','Vendredi 28'],slots:[['Droit Civil
A1','Libre','Physique
C2','Libre','Éco
B1'],['Libre','Maths
A2','Libre','Droit
A1','Libre'],['—','—','—','—','—'],['Info
C3','Libre','Libre','Maths
A2','Révision
★'],['Libre','Philo
B2','Libre','Libre','Libre'],['—','—','Mentorat
★','—','—']]};
const STUDENTS = <?= json_encode(array_map(fn($u)=>[
  'name'   => $u['nom_complet'],
  'email'  => $u['email'],
  'role'   => $u['role'],
  'statut' => $u['statut'],
  'date'   => date('d/m/Y',strtotime($u['created_at'])),
],$derniers_users)) ?>;
const USERS=<?= json_encode(array_map(fn($u)=>["name"=>h($u["nom_complet"]),"email"=>h($u["email"]),"role"=>h($u["role"]),"last"=>h($u["created_at"]),"status"=>$u["statut"]==="actif"?"active":"inactive"],$derniers_users)) ?>;
const RESERVATIONS = <?= json_encode(
  db_query($conn,
    "SELECT r.code_resa, s.nom AS salle, CONCAT(u.prenom,' ',u.nom) AS user_name,
            DATE_FORMAT(r.date_debut,'%a %d %b') AS date_str,
            DATE_FORMAT(r.date_debut,'%H:%i') AS heure,
            r.statut
     FROM reservations r JOIN salles s ON s.id=r.salle_id JOIN users u ON u.id=r.demandeur_id
     ORDER BY r.created_at DESC LIMIT 6","",[])
) ?>;
const AUDIT_LOGS = <?= json_encode(
  db_query($conn,
    "SELECT al.created_at AS time, CONCAT(u.prenom,' ',u.nom) AS user_name,
            al.action, al.ip_address AS ip
     FROM audit_log al LEFT JOIN users u ON u.id=al.user_id
     ORDER BY al.created_at DESC LIMIT 8","",[])
) ?>;
const SETTINGS_CARDS = [];
const REPORTS_LIST = [];

let mainChartRef=null;
const C={tooltipBg:'rgba(26,26,46,.95)',font:{family:'Ubuntu',size:11},tickColor:'#9ca3af',gridColor:'rgba(0,0,0,.05)'};

/* ══ INIT ══ */
document.addEventListener('DOMContentLoaded',()=>{
  buildKPIs('week');buildMainChart('week');buildDonut();
  buildRoomTable();buildHeatmap('heatmap');buildHeatmap('roomsHeatFull');
  buildRecents();buildPayments();buildMentors();buildTimetable();
  buildGrades();buildNotifs();buildPayCharts();buildRoomsChart();buildGradesChart();
  buildStudentTable();buildUserTable();buildReservationTable();buildAuditTable();
  buildReportsList();buildSettings();buildReportCharts();
  setTimeout(()=>animateRing(73),400);
  document.getElementById('chartMonthLabel').textContent=new Date().toISOString().slice(0,7);
});

/* ══ SIDEBAR TOGGLE ══ */
function toggleSidebar(){
  const s=document.getElementById('sidebar'),m=document.getElementById('main');
  s.classList.toggle('collapsed');m.classList.toggle('expanded');
  document.getElementById('sidebarIcon').className=s.classList.contains('collapsed')?'bi bi-layout-sidebar':'bi bi-layout-sidebar-reverse';
}

/* ══ NAV ══ */
const PAGES={dashboard:'Vue d\'ensemble',students:'Étudiants',payment:'Paiements',mentoring:'Mentorat',rooms:'Réservations',timetable:'Emplois du Temps',grades:'Notes & Bulletins',users:'Utilisateurs',notifs:'Notifications',reports:'Rapports',settings:'Paramètres',audit:'Audit & Sécurité'};
function nav(id,btn){
  document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.page-panel').forEach(p=>p.style.display='none');
  const panel=document.getElementById('page-'+id);
  panel.style.display='block';panel.style.animation='none';
  requestAnimationFrame(()=>panel.style.animation='fadeUp .3s ease both');
  document.querySelector('.topbar-brand').innerHTML=`<i class="bi bi-mortarboard-fill"></i> EduConnect — ${PAGES[id]||id}`;
}

/* ══ TAB NAV ══ */
function setTab(tab,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  toast(`Vue: ${btn.textContent.trim()}`);
}

/* ══ PERIOD ══ */
function setPeriod(p,btn,label){
  document.querySelectorAll('#page-dashboard .tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('periodLabel').textContent=label;
  buildKPIs(p);updateMainChart(p);
}

/* ══ KPIs ══ */
function buildKPIs(p){const d=PERIODS[p];animCnt('kv0',d.students);animCnt('kv1',d.payments);animCnt('kv2',d.mentoring);animCnt('kv3',d.occ,'%')}
function animCnt(id,target,suf=''){
  const el=document.getElementById(id);if(!el)return;const t0=performance.now();
  (function step(now){const t=Math.min((now-t0)/800,1);el.textContent=Math.round((1-Math.pow(1-t,3))*target)+suf;if(t<1)requestAnimationFrame(step)})(t0);
}

/* ══ CHARTS ══ */
function buildMainChart(p){
  const ctx=document.getElementById('mainChart').getContext('2d'),d=PERIODS[p];
  mainChartRef=new Chart(ctx,{type:'bar',data:{labels:d.labels,datasets:[
    {label:'Réservations',data:d.reserv,backgroundColor:'rgba(50,110,169,.2)',borderColor:'rgba(50,110,169,.7)',borderWidth:1.5,borderRadius:6,borderSkipped:false},
    {label:'Paiements',data:d.pays,backgroundColor:'rgba(39,174,96,.2)',borderColor:'rgba(39,174,96,.7)',borderWidth:1.5,borderRadius:6,borderSkipped:false}
  ]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom',labels:{font:C.font,boxWidth:8,padding:12,color:C.tickColor}},tooltip:{backgroundColor:C.tooltipBg,titleFont:{family:'Ubuntu',size:11},bodyFont:{family:'Ubuntu',size:12},padding:8}},scales:{x:{grid:{display:false},ticks:{font:C.font,color:C.tickColor}},y:{grid:{color:C.gridColor,drawBorder:false},ticks:{font:C.font,color:C.tickColor},beginAtZero:true}},animation:{duration:600,easing:'easeOutQuart'}}});
}
function updateMainChart(p){if(!mainChartRef)return;const d=PERIODS[p];mainChartRef.data.labels=d.labels;mainChartRef.data.datasets[0].data=d.reserv;mainChartRef.data.datasets[1].data=d.pays;mainChartRef.update()}
function switchChart(type,btn){
  if(!mainChartRef)return;const isArea=type==='area',ct=isArea?'line':type;
  mainChartRef.config.type=ct;
  mainChartRef.data.datasets.forEach((ds,i)=>{ds.fill=isArea;ds.tension=(ct==='line'||isArea)?.4:undefined;ds.pointRadius=(ct==='line'||isArea)?3:undefined;ds.borderWidth=(ct==='line'||isArea)?2:1.5;ds.backgroundColor=isArea?(i?'rgba(39,174,96,.07)':'rgba(50,110,169,.07)'):(i?'rgba(39,174,96,.2)':'rgba(50,110,169,.2)')});
  mainChartRef.update();
}
function buildDonut(){
  new Chart(document.getElementById('donutChart').getContext('2d'),{type:'doughnut',data:{labels:['Payé','En cours','En retard'],datasets:[{data: [<?= (int)($stats_admin['nb_etudiants']??0) ?>, <?= (int)($stats_admin['nb_enseignants']??0) ?>, <?= (int)($stats_admin['nb_mentors']??0) ?>],backgroundColor:['#27ae60','#e67e22','#C41E3A'],borderWidth:0,hoverOffset:6}]},options:{responsive:true,cutout:'65%',plugins:{legend:{display:false},tooltip:{backgroundColor:C.tooltipBg,callbacks:{label:ctx=>` ${ctx.label}: ${ctx.raw}%`}}}}});
}
function buildPayCharts(){
  new Chart(document.getElementById('payMethodChart').getContext('2d'),{type:'doughnut',data:{labels:['Wave','Orange Money','Carte','Virement'],datasets:[{data: [<?= (int)($stats_admin['nb_etudiants']??0) ?>, <?= (int)($stats_admin['nb_enseignants']??0) ?>, <?= (int)($stats_admin['nb_mentors']??0) ?>, <?= (int)($stats_admin['nb_parents']??0) ?>],backgroundColor:['#326ea9','#C41E3A','#D4A843','#27ae60'],borderWidth:0,hoverOffset:4}]},options:{responsive:true,cutout:'60%',plugins:{legend:{position:'bottom',labels:{font:C.font,boxWidth:8,padding:10,color:C.tickColor}},tooltip:{backgroundColor:C.tooltipBg,callbacks:{label:ctx=>` ${ctx.label}: ${ctx.raw}%`}}}}});
  new Chart(document.getElementById('payMonthChart').getContext('2d'),{type:'bar',data:{labels:['Sep','Oct','Nov','Déc','Jan','Fév','Mar'],datasets:[{data: <?= json_encode(array_map(fn($r)=>(float)$r['total_confirme'],$paiements_par_mois)) ?>,backgroundColor:'rgba(50,110,169,.2)',borderColor:'rgba(50,110,169,.7)',borderWidth:1.5,borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false},tooltip:{backgroundColor:C.tooltipBg,callbacks:{label:ctx=>` ${(ctx.raw/1000).toFixed(0)}k FCFA`}}},scales:{x:{grid:{display:false},ticks:{font:C.font,color:C.tickColor}},y:{grid:{color:C.gridColor},beginAtZero:true,ticks:{font:C.font,color:C.tickColor,callback:v=>(v/1000)+'k'}}}}});
}
function buildRoomsChart(){
  new Chart(document.getElementById('roomsChart').getContext('2d'),{type:'bar',data:{labels:ROOMS_TOP.map(r=>r.name),datasets:[{label:'%',data:ROOMS_TOP.map(r=>r.pct),backgroundColor:ROOMS_TOP.map(r=>r.pct>75?'rgba(196,30,58,.65)':r.pct>55?'rgba(230,126,34,.65)':'rgba(39,174,96,.65)'),borderRadius:8,borderSkipped:false}]},options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false},tooltip:{backgroundColor:C.tooltipBg,callbacks:{label:ctx=>` ${ctx.raw}%`}}},scales:{x:{grid:{color:C.gridColor},ticks:{font:C.font,color:C.tickColor},max:100},y:{grid:{display:false},ticks:{font:C.font,color:C.tickColor}}}}});
}
function buildGradesChart(){
  new Chart(document.getElementById('gradesChart').getContext('2d'),{type:'radar',data:{labels:GRADES.map(g=>g.module.split(' ')[0]),datasets:[{label:'Notes /20',data:GRADES.map(g=>g.note),backgroundColor:'rgba(50,110,169,.10)',borderColor:'#326ea9',borderWidth:2,pointBackgroundColor:'#C41E3A',pointRadius:4}]},options:{responsive:true,scales:{r:{min:0,max:20,ticks:{stepSize:5,font:C.font,color:C.tickColor,backdropColor:'transparent'},pointLabels:{font:{family:'Ubuntu',size:11},color:C.tickColor},grid:{color:'rgba(0,0,0,.06)'},angleLines:{color:'rgba(0,0,0,.06)'}}},plugins:{legend:{display:false},tooltip:{backgroundColor:C.tooltipBg,callbacks:{label:ctx=>` ${ctx.raw}/20`}}}}});
}
function buildReportCharts(){
  if(!document.getElementById('successChart'))return;
  new Chart(document.getElementById('successChart').getContext('2d'),{type:'bar',data:{labels:['Informatique','Droit','Économie','Maths','Anglais','Philosophie'],datasets:[{label:'Taux réussite %',data: <?= json_encode(array_map(fn($cl)=>$cl['moy_classe'] ? round((float)$cl['moy_classe']/20*100) : 0,$moyennes_classes)) ?>,backgroundColor:['rgba(50,110,169,.7)','rgba(196,30,58,.7)','rgba(39,174,96,.7)','rgba(230,126,34,.7)','rgba(8,145,178,.7)','rgba(124,58,237,.7)'],borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false},tooltip:{backgroundColor:C.tooltipBg}},scales:{x:{grid:{display:false},ticks:{font:C.font,color:C.tickColor}},y:{grid:{color:C.gridColor},ticks:{font:C.font,color:C.tickColor},max:100}}}});
  new Chart(document.getElementById('revenueChart').getContext('2d'),{type:'line',data:{labels:['Sep','Oct','Nov','Déc','Jan','Fév','Mar'],datasets:[{label:'Revenus (FCFA)',data: <?= json_encode(array_map(fn($r)=>(float)$r['total_confirme'],$paiements_par_mois)) ?>,borderColor:'#326ea9',backgroundColor:'rgba(50,110,169,.08)',borderWidth:2,fill:true,tension:.4,pointBackgroundColor:'#C41E3A',pointRadius:4}]},options:{responsive:true,plugins:{legend:{display:false},tooltip:{backgroundColor:C.tooltipBg,callbacks:{label:ctx=>` ${(ctx.raw/1000000).toFixed(1)}M FCFA`}}},scales:{x:{grid:{display:false},ticks:{font:C.font,color:C.tickColor}},y:{grid:{color:C.gridColor},ticks:{font:C.font,color:C.tickColor,callback:v=>(v/1000000)+'M'}}}}});
}

/* ══ TABLE BUILDERS ══ */
function buildRoomTable(){
  const colors={'>75':'#C41E3A','>55':'#e67e22','':'#27ae60'};
  document.getElementById('roomTbl').innerHTML=ROOMS_TOP.map((r,i)=>{
    const c=r.pct>75?'#C41E3A':r.pct>55?'#e67e22':'#27ae60';
    return`<tr><td style="color:var(--txt3);font-size:11px">${i+1}</td><td style="font-weight:600">${r.name}</td><td><span class="badge ${r.pct>75?'badge-red':r.pct>55?'badge-orange':'badge-green'}" style="font-size:9px">${r.type}</span></td><td><div class="pbar" style="width:70px"><div class="pbar-fill" style="width:${r.pct}%;background:${c}"></div></div></td><td style="font-weight:700;color:${c};text-align:right">${r.pct}%</td></tr>`;
  }).join('');
}
function buildHeatmap(cid){
  const el=document.getElementById(cid);if(!el)return;
  const mx=Math.max(...HM_DATA.flat());
  let h='<div class="heatmap"><div class="hm-lbl"></div>';
  HM_DAYS.forEach(d=>h+=`<div class="hm-hdr">${d}</div>`);
  HM_HOURS.forEach((hr,hi)=>{h+=`<div class="hm-lbl">${hr}</div>`;HM_DAYS.forEach((_,di)=>{const v=HM_DATA[hi][di],int=v/mx;const bg=v===0?'#f3f4f6':`rgba(50,110,169,${.1+int*.8})`;const tc=int>.55?'#fff':'#6b7280';h+=`<div class="hm-cell" style="background:${bg}" onmouseenter="showHmTip(event,'${HM_DAYS[di]} ${hr}: ${v} rés.')" onmouseleave="document.getElementById('hmTip').style.display='none'" onclick="toast('${v} réservation(s)')"><span class="hm-num" style="color:${tc}">${v>0?v:''}</span></div>`})});
  h+='</div>';el.innerHTML=h;
}
function showHmTip(e,txt){const t=document.getElementById('hmTip');t.textContent=txt;t.style.display='block';t.style.left=(e.clientX+10)+'px';t.style.top=(e.clientY-32)+'px'}
function buildRecents(){
  const sc={ok:'s-ok',pend:'s-pend',new:'s-new'};
  document.getElementById('recentAct').innerHTML=RECENTS.map(r=>`<div class="act-row"><div class="act-ico" style="background:var(--surface2)">${r.ico}</div><div style="flex:1"><div class="act-title">${r.title}</div><div class="act-meta">${r.meta}</div></div><div style="text-align:right;flex-shrink:0"><div class="act-time">${r.time}</div><div class="act-stat ${sc[r.status]}">● ${r.label}</div></div></div>`).join('');
}
function buildPayments(){
  document.getElementById('payList').innerHTML=PAYS.map(p=>`<div class="pay-row"><div class="pay-ico" style="background:${p.color}18"><span style="font-size:16px">${p.pct===100?'✅':'💳'}</span></div><div style="flex:1"><div class="pay-name">${p.name}</div><div class="pay-plan">${p.plan} — ${p.amount} FCFA</div><div class="pay-bar"><div class="pay-fill" style="width:${p.pct}%;background:${p.pct===100?'#27ae60':'#e67e22'}"></div></div></div><div style="text-align:right;flex-shrink:0"><div style="font-size:13px;font-weight:700;color:var(--txt)">${p.pct}%</div><div style="font-size:10px;color:var(--txt3)">${p.pct===100?'✓ Soldé':'Reste: '+p.due+' F'}</div></div></div>`).join('');
  document.getElementById('paidAmt').textContent='2 115 000 F';document.getElementById('pendAmt').textContent='335 000 F';
  setTimeout(()=>animateRing(73),500);
}
function animateRing(pct){const circ=2*Math.PI*50,offset=circ*(1-pct/100),ring=document.getElementById('ringFill'),lbl=document.getElementById('ringPct');if(!ring)return;ring.style.strokeDasharray=circ;ring.style.strokeDashoffset=circ;setTimeout(()=>ring.style.strokeDashoffset=offset,100);let c=0;const iv=setInterval(()=>{c=Math.min(c+2,pct);if(lbl)lbl.textContent=c+'%';if(c>=pct)clearInterval(iv)},14)}
function buildMentors(){
  document.getElementById('mentorsGrid').innerHTML=MENTORS.map(m=>`<div class="mentor-card" onclick="toast('Demande envoyée à ${m.name} !')"><div class="m-ava" style="background:${m.color}">${m.initials}</div><div class="m-name">${m.name}</div><div class="m-mod">${m.module}</div><div class="m-stars">${m.rating}</div><div class="m-tag">Mention TB</div><div class="m-cnt">${m.sessions} sessions</div></div>`).join('');
}
function buildTimetable(){
  const d=TT_DATA;let html='<div class="tt-grid" style="min-width:500px"><div style="background:transparent"></div>';
  d.days.forEach(day=>html+=`<div class="tt-day">${day}</div>`);
  d.rows.forEach((time,ri)=>{html+=`<div class="tt-time">${time}</div>`;d.days.forEach((day,di)=>{const cell=d.slots[ri][di],isEmpty=cell==='Libre',isBreak=cell==='—',isMine=cell.includes('★');const cls=isEmpty?'':isMine?'mine':isBreak?'':'booked';const label=isBreak?'':isEmpty?'<i class="bi bi-plus" style="opacity:.3;font-size:11px"></i>':cell.replace('
','<br>');html+=`<div class="tt-slot ${cls}" onclick="${isEmpty?`toast('Créneau réservé !')`:`toast('${cell.replace('
',' — ')}')`}"><span style="text-align:center;line-height:1.4;font-size:10.5px">${label}</span></div>`})});
  html+='</div>';document.getElementById('timetable').innerHTML=html;
}
function buildGrades(){
  document.getElementById('gradesList').innerHTML=GRADES.map(g=>{const color=g.note>=16?'#27ae60':g.note>=14?'#326ea9':g.note>=12?'#e67e22':'#C41E3A';const pc=g.rank==='TB'?'badge-green':g.rank==='B'?'badge-blue':'badge-orange';return`<div class="grade-row"><div><div class="g-name">${g.module}</div><div class="g-coeff">Coeff. ${g.coeff}</div></div><div style="text-align:right"><div class="g-val" style="color:${color}">${g.note}/20</div><span class="badge ${pc}">${g.rank}</span></div></div>`}).join('');
}
function buildNotifs(){
  document.getElementById('notifsList').innerHTML=NOTIFS.map((n,i)=>`<div class="notif-row ${n.unread?'unread':''}" id="notif${i}" onclick="markRead(${i})"><div class="notif-ico">${n.ico}</div><div style="flex:1"><div class="notif-title">${n.title}</div><div class="notif-body">${n.body}</div><div class="notif-time">${n.time}</div></div>${n.unread?'<div class="notif-dot"></div>':''}</div>`).join('');
}
function markRead(i){const el=document.getElementById('notif'+i);if(!el)return;el.classList.remove('unread');const d=el.querySelector('.notif-dot');if(d)d.remove()}
function markAllRead(){NOTIFS.forEach((_,i)=>markRead(i));toast('✓ Toutes les notifications lues')}
function buildStudentTable(){
  const statusBadge={active:'badge-green',warning:'badge-orange',suspended:'badge-red'};
  const statusLabel={active:'Actif',warning:'Avertissement',suspended:'Suspendu'};
  document.getElementById('studentTable').innerHTML=STUDENTS.map(s=>`<tr><td><div style="display:flex;align-items:center;gap:8px"><span class="avatar-sm" style="background:#326ea9;font-size:10px">${s.name.split(' ').map(n=>n[0]).join('').slice(0,2)}</span>${s.name}</div></td><td><span class="badge badge-blue">${s.level}</span></td><td>${s.field}</td><td><div style="display:flex;align-items:center;gap:6px"><div class="pbar" style="width:60px"><div class="pbar-fill" style="width:${s.pay}%;background:${s.pay===100?'#27ae60':s.pay>60?'#e67e22':'#C41E3A'}"></div></div>${s.pay}%</div></td><td style="font-weight:700;color:${s.avg>=16?'#27ae60':s.avg>=12?'#326ea9':'#C41E3A'}">${s.avg}/20</td><td><span class="badge ${statusBadge[s.status]}">${statusLabel[s.status]}</span></td><td><div style="display:flex;gap:5px"><button class="btn-outline btn-sm" onclick="toast('Profil: ${s.name}')"><i class="bi bi-eye"></i></button><button class="btn-outline btn-sm" onclick="toast('Modifier: ${s.name}')"><i class="bi bi-pencil"></i></button></div></td></tr>`).join('');
}
function buildUserTable(){
  const roleBadge={student:'badge-blue','Super Admin':'badge-red',Admin:'badge-orange',Enseignant:'badge-teal',Étudiant:'badge-blue'};
  document.getElementById('userTable').innerHTML=USERS.map(u=>`<tr><td><div style="display:flex;align-items:center;gap:8px"><span class="avatar-sm" style="background:#326ea9;font-size:10px">${u.name.split(' ').map(n=>n[0]).join('').slice(0,2)}</span>${u.name}</div></td><td style="color:var(--txt2)">${u.email}</td><td><span class="badge ${roleBadge[u.role]||'badge-blue'}">${u.role}</span></td><td>${u.entity}</td><td style="color:var(--txt2)">${u.last}</td><td><span class="badge badge-green">Actif</span></td><td><div style="display:flex;gap:5px"><button class="btn-outline btn-sm" onclick="toast('Profil: ${u.name}')"><i class="bi bi-eye"></i></button><button class="btn-outline btn-sm" onclick="toast('Modifier: ${u.name}')"><i class="bi bi-pencil"></i></button><button class="btn-danger btn-sm" onclick="toast('Compte suspendu')"><i class="bi bi-lock"></i></button></div></td></tr>`).join('');
}
function buildReservationTable(){
  const statusBadge={confirmed:'badge-green',pending:'badge-orange',cancelled:'badge-red'};
  const statusLabel={confirmed:'Confirmé',pending:'En attente',cancelled:'Annulé'};
  document.getElementById('reservationTable').innerHTML=RESERVATIONS.map(r=>`<tr><td style="font-weight:600">${r.room}</td><td>${r.user}</td><td>${r.date} · ${r.time}</td><td>${r.reason}</td><td>${r.cap}</td><td><span class="badge ${statusBadge[r.status]}">${statusLabel[r.status]}</span></td><td><div style="display:flex;gap:5px"><button class="btn-outline btn-sm" onclick="toast('Modifier réservation')"><i class="bi bi-pencil"></i></button><button class="btn-danger btn-sm" onclick="toast('Réservation annulée')"><i class="bi bi-x"></i></button></div></td></tr>`).join('');
}
function buildAuditTable(){
  document.getElementById('auditTable').innerHTML=AUDIT_LOGS.map(l=>`<tr><td style="font-size:11px;color:var(--txt2);white-space:nowrap">${l.time}</td><td>${l.user}</td><td>${l.action}</td><td style="font-family:monospace;font-size:11px;color:var(--txt2)">${l.ip}</td><td><span class="badge ${l.status==='ok'?'badge-green':'badge-red'}">${l.status==='ok'?'✓ Succès':'✗ Échec'}</span></td></tr>`).join('');
}
function buildReportsList(){
  document.getElementById('reportsList').innerHTML=REPORTS_LIST.map(r=>`<div class="report-row" onclick="toast('📊 Génération: ${r.name}')"><div class="report-icon" style="background:${r.color}18;color:${r.color}"><i class="${r.ico}"></i></div><div style="flex:1"><div class="report-name">${r.name}</div><div class="report-desc">${r.desc}</div></div><button class="btn-primary btn-sm" onclick="event.stopPropagation();toast('📥 Export: ${r.name}')"><i class="bi bi-download"></i>Générer</button></div>`).join('');
}
function buildSettings(){
  document.getElementById('settingsGrid').innerHTML=SETTINGS_CARDS.map(s=>`<div class="setting-card" onclick="toast('⚙️ Ouverture: ${s.title}')"><div class="setting-ico" style="background:${s.color}18"><span style="font-size:20px">${s.ico}</span></div><div class="setting-title">${s.title}</div><div class="setting-desc">${s.desc}</div></div>`).join('');
}

/* ══ TOAST ══ */
let toastTimer;
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3000)}
</script>
</body>
</html>
