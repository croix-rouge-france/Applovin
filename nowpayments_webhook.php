<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Vérifier la signature
$received_hmac = $_SERVER['HTTP_X_NOWPAYMENTS_SIGNATURE'] ?? '';
$request_body = file_get_contents('php://input');
$calculated_hmac = hash_hmac('sha512', $request_body, NOWPAYMENTS_WEBHOOK_SECRET);

if ($received_hmac !== $calculated_hmac) {
    http_response_code(401);
    die('Signature invalide');
}

$data = json_decode($request_body, true);

// Traitement du statut de paiement
if ($data['payment_status'] === 'finished') {
    $payment_id = $data['order_id']; // Doit correspondre à votre payment_id
    
    $stmt = $pdo->prepare("
        UPDATE investments SET 
            status = 'completed',
            updated_at = NOW(),
            payment_details = ?
        WHERE payment_id = ? AND status = 'pending'
    ");
    
    $stmt->execute([json_encode($data), $payment_id]);
    
    if ($stmt->rowCount() > 0) {
        // Envoyer une notification à l'utilisateur si nécessaire
    }
}

http_response_code(200);
echo 'OK';