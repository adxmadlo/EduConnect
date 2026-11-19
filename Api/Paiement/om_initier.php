<?php
/**
 * EduConnect — Orange Money Business — Initier un paiement
 * POST /Api/Paiement/om_initier.php
 * Body JSON : { frais_id, montant, description }
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']); exit;
}

$input      = json_decode(file_get_contents('php://input'), true);
$frais_id   = (int)($input['frais_id']  ?? 0);
$montant    = (float)($input['montant'] ?? 0);
$description= trim($input['description'] ?? 'Scolarité EduConnect');

if (!$frais_id || $montant <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'frais_id et montant requis']); exit;
}

$om_api_key    = $_ENV['OM_API_KEY']    ?? getenv('OM_API_KEY');
$om_merchant   = $_ENV['OM_MERCHANT_KEY'] ?? getenv('OM_MERCHANT_KEY');
if (!$om_api_key) {
    http_response_code(500);
    echo json_encode(['error' => 'Clé API Orange Money non configurée']); exit;
}

$recu_numero = 'REC-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
$base_url    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/educonnect';

// ── Étape 1 : Obtenir le token d'accès OM ───────────────────
$token_ch = curl_init('https://api.orange.com/oauth/v3/token');
curl_setopt_array($token_ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Basic ' . base64_encode($om_api_key . ':' . $om_merchant),
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_TIMEOUT => 10,
]);
$token_resp = curl_exec($token_ch);
$token_data = json_decode($token_resp, true);
curl_close($token_ch);

$access_token = $token_data['access_token'] ?? '';
if (!$access_token) {
    http_response_code(502);
    echo json_encode(['error' => 'Impossible d\'obtenir le token OM']); exit;
}

// ── Étape 2 : Créer le paiement ──────────────────────────────
$payload = [
    'merchant_key'    => $om_merchant,
    'currency'        => 'OUV',
    'order_id'        => $recu_numero,
    'amount'          => (int)$montant,
    'return_url'      => $base_url . '/Views/Etudiant/etudiant_paiements.php?success=1',
    'cancel_url'      => $base_url . '/Views/Etudiant/etudiant_paiements.php?cancel=1',
    'notif_url'       => $base_url . '/Api/Paiement/om_callback.php',
    'lang'            => 'fr',
    'reference'       => $description,
];

$pay_ch = curl_init('https://api.orange.com/orange-money-webpay/sn/v1/webpayment');
curl_setopt_array($pay_ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
]);
$pay_resp  = curl_exec($pay_ch);
$http_code = curl_getinfo($pay_ch, CURLINFO_HTTP_CODE);
curl_close($pay_ch);

$pay_data  = json_decode($pay_resp, true);
$pay_token = $pay_data['token']          ?? '';
$pay_url   = $pay_data['payment_url']    ?? '';

if (!$pay_url || !$pay_token) {
    http_response_code(502);
    echo json_encode(['error' => 'Réponse OM invalide: ' . ($pay_data['message'] ?? 'Inconnu')]); exit;
}

// ── Enregistrer le paiement en attente ──────────────────────
$uid = (int)$user['id'];
db_exec($conn,
    "INSERT INTO paiements (frais_id, payeur_id, montant, moyen,
     om_order_id, om_token, recu_numero, statut)
     VALUES (?, ?, ?, 'orange_money', ?, ?, ?, 'en_attente')",
    'iidsss',
    [$frais_id, $uid, $montant, $recu_numero, $pay_token, $recu_numero]
);

db_exec($conn,
    "INSERT INTO audit_log (user_id, action, detail, ip_address)
     VALUES (?, 'OM_CHECKOUT_CREATED', ?, ?)",
    'iss',
    [$uid, json_encode(['frais_id'=>$frais_id,'montant'=>$montant,'order_id'=>$recu_numero]),
     $_SERVER['REMOTE_ADDR'] ?? '']
);

echo json_encode([
    'payment_url'  => $pay_url,
    'token'        => $pay_token,
    'recu_numero'  => $recu_numero,
]);
