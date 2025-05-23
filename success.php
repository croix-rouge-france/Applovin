<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Journaliser la visite à la page de succès (pour débogage)
file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Page succès visitée, GET: " . print_r($_GET, true) . PHP_EOL, FILE_APPEND);

// Mettre à jour le statut du dépôt (par exemple, de 'pending' à 'completed')
if (isset($_GET['token'])) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE deposits SET status = 'completed', updated_at = NOW() WHERE invoice_token = ? AND user_id = ?");
    $stmt->execute([$_GET['token'], $_SESSION['user_id']]);
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - Applovin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header bg-success text-white">
                        <h2 class="mb-0"><i class="fas fa-check-circle me-2"></i> Paiement Réussi</h2>
                    </div>
                    <div class="card-body text-center">
                        <p class="lead">Votre dépôt a été effectué avec succès !</p>
                        <p>Vous serez redirigé vers votre tableau de bord dans quelques secondes...</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3">Retour au Tableau de Bord</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        setTimeout(() => {
            window.location.href = 'dashboard.php';
        }, 5000);
    </script>
</body>
</html>
