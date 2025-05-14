<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/paydunya_config.php';

// Vérifier si un payment_id a été envoyé
if (!isset($_POST['payment_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID de paiement manquant']);
    exit;
}

$payment_id = $_POST['payment_id'];

// Vérifier le statut dans la base de données
$stmt = $pdo->prepare("SELECT status FROM payments WHERE payment_id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if ($payment) {
    if ($payment['status'] === 'completed') {
        echo json_encode(['status' => 'completed']);
    } else {
        // Optionnel: Vérifier directement avec l'API PayDunya si nécessaire
        echo json_encode(['status' => 'pending']);
    }
} else {
    echo json_encode(['status' => 'not_found']);
}
?>