<?php
/**
 * EduConnect — parent_messages.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Messages Parent (Firebase)
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

// Contacts : enseignants de la classe de l'enfant + admin
$enfant_msg = db_row($conn,
    "SELECT e.classe_id FROM etudiants e WHERE e.parent_id=? LIMIT 1","i",[$uid]);
$classe_id_msg = (int)($enfant_msg["classe_id"] ?? 0);

$contacts_parent = db_query($conn,
    "SELECT DISTINCT u.id, u.prenom, u.nom, u.role
     FROM users u
     WHERE u.statut='actif' AND u.id!=?
       AND (
         u.role IN ('admin','chef_admin')
         OR (u.role='enseignant' AND u.id IN (
             SELECT me.enseignant_id FROM module_enseignants me
             JOIN module_classes mc ON mc.module_id=me.module_id
             WHERE mc.classe_id=?
         ))
       )
     ORDER BY u.role, u.nom, u.prenom",
    "ii",[$uid,$classe_id_msg]
);
$firebase_user_data = json_encode([
    'uid'       => $uid,
    'nom'       => $user["prenom"]." ".$user["nom"],
    'role'      => $user["role"],
    'initiales' => $user["initiales"],
]);



$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Messages</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--gold-dk:#b8892e;--gold-lt:#f0c45a;--gold-dim:rgba(212,168,67,.15);
  --purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
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
@keyframes msgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

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

/* MAIN + TOPBAR */
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

/* CONTENT — full height chat layout */
.content{padding:0;flex:1;display:flex;overflow:hidden;height:calc(100vh - var(--topbar-h))}

/* THREAD LIST */
.thread-list{width:300px;min-width:260px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0}
.thread-header{padding:12px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.thread-header-title{font-size:13px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:6px}
.thread-header-title i{color:var(--gold-dk)}
.thread-search{padding:10px 12px;border-bottom:1px solid var(--border);flex-shrink:0}
.thread-search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border)}
.thread-search-field:focus-within{border-color:var(--gold)}
.thread-search-field i{color:var(--txt3);font-size:13px;flex-shrink:0}
.thread-search-field input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}
.thread-search-field input::placeholder{color:var(--txt3)}
.thread-scroll{flex:1;overflow-y:auto}

.thread-item{padding:12px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background var(--tr);display:flex;gap:10px;align-items:flex-start}
.thread-item:hover{background:var(--surface2)}
.thread-item.active{background:var(--gold-dim);border-left:3px solid var(--gold)}
.thread-item.unread{background:rgba(50,110,169,.04)}
.thread-ava{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0}
.thread-name{font-size:12.5px;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:5px}
.thread-preview{font-size:11px;color:var(--txt3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.thread-time{font-size:10px;color:var(--txt3);white-space:nowrap;margin-top:2px}
.unread-dot{width:7px;height:7px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:4px}

/* CHAT PANEL */
.chat-panel{flex:1;display:flex;flex-direction:column;overflow:hidden}

.chat-header{padding:12px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;background:var(--surface);flex-shrink:0}
.chat-header-ava{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0}
.chat-header-name{font-size:13px;font-weight:700;color:var(--txt)}
.chat-header-status{font-size:11px;color:var(--txt3);margin-top:1px}
.chat-header-actions{margin-left:auto;display:flex;gap:6px}
.chat-icon-btn{width:30px;height:30px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:13px;cursor:pointer;transition:all var(--tr)}
.chat-icon-btn:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}

/* Message area */
.chat-messages{flex:1;padding:18px 20px;overflow-y:auto;display:flex;flex-direction:column;gap:14px;background:var(--bg)}

.msg-group{display:flex;flex-direction:column;gap:4px;max-width:70%}
.msg-group.me{align-self:flex-end}
.msg-group.them{align-self:flex-start}

.msg-sender{font-size:10px;font-weight:600;color:var(--txt3);margin-bottom:2px;padding:0 4px}
.msg-group.me .msg-sender{text-align:right}

.msg-bubble{padding:10px 14px;border-radius:10px;font-size:13px;line-height:1.5;position:relative;animation:msgIn .2s ease both}
.msg-group.them .msg-bubble{background:var(--surface);border:1px solid var(--border);border-bottom-left-radius:3px;color:var(--txt)}
.msg-group.me .msg-bubble{background:var(--gold);color:#fff;border-bottom-right-radius:3px}

.msg-meta{font-size:10px;color:var(--txt3);padding:0 4px;display:flex;align-items:center;gap:4px}
.msg-group.me .msg-meta{justify-content:flex-end}
.msg-read{color:var(--green);font-size:11px}

/* Date separator */
.date-sep{text-align:center;font-size:10px;font-weight:700;color:var(--txt3);padding:6px 0;display:flex;align-items:center;gap:8px}
.date-sep::before,.date-sep::after{content:'';flex:1;height:1px;background:var(--border)}

/* Typing indicator */
.typing-indicator{display:none;align-self:flex-start;padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:10px;border-bottom-left-radius:3px;gap:4px}
.typing-indicator.show{display:flex}
.typing-dot{width:6px;height:6px;border-radius:50%;background:var(--txt3);animation:typingBounce 1s infinite}
.typing-dot:nth-child(2){animation-delay:.15s}
.typing-dot:nth-child(3){animation-delay:.3s}
@keyframes typingBounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-5px)}}

/* Compose area */
.chat-compose{padding:12px 16px;border-top:1px solid var(--border);background:var(--surface);display:flex;gap:8px;align-items:flex-end;flex-shrink:0}
.compose-input{flex:1;padding:8px 12px;border-radius:var(--r-sm);border:1px solid var(--border);font-family:'Ubuntu',sans-serif;font-size:13px;color:var(--txt);background:var(--surface2);resize:none;max-height:120px;outline:none;transition:border-color var(--tr)}
.compose-input:focus{border-color:var(--gold);background:var(--surface)}
.compose-input::placeholder{color:var(--txt3)}
.compose-btn{width:38px;height:38px;border-radius:var(--r-sm);background:var(--gold);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;transition:all var(--tr);flex-shrink:0;border:none}
.compose-btn:hover{background:var(--gold-dk);transform:translateY(-2px)}
.compose-actions{display:flex;gap:4px;align-items:center}
.compose-action{width:30px;height:30px;border-radius:var(--r-sm);background:none;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:14px;cursor:pointer;transition:all var(--tr)}
.compose-action:hover{background:var(--surface2);color:var(--gold-dk);border-color:var(--gold)}

/* BADGE */
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-red{background:var(--red-dim);color:var(--red)}

/* NEW MSG MODAL */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}
.modal.show{display:flex;animation:fadeUp .25s ease}
.modal-content{background:var(--surface);border-radius:var(--r);padding:22px;width:440px;max-width:92%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:7px}
.modal-title i{color:var(--gold-dk)}
.modal-close{width:26px;height:26px;border-radius:4px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);cursor:pointer;font-size:14px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red);border-color:var(--red-dim)}
.form-group{margin-bottom:12px}
.form-label{display:block;font-size:11.5px;font-weight:600;color:var(--txt);margin-bottom:4px}
.form-input{width:100%;padding:8px 10px;border-radius:var(--r-sm);border:1px solid var(--border);font-family:'Ubuntu',sans-serif;font-size:12.5px;color:var(--txt);background:var(--surface2);transition:all var(--tr)}
.form-input:focus{outline:none;border-color:var(--gold);background:var(--surface)}
.form-textarea{resize:vertical;min-height:80px}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid var(--border)}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}
.btn-primary{background:var(--gold);color:#fff}.btn-primary:hover{background:var(--gold-dk)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{border-color:var(--gold);color:var(--gold-dk)}

/* TOAST */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--gold)}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.thread-list{width:100%;position:absolute;z-index:10;height:100%}.thread-list.hidden{display:none}}
</style>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
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
    <button class="nav-item <?= $current_page === 'parent_etudiants.php' ? 'active' : '' ?>" onclick="location.href='parent_etudiants.php'"><i class="bi bi-person-fill"></i><span class="nav-item-txt">Mon Enfant</span></button>
    <button class="nav-item <?= $current_page === 'parent_paiements.php' ? 'active' : '' ?>" onclick="location.href='parent_paiements.php'"><i class="bi bi-credit-card"></i><span class="nav-item-txt">Paiements</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item <?= $current_page === 'parent_notes.php' ? 'active' : '' ?>" onclick="location.href='parent_notes.php'"><i class="bi bi-graph-up"></i><span class="nav-item-txt">Notes & Bulletins</span></button>
    <div class="nav-section">Suivi</div>
    <button class="nav-item <?= $current_page === 'parent_emploi_du_temps.php' ? 'active' : '' ?>" onclick="location.href='parent_emploi_du_temps.php'"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></button>
    <button class="nav-item active"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span></button>
    <button class="nav-item <?= $current_page === 'parent_notifications.php' ? 'active' : '' ?>" onclick="location.href='parent_notifications.php'"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item <?= $current_page === 'parent_profil.php' ? 'active' : '' ?>" onclick="location.href='parent_profil.php'"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
    <button class="nav-item <?= $current_page === 'parent_parametres.php' ? 'active' : '' ?>" onclick="location.href='parent_parametres.php'"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Parent/parent_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role">Parent · Marie Sow (L2)</div></div></div>
  
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- MAIN -->
<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-chat-dots-fill"></i> Messages</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="setTab('all',this)"><i class="bi bi-chat-dots"></i>Tous <span style="background:var(--red);color:#fff;font-size:9px;padding:1px 5px;border-radius:10px;margin-left:3px">2</span></button>
      <button class="tab-btn" onclick="setTab('admin',this)"><i class="bi bi-building"></i>Administration</button>
      <button class="tab-btn" onclick="setTab('profs',this)"><i class="bi bi-person-badge"></i>Professeurs</button>
      <button class="tab-btn" onclick="setTab('archive',this)"><i class="bi bi-archive"></i>Archivés</button>
    </div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="openNewMsg()" title="Nouveau message"><i class="bi bi-pencil-square"></i></button>
      <button class="tb-icon" onclick="location.href='parent_notifications.php'"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <div class="tb-user" onclick="location.href='parent_profil.php'">
        <div class="tb-ava"><?= $u_init ?></div>
        <div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div>
      </div>
    </div>
  </div>

  <!-- FULL HEIGHT CHAT LAYOUT -->
  <div class="content">

    <!-- Thread list -->
    <div class="thread-list" id="threadList">
      <div class="thread-header">
        <div class="thread-header-title"><i class="bi bi-chat-dots"></i>Conversations</div>
        <button class="chat-icon-btn" onclick="openNewMsg()" title="Nouveau"><i class="bi bi-plus"></i></button>
      </div>
      <div class="thread-search">
        <div class="thread-search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher une conversation…" oninput="filterThreads(this.value)"></div>
      </div>
      <div class="thread-scroll" id="threadScroll">

        <!-- Admin UCAO — unread -->
        <div class="thread-item active unread" id="t-admin" onclick="openThread('admin')">
          <div class="thread-ava" style="background:var(--blue)"><i class="bi bi-building" style="font-size:15px"></i></div>
          <div style="flex:1;min-width:0">
            <div class="thread-name">Administration UCAO <span style="width:7px;height:7px;background:var(--red);border-radius:50%;display:inline-block"></span></div>
            <div class="thread-preview">Votre paiement de 125 000 FCFA a bien été…</div>
            <div class="thread-time">Il y a 5h</div>
          </div>
          <div class="unread-dot"></div>
        </div>

        <!-- Prof. Martin — unread -->
        <div class="thread-item unread" id="t-martin" onclick="openThread('martin')">
          <div class="thread-ava" style="background:var(--green)">PM</div>
          <div style="flex:1;min-width:0">
            <div class="thread-name">Prof. Martin · Droit Civil <span style="width:7px;height:7px;background:var(--red);border-radius:50%;display:inline-block"></span></div>
            <div class="thread-preview">Félicitations ! Marie a obtenu 17.5/20…</div>
            <div class="thread-time">Hier</div>
          </div>
          <div class="unread-dot"></div>
        </div>

        <!-- Prof. Sall -->
        <div class="thread-item" id="t-sall" onclick="openThread('sall')">
          <div class="thread-ava" style="background:var(--gold-dk)">DS</div>
          <div style="flex:1;min-width:0">
            <div class="thread-name">Prof. Sall · Mathématiques</div>
            <div class="thread-preview">Rappel : contrôle vendredi 4 Avril…</div>
            <div class="thread-time">Il y a 2 jours</div>
          </div>
        </div>

        <!-- Fatou Diop (mentor) -->
        <div class="thread-item" id="t-fatou" onclick="openThread('fatou')">
          <div class="thread-ava" style="background:var(--purple)">FD</div>
          <div style="flex:1;min-width:0">
            <div class="thread-name">Fatou Diop · Mentor</div>
            <div class="thread-preview">Session confirmée pour jeudi 14h…</div>
            <div class="thread-time">Il y a 3 jours</div>
          </div>
        </div>

        <!-- Prof. Ndiaye -->
        <div class="thread-item" id="t-ndiaye" onclick="openThread('ndiaye')">
          <div class="thread-ava" style="background:var(--teal, #0891b2)">PN</div>
          <div style="flex:1;min-width:0">
            <div class="thread-name">Prof. Ndiaye · Informatique</div>
            <div class="thread-preview">Le projet de groupe est à remettre…</div>
            <div class="thread-time">Il y a 5 jours</div>
          </div>
        </div>

      </div>
    </div>

    <!-- Chat panel -->
    <div class="chat-panel">

      <div class="chat-header" id="chatHeader">
        <div class="chat-header-ava" id="chatAva" style="background:var(--blue)"><i class="bi bi-building" style="font-size:15px"></i></div>
        <div>
          <div class="chat-header-name" id="chatName">Administration UCAO</div>
          <div class="chat-header-status" id="chatStatus">En ligne · Répond généralement en quelques heures</div>
        </div>
        <div class="chat-header-actions">
          <div class="chat-icon-btn" onclick="toast('📞 Appel non disponible en démo')" title="Appeler"><i class="bi bi-telephone"></i></div>
          <div class="chat-icon-btn" onclick="toast('📎 Pièce jointe')" title="Pièce jointe"><i class="bi bi-paperclip"></i></div>
          <div class="chat-icon-btn" onclick="toast('🔍 Recherche dans la conversation')" title="Rechercher"><i class="bi bi-search"></i></div>
        </div>
      </div>

      <div class="chat-messages" id="chatMessages">

        <div class="date-sep">Aujourd'hui</div>

        <!-- Incoming -->
        <div class="msg-group them">
          <div class="msg-sender">Administration UCAO</div>
          <div class="msg-bubble">Bonjour M. Diallo. Nous confirmons la réception de votre paiement de 125 000 FCFA pour le premier trimestre 2026. Le reçu officiel #REC-2026-0341 est disponible dans votre espace parent.</div>
          <div class="msg-meta">Il y a 5h</div>
        </div>

        <!-- Outgoing -->
        <div class="msg-group me">
          <div class="msg-sender">Vous</div>
          <div class="msg-bubble">Merci pour la confirmation rapide. J'ai bien téléchargé le reçu. Quand sera disponible le bulletin du semestre 1 ?</div>
          <div class="msg-meta">Il y a 4h <i class="bi bi-check2-all msg-read"></i></div>
        </div>

        <!-- Incoming -->
        <div class="msg-group them">
          <div class="msg-sender">Administration UCAO</div>
          <div class="msg-bubble">Bonne nouvelle ! Le bulletin de Marie est déjà disponible dans la section Notes & Bulletins. Vous pouvez l'imprimer gratuitement depuis votre espace — plus besoin de payer les 5 000 FCFA de réémission !</div>
          <div class="msg-meta">Il y a 3h</div>
        </div>

        <!-- Outgoing -->
        <div class="msg-group me">
          <div class="msg-sender">Vous</div>
          <div class="msg-bubble">Parfait, merci ! Je l'ai téléchargé. Concernant le prochain paiement Q2, quelle est la date limite exacte ?</div>
          <div class="msg-meta">Il y a 2h <i class="bi bi-check2-all msg-read"></i></div>
        </div>

        <!-- Incoming -->
        <div class="msg-group them">
          <div class="msg-sender">Administration UCAO</div>
          <div class="msg-bubble">La date limite pour le paiement Q2 2026 est le <strong>15 Avril 2026</strong>. Vous serez rappelé automatiquement 7 jours, 3 jours et 1 jour avant l'échéance. Avez-vous d'autres questions ?</div>
          <div class="msg-meta">Il y a 1h</div>
        </div>

        <!-- Typing indicator -->
        <div class="typing-indicator" id="typingIndicator">
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
          <div class="typing-dot"></div>
        </div>

      </div>

      <!-- Compose -->
      <div class="chat-compose">
        <div class="compose-actions">
          <div class="compose-action" onclick="toast('📎 Pièce jointe')" title="Joindre un fichier"><i class="bi bi-paperclip"></i></div>
          <div class="compose-action" onclick="toast('😊 Emoji')" title="Emoji"><i class="bi bi-emoji-smile"></i></div>
        </div>
        <textarea class="compose-input" id="msgInput" rows="1" placeholder="Votre message…" onkeydown="handleEnter(event)" oninput="autoResize(this)"></textarea>
        <button class="compose-btn" onclick="sendMessage()" title="Envoyer">
          <i class="bi bi-send-fill"></i>
        </button>
      </div>

    </div><!-- /chat-panel -->

  </div><!-- /content -->
</main>

<!-- NEW MESSAGE MODAL -->
<div class="modal" id="newMsgModal">
  <div class="modal-content">
    <div class="modal-header">
      <div class="modal-title"><i class="bi bi-pencil-square"></i>Nouveau message</div>
      <button class="modal-close" onclick="closeModal()"><i class="bi bi-x"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Destinataire *</label>
      <select class="form-input">
        <option value="">Sélectionner…</option>
        <option>Administration UCAO</option>
        <option>Prof. Martin · Droit Civil</option>
        <option>Prof. Sall · Mathématiques</option>
        <option>Prof. Ndiaye · Informatique</option>
        <option>Prof. Ba · Physique</option>
        <option>Prof. Kane · Anglais</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Objet *</label>
      <input type="text" class="form-input" placeholder="Ex: Question sur les notes de Marie…">
    </div>
    <div class="form-group">
      <label class="form-label">Message *</label>
      <textarea class="form-input form-textarea" placeholder="Votre message…"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">Annuler</button>
      <button class="btn btn-primary" onclick="sendNewMsg()"><i class="bi bi-send-fill"></i>Envoyer</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ── Conversations data ── */
const THREADS = {
  admin: {
    name: 'Administration UCAO',
    status: 'En ligne · Répond généralement en quelques heures',
    ava: '<i class="bi bi-building" style="font-size:15px"></i>',
    bg: '#326ea9'
  },
  martin: {
    name: 'Prof. Martin · Droit Civil',
    status: 'Hors ligne · Répond dans la journée',
    ava: 'PM',
    bg: '#27ae60'
  },
  sall: {
    name: 'Prof. Sall · Mathématiques',
    status: 'En ligne',
    ava: 'DS',
    bg: '#b8892e'
  },
  fatou: {
    name: 'Fatou Diop · Mentor Droit Civil',
    status: 'En ligne · Mentor mention TB',
    ava: 'FD',
    bg: '#7C3AED'
  },
  ndiaye: {
    name: 'Prof. Ndiaye · Informatique',
    status: 'Hors ligne',
    ava: 'PN',
    bg: '#0891b2'
  }
};

const THREAD_MSGS = {
  martin: [
    {from:'them', text:'Bonjour M. Diallo. Je souhaitais vous informer que Marie a obtenu d\'excellents résultats lors du contrôle de Droit Civil. Sa note est de 17.5/20, mention Très Bien. Félicitations !', time:'Hier à 14h'},
    {from:'me', text:'Merci infiniment Prof. Martin ! Marie va être ravie. Elle a beaucoup travaillé ce semestre. Y a-t-il des points à améliorer pour le semestre 2 ?', time:'Hier à 16h'},
    {from:'them', text:'Marie maîtrise très bien la partie jurisprudence. Je lui conseille de renforcer sa pratique des cas pratiques en droit des contrats pour les prochaines sessions. Le mentorat avec Fatou Diop lui sera très utile.', time:'Hier à 18h'}
  ],
  sall: [
    {from:'them', text:'Bonjour M. Diallo. Rappel important : un contrôle de Mathématiques aura lieu vendredi 4 Avril à 8h00 dans l\'Amphi B. Marie devrait arriver 15 minutes avant.', time:'Il y a 2 jours'},
    {from:'me', text:'Merci pour le rappel Prof. Sall. Marie prépare bien ce contrôle. Elle a des séances de révision prévues cette semaine.', time:'Il y a 2 jours'}
  ],
  fatou: [
    {from:'them', text:'Bonjour M. Diallo. Je suis Fatou Diop, le mentor de Marie en Droit Civil. Je voulais confirmer notre session de visioconférence pour jeudi 3 Avril à 14h00.', time:'Il y a 3 jours'},
    {from:'me', text:'Parfait, merci Fatou. Marie m\'a parlé de vous en très bien. Je l\'ai prévenue, elle sera là à l\'heure.', time:'Il y a 3 jours'},
    {from:'them', text:'Super ! Nous allons travailler sur les cas pratiques et la jurisprudence. Je lui enverrai le lien de visio 30 minutes avant la session.', time:'Il y a 3 jours'}
  ],
  ndiaye: [
    {from:'them', text:'Bonjour M. Diallo. Marie est inscrite au projet de groupe en Informatique. La date de remise est le 20 Avril. Si vous avez des questions, n\'hésitez pas.', time:'Il y a 5 jours'}
  ]
};

let activeThread = 'admin';

function openThread(id) {
  // Update active thread in list
  document.querySelectorAll('.thread-item').forEach(t => t.classList.remove('active'));
  const el = document.getElementById('t-' + id);
  if (el) { el.classList.add('active'); el.classList.remove('unread'); const dot = el.querySelector('.unread-dot'); if (dot) dot.remove(); }

  activeThread = id;
  const t = THREADS[id];
  if (!t) return;

  // Update header
  document.getElementById('chatName').textContent = t.name;
  document.getElementById('chatStatus').textContent = t.status;
  document.getElementById('chatAva').innerHTML = t.ava;
  document.getElementById('chatAva').style.background = t.bg;

  // Build messages
  const msgs = document.getElementById('chatMessages');
  msgs.innerHTML = '<div class="date-sep">' + (id === 'admin' ? 'Aujourd\'hui' : 'Cette semaine') + '</div>';

  if (id === 'admin') {
    // Keep existing admin messages
    msgs.innerHTML += `
      <div class="msg-group them"><div class="msg-sender">${t.name}</div><div class="msg-bubble">Bonjour M. Diallo. Nous confirmons la réception de votre paiement de 125 000 FCFA pour le premier trimestre 2026. Le reçu officiel #REC-2026-0341 est disponible dans votre espace parent.</div><div class="msg-meta">Il y a 5h</div></div>
      <div class="msg-group me"><div class="msg-sender">Vous</div><div class="msg-bubble">Merci pour la confirmation rapide. J'ai bien téléchargé le reçu. Quand sera disponible le bulletin du semestre 1 ?</div><div class="msg-meta">Il y a 4h <i class="bi bi-check2-all msg-read"></i></div></div>
      <div class="msg-group them"><div class="msg-sender">${t.name}</div><div class="msg-bubble">Bonne nouvelle ! Le bulletin de Marie est déjà disponible dans la section Notes & Bulletins. Vous pouvez l'imprimer gratuitement depuis votre espace !</div><div class="msg-meta">Il y a 3h</div></div>
      <div class="msg-group me"><div class="msg-sender">Vous</div><div class="msg-bubble">Parfait, merci ! Je l'ai téléchargé. Concernant le prochain paiement Q2, quelle est la date limite exacte ?</div><div class="msg-meta">Il y a 2h <i class="bi bi-check2-all msg-read"></i></div></div>
      <div class="msg-group them"><div class="msg-sender">${t.name}</div><div class="msg-bubble">La date limite pour le paiement Q2 2026 est le <strong>15 Avril 2026</strong>. Vous serez rappelé automatiquement 7 jours, 3 jours et 1 jour avant l'échéance.</div><div class="msg-meta">Il y a 1h</div></div>`;
  } else if (THREAD_MSGS[id]) {
    THREAD_MSGS[id].forEach(m => {
      msgs.innerHTML += `<div class="msg-group ${m.from}"><div class="msg-sender">${m.from === 'me' ? 'Vous' : t.name}</div><div class="msg-bubble">${m.text}</div><div class="msg-meta">${m.time}${m.from==='me'?' <i class="bi bi-check2-all msg-read"></i>':''}</div></div>`;
    });
  }

  msgs.innerHTML += '<div class="typing-indicator" id="typingIndicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>';
  msgs.scrollTop = msgs.scrollHeight;
}

function sendMessage() {
  const input = document.getElementById('msgInput');
  const text = input.value.trim();
  if (!text) return;

  const msgs = document.getElementById('chatMessages');
  const typing = document.getElementById('typingIndicator');
  const t = THREADS[activeThread];

  // Add outgoing message
  const div = document.createElement('div');
  div.className = 'msg-group me';
  div.innerHTML = `<div class="msg-sender">Vous</div><div class="msg-bubble">${text}</div><div class="msg-meta">À l'instant <i class="bi bi-check2 msg-read"></i></div>`;
  msgs.insertBefore(div, typing);
  input.value = '';
  input.rows = 1;
  msgs.scrollTop = msgs.scrollHeight;

  // Show typing, then auto-reply
  typing.classList.add('show');
  msgs.scrollTop = msgs.scrollHeight;
  setTimeout(() => {
    typing.classList.remove('show');
    const reply = document.createElement('div');
    reply.className = 'msg-group them';
    reply.innerHTML = `<div class="msg-sender">${t ? t.name : 'Interlocuteur'}</div><div class="msg-bubble">Votre message a bien été transmis. Nous vous répondrons dans les meilleurs délais.</div><div class="msg-meta">À l'instant</div>`;
    msgs.insertBefore(reply, typing);
    msgs.scrollTop = msgs.scrollHeight;
  }, 1800);
}

function handleEnter(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function autoResize(el) {
  el.rows = 1;
  const lines = el.value.split('
').length;
  el.rows = Math.min(Math.max(lines, 1), 5);
}

function filterThreads(val) {
  document.querySelectorAll('.thread-item').forEach(t => {
    t.style.display = t.textContent.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}

function setTab(tab, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  toast('Filtre : ' + tab);
}

function openNewMsg() { document.getElementById('newMsgModal').classList.add('show'); }
function closeModal()  { document.getElementById('newMsgModal').classList.remove('show'); }
function sendNewMsg()  { toast('✉️ Message envoyé !'); closeModal(); }

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

let toastTimer;
function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}
const FIREBASE_USER = <?= $firebase_user_data ?>;
</script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body>
</html>
