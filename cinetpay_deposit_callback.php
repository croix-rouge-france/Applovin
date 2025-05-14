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

try {
    $pdo = getPDO();
    
    // Trouver le dépôt correspondant
    $stmt = $pdo->prepare("SELECT * FROM deposits WHERE transaction_id = ?");
    $stmt->execute([$data['transaction_id']]);
    $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($deposit) {
        $new_status = ($data['status'] === 'SUCCESS') ? 'completed' : 'failed';
        
        // Mettre à jour le statut
        $update = $pdo->prepare("UPDATE deposits SET 
                                status = ?, 
                                updated_at = NOW(), 
                                metadata = ? 
                                WHERE id = ?");
        $update->execute([$new_status, json_encode($data), $deposit['id']]);
        
        // Si succès, créditer le compte
        if ($new_status === 'completed') {
            // Pour USDT: amount est déjà en USD
            // Pour Mobile Money: on utilise usd_amount (conversion XOF->USD)
            $amount = ($deposit['currency'] === 'XOF') ? $deposit['usd_amount'] : $deposit['amount'];
            
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
               ->execute([$amount, $deposit['user_id']]);
            
            // Enregistrer comme transaction
            $pdo->prepare("INSERT INTO transactions 
                          (user_id, amount, type, status, metadata, created_at)
                          VALUES (?, ?, 'deposit', 'completed', ?, NOW())")
               ->execute([$deposit['user_id'], $amount, json_encode([
                   'method' => ($deposit['currency'] === 'XOF') ? 'mobile_money' : 'usdt',
                   'transaction_id' => $deposit['transaction_id']
               )]);
        }
    }
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    error_log("Deposit Callback Error: " . $e->getMessage());
    http_response_code(500);
}