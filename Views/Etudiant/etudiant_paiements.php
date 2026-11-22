<?php
/**
 * EduConnect — etudiant_paiements.php — Version dynamique
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
// DONNÉES DYNAMIQUES — Paiements Étudiant
// ═══════════════════════════════════════════════════════════
$etu_row = db_row($conn, "SELECT id FROM etudiants WHERE user_id = ?", "i", [(int)$user["id"]]);
$etu_id = (int)($etu_row["id"] ?? 0);

$frais = db_row($conn,
    "SELECT f.id AS frais_id, f.montant_total, f.plan, f.statut, f.date_echeance,
            COALESCE(SUM(CASE WHEN p.statut='confirme' THEN p.montant ELSE 0 END),0) AS paye
     FROM frais_scolarite f
     LEFT JOIN paiements p ON p.frais_id = f.id
     WHERE f.etudiant_id = ? AND f.annee_acad = '2025-2026' GROUP BY f.id LIMIT 1",
    "i", [$etu_id]
);
$frais_id      = (int)($frais["frais_id"] ?? 0);
$montant_total = (float)($frais["montant_total"] ?? 500000);
$montant_paye  = (float)($frais["paye"] ?? 0);
$solde_restant = $montant_total - $montant_paye;
$pct_paye      = $montant_total > 0 ? round(($montant_paye / $montant_total) * 100) : 0;

// Historique paiements
$historique = db_query($conn,
    "SELECT p.id, p.recu_numero, p.montant, p.moyen, p.statut, p.created_at, p.recu_url
     FROM paiements p WHERE p.frais_id = ?
     ORDER BY p.created_at DESC",
    "i", [$frais_id]
);
$nb_transactions = count($historique);

// Notifications non lues
$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0","i",[(int)$user["id"]])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Paiements</title>
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
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-bottom:20px}
.stat-box{background:linear-gradient(135deg,var(--blue),var(--blue-lt));color:#fff;border-radius:var(--r);padding:18px;animation:fadeUp .3s ease}.stat-value{font-size:28px;font-weight:700;margin-bottom:4px}.stat-label{font-size:12px;opacity:.9}
.table-container{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease}.table{width:100%;border-collapse:collapse}.table th{padding:12px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}.table td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}.table tr:hover{background:var(--surface2)}.table tr:last-child td{border:none}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-green{background:var(--green-dim);color:var(--green)}.badge-orange{background:var(--orange-dim);color:var(--orange)}.badge-red{background:var(--red-dim);color:var(--red)}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--blue)}.toast.show{transform:translateY(0);opacity:1}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start;gap:12px}.grid{grid-template-columns:1fr}}
</style>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
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
    <button class="nav-item <?= $current_page === 'etudiant_mentor.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_mentor.php'"><i class="bi bi-star"></i><span class="nav-item-txt">Mentorat</span></button>
    <button class="nav-item <?= $current_page === 'etudiant_emploi_du_temps.php' ? 'active' : '' ?>" onclick="location.href='/Views/Etudiant/etudiant_emploi_du_temps.php'"><i class="bi bi-calendar"></i><span class="nav-item-txt">Emploi du temps</span></button>
    <button class="nav-item active" onclick="nav('payments',this)"><i class="bi bi-credit-card"></i><span class="nav-item-txt">Paiements</span></button>
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
    <div class="topbar-brand"><i class="bi bi-credit-card"></i>Paiements</div>
    <div class="tab-nav" id="tabNav">
      <button class="tab-btn active" onclick="setTab('history',this)"><i class="bi bi-receipt"></i>Historique</button>
      <button class="tab-btn" onclick="setTab('invoices',this)"><i class="bi bi-file-earmark-pdf"></i>Factures</button>
    </div>
    <div class="topbar-right">
      <button class="tb-icon" onclick="toast('💳 Effectuer un paiement')" title="Payer"><i class="bi bi-plus-circle"></i></button>
      <button class="tb-icon" onclick="toast('🔔 Vous avez 1 notification')" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <div class="tb-user" onclick="toast('Profil <?= $u_full ?>')"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-credit-card"></i><div><h1>Paiements</h1><p>Historique et factures de vos paiements</p></div></div>
    </div>

    <div class="grid">
      <div class="stat-box"><div class="stat-value">450K</div><div class="stat-label">Total Payé</div></div>
      <div class="stat-box" style="background:linear-gradient(135deg,var(--green),#2ecc71)"><div class="stat-value">0 XOF</div><div class="stat-label">Solde Dû</div></div>
      <div class="stat-box" style="background:linear-gradient(135deg,var(--orange),#f39c12)"><div class="stat-value">12</div><div class="stat-label">Transactions</div></div>
    </div>

    <div class="table-container">
      <table class="table">
        <thead>
          <tr><th>Date</th><th>Description</th><th>Montant</th><th>Méthode</th><th>Statut</th><th>Reçu</th></tr>
        </thead>
        <tbody>
                <?php if (empty($historique)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--txt3);padding:16px">Aucune transaction</td></tr>
                <?php else: foreach ($historique as $tx):
                  $st_class = match($tx["statut"]){
                    "confirme"=>"badge-green","en_attente"=>"badge-orange",
                    "echec"=>"badge-red","rembourse"=>"badge-blue",default=>"badge-orange"
                  };
                  $st_label = match($tx["statut"]){
                    "confirme"=>"Confirmé","en_attente"=>"En attente",
                    "echec"=>"Échec","rembourse"=>"Remboursé",default=>"—"
                  };
                  $moyen_label = match($tx["moyen"]){
                    "wave"=>"Wave","orange_money"=>"Orange Money",
                    "carte"=>"Carte","virement"=>"Virement",default=>ucfirst($tx["moyen"])
                  };
                ?>
                <tr>
                  <td><?= h($tx["recu_numero"]) ?></td>
                  <td><?= number_format((float)$tx["montant"], 0, ",", " ") ?> FCFA</td>
                  <td><?= $moyen_label ?></td>
                  <td><span class="badge <?= $st_class ?>"><?= $st_label ?></span></td>
                  <td><?= h(date("d/m/Y", strtotime($tx["created_at"]))) ?>
                    <?php if ($tx["recu_url"]): ?>
                    <a href="<?= h($tx["recu_url"]) ?>" target="_blank"
                       style="margin-left:6px;color:var(--blue)"><i class="bi bi-download"></i></a>
                    <?php endif; ?>
                  </td>
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
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');setTimeout(()=>el.classList.remove('show'),3000)}
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
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body>
</html>
