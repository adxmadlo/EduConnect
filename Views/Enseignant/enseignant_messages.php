<?php
/**
 * EduConnect — enseignant_messages.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Messages Enseignant (Firebase)
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

// Contacts : étudiants des classes de cet enseignant + autres profs + admin
$contacts_ens = db_query($conn,
    "SELECT DISTINCT u.id, u.prenom, u.nom, u.role,
            COALESCE(c.code,'') AS classe_code
     FROM users u
     LEFT JOIN etudiants e ON e.user_id = u.id
     LEFT JOIN classes c ON c.id = e.classe_id
     WHERE u.statut='actif' AND u.id != ?
       AND (
         u.role IN ('admin','chef_admin','parent')
         OR (u.role = 'etudiant' AND c.id IN (
             SELECT mc.classe_id FROM module_classes mc
             JOIN module_enseignants me ON me.module_id = mc.module_id
             WHERE me.enseignant_id = ?
         ))
       )
     ORDER BY u.role, u.nom, u.prenom",
    "ii",[$uid,$uid]
);

$firebase_user_data = json_encode([
    'uid'      => $uid,
    'nom'      => $user["prenom"]." ".$user["nom"],
    'role'     => $user["role"],
    'initiales'=> $user["initiales"],
]);


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
<title>EduConnect — Messages</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════
   TOKENS
═══════════════════════════════════════ */
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
  --teal:#0891b2;--teal-lt:#06b6d4;--teal-dim:rgba(8,145,178,.12);
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
body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;overflow:hidden}
button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes msgIn{from{opacity:0;transform:translateY(6px) scale(.97)}to{opacity:1;transform:none}}

/* ═══ SIDEBAR ═══ */
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden;flex-shrink:0}
.sidebar.collapsed{width:54px}
.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--teal),var(--teal-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(8,145,178,.5)}
.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}
.logo-text span{color:#7dd3fc}
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
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;white-space:nowrap;position:relative;overflow:hidden}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--teal-lt);border-radius:0 2px 2px 0}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--teal-lt)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--teal),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}

/* ═══ MAIN SHELL ═══ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;height:100vh;transition:margin-left var(--tr);overflow:hidden}
.main.expanded{margin-left:54px}

/* ═══ TOPBAR ═══ */
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 16px;gap:10px;flex-shrink:0;box-shadow:var(--sh);z-index:10}
.topbar-brand{font-size:13px;font-weight:600;color:var(--teal);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative;flex-shrink:0}
.tb-icon:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--teal);background:var(--teal-dim)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--teal),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ 3-COLUMN MESSAGING LAYOUT ═══ */
.msg-shell{
  flex:1;display:grid;
  grid-template-columns:260px 1fr;
  overflow:hidden;
}

/* ── Left panel: conversation list ── */
.conv-panel{
  border-right:1px solid var(--border);
  background:var(--surface);
  display:flex;flex-direction:column;
  overflow:hidden;
}
.conv-topbar{
  padding:10px 12px;
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:8px;
  flex-shrink:0;
  background:var(--surface);
}
.conv-search{
  flex:1;display:flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  transition:border var(--tr);
}
.conv-search:focus-within{border-color:var(--teal);background:#fff}
.conv-search i{color:var(--txt3);font-size:12px;flex-shrink:0}
.conv-search input{background:none;border:none;outline:none;font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);width:100%}
.conv-search input::placeholder{color:var(--txt3)}
.new-conv-btn{
  width:30px;height:30px;border-radius:var(--r-sm);
  background:var(--teal);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:16px;
  transition:all var(--tr);flex-shrink:0;cursor:pointer;
}
.new-conv-btn:hover{background:var(--teal-lt);transform:scale(1.08)}

/* Filter tabs in conv panel */
.conv-filters{
  display:flex;gap:0;border-bottom:1px solid var(--border);
  flex-shrink:0;overflow-x:auto;scrollbar-width:none;
}
.conv-filters::-webkit-scrollbar{display:none}
.cf-btn{
  flex:1;padding:7px 4px;font-size:10.5px;font-weight:600;
  color:var(--txt3);cursor:pointer;border-bottom:2px solid transparent;
  white-space:nowrap;transition:all var(--tr);text-align:center;
  background:var(--surface);
}
.cf-btn:hover{color:var(--teal)}
.cf-btn.active{color:var(--teal);border-bottom-color:var(--teal)}

/* Conversation items */
.conv-list{flex:1;overflow-y:auto}
.conv-item{
  display:flex;align-items:center;gap:10px;
  padding:11px 14px;border-bottom:1px solid var(--border);
  cursor:pointer;transition:background var(--tr);position:relative;
}
.conv-item:hover{background:var(--surface2)}
.conv-item.active{background:var(--teal-dim);border-left:3px solid var(--teal)}
.conv-item.active .conv-name{color:var(--teal)}
.conv-ava{
  width:36px;height:36px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:12px;color:#fff;flex-shrink:0;
  position:relative;
}
.conv-online{
  position:absolute;bottom:-1px;right:-1px;
  width:9px;height:9px;border-radius:50%;
  background:var(--green);border:1.5px solid var(--surface);
}
.conv-meta{flex:1;min-width:0}
.conv-row1{display:flex;align-items:center;justify-content:space-between;margin-bottom:2px}
.conv-name{font-size:12.5px;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1}
.conv-time{font-size:10px;color:var(--txt3);flex-shrink:0;margin-left:6px}
.conv-row2{display:flex;align-items:center;gap:5px}
.conv-preview{font-size:11px;color:var(--txt3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1}
.conv-preview.unread{color:var(--txt);font-weight:600}
.conv-unread-dot{width:7px;height:7px;background:var(--red);border-radius:50%;flex-shrink:0}
.conv-type-chip{
  font-size:8.5px;font-weight:700;padding:1px 5px;
  border-radius:3px;flex-shrink:0;
}

/* ── Right: chat pane ── */
.chat-pane{
  display:flex;flex-direction:column;
  background:var(--bg);
  overflow:hidden;
}

/* Chat header */
.chat-head{
  height:var(--topbar-h);background:var(--surface);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 16px;gap:12px;
  flex-shrink:0;box-shadow:var(--sh);
}
.chat-head-ava{
  width:36px;height:36px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:12px;color:#fff;flex-shrink:0;
}
.chat-head-name{font-size:14px;font-weight:700;color:var(--txt)}
.chat-head-sub{font-size:11px;color:var(--txt3);margin-top:1px}
.chat-head-actions{margin-left:auto;display:flex;gap:6px}
.chat-action-btn{
  width:32px;height:32px;border-radius:var(--r-sm);
  background:var(--surface2);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  color:var(--txt2);font-size:15px;cursor:pointer;transition:all var(--tr);
}
.chat-action-btn:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}

/* Date dividers */
.date-divider{
  display:flex;align-items:center;gap:10px;
  margin:10px 0;animation:fadeIn .3s ease;
}
.date-divider::before,.date-divider::after{content:'';flex:1;height:1px;background:var(--border)}
.date-divider span{font-size:10px;font-weight:600;color:var(--txt3);white-space:nowrap;padding:0 6px}

/* Messages body */
.chat-body{
  flex:1;overflow-y:auto;
  padding:14px 16px;
  display:flex;flex-direction:column;gap:6px;
}

/* Message bubbles */
.msg-row{display:flex;align-items:flex-end;gap:8px;animation:msgIn .2s ease}
.msg-row.sent{justify-content:flex-end}
.msg-row-ava{
  width:28px;height:28px;border-radius:6px;
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:10px;color:#fff;flex-shrink:0;
}
.bubble{
  max-width:66%;
  padding:9px 13px;
  border-radius:14px;
  font-size:12.5px;line-height:1.5;
  position:relative;
  word-break:break-word;
}
.bubble.received{
  background:var(--surface);
  border:1px solid var(--border);
  color:var(--txt);
  border-bottom-left-radius:4px;
  box-shadow:var(--sh);
}
.bubble.sent{
  background:linear-gradient(135deg,var(--teal),var(--teal-lt));
  color:#fff;
  border-bottom-right-radius:4px;
}
.bubble-time{
  font-size:9px;margin-top:4px;
  display:block;text-align:right;
}
.bubble.received .bubble-time{color:var(--txt3)}
.bubble.sent .bubble-time{color:rgba(255,255,255,.7)}

/* Attachment bubble */
.attach-bubble{
  display:flex;align-items:center;gap:9px;
  padding:9px 12px;border-radius:10px;
  background:var(--surface);border:1px solid var(--border);
  box-shadow:var(--sh);max-width:240px;cursor:pointer;
  transition:all var(--tr);
}
.attach-bubble:hover{border-color:var(--teal);box-shadow:var(--sh-md)}
.attach-ico{
  width:34px;height:34px;border-radius:7px;
  display:flex;align-items:center;justify-content:center;
  font-size:17px;flex-shrink:0;
}
.attach-name{font-size:11.5px;font-weight:600;color:var(--txt);margin-bottom:2px}
.attach-size{font-size:10px;color:var(--txt3)}
/* sent attachment */
.msg-row.sent .attach-bubble{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.3)}
.msg-row.sent .attach-name,.msg-row.sent .attach-size{color:#fff}

/* System message */
.sys-msg{
  text-align:center;font-size:10.5px;color:var(--txt3);
  background:var(--surface2);border-radius:20px;padding:4px 12px;
  margin:4px auto;display:inline-block;align-self:center;
}

/* ── Compose bar ── */
.compose-bar{
  padding:10px 12px;background:var(--surface);
  border-top:1px solid var(--border);flex-shrink:0;
}
.compose-tools{display:flex;align-items:center;gap:6px;margin-bottom:8px}
.compose-tool-btn{
  display:flex;align-items:center;gap:5px;
  padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;
  cursor:pointer;transition:all var(--tr);
  background:var(--surface2);border:1px solid var(--border);color:var(--txt2);
}
.compose-tool-btn:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal)}
.compose-tool-btn i{font-size:13px}
.compose-row{display:flex;align-items:center;gap:8px}
.compose-input{
  flex:1;min-height:38px;max-height:120px;
  padding:9px 13px;
  border:1.5px solid var(--border2);border-radius:20px;
  font-family:'Ubuntu',sans-serif;font-size:13px;color:var(--txt);
  background:var(--surface2);outline:none;resize:none;
  transition:all var(--tr);line-height:1.4;overflow-y:auto;
}
.compose-input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px var(--teal-dim)}
.compose-input:empty::before{content:attr(data-placeholder);color:var(--txt3)}
.send-btn{
  width:38px;height:38px;border-radius:50%;
  background:linear-gradient(135deg,var(--teal),var(--teal-lt));
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-size:16px;cursor:pointer;transition:all var(--tr);flex-shrink:0;
  box-shadow:0 2px 8px rgba(8,145,178,.35);
}
.send-btn:hover{transform:scale(1.1);box-shadow:0 4px 14px rgba(8,145,178,.5)}

/* ── Empty state ── */
.chat-empty{
  flex:1;display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:12px;color:var(--txt3);
  animation:fadeIn .4s ease;
}
.chat-empty i{font-size:52px;color:var(--border2)}
.chat-empty p{font-size:14px;font-weight:600;color:var(--txt2)}
.chat-empty small{font-size:12px}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600}
.badge-teal  {background:var(--teal-dim);  color:var(--teal)}
.badge-blue  {background:var(--blue-dim);  color:var(--blue)}
.badge-green {background:var(--green-dim); color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-purple{background:var(--purple-dim);color:var(--purple)}
.badge-red   {background:var(--red-dim);   color:var(--red)}

/* ═══ MODALS ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:600;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:14px;padding:24px;width:480px;max-width:100%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease;max-height:88vh;overflow-y:auto}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:13px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:15px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red)}
.form-field{margin-bottom:13px}
.form-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px;display:flex;align-items:center;gap:5px}
.form-label i{color:var(--teal);font-size:12px}
.form-input{width:100%;padding:9px 12px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px;color:var(--txt);background:var(--surface);transition:border var(--tr)}
.form-input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-dim)}
.form-textarea{resize:vertical;min-height:90px}

/* Recipient chips */
.recipient-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
.r-chip{display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;background:var(--teal-dim);color:var(--teal);border:1px solid rgba(8,145,178,.25)}
.r-chip button{width:14px;height:14px;border-radius:50%;background:rgba(8,145,178,.3);display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--teal);cursor:pointer;transition:background var(--tr)}
.r-chip button:hover{background:var(--red);color:#fff}

/* Group selector tiles */
.group-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.group-tile{padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--r-sm);cursor:pointer;transition:all var(--tr);background:var(--surface2)}
.group-tile:hover{border-color:var(--teal);background:var(--teal-dim)}
.group-tile.selected{border-color:var(--teal);background:var(--teal-dim)}
.group-tile-name{font-size:12px;font-weight:700;color:var(--txt);margin-bottom:2px}
.group-tile-meta{font-size:10px;color:var(--txt3)}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}
.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}
.btn-primary:hover{background:var(--teal-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--teal);color:var(--teal)}
.btn-sm{padding:5px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

/* ═══ RESPONSIVE ═══ */
@media(max-width:900px){.msg-shell{grid-template-columns:220px 1fr}}
@media(max-width:700px){
  .sidebar{transform:translateX(-100%)}
  .main{margin-left:0}
  .conv-panel{display:none}
  .msg-shell{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-person-video3"></i></div>
    <div>
      <div class="logo-text">Edu<span>Connect</span></div>
      <div style="font-size:9px;color:rgba(255,255,255,.4)">Enseignant</div>
    </div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Principal</div>
    <a href="enseignant_dashboard.php" class="nav-item"><i class="bi bi-speedometer2"></i><span class="nav-item-txt">Tableau de Bord</span></a>
    <a href="enseignant_cours.php" class="nav-item"><i class="bi bi-book-fill"></i><span class="nav-item-txt">Mes Cours</span></a>
    <a href="enseignant_emploi_du_temps.php" class="nav-item"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Emploi du Temps</span></a>
    <div class="nav-section">Pédagogie</div>
    <a href="enseignant_notes.php" class="nav-item"><i class="bi bi-pencil-square"></i><span class="nav-item-txt">Saisie des Notes</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_etudiants.php" class="nav-item"><i class="bi bi-people-fill"></i><span class="nav-item-txt">Mes Étudiants</span></a>
    <a href="enseignant_ressources.php" class="nav-item"><i class="bi bi-folder-fill"></i><span class="nav-item-txt">Ressources</span></a>
    <div class="nav-section">Communication</div>
    <a href="enseignant_messages.php" class="nav-item active"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <a href="enseignant_notifications.php" class="nav-item"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></a>
    <div class="nav-section">Compte</div>
    <a href="enseignant_profil.php" class="nav-item"><i class="bi bi-person-circle"></i><span class="nav-item-txt">Mon Profil</span></a>
    <a href="enseignant_parametres.php" class="nav-item"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></a>
  </div>
  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Enseignant/enseignant_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role">Enseignante — Informatique</div>
      </div>
    </div>
  
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main" id="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-chat-dots-fill"></i>Messagerie</div>
    <div style="font-size:12px;color:var(--txt3);display:flex;align-items:center;gap:6px">
      <span id="unreadCount" style="background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px">4</span>
      messages non lus
    </div>
    <div class="topbar-right">
      <button class="tb-icon" title="Diffusion groupe" onclick="openBroadcast()">
        <i class="bi bi-megaphone-fill" style="color:var(--teal)"></i>
      </button>
      <button class="tb-icon" title="Notifications">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <div class="tb-user">
        <div class="tb-ava"><?= $u_init ?></div>
        <div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div>
      </div>
    </div>
  </div>

  <!-- 2-column message shell -->
  <div class="msg-shell">

    <!-- ── CONVERSATION LIST ── -->
    <div class="conv-panel">
      <!-- Search + new conv -->
      <div class="conv-topbar">
        <div class="conv-search">
          <i class="bi bi-search"></i>
          <input type="text" placeholder="Rechercher…" id="convSearch" oninput="filterConvs(this.value)">
        </div>
        <button class="new-conv-btn" title="Nouveau message" onclick="openNewConv()"><i class="bi bi-plus"></i></button>
      </div>

      <!-- Filter tabs -->
      <div class="conv-filters">
        <button class="cf-btn active" onclick="filterTab('all',this)">Tous</button>
        <button class="cf-btn" onclick="filterTab('unread',this)">Non lus <span id="unreadBadge" style="background:var(--red);color:#fff;font-size:8px;font-weight:700;padding:0 4px;border-radius:10px;margin-left:3px">4</span></button>
        <button class="cf-btn" onclick="filterTab('student',this)">Étudiants</button>
        <button class="cf-btn" onclick="filterTab('parent',this)">Parents</button>
        <button class="cf-btn" onclick="filterTab('admin',this)">Admin</button>
      </div>

      <!-- List -->
      <div class="conv-list" id="convList"></div>
    </div>

    <!-- ── CHAT PANE ── -->
    <div class="chat-pane" id="chatPane">
      <!-- Empty state by default -->
      <div class="chat-empty" id="chatEmpty">
        <i class="bi bi-chat-dots"></i>
        <p>Sélectionnez une conversation</p>
        <small>Choisissez un contact dans la liste ou démarrez une nouvelle conversation</small>
        <button class="btn btn-primary" style="margin-top:6px" onclick="openNewConv()">
          <i class="bi bi-plus-circle"></i>Nouveau message
        </button>
      </div>

      <!-- Chat area (hidden until conversation selected) -->
      <div id="chatArea" style="display:none;flex-direction:column;height:100%">
        <!-- Chat header -->
        <div class="chat-head">
          <div class="chat-head-ava" id="chatHeadAva">FB</div>
          <div>
            <div class="chat-head-name" id="chatHeadName">—</div>
            <div class="chat-head-sub" id="chatHeadSub">—</div>
          </div>
          <div class="chat-head-actions">
            <button class="chat-action-btn" title="Voir profil" onclick="toast('👤 Profil ouvert')"><i class="bi bi-person-circle"></i></button>
            <button class="chat-action-btn" title="Joindre un fichier" onclick="toast('📎 Sélection de fichier')"><i class="bi bi-paperclip"></i></button>
            <button class="chat-action-btn" title="Archiver" onclick="toast('📦 Conversation archivée')"><i class="bi bi-archive"></i></button>
          </div>
        </div>

        <!-- Messages -->
        <div class="chat-body" id="chatBody"></div>

        <!-- Compose -->
        <div class="compose-bar">
          <!-- Quick action shortcuts -->
          <div class="compose-tools">
            <button class="compose-tool-btn" onclick="attachNote()"><i class="bi bi-pencil-square"></i>Note de cours</button>
            <button class="compose-tool-btn" onclick="attachResource()"><i class="bi bi-file-earmark-pdf"></i>Ressource</button>
            <button class="compose-tool-btn" onclick="proposeSession()"><i class="bi bi-calendar-plus"></i>Rendez-vous</button>
            <button class="compose-tool-btn" onclick="toast('📎 Fichier joint')"><i class="bi bi-paperclip"></i>Fichier</button>
          </div>
          <div class="compose-row">
            <div class="compose-input" id="composeInput" contenteditable="true" data-placeholder="Écrire un message…" onkeydown="handleEnter(event)"></div>
            <button class="send-btn" onclick="sendMessage()" title="Envoyer (Entrée)"><i class="bi bi-send-fill"></i></button>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /msg-shell -->
</main>

<!-- ═══ NEW CONVERSATION MODAL ═══ -->
<div class="modal-overlay" id="newConvModal" onclick="if(event.target===this)closeNewConv()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-chat-dots-fill" style="color:var(--teal)"></i>Nouveau message</div>
      <button class="modal-close" onclick="closeNewConv()">✕</button>
    </div>

    <div class="form-field">
      <div class="form-label"><i class="bi bi-people"></i>Destinataire(s)</div>
      <div class="recipient-chips" id="recipientChips"></div>
      <input class="form-input" type="text" id="recipientInput" placeholder="Nom, groupe ou cours…" oninput="suggestRecipients(this.value)" autocomplete="off">
      <div id="recipientSuggestions" style="border:1px solid var(--border);border-radius:var(--r-sm);margin-top:4px;display:none;max-height:160px;overflow-y:auto;background:var(--surface);box-shadow:var(--sh-md)"></div>
    </div>

    <div class="form-field">
      <div class="form-label"><i class="bi bi-tag"></i>Objet</div>
      <input class="form-input" type="text" id="newSubject" placeholder="ex : Question sur le TP n°3">
    </div>

    <div class="form-field" style="margin:0">
      <div class="form-label"><i class="bi bi-chat-text"></i>Message</div>
      <textarea class="form-input form-textarea" id="newMessage" placeholder="Écrivez votre message ici…"></textarea>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px">
      <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closeNewConv()">Annuler</button>
      <button class="btn btn-primary" style="flex:2;justify-content:center" onclick="sendNewConv()">
        <i class="bi bi-send-fill"></i>Envoyer
      </button>
    </div>
  </div>
</div>

<!-- ═══ BROADCAST MODAL ═══ -->
<div class="modal-overlay" id="broadcastModal" onclick="if(event.target===this)closeBroadcast()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-megaphone-fill" style="color:var(--teal)"></i>Diffusion à un groupe</div>
      <button class="modal-close" onclick="closeBroadcast()">✕</button>
    </div>

    <div style="background:var(--teal-dim);border-left:3px solid var(--teal);border-radius:var(--r-sm);padding:9px 12px;margin-bottom:14px;font-size:12px;color:var(--blue-dk)">
      <i class="bi bi-info-circle"></i> Le message sera envoyé individuellement à chaque étudiant du groupe sélectionné.
    </div>

    <div class="form-field">
      <div class="form-label"><i class="bi bi-collection"></i>Groupe(s) destinataire(s)</div>
      <div class="group-grid" id="groupGrid"></div>
    </div>

    <div class="form-field">
      <div class="form-label"><i class="bi bi-tag"></i>Objet</div>
      <input class="form-input" type="text" id="bcSubject" placeholder="ex : Rappel — Examen partiel jeudi 8h">
    </div>

    <div class="form-field" style="margin:0">
      <div class="form-label"><i class="bi bi-chat-text"></i>Message</div>
      <textarea class="form-input form-textarea" id="bcMessage" placeholder="Votre message pour le groupe…"></textarea>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px">
      <span id="bcCount" style="font-size:11px;color:var(--txt3)">0 groupe sélectionné</span>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" onclick="closeBroadcast()">Annuler</button>
        <button class="btn btn-primary btn-sm" onclick="sendBroadcast()"><i class="bi bi-megaphone"></i>Diffuser</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ════════════════════════════════════════════
   DATA
════════════════════════════════════════════ */
const ME = { name:'Dr. Fatima Ba', initials:'FB', color:'linear-gradient(135deg,#0891b2,#06b6d4)' };

const CONTACTS = <?= json_encode(array_map(fn($ct)=>[
  'id'       => (int)$ct['id'],
  'initials' => strtoupper(substr($ct['prenom'],0,1).substr($ct['nom'],0,1)),
  'name'     => $ct['prenom'].' '.$ct['nom'],
  'role'     => $ct['role'],
  'classe'   => $ct['classe_code'] ?? '',
  'online'   => false,
], $contacts_ens)) ?>;

const CONVERSATIONS = [];  // chargé depuis Firebase Firestore

const GROUPS = [];  // chargé depuis Firebase Firestore

const ALL_RECIPIENTS = typeof CONTACTS !== "undefined" ? CONTACTS : [];

/* ════ STATE ════ */
let activeConvId   = null;
let currentFilter  = 'all';
let selectedGroups = new Set();
let newRecipients  = [];

/* ════ INIT ════ */
document.addEventListener('DOMContentLoaded', () => {
  renderConvList();
  buildGroupGrid();
});

/* ════ CONVERSATION LIST ════ */
function getContact(id){ return CONTACTS.find(c=>c.id===id); }

function convMatchesFilter(conv){
  if(currentFilter === 'all')     return true;
  if(currentFilter === 'unread')  return conv.unread > 0;
  const c = getContact(conv.contactId);
  return c && c.type === currentFilter;
}

function renderConvList(q=''){
  const list = document.getElementById('convList');
  const ql   = q.toLowerCase();
  const filtered = CONVERSATIONS.filter(conv=>{
    const c = getContact(conv.contactId);
    if(!c) return false;
    if(!convMatchesFilter(conv)) return false;
    if(ql && !c.name.toLowerCase().includes(ql) && !conv.preview.toLowerCase().includes(ql)) return false;
    return true;
  });

  if(!filtered.length){
    list.innerHTML = `<div style="text-align:center;padding:28px 16px;color:var(--txt3);font-size:12px"><i class="bi bi-search" style="font-size:24px;display:block;margin-bottom:8px;color:var(--border2)"></i>Aucune conversation trouvée</div>`;
    return;
  }

  list.innerHTML = filtered.map(conv=>{
    const c = getContact(conv.contactId);
    const isActive = activeConvId === conv.contactId;
    const typeColors = {student:'var(--teal)',parent:'var(--orange)',admin:'var(--red)'};
    const typeLabels = {student:'Étudiant·e',parent:'Parent',admin:'Admin/Collègue'};
    return `<div class="conv-item${isActive?' active':''}" onclick="openConv(${conv.contactId})">
      <div class="conv-ava" style="background:${c.color}">
        ${c.initials}
        ${c.online?'<div class="conv-online"></div>':''}
      </div>
      <div class="conv-meta">
        <div class="conv-row1">
          <div class="conv-name">${c.name}</div>
          <div class="conv-time">${conv.lastTime}</div>
        </div>
        <div class="conv-row2">
          <div class="conv-preview${conv.unread?'  unread':''}">${conv.preview}</div>
          ${conv.unread?'<div class="conv-unread-dot"></div>':''}
        </div>
        <div style="margin-top:4px">
          <span class="conv-type-chip badge" style="background:${typeColors[c.type]}22;color:${typeColors[c.type]}">${typeLabels[c.type]}</span>
        </div>
      </div>
    </div>`;
  }).join('');
}

/* ════ OPEN CONVERSATION ════ */
function openConv(contactId){
  activeConvId = contactId;
  const c    = getContact(contactId);
  const conv = CONVERSATIONS.find(cv=>cv.contactId===contactId);
  if(!c||!conv) return;

  // Mark as read
  conv.unread = 0;
  conv.messages.forEach(m=>{ m.unread=false; });
  updateUnreadCount();
  renderConvList(document.getElementById('convSearch').value);

  // Show chat area
  document.getElementById('chatEmpty').style.display = 'none';
  const ca = document.getElementById('chatArea');
  ca.style.display = 'flex';

  // Header
  const headAva = document.getElementById('chatHeadAva');
  headAva.textContent = c.initials;
  headAva.style.background = c.color;
  document.getElementById('chatHeadName').textContent = c.name;
  document.getElementById('chatHeadSub').textContent  = c.role + ' · ' + c.course + (c.online?' · 🟢 En ligne':'');

  // Messages
  renderMessages(conv);
}

function renderMessages(conv){
  const body = document.getElementById('chatBody');
  let html = '';
  let lastDateLabel = '';

  conv.messages.forEach((m,i)=>{
    // Date divider
    const rawTime = m.time || '';
    const dateKey = rawTime.includes('Hier')?'Hier':rawTime.includes('Lun')?'Lundi':rawTime.includes('Mar')?'Mardi':rawTime.includes('Mer')?'Mercredi':rawTime.includes('Jeu')?'Jeudi':rawTime.includes('Ven')?'Vendredi':rawTime.includes('Dim')?'Dimanche':'Aujourd\'hui';
    if(dateKey !== lastDateLabel){
      html += `<div class="date-divider"><span>${dateKey}</span></div>`;
      lastDateLabel = dateKey;
    }

    const isSent = m.from === 'me';
    const c = getContact(conv.contactId);

    if(m.attach){
      html += `<div class="msg-row${isSent?' sent':''}">
        ${!isSent?`<div class="msg-row-ava" style="background:${c.color}">${c.initials}</div>`:''}
        <div>
          <div class="attach-bubble" onclick="toast('📥 ${m.attachName} téléchargé')">
            <div class="attach-ico" style="background:${m.attachColor}22;color:${m.attachColor}"><i class="bi ${m.attachIcon}"></i></div>
            <div><div class="attach-name">${m.attachName}</div><div class="attach-size">${m.attachSize}</div></div>
            <i class="bi bi-download" style="color:var(--txt3);font-size:13px;margin-left:auto"></i>
          </div>
          <span class="bubble-time">${m.time}</span>
        </div>
        ${isSent?`<div class="msg-row-ava" style="background:linear-gradient(135deg,var(--teal),var(--teal-lt))">FB</div>`:''}
      </div>`;
    } else {
      html += `<div class="msg-row${isSent?' sent':''}">
        ${!isSent?`<div class="msg-row-ava" style="background:${c.color}">${c.initials}</div>`:''}
        <div class="bubble ${isSent?'sent':'received'}">${m.text}<span class="bubble-time">${m.time}${isSent?' <i class="bi bi-check2-all" style="font-size:10px;opacity:.7"></i>':''}</span></div>
        ${isSent?`<div class="msg-row-ava" style="background:linear-gradient(135deg,var(--teal),var(--teal-lt))">FB</div>`:''}
      </div>`;
    }
  });

  body.innerHTML = html;
  body.scrollTop = body.scrollHeight;
}

/* ════ SEND MESSAGE ════ */
function sendMessage(){
  const input = document.getElementById('composeInput');
  const text  = input.innerText.trim();
  if(!text || activeConvId===null) return;

  const conv  = CONVERSATIONS.find(cv=>cv.contactId===activeConvId);
  if(!conv) return;

  const now = new Date();
  const timeStr = now.getHours()+':'+(now.getMinutes()<10?'0':'')+now.getMinutes();
  conv.messages.push({ from:'me', text, time: timeStr });
  conv.preview = text;
  conv.lastTime = timeStr;

  input.innerText = '';
  renderMessages(conv);
  renderConvList(document.getElementById('convSearch').value);
}

function handleEnter(e){
  if(e.key==='Enter' && !e.shiftKey){
    e.preventDefault();
    sendMessage();
  }
}

/* ════ QUICK ACTIONS ════ */
function attachNote(){
  if(activeConvId===null){ toast('⚠️ Sélectionnez une conversation'); return; }
  const conv = CONVERSATIONS.find(cv=>cv.contactId===activeConvId);
  const now  = new Date();
  const t    = now.getHours()+':'+(now.getMinutes()<10?'0':'')+now.getMinutes();
  conv.messages.push({ from:'me', attach:true, attachName:'Notes_CC_BDD_S1.pdf', attachSize:'256 Ko', attachIcon:'bi-file-earmark-pdf', attachColor:'#C41E3A', time:t });
  conv.preview = '📎 Notes_CC_BDD_S1.pdf';
  renderMessages(conv);
  renderConvList();
  toast('📄 Note de cours jointe');
}

function attachResource(){
  if(activeConvId===null){ toast('⚠️ Sélectionnez une conversation'); return; }
  const conv = CONVERSATIONS.find(cv=>cv.contactId===activeConvId);
  const now  = new Date();
  const t    = now.getHours()+':'+(now.getMinutes()<10?'0':'')+now.getMinutes();
  conv.messages.push({ from:'me', attach:true, attachName:'Cours4_Transactions_ACID.pdf', attachSize:'1.2 Mo', attachIcon:'bi-file-earmark-slides', attachColor:'#0891b2', time:t });
  conv.preview = '📎 Cours4_Transactions_ACID.pdf';
  renderMessages(conv);
  renderConvList();
  toast('📘 Ressource jointe');
}

function proposeSession(){
  if(activeConvId===null){ toast('⚠️ Sélectionnez une conversation'); return; }
  const conv = CONVERSATIONS.find(cv=>cv.contactId===activeConvId);
  const now  = new Date();
  const t    = now.getHours()+':'+(now.getMinutes()<10?'0':'')+now.getMinutes();
  conv.messages.push({ from:'me', text:'📅 Je vous propose un rendez-vous de permanence : Mercredi 10h–11h ou Jeudi 14h–15h en salle B204. Confirmez votre disponibilité.', time:t });
  conv.preview = '📅 Proposition de rendez-vous';
  renderMessages(conv);
  renderConvList();
}

/* ════ FILTER TABS ════ */
function filterTab(filter, btn){
  currentFilter = filter;
  document.querySelectorAll('.cf-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderConvList(document.getElementById('convSearch').value);
}
function filterConvs(q){ renderConvList(q); }

/* ════ UNREAD COUNT ════ */
function updateUnreadCount(){
  const total = CONVERSATIONS.reduce((a,c)=>a+c.unread,0);
  document.getElementById('unreadCount').textContent = total;
  document.getElementById('unreadBadge').textContent = total;
  document.querySelector('.nav-badge').textContent    = total;
}

/* ════ NEW CONVERSATION MODAL ════ */
function openNewConv(){ document.getElementById('newConvModal').classList.add('open'); }
function closeNewConv(){
  document.getElementById('newConvModal').classList.remove('open');
  document.getElementById('recipientInput').value = '';
  document.getElementById('newSubject').value = '';
  document.getElementById('newMessage').value = '';
  newRecipients = [];
  renderRecipientChips();
  document.getElementById('recipientSuggestions').style.display='none';
}

function suggestRecipients(q){
  const box = document.getElementById('recipientSuggestions');
  if(!q){ box.style.display='none'; return; }
  const matches = ALL_RECIPIENTS.filter(r=>r.label.toLowerCase().includes(q.toLowerCase()));
  if(!matches.length){ box.style.display='none'; return; }
  box.style.display='block';
  box.innerHTML = matches.slice(0,6).map(r=>`
    <div onclick="addRecipient('${r.label.replace(/'/g,"\'")}','${r.sub.replace(/'/g,"\'")}','${r.type}')"
         style="padding:8px 12px;cursor:pointer;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);transition:background var(--tr)"
         onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
      <i class="bi ${r.type==='group'?'bi-people-fill':r.type==='parent'?'bi-person-hearts':r.type==='admin'?'bi-shield-fill':'bi-person-fill'}" style="color:var(--teal);font-size:14px;flex-shrink:0"></i>
      <div><div style="font-size:12.5px;font-weight:600">${r.label}</div><div style="font-size:10.5px;color:var(--txt3)">${r.sub}</div></div>
    </div>`).join('');
}

function addRecipient(label, sub, type){
  if(!newRecipients.find(r=>r.label===label)){
    newRecipients.push({label,sub,type});
    renderRecipientChips();
  }
  document.getElementById('recipientInput').value='';
  document.getElementById('recipientSuggestions').style.display='none';
}
function removeRecipient(i){
  newRecipients.splice(i,1);
  renderRecipientChips();
}
function renderRecipientChips(){
  document.getElementById('recipientChips').innerHTML = newRecipients.map((r,i)=>
    `<div class="r-chip">${r.label}<button onclick="removeRecipient(${i})">✕</button></div>`
  ).join('');
}

function sendNewConv(){
  if(!newRecipients.length){ toast('⚠️ Ajoutez au moins un destinataire'); return; }
  const msg = document.getElementById('newMessage').value.trim();
  if(!msg){ toast('⚠️ Écrivez votre message'); return; }
  closeNewConv();
  toast('✅ Message envoyé à ' + newRecipients.map(r=>r.label).join(', '));
}

/* ════ BROADCAST MODAL ════ */
function buildGroupGrid(){
  document.getElementById('groupGrid').innerHTML = GROUPS.map(g=>`
    <div class="group-tile" id="gt-${g.id}" onclick="toggleGroup('${g.id}',this)">
      <div class="group-tile-name">${g.name}</div>
      <div class="group-tile-meta"><i class="bi bi-book" style="font-size:10px"></i> ${g.course} · <i class="bi bi-people" style="font-size:10px"></i> ${g.students} étud.</div>
    </div>`).join('');
}
function toggleGroup(id, el){
  if(selectedGroups.has(id)) selectedGroups.delete(id);
  else selectedGroups.add(id);
  el.classList.toggle('selected');
  const total = [...selectedGroups].reduce((a,gid)=>{const g=GROUPS.find(x=>x.id===gid);return a+(g?g.students:0);},0);
  document.getElementById('bcCount').textContent = selectedGroups.size + ' groupe(s) · ' + total + ' étudiants';
}
function openBroadcast(){ document.getElementById('broadcastModal').classList.add('open'); }
function closeBroadcast(){
  document.getElementById('broadcastModal').classList.remove('open');
  selectedGroups.clear();
  document.querySelectorAll('.group-tile').forEach(t=>t.classList.remove('selected'));
  document.getElementById('bcCount').textContent = '0 groupe sélectionné';
  document.getElementById('bcSubject').value = '';
  document.getElementById('bcMessage').value = '';
}
function sendBroadcast(){
  if(!selectedGroups.size){ toast('⚠️ Sélectionnez au moins un groupe'); return; }
  const msg = document.getElementById('bcMessage').value.trim();
  if(!msg){ toast('⚠️ Écrivez votre message'); return; }
  const total = [...selectedGroups].reduce((a,gid)=>{const g=GROUPS.find(x=>x.id===gid);return a+(g?g.students:0);},0);
  closeBroadcast();
  toast('📢 Message diffusé à ' + total + ' étudiant(s)');
}

/* ════ SIDEBAR ════ */
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

/* ════ TOAST ════ */
let toastT;
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;el.classList.add('show');
  clearTimeout(toastT);toastT=setTimeout(()=>el.classList.remove('show'),3200);
}
const FIREBASE_USER = <?= $firebase_user_data ?>;
</script>
</body>
</html>
