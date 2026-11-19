<?php
/**
 * EduConnect — parent_notifications.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Notifications Parent
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

if ($_SERVER["REQUEST_METHOD"]==="POST") {
    if (isset($_POST["mark_all"])) {
        db_exec($conn,"UPDATE notifications SET lu=1,lu_le=NOW() WHERE user_id=?","i",[$uid]);
    } elseif (isset($_POST["notif_id"])) {
        db_exec($conn,"UPDATE notifications SET lu=1,lu_le=NOW() WHERE id=? AND user_id=?",
                "ii",[(int)$_POST["notif_id"],$uid]);
    }
}

$filtre_parent = $_GET["type"] ?? "toutes";
$where_p_notif = match($filtre_parent){
    "paiements" => "AND type='paiement'",
    "scolaire"  => "AND type IN ('note','bulletin','examen')",
    "admin"     => "AND type='systeme'",
    default     => ""
};
$notifications_parent = db_query($conn,
    "SELECT id,type,titre,corps,lien,lu,canal,priorite,envoye_le
     FROM notifications WHERE user_id=? $where_p_notif
     ORDER BY envoye_le DESC LIMIT 50",
    "i",[$uid]
);
$nb_notifs = count(array_filter($notifications_parent,fn($n)=>!$n["lu"]));



$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Notifications</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--gold-dk:#b8892e;--gold-lt:#f0c45a;--gold-dim:rgba(212,168,67,.15);--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--gold),var(--gold-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(212,168,67,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--gold-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--gold-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}.sidebar.collapsed .nav-badge{opacity:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--gold-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--gold-dk);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}.tab-nav::-webkit-scrollbar{display:none}.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}.tab-btn:hover{background:var(--surface);color:var(--txt)}.tab-btn.active{background:var(--gold);color:#fff;box-shadow:0 1px 4px rgba(212,168,67,.4)}.tab-btn i{font-size:13px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer}.tb-icon:hover{background:var(--gold-dim);border-color:var(--gold);color:var(--gold-dk)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--gold);background:var(--gold-dim)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--gold),var(--gold-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
/* content max-width:600px centered — exact match to etudiant_notifications.php */
.content{padding:18px 20px;flex:1;overflow-y:auto;max-width:640px;margin:0 auto;width:100%}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease;flex-wrap:wrap;gap:10px}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--gold-dk)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
/* notif-item — exact structure as etudiant_notifications.php, gold accent for unread */
.notif-item{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:14px;margin-bottom:12px;animation:fadeUp .3s ease;transition:all var(--tr);cursor:pointer;border-left:3px solid transparent}
.notif-item:hover{box-shadow:var(--sh-md);transform:translateX(4px)}
.notif-item.unread{background:var(--gold-dim);border-color:var(--gold);border-left-color:var(--gold-dk)}
.notif-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px}
.notif-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.notif-icon.gold{background:var(--gold-dim);color:var(--gold-dk)}
.notif-icon.green{background:var(--green-dim);color:var(--green)}
.notif-icon.orange{background:var(--orange-dim);color:var(--orange)}
.notif-icon.red{background:var(--red-dim);color:var(--red)}
.notif-icon.blue{background:var(--blue-dim);color:var(--blue)}
.notif-icon.purple{background:var(--purple-dim);color:var(--purple)}
.notif-content{flex:1}
.notif-title{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:2px;display:flex;align-items:center;gap:6px}
.notif-desc{font-size:11px;color:var(--txt3);line-height:1.5}
.notif-time{font-size:10px;color:var(--txt3);margin-top:6px;display:flex;align-items:center;gap:5px}
.notif-new{font-size:9px;font-weight:700;padding:2px 6px;border-radius:3px;background:var(--gold);color:#fff}
/* Day section separator */
.day-sep{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--txt3);padding:4px 0 10px;display:flex;align-items:center;gap:8px;margin-top:4px}
.day-sep::after{content:'';flex:1;height:1px;background:var(--border)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-green{background:var(--green-dim);color:var(--green)}.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-gold{background:var(--gold-dim);color:var(--gold-dk)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--gold)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px;max-width:100%}}
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
    <button class="nav-item active"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span></button>
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
    <div class="topbar-brand"><i class="bi bi-bell-fill"></i>Notifications</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="setTab('toutes',this)"><i class="bi bi-list-ul"></i>Toutes <span id="unreadCount" style="background:var(--red);color:#fff;font-size:9px;padding:1px 5px;border-radius:10px;margin-left:2px">3</span></button>
      <button class="tab-btn" onclick="setTab('nonlues',this)"><i class="bi bi-envelope"></i>Non lues</button>
      <button class="tab-btn" onclick="setTab('paiements',this)"><i class="bi bi-credit-card"></i>Paiements</button>
      <button class="tab-btn" onclick="setTab('scolaire',this)"><i class="bi bi-mortarboard"></i>Scolaire</button>
    </div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="markAllRead()" title="Tout marquer lu"><i class="bi bi-check-all"></i></button>
      <div class="tb-user" onclick="location.href='parent_profil.php'"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-bell"></i><div><h1>Notifications</h1><p>Restez informé de l'actualité académique de Marie Sow</p></div></div>
    </div>

    <div class="day-sep">Aujourd'hui</div>

    <!-- 1 — Paiement confirmé — UNREAD -->
    <div class="notif-item unread" id="n1" onclick="markRead('n1','Paiement Q1 2026 confirmé')">
      <div class="notif-header">
        <div class="notif-icon green"><i class="bi bi-check-circle-fill"></i></div>
        <div class="notif-content">
          <div class="notif-title">Paiement Q1 2026 confirmé <span class="notif-new">NOUVEAU</span></div>
          <div class="notif-desc">Votre paiement de 125 000 FCFA a bien été reçu par l'UCAO Saint-Michel. Le reçu officiel #REC-2026-0341 avec QR code de vérification est disponible dans votre espace Paiements.</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Aujourd'hui à 09h15 · <a href="parent_paiements.php" style="color:var(--gold-dk);font-weight:600">Voir les paiements →</a></div>
        </div>
      </div>
    </div>

    <!-- 2 — Bulletin disponible — UNREAD -->
    <div class="notif-item unread" id="n2" onclick="markRead('n2','Bulletin Semestre 2 disponible')">
      <div class="notif-header">
        <div class="notif-icon blue"><i class="bi bi-file-earmark-text"></i></div>
        <div class="notif-content">
          <div class="notif-title">Bulletin Semestre 2 disponible <span class="notif-new">NOUVEAU</span></div>
          <div class="notif-desc">Le bulletin officiel de Marie Sow pour le Semestre 2 2025/2026 est disponible. Téléchargez-le gratuitement depuis votre espace — évitez les frais de réémission de 5 000 FCFA.</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Aujourd'hui à 08h30 · <a href="parent_notes.php" style="color:var(--gold-dk);font-weight:600">Télécharger →</a></div>
        </div>
      </div>
    </div>

    <!-- 3 — Note publiée — UNREAD -->
    <div class="notif-item unread" id="n3" onclick="markRead('n3','Note Droit Civil publiée')">
      <div class="notif-header">
        <div class="notif-icon gold"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="notif-content">
          <div class="notif-title">Note publiée — Droit Civil <span class="notif-new">NOUVEAU</span></div>
          <div class="notif-desc">Le Prof. Martin a publié la note de Droit Civil. Marie a obtenu 17.5/20 — Mention Très Bien. Excellent résultat, elle dépasse l'objectif de 2.5 points !</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Aujourd'hui à 07h45 · <a href="parent_notes.php" style="color:var(--gold-dk);font-weight:600">Voir les notes →</a></div>
        </div>
      </div>
    </div>

    <div class="day-sep">Hier</div>

    <!-- 4 — Mentorat confirmé -->
    <div class="notif-item" onclick="toast('Session mentorat : Mer 2 Avr 18h00')">
      <div class="notif-header">
        <div class="notif-icon purple"><i class="bi bi-star-fill"></i></div>
        <div class="notif-content">
          <div class="notif-title">Session de mentorat confirmée</div>
          <div class="notif-desc">Fatou Diop confirme la session de Droit Civil. Mercredi 2 Avril à 18h00 en visioconférence. Marie recevra le lien 30 minutes avant la session.</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Hier à 16h00</div>
        </div>
      </div>
    </div>

    <!-- 5 — Rappel examen -->
    <div class="notif-item" onclick="toast('Examen Maths : Ven 4 Avr — Amphi B — 14h00')">
      <div class="notif-header">
        <div class="notif-icon orange"><i class="bi bi-alarm"></i></div>
        <div class="notif-content">
          <div class="notif-title">Rappel — Contrôle de Mathématiques dans 2 jours</div>
          <div class="notif-desc">Contrôle continu de Mathématiques vendredi 4 Avril à 14h00 dans l'Amphi B. Il est recommandé à Marie d'arriver 15 minutes avant. Prof. Sall.</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Hier à 09h00</div>
        </div>
      </div>
    </div>

    <div class="day-sep">Cette semaine</div>

    <!-- 6 — Présence -->
    <div class="notif-item" onclick="toast('Cours Informatique — Mardi 01/04')">
      <div class="notif-header">
        <div class="notif-icon green"><i class="bi bi-calendar-check"></i></div>
        <div class="notif-content">
          <div class="notif-title">Présence confirmée — Informatique</div>
          <div class="notif-desc">Marie était présente au cours d'Informatique du mardi 01/04. Assiduité globale ce semestre : 94%.</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Mardi à 12h30</div>
        </div>
      </div>
    </div>

    <!-- 7 — Réservation salle -->
    <div class="notif-item" onclick="toast('Salle B2 réservée — Jeu 3 Avr 14h–16h')">
      <div class="notif-header">
        <div class="notif-icon blue"><i class="bi bi-building-check"></i></div>
        <div class="notif-content">
          <div class="notif-title">Réservation de salle confirmée</div>
          <div class="notif-desc">Marie a réservé la Salle B2 pour une session de révision en groupe le jeudi 3 Avril de 14h à 16h. 4 camarades participent.</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Lundi à 11h15</div>
        </div>
      </div>
    </div>

    <!-- 8 — Message reçu -->
    <div class="notif-item" onclick="location.href='parent_messages.php'">
      <div class="notif-header">
        <div class="notif-icon blue"><i class="bi bi-chat-dots-fill"></i></div>
        <div class="notif-content">
          <div class="notif-title">Message de l'Administration UCAO</div>
          <div class="notif-desc">L'administration a répondu à votre question concernant la date limite du paiement Q2 2026 : avant le 15 Avril 2026.</div>
          <div class="notif-time"><i class="bi bi-clock"></i>Lundi à 10h00 · <a href="parent_messages.php" style="color:var(--gold-dk);font-weight:600">Voir le message →</a></div>
        </div>
      </div>
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

let unread = 3;
function markRead(id, label) {
  const el = document.getElementById(id);
  if (!el) return;
  if (el.classList.contains('unread')) {
    el.classList.remove('unread');
    const badge = el.querySelector('.notif-new');
    if (badge) badge.remove();
    unread = Math.max(0, unread - 1);
    document.getElementById('unreadCount').textContent = unread;
    if (unread === 0) document.getElementById('unreadCount').style.display = 'none';
    toast('✓ Lu : ' + label);
  }
}

function markAllRead() {
  ['n1','n2','n3'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.classList.remove('unread');
      const badge = el.querySelector('.notif-new');
      if (badge) badge.remove();
    }
  });
  unread = 0;
  const cnt = document.getElementById('unreadCount');
  if (cnt) cnt.style.display = 'none';
  toast('✅ Toutes les notifications marquées comme lues');
}

let toastTimer;
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3200)}
</script>
</body>
</html>
