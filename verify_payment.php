<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_POST['payment_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'ID de paiement manquant']));
}

// 1. Vérifier d'abord en base de données
$stmt = $pdo->prepare("SELECT * FROM investments WHERE payment_id = ?");
$stmt->execute([$_POST['payment_id']]);
$payment = $stmt->fetch();

if (!$payment) {
    die(json_encode(['status' => 'error', 'message' => 'Transaction introuvable']));
}

if ($payment['status'] === 'completed') {
    die(json_encode([
        'status' => 'completed',
        'amount' => $payment['amount'],
        'plan_id' => $payment['plan_id']
    ]));
}

// 2. Si non confirmé, vérifier avec l'API NowPayments
$api_key = NOWPAYMENTS_API_KEY;
$payment_id = $payment['payment_id'];

$ch = curl_init("https://api.nowpayments.io/v1/payment/$payment_id");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-api-key: $api_key"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (isset($result['payment_status']) && $result['payment_status'] === 'finished') {
    // Mettre à jour la base de données
    $stmt = $pdo->prepare("
        UPDATE investments SET 
            status = 'completed',
            updated_at = NOW(),
            payment_details = ?
        WHERE payment_id = ?
    ");
    $stmt->execute([json_encode($result), $payment_id]);
    
    echo json_encode([
        'status' => 'completed',
        'amount' => $payment['amount'],
        'plan_id' => $payment['plan_id']
    ]);
} else {
    echo json_encode(['status' => 'pending']);
}