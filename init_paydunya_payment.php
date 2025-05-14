<?php
// Désactiver toute sortie parasite avant le JSON
ob_start();

// Définir le type de contenu immédiatement
header('Content-Type: application/json');

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/paydunya_config.php';

try {
    // Charger la configuration PayDunya
    $paydunyaConfig = PaydunyaConfig::getInstance();
    $storeConfig = $paydunyaConfig->getStoreConfig();

    // Vérifier les données reçues via POST
    if (!isset($_POST['payment_id'], $_POST['plan_id'], $_POST['amount'], $_POST['phone'])) {
        throw new Exception('Données manquantes pour initialiser le paiement');
    }

    // Sécuriser et valider les données
    $payment_id = filter_var($_POST['payment_id'], FILTER_SANITIZE_STRING);
    $plan_id = intval($_POST['plan_id']);
    $amount = floatval($_POST['amount']);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);

    if ($plan_id <= 0 || $amount <= 0 || empty($phone) || !preg_match('/^\+?\d{9,15}$/', $phone)) {
        throw new Exception('Données invalides : plan, montant ou numéro de téléphone incorrect');
    }

    // Ajouter l'indicatif si nécessaire (par exemple, +221 pour le Sénégal)
    if (!preg_match('/^\+/', $phone)) {
        $phone = "+221" . $phone; // À adapter selon le pays sélectionné
    }

    // Créer une facture PayDunya
    $invoice = new Paydunya_Checkout_Invoice();
    $invoice->addItem("Investissement Plan $plan_id", 1, $amount, $amount, "Plan d'investissement niveau $plan_id");
    $invoice->setTotalAmount($amount);
    $invoice->setDescription("Investissement dans le plan niveau $plan_id");
    $invoice->setCallbackUrl($storeConfig['callbackUrl']);
    $invoice->setReturnUrl($storeConfig['websiteUrl'] . "/return.php");

    // Ajouter les informations du client
    $invoice->setPhoneNumber($phone);

    // Ajouter des données personnalisées
    $invoice->addCustomData('payment_id', $payment_id);
    $invoice->addCustomData('plan_id', $plan_id);
    $invoice->addCustomData('phone', $phone);

    // Enregistrer la transaction en base de données avant paiement
    $stmt = $pdo->prepare("
        INSERT INTO payments (payment_id, plan_id, amount, payment_method, status, created_at) 
        VALUES (:payment_id, :plan_id, :amount, :method, 'pending', NOW())
    ");
    $stmt->execute([
        ':payment_id' => $payment_id,
        ':plan_id' => $plan_id,
        ':amount' => $amount,
        ':method' => "Mobile Money"
    ]);

    // Démarrer le paiement
    if ($invoice->create()) {
        // Vider le buffer pour éviter toute sortie parasite
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => "Paiement initialisé. Vérifiez votre téléphone ($phone) pour valider le paiement.",
            'invoice_url' => $invoice->getInvoiceUrl(),
            'token' => $invoice->getToken()
        ]);
    } else {
        throw new Exception("Échec de la création de la facture PayDunya : " . $invoice->getStatus());
    }
} catch (Exception $e) {
    // Vider le buffer et logger l'erreur
    ob_end_clean();
    error_log("Erreur dans init_paydunya_payment.php : " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => "Erreur lors de l'initialisation du paiement : " . $e->getMessage()
    ]);
}
?>