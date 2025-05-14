<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/NowPayments.php';

// Log les requêtes entrantes pour debug
file_put_contents('ipn_log.txt', date('[Y-m-d H:i:s]')." IPN Received\n", FILE_APPEND);

// Vérification de la signature
$received_hmac = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
$request_body = file_get_contents('php://input');
$expected_hmac = hash_hmac('sha512', $request_body, NOWPAYMENTS_API_KEY);

if (!hash_equals($expected_hmac, $received_hmac)) {
    file_put_contents('ipn_log.txt', "Invalid HMAC\n", FILE_APPEND);
    http_response_code(401);
    die('Invalid signature');
}

$data = json_decode($request_body, true);
file_put_contents('ipn_log.txt', print_r($data, true)."\n", FILE_APPEND);

// Traitement seulement pour les paiements complétés
if ($data['payment_status'] === 'finished') {
    try {
        $pdo = getPDO();
        
        // Vérifier si le paiement existe déjà
        $stmt = $pdo->prepare("SELECT id FROM transactions WHERE metadata->>'payment_id' = ?");
        $stmt->execute([$data['payment_id']]);
        
        if (!$stmt->fetch()) {
            // Extraire user_id de l'order_id (format: DEP_userID_timestamp)
            $order_id = $data['order_id'] ?? '';
            $user_id = explode('_', $order_id)[1] ?? null;
            
            if ($user_id) {
                $amount = $data['actually_paid'];
                $metadata = json_encode([
                    'payment_id' => $data['payment_id'],
                    'invoice_id' => $data['invoice_id'] ?? null,
                    'pay_address' => $data['pay_address'] ?? null,
                    'ipn_data' => $data
                ]);
                
                // Enregistrer la transaction
                $stmt = $pdo->prepare("INSERT INTO transactions 
                    (user_id, amount, payment_method, status, metadata, created_at) 
                    VALUES (?, ?, 'usdt', 'completed', ?, NOW())");
                
                $stmt->execute([$user_id, $amount, $metadata]);
                
                // Mettre à jour le solde
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
                   ->execute([$amount, $user_id]);
                   
                file_put_contents('ipn_log.txt', "Payment processed: ".$data['payment_id']."\n", FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        file_put_contents('ipn_log.txt', "Error: ".$e->getMessage()."\n", FILE_APPEND);
    }
}

http_response_code(200);
echo 'OK';