<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Désactiver l'affichage des erreurs pour éviter de corrompre la redirection
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Vérifier les paramètres retournés par PayDunya
$payment_id = $_GET['payment_id'] ?? '';
$status = $_GET['status'] ?? 'failed'; // Supposons que PayDunya renvoie un paramètre 'status'
$amount = floatval($_GET['amount'] ?? 0);
$plan_id = intval($_GET['plan_id'] ?? 0);

// Log pour débogage
error_log("Retour PayDunya : payment_id=$payment_id, status=$status, amount=$amount, plan_id=$plan_id");

if ($payment_id && $status === 'completed' && $amount > 0 && $plan_id > 0) {
    try {
        // Connexion à la base de données
        if (!isset($pdo)) {
            throw new Exception('Connexion à la base de données non initialisée');
        }

        // Insérer ou mettre à jour la transaction dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO payments (payment_id, plan_id, amount, payment_method, status, created_at) 
            VALUES (:payment_id, :plan_id, :amount, :method, 'completed', NOW())
            ON DUPLICATE KEY UPDATE status = 'completed', updated_at = NOW()
        ");
        $stmt->execute([
            ':payment_id' => $payment_id,
            ':plan_id' => $plan_id,
            ':amount' => $amount,
            ':method' => "Mobile Money (PayDunya)"
        ]);

        // Rediriger vers le tableau de bord avec un message de succès
        header("Location: dashboard.php?payment_id=$payment_id&status=success");
        exit;
    } catch (Exception $e) {
        error_log("Erreur dans return.php : " . $e->getMessage());
        header("Location: deposit.php?plan_id=$plan_id&error=" . urlencode("Erreur lors de l'enregistrement du paiement : " . $e->getMessage()));
        exit;
    }
} else {
    // Paiement non complété ou données invalides
    error_log("Paiement non complété ou données invalides : payment_id=$payment_id, status=$status");
    header("Location: deposit.php?plan_id=$plan_id&error=" . urlencode("Le paiement n'a pas été complété ou les données sont invalides."));
    exit;
}
?>