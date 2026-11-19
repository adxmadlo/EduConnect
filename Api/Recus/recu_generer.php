<?php
/**
 * EduConnect — Génération Reçu PDF
 * GET /Api/Recus/recu_generer.php?paiement_id=X
 * Génère un reçu HTML imprimable (ou PDF via TCPDF si disponible)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

$paiement_id = (int)($_GET['paiement_id'] ?? 0);
if (!$paiement_id) {
    http_response_code(400);
    echo "Paramètre paiement_id requis"; exit;
}

// Vérifier que l'utilisateur peut accéder à ce reçu
$uid  = (int)$user['id'];
$role = $user['role'];

$paiement = db_row($conn,
    "SELECT p.id, p.recu_numero, p.montant, p.moyen, p.statut,
            p.created_at, p.confirmed_at, p.wave_fee,
            u.nom AS payeur_nom, u.prenom AS payeur_prenom, u.email AS payeur_email,
            u2.nom AS etud_nom, u2.prenom AS etud_prenom, u2.numero_ucao,
            f.annee_acad, f.plan,
            c.code AS classe_code
     FROM paiements p
     JOIN users u ON u.id = p.payeur_id
     JOIN frais_scolarite f ON f.id = p.frais_id
     JOIN etudiants e ON e.id = f.etudiant_id
     JOIN users u2 ON u2.id = e.user_id
     JOIN classes c ON c.id = e.classe_id
     WHERE p.id = ?
       AND (p.payeur_id = ?
            OR e.user_id = ?
            OR ? IN ('admin','chef_admin'))
     LIMIT 1",
    'iisi',
    [$paiement_id, $uid, $uid, $role]
);

if (!$paiement) {
    http_response_code(403);
    echo "Reçu introuvable ou accès refusé"; exit;
}

if ($paiement['statut'] !== 'confirme') {
    http_response_code(400);
    echo "Reçu disponible uniquement pour les paiements confirmés"; exit;
}

// ── Générer QR code URL (simple — encode recu_numero) ───────
$qr_data     = urlencode($paiement['recu_numero']);
$qr_url      = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={$qr_data}";
$moyen_label = match($paiement['moyen']) {
    'wave'         => '🌊 Wave Business',
    'orange_money' => '🟠 Orange Money',
    'carte'        => '💳 Carte bancaire',
    'virement'     => '🏦 Virement bancaire',
    default        => ucfirst($paiement['moyen']),
};
$date_confirm = $paiement['confirmed_at']
    ? date('d/m/Y à H:i', strtotime($paiement['confirmed_at']))
    : date('d/m/Y à H:i', strtotime($paiement['created_at']));

// ── Mettre à jour l'URL du reçu en BDD ──────────────────────
$recu_url = "/Api/Recus/recu_generer.php?paiement_id={$paiement_id}";
db_exec($conn,
    "UPDATE paiements SET recu_url = ?, qr_code = ? WHERE id = ?",
    'ssi', [$recu_url, $qr_url, $paiement_id]
);

// ── Sortie HTML imprimable ───────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Reçu <?= h($paiement['recu_numero']) ?> — EduConnect</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 13px; color: #1a1a2e; background: #f5f5f5; }
  .receipt { max-width: 650px; margin: 20px auto; background: #fff; border-radius: 12px;
             box-shadow: 0 4px 24px rgba(0,0,0,.12); overflow: hidden; }
  .header { background: linear-gradient(135deg, #326ea9, #1f4a7a); color: #fff; padding: 28px 32px; }
  .header .logo { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
  .header .logo span { color: #a8c8e8; }
  .header .subtitle { font-size: 12px; opacity: .75; }
  .header .recu-num { font-size: 18px; font-weight: 700; margin-top: 12px;
                       background: rgba(255,255,255,.15); border-radius: 8px;
                       padding: 8px 14px; display: inline-block; }
  .status-bar { background: #27ae60; color: #fff; text-align: center;
                padding: 10px; font-weight: 700; font-size: 13px; letter-spacing: .5px; }
  .body { padding: 28px 32px; }
  .section-title { font-size: 11px; font-weight: 700; letter-spacing: 1.5px;
                   text-transform: uppercase; color: #9ca3af; margin: 20px 0 10px; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .info-item { background: #f8f9fb; border-radius: 8px; padding: 10px 14px; }
  .info-label { font-size: 10px; color: #9ca3af; text-transform: uppercase;
                letter-spacing: .8px; margin-bottom: 3px; }
  .info-value { font-size: 13px; font-weight: 600; color: #1a1a2e; }
  .amount-box { background: linear-gradient(135deg, #e8f0f8, #d0e4f5);
                border: 2px solid #326ea9; border-radius: 10px; padding: 20px;
                text-align: center; margin: 20px 0; }
  .amount-box .label { font-size: 11px; color: #326ea9; text-transform: uppercase;
                        letter-spacing: 1px; margin-bottom: 6px; }
  .amount-box .amount { font-size: 32px; font-weight: 700; color: #1f4a7a; }
  .amount-box .currency { font-size: 16px; color: #326ea9; margin-left: 4px; }
  .qr-section { display: flex; align-items: center; gap: 20px; margin-top: 20px;
                 padding: 16px; background: #f8f9fb; border-radius: 10px; }
  .qr-section img { width: 90px; height: 90px; border-radius: 6px; }
  .qr-text { font-size: 11px; color: #4b5563; line-height: 1.6; }
  .footer { background: #f8f9fb; padding: 16px 32px; text-align: center;
             font-size: 11px; color: #9ca3af; border-top: 1px solid #e0e3e8; }
  .divider { border: none; border-top: 1px dashed #e0e3e8; margin: 16px 0; }
  @media print {
    body { background: #fff; }
    .receipt { box-shadow: none; margin: 0; max-width: 100%; }
    .no-print { display: none; }
  }
</style>
</head>
<body>
<div class="receipt">
  <div class="header">
    <div class="logo">Edu<span>Connect</span></div>
    <div class="subtitle">UCAO Saint-Michel — Plateforme Académique</div>
    <div class="recu-num">Reçu N° <?= h($paiement['recu_numero']) ?></div>
  </div>
  <div class="status-bar">✅ PAIEMENT CONFIRMÉ — <?= $date_confirm ?></div>

  <div class="body">
    <div class="amount-box">
      <div class="label">Montant payé</div>
      <div>
        <span class="amount"><?= number_format((float)$paiement['montant'], 0, ',', ' ') ?></span>
        <span class="currency">FCFA</span>
      </div>
      <div style="margin-top:8px;font-size:12px;color:#326ea9"><?= $moyen_label ?></div>
    </div>

    <div class="section-title">Informations étudiant</div>
    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">Nom complet</div>
        <div class="info-value"><?= h($paiement['etud_prenom'] . ' ' . $paiement['etud_nom']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Matricule UCAO</div>
        <div class="info-value"><?= h($paiement['numero_ucao']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Classe</div>
        <div class="info-value"><?= h($paiement['classe_code']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Année académique</div>
        <div class="info-value"><?= h($paiement['annee_acad']) ?></div>
      </div>
    </div>

    <div class="section-title">Informations payeur</div>
    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">Payeur</div>
        <div class="info-value"><?= h($paiement['payeur_prenom'] . ' ' . $paiement['payeur_nom']) ?></div>
      </div>
      <div class="info-item">
        <div class="info-label">Plan de paiement</div>
        <div class="info-value"><?= ucfirst(h($paiement['plan'])) ?></div>
      </div>
    </div>

    <hr class="divider">

    <div class="qr-section">
      <img src="<?= h($qr_url) ?>" alt="QR Code vérification" loading="lazy">
      <div class="qr-text">
        <strong>Vérification du reçu</strong><br>
        Scannez ce QR code pour vérifier l'authenticité de ce reçu.<br>
        Référence : <strong><?= h($paiement['recu_numero']) ?></strong><br>
        <em style="color:#27ae60">✅ Ce reçu fait foi en cas de litige.</em>
      </div>
    </div>

    <div class="no-print" style="margin-top:20px;text-align:center">
      <button onclick="window.print()"
        style="padding:10px 24px;background:#326ea9;color:#fff;border:none;
               border-radius:8px;font-size:13px;cursor:pointer;margin-right:8px">
        🖨️ Imprimer
      </button>
      <a href="javascript:history.back()"
        style="padding:10px 24px;background:#f8f9fb;color:#326ea9;border:1px solid #e0e3e8;
               border-radius:8px;font-size:13px;text-decoration:none">
        ← Retour
      </a>
    </div>
  </div>

  <div class="footer">
    Document généré le <?= date('d/m/Y à H:i') ?> —
    UCAO Saint-Michel, Dakar, Sénégal —
    Ce reçu est officiel et numéroté.
  </div>
</div>
</body>
</html>
