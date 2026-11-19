<?php
/**
 * EduConnect — Orange Money — Notification IPN
 * URL notif_url enregistrée dans la requête de paiement OM
 * OM envoie : POST application/json
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

$raw_body = file_get_contents('php://input');
$data     = json_decode($raw_body, true);

// ── Vérification HMAC Orange Money ──────────────────────────
$om_secret = $_ENV['OM_WEBHOOK_SECRET'] ?? getenv('OM_WEBHOOK_SECRET');
$om_signature = $_SERVER['HTTP_X_ORANGE_SIGNATURE'] ?? '';

if ($om_secret && $om_signature) {
    $expected = hash_hmac('sha256', $raw_body, $om_secret);
    if (!hash_equals($expected, $om_signature)) {
        http_response_code(401);
        error_log('[EduConnect] OM callback: signature invalide');
        exit;
    }
}

if (!$data) {
    http_response_code(400); exit;
}

$status    = $data['status']   ?? '';
$order_id  = $data['txnid']    ?? ($data['order_id'] ?? '');
$pay_token = $data['token']    ?? '';

if ($status === 'SUCCESS' || $status === '00') {

    $paiement = db_row($conn,
        "SELECT id, frais_id, payeur_id, montant
         FROM paiements
         WHERE (om_order_id = ? OR recu_numero = ?) AND statut = 'en_attente' LIMIT 1",
        'ss', [$order_id, $order_id]
    );

    if ($paiement) {
        db_exec($conn,
            "UPDATE paiements
             SET statut = 'confirme', callback_payload = ?, confirmed_at = NOW()
             WHERE id = ?",
            'si', [$raw_body, (int)$paiement['id']]
        );

        // Mettre à jour frais de scolarité
        $total_paye = db_row($conn,
            "SELECT COALESCE(SUM(montant),0) AS tp FROM paiements
             WHERE frais_id = ? AND statut = 'confirme'",
            'i', [(int)$paiement['frais_id']]
        )['tp'] ?? 0;

        $montant_total = db_row($conn,
            "SELECT montant_total FROM frais_scolarite WHERE id = ?",
            'i', [(int)$paiement['frais_id']]
        )['montant_total'] ?? 1;

        $nouveau_statut = ($total_paye >= $montant_total) ? 'solde' : 'en_cours';
        db_exec($conn,
            "UPDATE frais_scolarite SET statut = ? WHERE id = ?",
            'si', [$nouveau_statut, (int)$paiement['frais_id']]
        );

        // Notifier
        db_exec($conn,
            "INSERT INTO notifications (user_id, type, titre, corps, canal, priorite)
             VALUES (?, 'paiement', 'Paiement Orange Money confirmé ✓',
             ?, 'in_app,email', 'haute')",
            'is', [
                (int)$paiement['payeur_id'],
                'Votre paiement de ' . number_format((float)$paiement['montant'], 0, ',', ' ') . ' FCFA a été confirmé via Orange Money.'
            ]
        );

        db_exec($conn,
            "INSERT INTO audit_log (user_id, action, entite, entite_id, ip_address)
             VALUES (?, 'OM_PAYMENT_CONFIRMED', 'paiements', ?, ?)",
            'iis',
            [(int)$paiement['payeur_id'], (int)$paiement['id'], $_SERVER['REMOTE_ADDR'] ?? '']
        );
    }

} elseif ($status === 'FAILED' || $status === 'CANCELLED') {
    db_exec($conn,
        "UPDATE paiements SET statut = 'echec', callback_payload = ?
         WHERE om_order_id = ? OR recu_numero = ?",
        'sss', [$raw_body, $order_id, $order_id]
    );
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
