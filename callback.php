<?php
require_once __DIR__ . '/vendor/autoload.php';
use Paydunya\Setup;

// Hardcoded PayDunya API keys
define('PAYDUNYA_MASTER_KEY', '61UU2abw-fmvT-nNDA-GFMe-WcecHjEdfYoP');
define('PAYDUNYA_PRIVATE_KEY', 'live_private_omjNDYClxSRu8KZoDBSvLRo4QEm');
define('PAYDUNYA_TOKEN', 'X7R67BRbIbnthZ7BTyPr');

// Configure PayDunya
Setup::setMasterKey(PAYDUNYA_MASTER_KEY);
Setup::setPrivateKey(PAYDUNYA_PRIVATE_KEY);
Setup::setToken(PAYDUNYA_TOKEN);
Setup::setMode('live');

// Configuration de la connexion à la base de données
$host = 'mysql-applovin.alwaysdata.net';
$dbname = 'applovin_db';
$username = 'applovin';
$password = '@Motdepasse0000';

// Receive IPN data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate IPN data
if (!$data || !isset($data['data']['hash'], $data['data']['status'], $data['data']['invoice']['token'])) {
    http_response_code(400);
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : IPN invalide - Données manquantes\n", FILE_APPEND);
    exit;
}

// Verify the hash
$expected_hash = hash('sha512', PAYDUNYA_MASTER_KEY);
if ($expected_hash !== $data['data']['hash']) {
    http_response_code(401);
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Hash IPN invalide - Attendu: $expected_hash, Reçu: {$data['data']['hash']}\n", FILE_APPEND);
    exit;
}

// Extract payment details
$invoice_token = $data['data']['invoice']['token'];
$status = $data['data']['status'];
$custom_data = $data['data']['invoice']['custom_data'] ?? [];
$user_id = $custom_data['user_id'] ?? null;
$plan_id = $custom_data['plan_id'] ?? null;
$amount = $data['data']['invoice']['total_amount'] ?? 0; // Amount in XOF
$payment_method = $data['data']['invoice']['payment_method'] ?? 'mobile_money';
$network = $custom_data['network'] ?? 'N/A';
$country = $custom_data['country'] ?? 'N/A';
$phone = $custom_data['phone'] ?? 'N/A';

// Validate required fields
if (!$user_id || !is_numeric($user_id) || $amount <= 0) {
    http_response_code(400);
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Données invalides - user_id: $user_id, amount: $amount\n", FILE_APPEND);
    exit;
}

try {
    // Connexion PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Vérifier si l'IPN a déjà été traité
    $stmt = $pdo->prepare("SELECT status FROM deposits WHERE invoice_token = ? AND user_id = ?");
    $stmt->execute([$invoice_token, $user_id]);
    $existing = $stmt->fetch();
    if ($existing && $existing['status'] === 'completed') {
        http_response_code(200);
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : IPN déjà traité pour token $invoice_token\n", FILE_APPEND);
        exit;
    }

    // Start a transaction
    $pdo->beginTransaction();

    // Update deposits table
    $stmt = $pdo->prepare("UPDATE deposits 
                           SET status = ?, amount = ?, payment_method = ?, network = ?, country = ?, phone = ?, updated_at = NOW() 
                           WHERE invoice_token = ? AND user_id = ?");
    $stmt->execute([$status, $amount, $payment_method, $network, $country, $phone, $invoice_token, $user_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Aucun dépôt trouvé pour le token $invoice_token et user_id $user_id");
    }

    // If payment is confirmed, update transactions and user balance
    if ($status === 'completed') {
        // Convert amount to USD
        $usd_amount = $amount / (defined('EXCHANGE_RATE') ? EXCHANGE_RATE : 600);

        // Insert into transactions table
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, plan_id, created_at) 
                               VALUES (?, 'deposit', ?, 'completed', ?, NOW())");
        $stmt->execute([$user_id, $usd_amount, $plan_id]);

        // Update user balance
        $stmt = $pdo->prepare("UPDATE users 
                               SET balance = balance + ?, invested = invested + ? 
                               WHERE id = ?");
        $stmt->execute([$usd_amount, $usd_amount, $user_id]);

        // Log success
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Paiement confirmé - Token: $invoice_token, User: $user_id, Plan: $plan_id, Montant: $amount XOF ($usd_amount USD)\n", FILE_APPEND);
    }

    // Commit transaction
    $pdo->commit();

    // Log successful processing
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : IPN traité - Token: $invoice_token, Statut: $status, User: $user_id, Plan: $plan_id\n", FILE_APPEND);

    http_response_code(200);
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur IPN : " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}
?>
