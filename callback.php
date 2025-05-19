<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
use Paydunya\Setup;

// Configurer Paydunya
Setup::setMasterKey(getenv('PAYDUNYA_MASTER_KEY') ?: throw new Exception('PAYDUNYA_MASTER_KEY manquant'));

// Recevoir les données IPN
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['data']['hash'], $data['data']['status'], $data['data']['invoice']['token'])) {
    http_response_code(400);
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : IPN invalide\n", FILE_APPEND);
    exit;
}

// Vérifier le hash
$expected_hash = hash_hmac('sha512', $input, getenv('PAYDUNYA_MASTER_KEY'));
if ($expected_hash !== $data['data']['hash']) {
    http_response_code(401);
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Hash IPN invalide\n", FILE_APPEND);
    exit;
}

// Traiter la notification
$invoice_token = $data['data']['invoice']['token'];
$status = $data['data']['status'];
$custom_data = $data['data']['invoice']['custom_data'];
$user_id = $custom_data['user_id'] ?? null;
$plan_id = $custom_data['plan_id'] ?? null;
$payment_id = $custom_data['payment_id'] ?? null;
$amount = $data['data']['invoice']['total_amount'] ?? 0;

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE deposits SET status = ? WHERE invoice_token = ?");
    $stmt->execute([$status, $invoice_token]);

    // Journaliser la mise à jour
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : IPN reçu - Token: $invoice_token, Statut: $status, User: $user_id, Plan: $plan_id\n", FILE_APPEND);

    // Si le paiement est confirmé, envoyer une notification (par email, par exemple)
    if ($status === 'completed') {
        // Exemple : Envoyer un email (nécessite une bibliothèque comme PHPMailer)
        // mail($user_email, "Paiement confirmé", "Votre paiement de $amount XOF pour le plan $plan_id a été confirmé.");
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Paiement confirmé pour $user_id\n", FILE_APPEND);
    }

    http_response_code(200);
} catch (Exception $e) {
    http_response_code(500);
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur IPN : " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
