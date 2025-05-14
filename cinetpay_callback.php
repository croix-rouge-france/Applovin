<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Vérification de la signature
$received_signature = $_SERVER['HTTP_X_CINETPAY_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
$expected_signature = hash_hmac('sha256', $payload, CINETPAY_SECRET_KEY);

if (!hash_equals($expected_signature, $received_signature)) {
    http_response_code(401);
    exit('Signature invalide');
}

$data = json_decode($payload, true);

// Traitement du statut
try {
    $pdo = getPDO();
    
    // Trouver le retrait correspondant
    $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE cinetpay_id = ?");
    $stmt->execute([$data['transaction_id']]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($withdrawal) {
        $new_status = ($data['status'] === 'SUCCESS') ? 'success' : 'failed';
        
        $update = $pdo->prepare("UPDATE withdrawals SET 
                                status = ?, 
                                updated_at = NOW(), 
                                metadata = ? 
                                WHERE id = ?");
        $update->execute([$new_status, json_encode($data), $withdrawal['id']]);
        
        // Si échec, rembourser le solde
        if ($new_status === 'failed') {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
               ->execute([$withdrawal['amount'], $withdrawal['user_id']]);
        }
    }
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    error_log("Callback Error: " . $e->getMessage());
    http_response_code(500);
}