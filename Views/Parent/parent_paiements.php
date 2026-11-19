<?php
/**
 * EduConnect — parent_paiements.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Paiements Parent
// ═══════════════════════════════════════════════════════════
// Récupérer l'enfant lié
$enfant = db_row($conn,
    "SELECT u.nom, u.prenom, u.numero_ucao, e.id AS etu_id, c.code AS classe, c.nom AS classe_nom
     FROM etudiants e
     JOIN users u ON u.id = e.user_id
     JOIN classes c ON c.id = e.classe_id
     WHERE e.parent_id = ? LIMIT 1",
    "i", [(int)$user["id"]]
);
$enfant_nom  = $enfant ? h($enfant["prenom"] . " " . $enfant["nom"]) : "Enfant non lié";
$enfant_init = $enfant ? h(substr($enfant["prenom"],0,1) . substr($enfant["nom"],0,1)) : "?";
$enfant_class = $enfant ? h($enfant["classe"]) : "";
$etu_id = (int)($enfant["etu_id"] ?? 0);

// Frais + total payé
$frais = db_row($conn,
    "SELECT f.id AS frais_id, f.montant_total, f.plan, f.statut, f.date_echeance,
            COALESCE(SUM(CASE WHEN p.statut='confirme' THEN p.montant ELSE 0 END),0) AS paye
     FROM frais_scolarite f
     LEFT JOIN paiements p ON p.frais_id = f.id
     WHERE f.etudiant_id = ? AND f.annee_acad = '2025-2026'
     GROUP BY f.id LIMIT 1",
    "i", [$etu_id]
);
$frais_id      = (int)($frais["frais_id"] ?? 0);
$montant_total = (float)($frais["montant_total"] ?? 500000);
$montant_paye  = (float)($frais["paye"] ?? 0);
$solde_restant = $montant_total - $montant_paye;
$pct_paye      = $montant_total > 0 ? round(($montant_paye / $montant_total) * 100) : 0;

// Historique des transactions
$historique = db_query($conn,
    "SELECT p.recu_numero, p.montant, p.moyen, p.statut, p.created_at, p.recu_url, p.qr_code
     FROM paiements p WHERE p.frais_id = ? ORDER BY p.created_at DESC",
    "i", [$frais_id]
);
$nb_transactions = count($historique);

$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0","i",[(int)$user["id"]])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Paiements Parent</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<style>
/* ═══════════════════════════════════════
   TOKENS — identiques à tout le projet
═══════════════════════════════════════ */
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--gold-dim:rgba(212,168,67,.12);
  --purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
  --teal:#0891b2;--teal-dim:rgba(8,145,178,.12);
  /* Parent accent = orange/gold */
  --accent:#e67e22;--accent-dk:#c0392b;--accent-lt:#f39c12;
  --accent-dim:rgba(230,126,34,.12);
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

/* ═══ SIDEBAR — accent orange for parent ═══ */
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:54px}
.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--accent-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(230,126,34,.5)}
.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}
.logo-text span{color:#fbd38d}
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
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--accent-lt);border-radius:0 2px 2px 0}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--accent-lt)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0;transition:opacity var(--tr)}
.sidebar.collapsed .nav-badge{opacity:0}
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
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
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--accent),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1;overflow-y:auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;animation:fadeUp .3s ease}
.page-title{display:flex;align-items:center;gap:10px}
.page-title h1{font-size:22px;font-weight:700;color:var(--txt)}
.page-title i{font-size:26px;color:var(--blue)}
.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.page-actions{display:flex;gap:8px}

/* ═══ CHILD SELECTOR ═══ */
.child-bar{
  display:flex;align-items:center;gap:10px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--r);padding:12px 16px;margin-bottom:16px;
  box-shadow:var(--sh);animation:fadeUp .3s ease;flex-wrap:wrap;
}
.child-bar-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);flex-shrink:0}
.child-chips{display:flex;gap:6px;flex-wrap:wrap;flex:1}
.child-chip{
  display:flex;align-items:center;gap:7px;
  padding:6px 14px;border-radius:20px;
  border:1.5px solid var(--border2);background:var(--surface2);
  cursor:pointer;transition:all var(--tr);font-size:12px;font-weight:500;color:var(--txt2);
}
.child-chip:hover{border-color:var(--accent);color:var(--accent)}
.child-chip.active{border-color:var(--accent);background:var(--accent-dim);color:var(--accent);font-weight:700}
.child-chip-ava{width:22px;height:22px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:9px;color:#fff;flex-shrink:0}

/* ═══ STAT CARDS ═══ */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;animation:fadeUp .3s ease}
.stat-box{border-radius:var(--r);padding:16px 18px;color:#fff;position:relative;overflow:hidden}
.stat-box::after{content:'';position:absolute;bottom:-20px;right:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.08)}
.stat-val{font-size:26px;font-weight:700;line-height:1;margin-bottom:4px}
.stat-lbl{font-size:11px;opacity:.88}
.stat-icon{position:absolute;top:14px;right:16px;font-size:22px;opacity:.35}
.sb-blue  {background:linear-gradient(135deg,var(--blue),var(--blue-lt))}
.sb-green {background:linear-gradient(135deg,var(--green),var(--green-lt))}
.sb-orange{background:linear-gradient(135deg,var(--accent),var(--accent-lt))}
.sb-red   {background:linear-gradient(135deg,var(--red),var(--red-lt))}

/* ═══ PAYMENT PLAN CARD ═══ */
.plan-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  box-shadow:var(--sh);padding:18px;margin-bottom:16px;animation:fadeUp .3s ease;
}
.plan-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.plan-title{font-size:14px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.plan-title i{color:var(--blue);font-size:16px}

.progress-track{background:var(--border);border-radius:10px;height:10px;overflow:hidden;margin-bottom:8px}
.progress-fill{height:100%;border-radius:10px;transition:width .8s cubic-bezier(.4,0,.2,1)}

.tranches{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:14px}
.tranche{border:1.5px solid var(--border);border-radius:var(--r-sm);padding:10px 12px;text-align:center;transition:all var(--tr)}
.tranche.paid{border-color:var(--green);background:var(--green-dim)}
.tranche.due  {border-color:var(--accent);background:var(--accent-dim)}
.tranche.next {border-color:var(--blue);background:var(--blue-xlt)}
.tranche.upcoming{border-color:var(--border2);background:var(--surface2)}
.tranche-num{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.tranche-amount{font-size:14px;font-weight:700}
.tranche-date{font-size:10px;color:var(--txt3);margin-top:2px}

/* ═══ PAYMENT MODAL ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:12px;padding:24px;width:480px;max-width:100%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease;max-height:90vh;overflow-y:auto}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.modal-title{font-size:16px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:16px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red)}

/* Payment method selector */
.pay-methods{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}
.pay-method-btn{
  padding:12px 10px;border:2px solid var(--border);border-radius:var(--r-sm);
  display:flex;flex-direction:column;align-items:center;gap:5px;cursor:pointer;
  transition:all var(--tr);background:var(--surface);
}
.pay-method-btn:hover{border-color:var(--blue);background:var(--blue-xlt)}
.pay-method-btn.selected{border-color:var(--blue);background:var(--blue-xlt)}
.pay-method-btn i{font-size:22px}
.pay-method-btn span{font-size:11px;font-weight:600;color:var(--txt2)}
.pay-method-btn.selected span{color:var(--blue)}

.modal-field{margin-bottom:14px}
.modal-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px}
.modal-input{width:100%;padding:9px 12px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px;color:var(--txt);background:var(--surface);transition:border var(--tr)}
.modal-input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(50,110,169,.1)}
.modal-amount-display{
  background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);
  padding:14px 16px;text-align:center;margin-bottom:14px;
}
.mad-label{font-size:11px;color:var(--txt3);margin-bottom:4px}
.mad-val{font-size:28px;font-weight:700;color:var(--blue)}
.mad-note{font-size:10px;color:var(--txt3);margin-top:2px}
.modal-notice{
  background:var(--blue-xlt);border-left:3px solid var(--blue);
  border-radius:var(--r-sm);padding:10px 12px;font-size:11.5px;color:var(--blue-dk);
  margin-bottom:14px;line-height:1.5;
}

/* ═══ TABLES ═══ */
.table-container{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;margin-bottom:16px}
.table-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.table-head-title{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:7px}
.table-head-title i{color:var(--blue);font-size:14px}
.table{width:100%;border-collapse:collapse}
.table th{padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}
.table td{padding:11px 14px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}
.table tr:hover{background:var(--surface2)}
.table tr:last-child td{border:none}

/* ═══ BADGES ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--accent)}
.badge-red{background:var(--red-dim);color:var(--red)}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent}
.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}
.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-success{background:var(--green);color:#fff;border-color:var(--green)}
.btn-success:hover{background:var(--green-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.btn-sm{padding:5px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ CHART ═══ */
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:16px;animation:fadeUp .3s ease;margin-bottom:16px}
.card-title{font-size:12px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.card-title i{color:var(--blue)}
.chart-container{position:relative;height:220px}

/* ═══ RECEIPT PREVIEW ═══ */
.receipt-preview{
  background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);
  padding:16px;font-size:12px;line-height:1.7;
}
.receipt-row{display:flex;justify-content:space-between;margin-bottom:4px}
.receipt-row.total{font-weight:700;font-size:13px;padding-top:8px;margin-top:4px;border-top:1px solid var(--border2)}
.receipt-code{text-align:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--border2);font-size:11px;color:var(--txt3)}
.receipt-code strong{color:var(--blue);font-size:14px;display:block}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--accent);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1100px){.stats-row{grid-template-columns:1fr 1fr}.tranches{grid-template-columns:1fr 1fr}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start;gap:10px}.stats-row{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<!-- ════════════════ SIDEBAR ════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-people-fill"></i></div>
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
    <div class="nav-section">Tableau de bord</div>
    <a href="parent_dashboard.php" class="nav-item">
      <i class="bi bi-speedometer2"></i>
      <span class="nav-item-txt">Vue d'ensemble</span>
    </a>

    <div class="nav-section">Mon enfant</div>
    <a href="parent_notes.php" class="nav-item">
      <i class="bi bi-graph-up"></i>
      <span class="nav-item-txt">Notes & Bulletins</span>
    </a>
    <a href="parent_paiements.php" class="nav-item active">
      <i class="bi bi-credit-card"></i>
      <span class="nav-item-txt">Paiements</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
    <a href="parent_emploi_du_temps.php" class="nav-item">
      <i class="bi bi-calendar3"></i>
      <span class="nav-item-txt">Emploi du temps</span>
    </a>
    <a href="parent_messages.php" class="nav-item">
      <i class="bi bi-chat-dots"></i>
      <span class="nav-item-txt">Messages</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
    <a href="parent_notifications.php" class="nav-item">
      <i class="bi bi-bell"></i>
      <span class="nav-item-txt">Notifications</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>

    <div class="nav-section">Compte</div>
    <a href="parent_profil.php" class="nav-item">
      <i class="bi bi-person-circle"></i>
      <span class="nav-item-txt">Mon Profil</span>
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Parent/parent_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role">Parent · 2 enfants</div>
      </div>
    </div>
  
  <a href="/Views/Parent/parent_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- ════════════════ MAIN ════════════════ -->
<main class="main" id="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-brand">
      <i class="bi bi-credit-card"></i>
      Paiements
    </div>

    <div class="tab-nav">
      <button class="tab-btn active" onclick="setView('overview',this)">
        <i class="bi bi-wallet2"></i>Aperçu
      </button>
      <button class="tab-btn" onclick="setView('history',this)">
        <i class="bi bi-receipt"></i>Historique
      </button>
      <button class="tab-btn" onclick="setView('receipts',this)">
        <i class="bi bi-file-earmark-pdf"></i>Reçus
      </button>
    </div>

    <div class="topbar-right">
      <button class="tb-icon" title="Effectuer un paiement" onclick="openPayModal()">
        <i class="bi bi-plus-circle-fill" style="color:var(--accent)"></i>
      </button>
      <button class="tb-icon" title="Notifications">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <div class="tb-user">
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
        <i class="bi bi-credit-card"></i>
        <div>
          <h1>Suivi des Paiements</h1>
          <p>Frais de scolarité et transactions — Année 2025–2026</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="toast('📥 Export relevé PDF téléchargé')">
          <i class="bi bi-download"></i>Exporter
        </button>
        <button class="btn btn-success" onclick="openPayModal()">
          <i class="bi bi-plus-circle"></i>Effectuer un paiement
        </button>
      </div>
    </div>

    <!-- Child selector -->
    <div class="child-bar">
      <span class="child-bar-label"><i class="bi bi-people-fill" style="color:var(--accent)"></i> Enfant :</span>
      <div class="child-chips">
        <div class="child-chip active" onclick="selectChild('amadou',this)">
          <div class="child-chip-ava" style="background:var(--blue)">AS</div>
          <?= $enfant_nom ?? "—" ?>
          <span class="badge badge-blue" style="font-size:9px">L2 Info</span>
        </div>
        <div class="child-chip" onclick="selectChild('mariama',this)">
          <div class="child-chip-ava" style="background:var(--green)">MS</div>
          Mariama Sow
          <span class="badge badge-orange" style="font-size:9px">L1 Droit</span>
        </div>
      </div>
      <div style="font-size:11px;color:var(--txt3);flex-shrink:0">
        <i class="bi bi-info-circle"></i> Cliquez pour changer d'enfant
      </div>
    </div>

    <!-- ── OVERVIEW VIEW ── -->
    <div id="view-overview">

      <!-- KPI stats -->
      <div class="stats-row">
        <div class="stat-box sb-green">
          <i class="bi bi-check-circle-fill stat-icon"></i>
          <div class="stat-val" id="statPaye"><?= number_format($montant_paye, 0, ",", " ") ?></div>
          <div class="stat-lbl">Total payé (FCFA)</div>
        </div>
        <div class="stat-box sb-red">
          <i class="bi bi-exclamation-circle-fill stat-icon"></i>
          <div class="stat-val" id="statDu"><?= number_format($solde_restant, 0, ",", " ") ?></div>
          <div class="stat-lbl">Solde restant (FCFA)</div>
        </div>
        <div class="stat-box sb-blue">
          <i class="bi bi-calendar-check-fill stat-icon"></i>
          <div class="stat-val" id="statProchaine">15 Jan</div>
          <div class="stat-lbl">Prochaine échéance</div>
        </div>
        <div class="stat-box sb-orange">
          <i class="bi bi-bar-chart-fill stat-icon"></i>
          <div class="stat-val" id="statPct"><?= $pct_paye ?>%</div>
          <div class="stat-lbl">Taux de recouvrement</div>
        </div>
      </div>

      <!-- Payment plan -->
      <div class="plan-card">
        <div class="plan-card-head">
          <div class="plan-title"><i class="bi bi-calendar-range"></i>Plan de paiement — <span id="planChildName"><?= $enfant_nom ?? "—" ?></span></div>
          <span class="badge badge-blue" id="planBadge">Trimestriel · 600 000 FCFA/an</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <span style="font-size:11px;font-weight:600;color:var(--txt3)">Progression annuelle</span>
          <span style="font-size:12px;font-weight:700;color:var(--green)" id="planPct">75% payé</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" id="planBar" style="width:75%;background:linear-gradient(90deg,var(--green),var(--green-lt))"></div>
        </div>
        <div class="tranches" id="tranchesGrid"></div>
      </div>

      <!-- 2-col: chart + quick pay -->
      <div style="display:grid;grid-template-columns:1fr 320px;gap:16px;margin-bottom:16px">
        <div class="card">
          <div class="card-title"><i class="bi bi-bar-chart"></i>Historique des paiements</div>
          <div class="chart-container"><canvas id="payChart"></canvas></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px">
          <!-- Quick pay alert -->
          <div style="background:linear-gradient(135deg,var(--accent),var(--accent-lt));border-radius:var(--r);padding:16px;color:#fff;animation:fadeUp .3s ease">
            <div style="font-size:11px;opacity:.85;margin-bottom:4px"><i class="bi bi-exclamation-circle"></i> Prochaine échéance</div>
            <div style="font-size:20px;font-weight:700;margin-bottom:2px" id="nextAmount">150 000 FCFA</div>
            <div style="font-size:11px;opacity:.85;margin-bottom:14px" id="nextDate">À payer avant le 15 janvier 2026</div>
            <button class="btn" style="background:#fff;color:var(--accent);width:100%;justify-content:center;font-weight:700" onclick="openPayModal()">
              <i class="bi bi-lightning-charge-fill"></i>Payer maintenant
            </button>
          </div>
          <!-- Payment methods summary -->
          <div class="card" style="padding:14px;flex:1">
            <div class="card-title"><i class="bi bi-phone"></i>Moyens acceptés</div>
            <div style="display:flex;flex-direction:column;gap:8px">
              <div style="display:flex;align-items:center;gap:10px;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm)">
                <div style="width:32px;height:32px;background:var(--blue-dim);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:16px"><i class="bi bi-phone-fill"></i></div>
                <div><div style="font-size:12px;font-weight:600">Wave</div><div style="font-size:10px;color:var(--txt3)">Instantané · Sans frais</div></div>
                <span class="badge badge-green" style="margin-left:auto">Recommandé</span>
              </div>
              <div style="display:flex;align-items:center;gap:10px;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm)">
                <div style="width:32px;height:32px;background:var(--orange-dim);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:16px"><i class="bi bi-phone"></i></div>
                <div><div style="font-size:12px;font-weight:600">Orange Money</div><div style="font-size:10px;color:var(--txt3)">Instantané</div></div>
              </div>
              <div style="display:flex;align-items:center;gap:10px;padding:8px;border:1px solid var(--border);border-radius:var(--r-sm)">
                <div style="width:32px;height:32px;background:var(--blue-dim);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:16px"><i class="bi bi-credit-card-fill"></i></div>
                <div><div style="font-size:12px;font-weight:600">Carte bancaire</div><div style="font-size:10px;color:var(--txt3)">Visa / Mastercard</div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /overview -->

    <!-- ── HISTORY VIEW ── -->
    <div id="view-history" style="display:none">
      <div class="table-container">
        <div class="table-head">
          <div class="table-head-title"><i class="bi bi-receipt"></i>Historique des transactions — <span id="histChildName"><?= $enfant_nom ?? "—" ?></span></div>
          <div style="display:flex;gap:6px">
            <select style="padding:5px 10px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:11px;color:var(--txt2)" onchange="toast('Filtre appliqué')">
              <option>Toutes les années</option><option selected>2025–2026</option><option>2024–2025</option>
            </select>
            <button class="btn btn-sm btn-secondary" onclick="toast('📥 Export CSV téléchargé')"><i class="bi bi-download"></i>Export</button>
          </div>
        </div>
        <table class="table">
          <thead>
            <tr><th>Date</th><th>Description</th><th>Tranche</th><th>Montant</th><th>Méthode</th><th>Statut</th><th>Reçu</th></tr>
          </thead>
          <tbody id="historyBody"></tbody>
        </table>
      </div>
    </div><!-- /history -->

    <!-- ── RECEIPTS VIEW ── -->
    <div id="view-receipts" style="display:none">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" id="receiptsGrid"></div>
    </div><!-- /receipts -->

  </div><!-- /content -->
</main>

<!-- ══ PAYMENT MODAL ══ -->
<div class="modal-overlay" id="payModal" onclick="if(event.target===this)closePayModal()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-credit-card-fill" style="color:var(--blue)"></i>Effectuer un paiement</div>
      <button class="modal-close" onclick="closePayModal()">✕</button>
    </div>

    <!-- Amount display -->
    <div class="modal-amount-display">
      <div class="mad-label">Montant de la prochaine tranche</div>
      <div class="mad-val" id="modalAmount">150 000 FCFA</div>
      <div class="mad-note">Pour — <strong id="modalChildName"><?= $enfant_nom ?? "—" ?></strong> — Tranche T3 · Scolarité 2025–2026</div>
    </div>

    <!-- Payment method -->
    <div class="modal-field">
      <div class="modal-label">Moyen de paiement</div>
      <div class="pay-methods">
        <div class="pay-method-btn selected" onclick="payerWave(<?= $frais_id ?>, <?= $solde_restant ?>)">
          <i class="bi bi-phone-fill" style="color:var(--blue)"></i>
          <span>Wave</span>
        </div>
        <div class="pay-method-btn" onclick="payerOM(<?= $frais_id ?>, <?= $solde_restant ?>)">
          <i class="bi bi-phone" style="color:var(--accent)"></i>
          <span>Orange Money</span>
        </div>
        <div class="pay-method-btn" onclick="selectPayMethod(this,'Carte bancaire')">
          <i class="bi bi-credit-card-fill" style="color:var(--blue)"></i>
          <span>Carte bancaire</span>
        </div>
        <div class="pay-method-btn" onclick="selectPayMethod(this,'Virement')">
          <i class="bi bi-bank" style="color:var(--txt2)"></i>
          <span>Virement</span>
        </div>
      </div>
    </div>

    <!-- Phone number (Wave / OM) -->
    <div class="modal-field" id="phoneField">
      <div class="modal-label">Numéro de téléphone</div>
      <input class="modal-input" type="tel" id="payPhone" placeholder="+221 7X XXX XX XX">
    </div>

    <!-- Notice -->
    <div class="modal-notice">
      <i class="bi bi-shield-check" style="color:var(--blue)"></i>
      <strong>Paiement sécurisé EduConnect.</strong> Un reçu PDF avec QR code vous sera envoyé immédiatement après confirmation. Le statut «&nbsp;À jour&nbsp;» est mis à jour en temps réel — aucune exclusion possible.
    </div>

    <div style="display:flex;gap:10px">
      <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closePayModal()">Annuler</button>
      <button class="btn btn-success" style="flex:2;justify-content:center" onclick="confirmPayment()">
        <i class="bi bi-lock-fill"></i>Confirmer le paiement
      </button>
    </div>
  </div>
</div>

<!-- ══ RECEIPT MODAL ══ -->
<div class="modal-overlay" id="receiptModal" onclick="if(event.target===this)document.getElementById('receiptModal').classList.remove('open')">
  <div class="modal-box" style="width:400px">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-file-earmark-check-fill" style="color:var(--green)"></i>Reçu de paiement</div>
      <button class="modal-close" onclick="document.getElementById('receiptModal').classList.remove('open')">✕</button>
    </div>
    <div class="receipt-preview" id="receiptBody"></div>
    <div style="display:flex;gap:8px;margin-top:16px">
      <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="toast('📥 PDF téléchargé');document.getElementById('receiptModal').classList.remove('open')">
        <i class="bi bi-download"></i>Télécharger PDF
      </button>
      <button class="btn btn-primary" style="flex:1;justify-content:center" onclick="toast('📤 Partagé par email');document.getElementById('receiptModal').classList.remove('open')">
        <i class="bi bi-envelope"></i>Envoyer par email
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ════════════════════════════════
   DATA
════════════════════════════════ */
const CHILDREN = {
  amadou: {
    name: '<?= $enfant_nom ?? "Étudiant" ?>',
    classe: 'L2 Informatique',
    plan: 'Trimestriel',
    total: 600000,
    paid: 450000,
    pct: 75,
    nextAmount: 150000,
    nextDate: '15 janvier 2026',
    tranches: [
      { label:'T1', amount:150000, date:'15 Sep 2025', status:'paid' },
      { label:'T2', amount:150000, date:'15 Nov 2025', status:'paid' },
      { label:'T3', amount:150000, date:'15 Jan 2026', status:'due'  },
      { label:'T4', amount:150000, date:'15 Mar 2026', status:'upcoming' },
    ],
    history: [
      { date:'15 Nov 2025', desc:'Frais de scolarité S1',  tranche:'T2', amount:150000, method:'Wave',         status:'paid', code:'REC-2026-0892' },
      { date:'15 Sep 2025', desc:'Frais de scolarité S1',  tranche:'T1', amount:150000, method:'Orange Money', status:'paid', code:'REC-2026-0741' },
      { date:'10 Sep 2025', desc:'Frais d\'inscription',    tranche:'—',  amount:25000,  method:'Wave',         status:'paid', code:'REC-2026-0703' },
    ],
  },
  mariama: {
    name: 'Mariama Sow',
    classe: 'L1 Droit',
    plan: 'Semestriel',
    total: 500000,
    paid: 250000,
    pct: 50,
    nextAmount: 250000,
    nextDate: '1 février 2026',
    tranches: [
      { label:'S1', amount:250000, date:'01 Sep 2025', status:'paid' },
      { label:'S2', amount:250000, date:'01 Fév 2026', status:'due'  },
      { label:'—',  amount:0,     date:'—',           status:'upcoming' },
      { label:'—',  amount:0,     date:'—',           status:'upcoming' },
    ],
    history: [
      { date:'01 Sep 2025', desc:'Frais de scolarité S1', tranche:'S1', amount:250000, method:'Carte', status:'paid', code:'REC-2026-0688' },
      { date:'28 Août 2025',desc:'Frais d\'inscription',   tranche:'—',  amount:20000,  method:'Wave',  status:'paid', code:'REC-2026-0651' },
    ],
  }
};

let currentChild = 'amadou';
let selectedMethod = 'Wave';
let chartRef = null;

/* ════ INIT ════ */
document.addEventListener('DOMContentLoaded', () => {
  renderChild('amadou');
  buildChart();
});

/* ════ CHILD SELECT ════ */
function selectChild(key, btn){
  currentChild = key;
  document.querySelectorAll('.child-chip').forEach(c=>c.classList.remove('active'));
  btn.classList.add('active');
  renderChild(key);
  updateChart();
  toast('👤 Enfant sélectionné : ' + CHILDREN[key].name);
}

function renderChild(key){
  const c = CHILDREN[key];

  // Stats
  document.getElementById('statPaye').textContent     = c.paid.toLocaleString('fr-FR');
  document.getElementById('statDu').textContent       = (c.total - c.paid).toLocaleString('fr-FR');
  document.getElementById('statProchaine').textContent = c.nextDate.split(' ')[0]+' '+c.nextDate.split(' ')[1];
  document.getElementById('statPct').textContent      = c.pct + '%';

  // Plan
  document.getElementById('planChildName').textContent = c.name;
  document.getElementById('planBadge').textContent     = c.plan + ' · ' + c.total.toLocaleString('fr-FR') + ' FCFA/an';
  document.getElementById('planPct').textContent       = c.pct + '% payé';
  document.getElementById('planBar').style.width       = c.pct + '%';

  // Tranches
  const trancheColors = { paid:'var(--green)', due:'var(--accent)', next:'var(--blue)', upcoming:'var(--txt3)' };
  document.getElementById('tranchesGrid').innerHTML = c.tranches.map(t => {
    if(t.amount === 0) return `<div class="tranche upcoming"><div class="tranche-num" style="color:var(--txt3)">—</div><div class="tranche-amount" style="color:var(--txt3)">—</div><div class="tranche-date">—</div></div>`;
    const icon = t.status==='paid'?'✓':t.status==='due'?'!':'○';
    const color = trancheColors[t.status] || 'var(--txt3)';
    return `<div class="tranche ${t.status}">
      <div class="tranche-num" style="color:${color}">${icon} ${t.label}</div>
      <div class="tranche-amount" style="color:${color}">${(t.amount/1000).toFixed(0)}k FCFA</div>
      <div class="tranche-date">${t.date}</div>
    </div>`;
  }).join('');

  // Next payment widget
  document.getElementById('nextAmount').textContent = c.nextAmount.toLocaleString('fr-FR') + ' FCFA';
  document.getElementById('nextDate').textContent   = 'À payer avant le ' + c.nextDate;

  // History
  document.getElementById('histChildName').textContent = c.name;
  document.getElementById('historyBody').innerHTML = c.history.map(h => `
    <tr>
      <td>${h.date}</td>
      <td style="font-weight:500;color:var(--txt)">${h.desc}</td>
      <td><span class="badge badge-blue">${h.tranche}</span></td>
      <td><strong>${h.amount.toLocaleString('fr-FR')} FCFA</strong></td>
      <td>${methodBadge(h.method)}</td>
      <td><span class="badge badge-green"><i class="bi bi-check-circle"></i>Payé</span></td>
      <td>
        <button class="btn btn-sm btn-secondary" onclick='showReceipt(${JSON.stringify(h)})'>
          <i class="bi bi-eye"></i>Voir
        </button>
      </td>
    </tr>`).join('');

  // Receipts grid
  document.getElementById('receiptsGrid').innerHTML = c.history.map(h => `
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:16px;box-shadow:var(--sh);animation:fadeUp .3s ease">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div style="font-size:13px;font-weight:700;color:var(--txt)">${h.desc}</div>
        <span class="badge badge-green"><i class="bi bi-check-circle"></i>Payé</span>
      </div>
      <div style="font-size:11px;color:var(--txt3);margin-bottom:3px"><i class="bi bi-calendar-event"></i> ${h.date}</div>
      <div style="font-size:11px;color:var(--txt3);margin-bottom:3px"><i class="bi bi-tag"></i> ${h.code}</div>
      <div style="font-size:16px;font-weight:700;color:var(--blue);margin:10px 0">${h.amount.toLocaleString('fr-FR')} FCFA</div>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sm btn-secondary" style="flex:1;justify-content:center" onclick='showReceipt(${JSON.stringify(h)})'>
          <i class="bi bi-eye"></i>Aperçu
        </button>
        <button class="btn btn-sm btn-primary" style="flex:1;justify-content:center" onclick="toast('📥 ${h.code} téléchargé')">
          <i class="bi bi-download"></i>PDF
        </button>
      </div>
    </div>`).join('');

  // Modal defaults
  document.getElementById('modalAmount').textContent   = c.nextAmount.toLocaleString('fr-FR') + ' FCFA';
  document.getElementById('modalChildName').textContent = c.name;
}

function methodBadge(m){
  const map = {
    'Wave':         '<span class="badge badge-blue"><i class="bi bi-phone-fill"></i>Wave</span>',
    'Orange Money': '<span class="badge badge-orange"><i class="bi bi-phone"></i>Orange Money</span>',
    'Carte':        '<span class="badge badge-blue"><i class="bi bi-credit-card-fill"></i>Carte</span>',
    'Virement':     '<span class="badge"><i class="bi bi-bank"></i>Virement</span>',
  };
  return map[m] || m;
}

/* ════ CHART ════ */
function buildChart(){
  const ctx = document.getElementById('payChart').getContext('2d');
  chartRef = new Chart(ctx,{
    type:'bar',
    data:{
      labels: <?= json_encode(array_map(fn($h)=>date('d/m',strtotime($h['created_at'])),$historique)) ?>,
      datasets:[
        { label:'Amadou',  data: <?= json_encode(array_map(fn($h)=>$h['statut']==='confirme'?(float)$h['montant']:0, $historique)) ?>,     backgroundColor:'rgba(50,110,169,.75)',  borderRadius:5 },
        { label:'Mariama', data: <?= json_encode(array_map(fn($h)=>(float)$h['montant'],$historique)) ?>,     backgroundColor:'rgba(230,126,34,.65)',  borderRadius:5 },
      ]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{family:'Ubuntu',size:11},boxWidth:10}}},scales:{x:{grid:{display:false},ticks:{font:{family:'Ubuntu',size:10}}},y:{grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{family:'Ubuntu',size:10},callback:v=>(v/1000)+'k'}}}}
  });
}
function updateChart(){
  if(!chartRef) return;
  const c = CHILDREN[currentChild];
  chartRef.data.datasets[0].data = c.history.filter(h=>h.amount>0).map(h=>h.amount);
  chartRef.update();
}

/* ════ VIEW SWITCH ════ */
function setView(v, btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  ['overview','history','receipts'].forEach(id=>{
    document.getElementById('view-'+id).style.display = id===v ? '' : 'none';
  });
}

/* ════ PAYMENT MODAL ════ */
function openPayModal(){ document.getElementById('payModal').classList.add('open'); }
function closePayModal(){ document.getElementById('payModal').classList.remove('open'); }

function selectPayMethod(btn, method){
  selectedMethod = method;
  document.querySelectorAll('.pay-method-btn').forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('phoneField').style.display = (method==='Wave'||method==='Orange Money') ? '' : 'none';
}

function confirmPayment(){
  const phone = document.getElementById('payPhone').value.trim();
  if((selectedMethod==='Wave'||selectedMethod==='Orange Money') && !phone){
    toast('⚠️ Veuillez saisir votre numéro de téléphone');
    return;
  }
  const c = CHILDREN[currentChild];
  const code = 'REC-2026-' + String(Math.floor(Math.random()*9000)+1000);
  closePayModal();
  toast(`✅ Paiement de ${c.nextAmount.toLocaleString('fr-FR')} FCFA confirmé via ${selectedMethod} — Reçu ${code}`);

  // Add to history
  const today = new Date().toLocaleDateString('fr-FR',{day:'2-digit',month:'short',year:'numeric'});
  c.history.unshift({ date:today, desc:'Frais de scolarité — Paiement en ligne', tranche:c.tranches.find(t=>t.status==='due')?.label||'—', amount:c.nextAmount, method:selectedMethod, status:'paid', code });
  c.paid += c.nextAmount;
  c.pct = Math.round(c.paid/c.total*100);
  const dueTranche = c.tranches.find(t=>t.status==='due');
  if(dueTranche) dueTranche.status = 'paid';
  renderChild(currentChild);
}

/* ════ RECEIPT MODAL ════ */
function showReceipt(h){
  const c = CHILDREN[currentChild];
  document.getElementById('receiptBody').innerHTML = `
    <div style="text-align:center;margin-bottom:14px">
      <div style="font-size:24px;margin-bottom:4px">🎓</div>
      <div style="font-size:14px;font-weight:700;color:var(--blue)">EduConnect — UCAO Saint-Michel</div>
      <div style="font-size:11px;color:var(--txt3)">Reçu officiel de paiement</div>
    </div>
    <div class="receipt-row"><span>Référence</span><strong style="color:var(--blue)">${h.code}</strong></div>
    <div class="receipt-row"><span>Étudiant</span><span>${c.name}</span></div>
    <div class="receipt-row"><span>Filière</span><span>${c.classe}</span></div>
    <div class="receipt-row"><span>Description</span><span>${h.desc}</span></div>
    <div class="receipt-row"><span>Tranche</span><span>${h.tranche}</span></div>
    <div class="receipt-row"><span>Date</span><span>${h.date}</span></div>
    <div class="receipt-row"><span>Moyen</span><span>${h.method}</span></div>
    <div class="receipt-row total"><span>Montant payé</span><span style="color:var(--green)">${h.amount.toLocaleString('fr-FR')} FCFA</span></div>
    <div class="receipt-code">
      Ce document numérique fait foi.<br>
      <strong>${h.code}</strong>
    </div>`;
  document.getElementById('receiptModal').classList.add('open');
}

/* ════ SIDEBAR / TOAST ════ */
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}
let toastT;
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;el.classList.add('show');
  clearTimeout(toastT);toastT=setTimeout(()=>el.classList.remove('show'),3500);
}
</script>

<script>
// ── Paiement Wave ────────────────────────────────────────────
async function payerWave(fraisId, montant) {
    const btn = event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ Connexion Wave...</span>';
    try {
        const resp = await fetch('/Api/Paiement/wave_initier.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                frais_id: fraisId,
                montant: montant,
                description: 'Frais de scolarité — EduConnect'
            })
        });
        const data = await resp.json();
        if (data.checkout_url) {
            window.location.href = data.checkout_url;
        } else {
            alert('Erreur Wave: ' + (data.error || 'Réponse invalide'));
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lightning-fill"></i> Payer avec Wave';
        }
    } catch(e) {
        alert('Erreur réseau. Vérifiez votre connexion.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lightning-fill"></i> Payer avec Wave';
    }
}

// ── Paiement Orange Money ────────────────────────────────────
async function payerOM(fraisId, montant) {
    const btn = event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳ Connexion Orange Money...</span>';
    try {
        const resp = await fetch('/Api/Paiement/om_initier.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                frais_id: fraisId,
                montant: montant,
                description: 'Frais de scolarité — EduConnect'
            })
        });
        const data = await resp.json();
        if (data.payment_url) {
            window.location.href = data.payment_url;
        } else {
            alert('Erreur Orange Money: ' + (data.error || 'Réponse invalide'));
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-circle-fill" style="color:#FF7900"></i> Payer avec Orange Money';
        }
    } catch(e) {
        alert('Erreur réseau. Vérifiez votre connexion.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-circle-fill" style="color:#FF7900"></i> Payer avec Orange Money';
    }
}

// ── Vérifier succès/erreur après retour paiement ─────────────
(function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('success') === '1') {
        const div = document.createElement('div');
        div.style.cssText = 'position:fixed;top:70px;right:20px;background:#27ae60;color:#fff;padding:14px 20px;border-radius:8px;font-size:13px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2)';
        div.innerHTML = '✅ Paiement confirmé ! Votre reçu est disponible ci-dessous.';
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 5000);
        // Nettoyer l'URL
        history.replaceState({}, '', window.location.pathname);
    } else if (params.get('error') === '1') {
        const div = document.createElement('div');
        div.style.cssText = 'position:fixed;top:70px;right:20px;background:#C41E3A;color:#fff;padding:14px 20px;border-radius:8px;font-size:13px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.2)';
        div.innerHTML = '⚠️ Paiement échoué ou annulé. Réessayez.';
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 5000);
        history.replaceState({}, '', window.location.pathname);
    }
})();
</script>
</body>
</html>
