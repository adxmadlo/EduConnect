<?php
/**
 * EduConnect — Wave Business API — Initier un paiement
 * POST /Api/Paiement/wave_initier.php
 * Body JSON : { frais_id, montant, description }
 * Returns   : { checkout_url } ou { error }
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// Vérifier méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Lire le body JSON
$input = json_decode(file_get_contents('php://input'), true);
$frais_id   = (int)($input['frais_id']   ?? 0);
$montant    = (float)($input['montant']  ?? 0);
$description= trim($input['description']?? 'Scolarité EduConnect');

if (!$frais_id || $montant <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'frais_id et montant requis']);
    exit;
}

// ── Clé API Wave (depuis variable d'environnement) ──────────
$wave_api_key = $_ENV['WAVE_API_KEY'] ?? getenv('WAVE_API_KEY');
if (!$wave_api_key) {
    http_response_code(500);
    echo json_encode(['error' => 'Clé API Wave non configurée']);
    exit;
}

// ── Générer numéro de reçu unique ────────────────────────────
$recu_numero = 'REC-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

// ── Appel API Wave Business ──────────────────────────────────
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . '/educonnect';

$payload = [
    'currency'          => 'XOF',
    'amount'            => (int)$montant,
    'error_url'         => $base_url . '/Views/Etudiant/etudiant_paiements.php?error=1',
    'success_url'       => $base_url . '/Views/Etudiant/etudiant_paiements.php?success=1',
    'payment_reason'    => $description,
    'client_reference'  => $recu_numero,
];

$ch = curl_init('https://api.wave.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $wave_api_key,
        'Content-Type: application/json',
        'Idempotency-Key: ' . $recu_numero,
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(502);
    echo json_encode(['error' => 'Erreur réseau: ' . $curl_error]);
    exit;
}

$data = json_decode($response, true);

if ($http_code !== 200 || empty($data['wave_launch_url'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Erreur Wave: ' . ($data['message'] ?? 'Réponse invalide')]);
    exit;
}

// ── Enregistrer le paiement en attente ──────────────────────
$uid = (int)$user['id'];
$wave_id = $data['id'] ?? '';

db_exec($conn,
    "INSERT INTO paiements (frais_id, payeur_id, montant, moyen,
     wave_checkout_id, recu_numero, statut)
     VALUES (?, ?, ?, 'wave', ?, ?, 'en_attente')",
    'iidss',
    [$frais_id, $uid, $montant, $wave_id, $recu_numero]
);

// Audit
db_exec($conn,
    "INSERT INTO audit_log (user_id, action, detail, ip_address)
     VALUES (?, 'WAVE_CHECKOUT_CREATED', ?, ?)",
    'iss',
    [$uid, json_encode(['frais_id'=>$frais_id,'montant'=>$montant,'wave_id'=>$wave_id]),
     $_SERVER['REMOTE_ADDR'] ?? '']
);

echo json_encode([
    'checkout_url'   => $data['wave_launch_url'],
    'recu_numero'    => $recu_numero,
    'wave_id'        => $wave_id,
]);
