<?php
/**
 * EduConnect — Wave Business — Webhook Callback
 * URL à enregistrer dans le dashboard Wave Business
 * Cette URL doit être publiquement accessible (pas localhost)
 *
 * Wave envoie : POST application/json
 * En-tête    : X-Wave-Signature: sha256=HMAC_SHA256(secret, body)
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

// ── Lire le body brut ─────────────────────────────────────────
$raw_body = file_get_contents('php://input');
$signature_header = $_SERVER['HTTP_X_WAVE_SIGNATURE'] ?? '';

// ── Vérifier la signature HMAC ───────────────────────────────
$wave_webhook_secret = $_ENV['WAVE_WEBHOOK_SECRET'] ?? getenv('WAVE_WEBHOOK_SECRET');

if ($wave_webhook_secret && $signature_header) {
    $expected = 'sha256=' . hash_hmac('sha256', $raw_body, $wave_webhook_secret);
    if (!hash_equals($expected, $signature_header)) {
        http_response_code(401);
        error_log('[EduConnect] Wave webhook: signature invalide');
        echo json_encode(['error' => 'Signature invalide']);
        exit;
    }
}

$data = json_decode($raw_body, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload invalide']);
    exit;
}

$event_type    = $data['type'] ?? '';
$checkout_id   = $data['data']['id'] ?? '';
$client_ref    = $data['data']['client_reference'] ?? '';
$status        = $data['data']['payment_status'] ?? '';
$wave_fee      = (float)($data['data']['wave_fees'] ?? 0);

// ── Traitement selon le type d'événement ─────────────────────
if ($event_type === 'checkout.session.completed' || $status === 'succeeded') {

    // Trouver le paiement par wave_checkout_id ou recu_numero
    $paiement = db_row($conn,
        "SELECT p.id, p.frais_id, p.payeur_id, p.montant
         FROM paiements p
         WHERE p.wave_checkout_id = ? OR p.recu_numero = ?
         LIMIT 1",
        'ss', [$checkout_id, $client_ref]
    );

    if ($paiement) {
        // Mettre à jour le paiement → confirmé
        db_exec($conn,
            "UPDATE paiements
             SET statut = 'confirme',
                 wave_fee = ?,
                 callback_payload = ?,
                 confirmed_at = NOW()
             WHERE id = ?",
            'dsi',
            [$wave_fee, $raw_body, (int)$paiement['id']]
        );

        // Mettre à jour les frais de scolarité
        $frais = db_row($conn,
            "SELECT f.id, f.montant_total,
                    COALESCE(SUM(CASE WHEN p2.statut='confirme' THEN p2.montant ELSE 0 END),0) AS total_paye
             FROM frais_scolarite f
             LEFT JOIN paiements p2 ON p2.frais_id = f.id AND p2.statut = 'confirme'
             WHERE f.id = ? GROUP BY f.id",
            'i', [(int)$paiement['frais_id']]
        );

        if ($frais) {
            $nouveau_statut = ($frais['total_paye'] + $paiement['montant']) >= $frais['montant_total']
                ? 'solde' : 'en_cours';
            db_exec($conn,
                "UPDATE frais_scolarite SET statut = ? WHERE id = ?",
                'si', [$nouveau_statut, (int)$frais['id']]
            );
        }

        // Notifier l'étudiant/parent
        db_exec($conn,
            "INSERT INTO notifications (user_id, type, titre, corps, canal, priorite)
             VALUES (?, 'paiement', 'Paiement confirmé ✓',
             'Votre paiement de " . number_format($paiement['montant'], 0, ',', ' ') . " FCFA a été confirmé via Wave.',
             'in_app,email', 'haute')",
            'i', [(int)$paiement['payeur_id']]
        );

        // Audit
        db_exec($conn,
            "INSERT INTO audit_log (user_id, action, entite, entite_id, detail, ip_address)
             VALUES (?, 'WAVE_PAYMENT_CONFIRMED', 'paiements', ?, ?, ?)",
            'iiss',
            [(int)$paiement['payeur_id'], (int)$paiement['id'],
             json_encode(['wave_id'=>$checkout_id,'montant'=>$paiement['montant']]),
             $_SERVER['REMOTE_ADDR'] ?? '']
        );

        http_response_code(200);
        echo json_encode(['status' => 'processed']);
    } else {
        error_log('[EduConnect] Wave callback: paiement non trouvé pour wave_id=' . $checkout_id);
        http_response_code(200); // Toujours 200 pour éviter les retries Wave
        echo json_encode(['status' => 'not_found']);
    }

} elseif ($status === 'failed' || $event_type === 'checkout.session.failed') {

    db_exec($conn,
        "UPDATE paiements SET statut = 'echec', callback_payload = ?
         WHERE wave_checkout_id = ? OR recu_numero = ?",
        'sss', [$raw_body, $checkout_id, $client_ref]
    );
    http_response_code(200);
    echo json_encode(['status' => 'marked_failed']);

} else {
    // Événement ignoré
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'type' => $event_type]);
}
