<?php
/**
 * EduConnect — etudiant_reservations.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Réservations Salles
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];

// Salles disponibles avec occupation temps réel
$salles_dispo = db_query($conn,
    "SELECT s.id, s.nom, s.batiment, s.etage, s.type, s.capacite, s.equipements,
            COUNT(CASE WHEN r.statut='confirmee' AND r.date_debut <= NOW() AND r.date_fin >= NOW() THEN 1 END) AS occupee_maintenant,
            COUNT(CASE WHEN r.statut='confirmee' AND r.date_debut >= NOW() THEN 1 END) AS reservations_a_venir
     FROM salles s
     LEFT JOIN reservations r ON r.salle_id = s.id
     WHERE s.actif = 1
     GROUP BY s.id
     ORDER BY s.batiment, s.etage, s.nom"
);

// Mes réservations
$mes_reservas = db_query($conn,
    "SELECT r.id, r.code_resa, r.objet, r.participants, r.date_debut, r.date_fin,
            r.statut, r.created_at, s.nom AS salle_nom, s.batiment
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     WHERE r.demandeur_id = ?
     ORDER BY r.date_debut DESC LIMIT 20",
    "i",[$uid]
);

// Traitement POST — nouvelle réservation
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["new_reservation"])) {
    $salle_id    = (int)$_POST["salle_id"];
    $objet       = htmlspecialchars(trim($_POST["objet"] ?? ""),ENT_QUOTES,"UTF-8");
    $participants= max(1,(int)($_POST["participants"] ?? 1));
    $date_debut  = trim($_POST["date_debut"]  ?? "");
    $date_fin    = trim($_POST["date_fin"]    ?? "");
    $err_resa = "";

    if (!$salle_id || !$objet || !$date_debut || !$date_fin) {
        $err_resa = "Tous les champs sont requis.";
    } elseif ($date_fin <= $date_debut) {
        $err_resa = "L'heure de fin doit être après l'heure de début.";
    } else {
        // Vérifier conflit
        $conflit = db_row($conn,
            "SELECT id FROM reservations
             WHERE salle_id=? AND statut='confirmee'
             AND date_debut < ? AND date_fin > ?",
            "iss",[$salle_id,$date_fin,$date_debut]
        );
        if ($conflit) {
            $err_resa = "Cette salle est déjà réservée sur ce créneau.";
        } else {
            $code = "RSV-".date("Y")."-".str_pad(mt_rand(1,99999),5,"0",STR_PAD_LEFT);
            db_exec($conn,
                "INSERT INTO reservations (salle_id,demandeur_id,objet,participants,date_debut,date_fin,statut,code_resa)
                 VALUES (?,?,?,?,?,?,'en_attente',?)",
                "iisiiss",
                [$salle_id,$uid,$objet,$participants,$date_debut,$date_fin,$code]
            );
            header("Location: etudiant_reservations.php?ok=1"); exit;
        }
    }
}
// POST — annuler réservation
if ($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["annuler_resa"])) {
    $resa_id = (int)$_POST["resa_id"];
    db_exec($conn,
        "UPDATE reservations SET statut='annulee' WHERE id=? AND demandeur_id=? AND date_debut > NOW()",
        "ii",[$resa_id,$uid]
    );
    header("Location: etudiant_reservations.php?cancel=1"); exit;
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
<title>EduConnect — Réserver une Salle</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════
   TOKENS — identiques à tout le projet
═══════════════════════════════════════════ */
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

/* ═══ SIDEBAR ═══ */
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:54px}
.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--green),var(--green-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(39,174,96,.5)}
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
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--green);border-radius:0 2px 2px 0}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--green)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--green),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}

/* ═══ MAIN / TOPBAR ═══ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}
.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}
.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--blue);color:#fff;box-shadow:0 1px 4px rgba(50,110,169,.3)}
.tab-btn i{font-size:13px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--green),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;animation:fadeUp .3s ease}
.page-title{display:flex;align-items:center;gap:10px}
.page-title h1{font-size:22px;font-weight:700;color:var(--txt)}
.page-title i{font-size:26px;color:var(--blue)}
.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.page-actions{display:flex;gap:8px}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent}
.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}
.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.btn-danger{background:var(--red-dim);color:var(--red);border-color:var(--red)}
.btn-danger:hover{background:var(--red);color:#fff}
.btn-sm{padding:5px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--orange)}
.badge-red{background:var(--red-dim);color:var(--red)}

/* ═══ LAYOUT: 2 COLUMNS ═══ */
.main-layout{display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:start}

/* ═══ FILTERS BAR ═══ */
.filters-bar{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  padding:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  box-shadow:var(--sh);margin-bottom:14px;animation:fadeUp .3s ease;
}
.filter-group{display:flex;flex-direction:column;gap:4px;min-width:120px}
.filter-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3)}
.filter-select{
  padding:6px 10px;border:1px solid var(--border2);border-radius:var(--r-sm);
  font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);background:var(--surface);
  cursor:pointer;transition:border var(--tr);
}
.filter-select:focus{outline:none;border-color:var(--blue)}
.filter-search{
  display:flex;align-items:center;gap:7px;padding:6px 12px;
  border:1px solid var(--border2);border-radius:var(--r-sm);
  background:var(--surface);flex:1;min-width:160px;
}
.filter-search:focus-within{border-color:var(--blue)}
.filter-search i{color:var(--txt3);font-size:13px}
.filter-search input{background:none;border:none;outline:none;font-family:'Ubuntu',sans-serif;font-size:12px;color:var(--txt);width:100%}

/* ═══ ROOMS GRID ═══ */
.rooms-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;animation:fadeUp .3s .1s ease both}

.room-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  box-shadow:var(--sh);overflow:hidden;transition:all var(--tr);cursor:pointer;
}
.room-card:hover{box-shadow:var(--sh-md);transform:translateY(-3px);border-color:var(--blue)}
.room-card.selected{border-color:var(--blue);box-shadow:0 0 0 3px rgba(50,110,169,.15)}
.room-card.unavailable{opacity:.55;cursor:not-allowed}
.room-card.unavailable:hover{transform:none;box-shadow:var(--sh);border-color:var(--border)}

.room-img{
  height:80px;
  display:flex;align-items:center;justify-content:center;
  font-size:36px;position:relative;overflow:hidden;
}
.room-status-badge{
  position:absolute;top:8px;right:8px;
  padding:3px 8px;border-radius:20px;font-size:9px;font-weight:700;
  backdrop-filter:blur(4px);
}
.rs-free{background:rgba(39,174,96,.85);color:#fff}
.rs-busy{background:rgba(231,76,60,.85);color:#fff}
.rs-maint{background:rgba(230,126,34,.85);color:#fff}

.room-body{padding:12px}
.room-name{font-size:13px;font-weight:700;color:var(--txt);margin-bottom:4px}
.room-type{font-size:10px;color:var(--txt3);margin-bottom:8px;display:flex;align-items:center;gap:4px}
.room-features{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px}
.room-feat{font-size:9px;font-weight:600;padding:2px 6px;border-radius:3px;background:var(--surface2);color:var(--txt2);border:1px solid var(--border)}
.room-cap{font-size:11px;color:var(--txt3);display:flex;align-items:center;gap:5px;margin-bottom:10px}
.room-cap i{color:var(--blue);font-size:12px}
.room-actions{display:flex;gap:6px}

/* Occupancy indicator */
.occ-bar-wrap{height:4px;background:var(--border);border-radius:10px;overflow:hidden;margin-bottom:8px}
.occ-bar-fill{height:100%;border-radius:10px;transition:width .6s ease}

/* ═══ BOOKING PANEL (right column) ═══ */
.booking-panel{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  box-shadow:var(--sh);position:sticky;top:calc(var(--topbar-h) + 14px);
  animation:fadeUp .3s .15s ease both;overflow:hidden;
}
.bp-header{
  background:linear-gradient(135deg,var(--blue),var(--blue-dk));
  padding:16px 18px;color:#fff;
}
.bp-title{font-size:14px;font-weight:700;margin-bottom:2px}
.bp-sub{font-size:11px;opacity:.75}
.bp-selected{
  padding:12px 14px;background:var(--blue-xlt);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px;
}
.bp-selected-icon{font-size:22px}
.bp-selected-name{font-size:13px;font-weight:700;color:var(--txt)}
.bp-selected-cap{font-size:11px;color:var(--txt3);margin-top:2px}
.bp-form{padding:16px}
.bp-field{margin-bottom:14px}
.bp-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px;display:flex;align-items:center;gap:5px}
.bp-label i{font-size:12px;color:var(--blue)}
.bp-input{
  width:100%;padding:9px 12px;border:1.5px solid var(--border2);
  border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:12.5px;
  color:var(--txt);background:var(--surface);transition:border var(--tr);
}
.bp-input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(50,110,169,.1)}
.bp-slots{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.slot-chip{
  padding:8px 6px;border-radius:var(--r-sm);border:1.5px solid var(--border2);
  font-size:11px;font-weight:600;text-align:center;cursor:pointer;
  transition:all var(--tr);color:var(--txt2);background:var(--surface);
}
.slot-chip:hover{border-color:var(--blue);background:var(--blue-xlt);color:var(--blue)}
.slot-chip.selected{border-color:var(--blue);background:var(--blue);color:#fff}
.slot-chip.taken{border-color:var(--border);background:var(--surface2);color:var(--txt3);cursor:not-allowed;opacity:.55}
.slot-chip.taken:hover{background:var(--surface2);color:var(--txt3);border-color:var(--border)}

.bp-textarea{resize:vertical;min-height:70px}
.bp-participants{display:flex;align-items:center;gap:8px}
.bp-participants-btn{
  width:32px;height:32px;border-radius:var(--r-sm);
  background:var(--surface2);border:1.5px solid var(--border2);
  display:flex;align-items:center;justify-content:center;
  font-size:16px;font-weight:700;color:var(--txt2);cursor:pointer;
  transition:all var(--tr);flex-shrink:0;
}
.bp-participants-btn:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}
.bp-participants-val{font-size:15px;font-weight:700;color:var(--txt);min-width:28px;text-align:center}
.bp-summary{
  background:var(--surface2);border:1px solid var(--border);
  border-radius:var(--r-sm);padding:10px 12px;margin-bottom:12px;
}
.bp-summary-row{display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px}
.bp-summary-row:last-child{margin:0;font-weight:700;font-size:12px;color:var(--txt);padding-top:6px;border-top:1px solid var(--border);margin-top:4px}
.bp-summary-row span:first-child{color:var(--txt3)}
.bp-error{color:var(--red);font-size:11px;margin-top:4px;display:none}

/* ═══ MY RESERVATIONS ═══ */
.my-reservations{margin-top:16px;animation:fadeUp .3s .2s ease both}
.section-title{font-size:13px;font-weight:700;color:var(--txt);margin-bottom:10px;display:flex;align-items:center;gap:7px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.section-title i{color:var(--blue);font-size:15px}

.res-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  padding:12px 14px;display:flex;align-items:center;gap:12px;
  margin-bottom:8px;box-shadow:var(--sh);transition:all var(--tr);
}
.res-card:hover{box-shadow:var(--sh-md)}
.res-card-icon{width:40px;height:40px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.res-info{flex:1}
.res-name{font-size:13px;font-weight:700;color:var(--txt);margin-bottom:2px}
.res-meta{font-size:11px;color:var(--txt3);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.res-meta i{font-size:11px}
.res-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px}
.res-code{font-size:9px;font-weight:700;color:var(--txt3);letter-spacing:.5px}

/* ═══ EMPTY STATE ═══ */
.empty-state{text-align:center;padding:40px 20px;color:var(--txt3)}
.empty-state i{font-size:40px;margin-bottom:10px;display:block;color:var(--border2)}
.empty-state p{font-size:13px}

/* ═══ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:12px;padding:24px;width:460px;max-width:100%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease;max-height:90vh;overflow-y:auto}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:16px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red)}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--green);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1100px){.main-layout{grid-template-columns:1fr}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.filters-bar{flex-direction:column}.rooms-grid{grid-template-columns:1fr}}
</style>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
</head>
<body>

<!-- ════════════════════ SIDEBAR ════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-mortarboard-fill"></i></div>
    <div>
      <div class="logo-text">Edu<span>Connect</span></div>
      <div style="font-size:9px;color:rgba(255,255,255,.4)">Étudiant</div>
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
    <div class="nav-section">Menu Principal</div>
    <a href="etudiant_dashboard.php" class="nav-item">
      <i class="bi bi-speedometer2"></i>
      <span class="nav-item-txt">Tableau de Bord</span>
    </a>
    <a href="etudiant_cours.php" class="nav-item">
      <i class="bi bi-book"></i>
      <span class="nav-item-txt">Mes Cours</span>
    </a>
    <a href="etudiants_notes.php" class="nav-item">
      <i class="bi bi-graph-up"></i>
      <span class="nav-item-txt">Mes Notes</span>
    </a>
    <a href="etudiant_mentoring.php" class="nav-item">
      <i class="bi bi-star"></i>
      <span class="nav-item-txt">Mentorat</span>
    </a>

    <div class="nav-section">Académique</div>
    <a href="etudiant_emploi_du_temps.php" class="nav-item">
      <i class="bi bi-calendar3"></i>
      <span class="nav-item-txt">Emploi du temps</span>
    </a>
    <a href="etudiant_reservation_salles.php" class="nav-item active">
      <i class="bi bi-building"></i>
      <span class="nav-item-txt">Réserver une salle</span>
    </a>
    <a href="etudiant_paiements.php" class="nav-item">
      <i class="bi bi-credit-card"></i>
      <span class="nav-item-txt">Paiements</span>
    </a>
    <a href="etudiant_messages.php" class="nav-item">
      <i class="bi bi-chat-dots"></i>
      <span class="nav-item-txt">Messages</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
    <a href="etudiant_notifications.php" class="nav-item">
      <i class="bi bi-bell"></i>
      <span class="nav-item-txt">Notifications</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Etudiant/etudiant_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role"><?= $u_role ?></div>
      </div>
    </div>
  
  <a href="/Views/Etudiant/etudiant_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
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
      <i class="bi bi-building"></i>
      Réservation de Salles
    </div>

    <div class="tab-nav">
      <button class="tab-btn active" onclick="setView('book',this)">
        <i class="bi bi-plus-circle"></i>Nouvelle réservation
      </button>
      <button class="tab-btn" onclick="setView('history',this)">
        <i class="bi bi-clock-history"></i>Mes réservations
      </button>
    </div>

    <div class="topbar-right">
      <button class="tb-icon" title="Notifications" onclick="toast('🔔 3 notifications')">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <button class="tb-icon" title="Règles de réservation" onclick="openRules()">
        <i class="bi bi-info-circle"></i>
      </button>
      <div class="tb-user" onclick="toast('👤 Profil — Marie Sow')">
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

    <!-- Page header -->
    <div class="page-header">
      <div class="page-title">
        <i class="bi bi-building"></i>
        <div>
          <h1>Réserver une Salle</h1>
          <p>36 espaces disponibles · Confirmation instantanée · Code de réservation QR</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="openRules()">
          <i class="bi bi-question-circle"></i>Règles d'utilisation
        </button>
      </div>
    </div>

    <!-- NEW BOOKING VIEW -->
    <div id="view-book">

      <!-- Filters -->
      <div class="filters-bar">
        <div class="filter-group">
          <div class="filter-label">Type de salle</div>
          <select class="filter-select" id="filterType" onchange="applyFilters()">
            <option value="">Tous les types</option>
            <option value="Amphithéâtre">Amphithéâtre</option>
            <option value="Informatique">Salle Informatique</option>
            <option value="Cours">Salle de Cours</option>
            <option value="Réunion">Salle de Réunion</option>
            <option value="Labo">Laboratoire</option>
          </select>
        </div>
        <div class="filter-group">
          <div class="filter-label">Capacité min.</div>
          <select class="filter-select" id="filterCap" onchange="applyFilters()">
            <option value="0">Toutes</option>
            <option value="10">≥ 10 places</option>
            <option value="25">≥ 25 places</option>
            <option value="50">≥ 50 places</option>
            <option value="100">≥ 100 places</option>
          </select>
        </div>
        <div class="filter-group">
          <div class="filter-label">Disponibilité</div>
          <select class="filter-select" id="filterAvail" onchange="applyFilters()">
            <option value="">Toutes</option>
            <option value="free">Disponible maintenant</option>
            <option value="today">Disponible aujourd'hui</option>
          </select>
        </div>
        <div class="filter-search">
          <i class="bi bi-search"></i>
          <input type="text" placeholder="Nom de salle, équipement…" id="filterSearch" oninput="applyFilters()">
        </div>
        <button class="btn btn-secondary btn-sm" onclick="resetFilters()">
          <i class="bi bi-x-circle"></i>Réinitialiser
        </button>
      </div>

      <!-- 2-column layout -->
      <div class="main-layout">

        <!-- Left: rooms grid -->
        <div>
          <div style="font-size:11px;color:var(--txt3);margin-bottom:10px;font-weight:600">
            <span id="roomCount">0</span> salle(s) affichée(s) ·
            <span style="color:var(--green)"><i class="bi bi-circle-fill" style="font-size:8px"></i> Disponible</span> ·
            <span style="color:var(--orange)"><i class="bi bi-circle-fill" style="font-size:8px"></i> Occupée</span> ·
            <span style="color:var(--red)"><i class="bi bi-circle-fill" style="font-size:8px"></i> Maintenance</span>
          </div>
          <div class="rooms-grid" id="roomsGrid"></div>
        </div>

        <!-- Right: booking panel -->
        <div>
          <div class="booking-panel">
            <div class="bp-header">
              <div class="bp-title"><i class="bi bi-calendar-plus"></i> Formulaire de réservation</div>
              <div class="bp-sub">Sélectionnez une salle puis remplissez le formulaire</div>
            </div>

            <!-- Selected room indicator -->
            <div class="bp-selected" id="bpSelected" style="display:none">
              <div class="bp-selected-icon" id="bpRoomEmoji">🏫</div>
              <div>
                <div class="bp-selected-name" id="bpRoomName">—</div>
                <div class="bp-selected-cap" id="bpRoomCap">—</div>
              </div>
              <button class="btn btn-sm btn-secondary" style="margin-left:auto" onclick="clearRoom()">
                <i class="bi bi-x"></i>
              </button>
            </div>
            <div class="bp-selected" id="bpEmpty" style="display:flex">
              <div class="bp-selected-icon">👆</div>
              <div>
                <div class="bp-selected-name">Aucune salle sélectionnée</div>
                <div class="bp-selected-cap">Cliquez sur une salle dans la liste</div>
              </div>
            </div>

            <div class="bp-form">
              <!-- Date -->
              <div class="bp-field">
                <div class="bp-label"><i class="bi bi-calendar-event"></i>Date de réservation *</div>
                <input class="bp-input" type="date" id="bpDate" min="" onchange="updateSlots()">
              </div>

              <!-- Time slots -->
              <div class="bp-field">
                <div class="bp-label"><i class="bi bi-clock"></i>Créneau horaire *</div>
                <div class="bp-slots" id="bpSlots"></div>
                <div class="bp-error" id="slotError">Veuillez sélectionner un créneau disponible.</div>
              </div>

              <!-- Motif -->
              <div class="bp-field">
                <div class="bp-label"><i class="bi bi-chat-text"></i>Motif de la réservation *</div>
                <select class="bp-input" id="bpMotif">
                  <option value="">Sélectionner un motif…</option>
                  <option value="Révision de groupe">Révision de groupe</option>
                  <option value="Projet tuteuré">Projet tuteuré</option>
                  <option value="Session de mentorat">Session de mentorat</option>
                  <option value="Réunion d'équipe">Réunion d'équipe</option>
                  <option value="Présentation / soutenance">Présentation / soutenance</option>
                  <option value="Autre">Autre</option>
                </select>
              </div>

              <!-- Participants -->
              <div class="bp-field">
                <div class="bp-label"><i class="bi bi-people"></i>Nombre de participants *</div>
                <div class="bp-participants">
                  <button class="bp-participants-btn" onclick="changePartic(-1)">−</button>
                  <span class="bp-participants-val" id="bpParticipants">2</span>
                  <button class="bp-participants-btn" onclick="changePartic(1)">+</button>
                  <span style="font-size:11px;color:var(--txt3);margin-left:4px">personnes</span>
                </div>
              </div>

              <!-- Note optionnelle -->
              <div class="bp-field">
                <div class="bp-label"><i class="bi bi-pencil"></i>Note (optionnel)</div>
                <textarea class="bp-input bp-textarea" id="bpNote" placeholder="Informations supplémentaires…"></textarea>
              </div>

              <!-- Summary -->
              <div class="bp-summary" id="bpSummary" style="display:none">
                <div class="bp-summary-row"><span>Salle</span><span id="sumRoom">—</span></div>
                <div class="bp-summary-row"><span>Date</span><span id="sumDate">—</span></div>
                <div class="bp-summary-row"><span>Créneau</span><span id="sumSlot">—</span></div>
                <div class="bp-summary-row"><span>Participants</span><span id="sumPartic">—</span></div>
                <div class="bp-summary-row"><span>Code réservation</span><span id="sumCode" style="color:var(--blue)">—</span></div>
              </div>

              <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="submitBooking()">
                <i class="bi bi-calendar-check"></i>Confirmer la réservation
              </button>
            </div>
          </div>
        </div>

      </div><!-- /main-layout -->

      <!-- My existing reservations (below the grid) -->
      <div class="my-reservations">
        <div class="section-title"><i class="bi bi-clock-history"></i>Mes réservations à venir</div>
        <div id="upcomingList"></div>
      </div>

    </div><!-- /view-book -->

    <!-- HISTORY VIEW -->
    <div id="view-history" style="display:none;animation:fadeUp .3s ease">
      <div class="section-title"><i class="bi bi-archive"></i>Historique complet de mes réservations</div>
      <div id="historyList"></div>
    </div>

  </div><!-- /content -->
</main>

<!-- Rules modal -->
<div class="modal-overlay" id="rulesModal" onclick="if(event.target===this)closeRules()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-info-circle-fill" style="color:var(--blue)"></i>Règles d'utilisation des salles</div>
      <button class="modal-close" onclick="closeRules()">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:12px;font-size:12.5px;color:var(--txt2);line-height:1.6">
      <div style="display:flex;gap:10px"><i class="bi bi-check-circle-fill" style="color:var(--green);margin-top:2px;flex-shrink:0"></i><span>Les réservations doivent être effectuées au minimum <strong>2 heures</strong> avant le début du créneau.</span></div>
      <div style="display:flex;gap:10px"><i class="bi bi-check-circle-fill" style="color:var(--green);margin-top:2px;flex-shrink:0"></i><span>Maximum <strong>2 réservations actives</strong> simultanément par étudiant.</span></div>
      <div style="display:flex;gap:10px"><i class="bi bi-check-circle-fill" style="color:var(--green);margin-top:2px;flex-shrink:0"></i><span>Annulation possible <strong>jusqu'à 1 heure avant</strong> le début. Au-delà, la salle reste réservée.</span></div>
      <div style="display:flex;gap:10px"><i class="bi bi-check-circle-fill" style="color:var(--green);margin-top:2px;flex-shrink:0"></i><span>Les salles doivent être laissées <strong>propres et rangées</strong> après utilisation.</span></div>
      <div style="display:flex;gap:10px"><i class="bi bi-x-circle-fill" style="color:var(--red);margin-top:2px;flex-shrink:0"></i><span>Les réservations pour des <strong>activités non académiques</strong> sont refusées.</span></div>
      <div style="display:flex;gap:10px"><i class="bi bi-x-circle-fill" style="color:var(--red);margin-top:2px;flex-shrink:0"></i><span>Toute réservation non utilisée sans annulation préalable entraîne un <strong>avertissement</strong>.</span></div>
      <div style="background:var(--blue-xlt);border-left:3px solid var(--blue);padding:10px 12px;border-radius:5px;font-size:11.5px">
        <strong style="color:var(--blue)">💡 Bon à savoir :</strong> Votre code de réservation QR fait foi en toutes circonstances — présentez-le à l'entrée de la salle. Le statut «&nbsp;Confirmé&nbsp;» dans EduConnect garantit votre accès.
      </div>
    </div>
    <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border)">
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="closeRules()">
        <i class="bi bi-check2"></i>J'ai compris
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ════════════════════════════════════════
   DATA
════════════════════════════════════════ */
const ROOMS = <?= json_encode(array_map(fn($s)=>[
  'id'       => (int)$s['id'],
  'name'     => $s['nom'],
  'batiment' => $s['batiment'],
  'etage'    => (int)$s['etage'],
  'type'     => $s['type'],
  'capacity' => (int)$s['capacite'],
  'equipments'=> json_decode($s['equipements']??'[]',true),
  'busy'     => (bool)$s['occupee_maintenant'],
  'upcoming' => (int)$s['reservations_a_venir'],
], $salles_dispo)) ?>;

const TIME_SLOTS = ['08:00','08:30','09:00','09:30','10:00','11:15','12:00','14:00','14:30','15:00','16:00','17:00','18:00','19:00'];

// My existing reservations
let MY_RESERVATIONS = [
  { id:'RSV-2026-0047', room:'Salle B202', emoji:'💻', date:'2026-04-04', slot:'13:00 – 15:00', motif:'Révision de groupe', participants:5, status:'confirmed', code:'QR-8821' },
  { id:'RSV-2026-0038', room:'Salle C303', emoji:'🤝', date:'2026-04-02', slot:'10:00 – 12:00', motif:'Projet tuteuré',    participants:3, status:'done',      code:'QR-7723' },
  { id:'RSV-2026-0021', room:'Amphi A',    emoji:'🏛️', date:'2026-03-28', slot:'08:00 – 10:00', motif:'Session de mentorat',participants:2,status:'done',      code:'QR-5541' },
];

/* STATE */
let selectedRoom = null;
let selectedSlot = null;
let participants = 2;
let filteredRooms = [...ROOMS];

/* INIT */
document.addEventListener('DOMContentLoaded', () => {
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('bpDate').min = today;
  document.getElementById('bpDate').value = today;
  renderRooms(ROOMS);
  renderSlots();
  renderUpcoming();
  renderHistory();
});

/* ═══ ROOMS ═══ */
function renderRooms(list){
  document.getElementById('roomCount').textContent = list.length;
  const statusLabel = { free:'Disponible', busy:'Occupée', maint:'Maintenance' };
  const statusCls   = { free:'rs-free', busy:'rs-busy', maint:'rs-maint' };
  const capColor    = (r) => r.status === 'free' ? 'var(--green)' : r.status === 'maint' ? 'var(--orange)' : 'var(--red)';
  const bgMap = { blue:'var(--blue-xlt)',green:'rgba(39,174,96,.08)',orange:'rgba(230,126,34,.08)',teal:'var(--teal-dim)',purple:'var(--purple-dim)',red:'var(--red-dim)' };

  document.getElementById('roomsGrid').innerHTML = list.map(r => {
    const unavail = r.status !== 'free';
    return `<div class="room-card${unavail?' unavailable':''}${selectedRoom&&selectedRoom.id===r.id?' selected':''}" onclick="${unavail?`toast('⚠️ ${r.name} n\'est pas disponible actuellement')`:`selectRoom(${r.id})`}" style="border-left:4px solid ${unavail?'var(--border)':r.occ===0?'var(--green)':'var(--orange)'}">
      <div class="room-img" style="background:${bgMap[r.color]||'var(--blue-xlt)'}">
        ${r.emoji}
        <span class="room-status-badge ${statusCls[r.status]}">${statusLabel[r.status]}</span>
      </div>
      <div class="room-body">
        <div class="room-name">${r.name}</div>
        <div class="room-type"><i class="bi bi-building" style="color:var(--blue)"></i>${r.type}</div>
        <div class="occ-bar-wrap"><div class="occ-bar-fill" style="width:${r.occ/r.cap*100}%;background:${r.occ/r.cap>.8?'var(--red)':r.occ/r.cap>.5?'var(--orange)':'var(--green)'}"></div></div>
        <div class="room-features">${r.feats.map(f=>`<span class="room-feat"><i class="bi bi-check2" style="color:var(--green);font-size:9px"></i>${f}</span>`).join('')}</div>
        <div class="room-cap"><i class="bi bi-people-fill"></i>${r.cap} places · ${r.occ > 0 ? r.occ + ' occupées' : 'Toutes libres'}</div>
        <div class="room-actions">
          ${unavail
            ? `<button class="btn btn-sm btn-secondary" style="flex:1;cursor:not-allowed;opacity:.5" disabled>Indisponible</button>`
            : `<button class="btn btn-sm btn-primary" style="flex:1" onclick="event.stopPropagation();selectRoom(${r.id})"><i class="bi bi-calendar-plus"></i>Réserver</button>`
          }
        </div>
      </div>
    </div>`;
  }).join('');
}

function selectRoom(id){
  selectedRoom = ROOMS.find(r=>r.id===id);
  selectedSlot = null;

  // Update panel
  document.getElementById('bpEmpty').style.display = 'none';
  document.getElementById('bpSelected').style.display = 'flex';
  document.getElementById('bpRoomEmoji').textContent = selectedRoom.emoji;
  document.getElementById('bpRoomName').textContent = selectedRoom.name;
  document.getElementById('bpRoomCap').textContent = `${selectedRoom.type} · ${selectedRoom.cap} places max`;
  updateSlots();
  updateSummary();
  renderRooms(filteredRooms); // re-render to show selection
  toast(`✅ ${selectedRoom.name} sélectionnée`);

  // Scroll to panel on mobile
  if(window.innerWidth < 1100){
    document.querySelector('.booking-panel').scrollIntoView({behavior:'smooth',block:'start'});
  }
}

function clearRoom(){
  selectedRoom = null;
  selectedSlot = null;
  document.getElementById('bpEmpty').style.display = 'flex';
  document.getElementById('bpSelected').style.display = 'none';
  document.getElementById('bpSummary').style.display = 'none';
  renderRooms(filteredRooms);
  renderSlots();
}

/* ═══ SLOTS ═══ */
function renderSlots(){
  // Randomize taken slots slightly based on selected room + date
  const seed = selectedRoom ? selectedRoom.id * 3 : 0;
  const taken = [1,3].map(i=>(i+seed)%TIME_SLOTS.length);
  document.getElementById('bpSlots').innerHTML = TIME_SLOTS.map((s,i)=>{
    const isTaken = !selectedRoom || taken.includes(i);
    return`<div class="slot-chip${isTaken?' taken':selectedSlot===i?' selected':''}" onclick="${isTaken?`toast('⏰ Ce créneau est déjà réservé')`:`selectSlot(${i})`}">
      ${s.label}
    </div>`;
  }).join('');
}

function selectSlot(i){
  selectedSlot = i;
  document.getElementById('slotError').style.display = 'none';
  renderSlots();
  updateSummary();
}

function updateSlots(){ renderSlots(); updateSummary(); }

/* ═══ PARTICIPANTS ═══ */
function changePartic(delta){
  const max = selectedRoom ? selectedRoom.cap : 150;
  participants = Math.max(1, Math.min(max, participants + delta));
  document.getElementById('bpParticipants').textContent = participants;
  updateSummary();
}

/* ═══ SUMMARY ═══ */
function updateSummary(){
  if(!selectedRoom || selectedSlot === null){ document.getElementById('bpSummary').style.display='none'; return; }
  document.getElementById('bpSummary').style.display = 'block';
  const dateVal = document.getElementById('bpDate').value;
  const dateStr = dateVal ? new Date(dateVal).toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'}) : '—';
  document.getElementById('sumRoom').textContent    = selectedRoom.name;
  document.getElementById('sumDate').textContent    = dateStr;
  document.getElementById('sumSlot').textContent    = TIME_SLOTS[selectedSlot].label;
  document.getElementById('sumPartic').textContent  = participants + ' personne(s)';
  document.getElementById('sumCode').textContent    = 'RSV-2026-' + String(Math.floor(Math.random()*9000)+1000);
}

/* ═══ SUBMIT ═══ */
function submitBooking(){
  if(!selectedRoom){ toast('⚠️ Veuillez sélectionner une salle'); return; }
  if(selectedSlot === null){ document.getElementById('slotError').style.display='block'; return; }
  const date  = document.getElementById('bpDate').value;
  const motif = document.getElementById('bpMotif').value;
  if(!date)  { toast('⚠️ Veuillez sélectionner une date'); return; }
  if(!motif) { toast('⚠️ Veuillez indiquer un motif'); return; }

  const code = 'RSV-2026-' + String(100 + MY_RESERVATIONS.length + 1).padStart(4,'0');
  const dateStr = new Date(date).toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long'});

  MY_RESERVATIONS.unshift({
    id: code,
    room: selectedRoom.name,
    emoji: selectedRoom.emoji,
    date, slot: TIME_SLOTS[selectedSlot].label,
    motif, participants,
    status:'confirmed',
    code: 'QR-' + String(Math.floor(Math.random()*9000)+1000)
  });

  toast(`🎉 Réservation confirmée ! ${selectedRoom.name} · ${dateStr} · ${TIME_SLOTS[selectedSlot].label}`);
  renderUpcoming();
  renderHistory();

  // Reset form
  clearRoom();
  document.getElementById('bpDate').value = new Date().toISOString().split('T')[0];
  document.getElementById('bpMotif').value = '';
  document.getElementById('bpNote').value  = '';
  participants = 2;
  document.getElementById('bpParticipants').textContent = '2';
}

/* ═══ UPCOMING & HISTORY ═══ */
function renderUpcoming(){
  const today = new Date(); today.setHours(0,0,0,0);
  const upcoming = MY_RESERVATIONS.filter(r=>r.status==='confirmed' && new Date(r.date) >= today);
  document.getElementById('upcomingList').innerHTML = upcoming.length === 0
    ? `<div class="empty-state"><i class="bi bi-calendar-x"></i><p>Aucune réservation à venir</p></div>`
    : upcoming.map(r => resCard(r, true)).join('');
}

function renderHistory(){
  document.getElementById('historyList').innerHTML = MY_RESERVATIONS.length === 0
    ? `<div class="empty-state"><i class="bi bi-inbox"></i><p>Aucune réservation enregistrée</p></div>`
    : MY_RESERVATIONS.map(r => resCard(r, false)).join('');
}

function resCard(r, showCancel){
  const statusBadge = r.status === 'confirmed'
    ? `<span class="badge badge-green"><i class="bi bi-check-circle-fill"></i>Confirmée</span>`
    : `<span class="badge"><i class="bi bi-check2-all"></i>Terminée</span>`;
  const dateStr = new Date(r.date).toLocaleDateString('fr-FR',{weekday:'short',day:'numeric',month:'short',year:'numeric'});

  return `<div class="res-card">
    <div class="res-card-icon" style="background:var(--blue-xlt);color:var(--blue)">${r.emoji}</div>
    <div class="res-info">
      <div class="res-name">${r.room} <span style="font-weight:400;color:var(--txt3)">· ${r.motif}</span></div>
      <div class="res-meta">
        <span><i class="bi bi-calendar-event"></i> ${dateStr}</span>
        <span><i class="bi bi-clock"></i> ${r.slot}</span>
        <span><i class="bi bi-people"></i> ${r.participants} pers.</span>
      </div>
    </div>
    <div class="res-right">
      ${statusBadge}
      <div class="res-code">${r.id}</div>
      ${showCancel && r.status==='confirmed'
        ? `<button class="btn btn-sm btn-danger" onclick="cancelReservation('${r.id}')"><i class="bi bi-x-circle"></i>Annuler</button>`
        : ''}
    </div>
  </div>`;
}

function cancelReservation(id){
  const r = MY_RESERVATIONS.find(x=>x.id===id);
  if(!r) return;
  r.status = 'done';
  renderUpcoming();
  renderHistory();
  toast(`🗑️ Réservation ${id} annulée`);
}

/* ═══ FILTERS ═══ */
function applyFilters(){
  const type  = document.getElementById('filterType').value;
  const cap   = parseInt(document.getElementById('filterCap').value)||0;
  const avail = document.getElementById('filterAvail').value;
  const q     = document.getElementById('filterSearch').value.toLowerCase();

  filteredRooms = ROOMS.filter(r => {
    if(type   && r.type !== type) return false;
    if(cap    && r.cap < cap)     return false;
    if(avail === 'free' && r.status !== 'free') return false;
    if(avail === 'today' && r.status === 'maint') return false;
    if(q && !r.name.toLowerCase().includes(q) && !r.feats.join(' ').toLowerCase().includes(q)) return false;
    return true;
  });
  renderRooms(filteredRooms);
}

function resetFilters(){
  document.getElementById('filterType').value  = '';
  document.getElementById('filterCap').value   = '0';
  document.getElementById('filterAvail').value = '';
  document.getElementById('filterSearch').value = '';
  filteredRooms = [...ROOMS];
  renderRooms(filteredRooms);
}

/* ═══ VIEW SWITCH ═══ */
function setView(v, btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('view-book').style.display    = v==='book'    ? '' : 'none';
  document.getElementById('view-history').style.display = v==='history' ? '' : 'none';
}

/* ═══ MODALS ═══ */
function openRules(){ document.getElementById('rulesModal').classList.add('open'); }
function closeRules(){ document.getElementById('rulesModal').classList.remove('open'); }

/* ═══ SIDEBAR ═══ */
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}

/* ═══ TOAST ═══ */
function toast(msg){
  const el = document.getElementById('toast');
  el.textContent = msg; el.classList.add('show');
  setTimeout(()=>el.classList.remove('show'), 3500);
}
</script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body>
</html>
