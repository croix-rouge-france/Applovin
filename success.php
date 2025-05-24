<?php
session_start();

// Supprimer l'affichage des erreurs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Vérifier la session
if (!isset($_SESSION['user_id'])) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Session user_id non définie, redirection vers login.php\n", FILE_APPEND);
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Connexion à la base de données
try {
    $dsn = "mysql:host=mysql-applovin.alwaysdata.net;dbname=applovin_db;charset=utf8";
    $conn = new PDO($dsn, 'applovin', '@Motdepasse0000', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Erreur de connexion : " . $e->getMessage() . "\n", FILE_APPEND);
    $error_message = "Une erreur est survenue lors du traitement de votre paiement. Veuillez réessayer plus tard.";
}

// Récupérer la dernière transaction complétée
$deposit = false;
$invoice_token = $_GET['invoice_token'] ?? null;

if ($invoice_token) {
    try {
        $stmt = $conn->prepare("SELECT amount, status, created_at 
                               FROM deposits 
                               WHERE user_id = ? AND invoice_token = ? AND status = 'completed'");
        $stmt->execute([$user_id, $invoice_token]);
        $deposit = $stmt->fetch();
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Erreur lors de la récupération du dépôt : " . $e->getMessage() . "\n", FILE_APPEND);
        $error_message = "Une erreur est survenue lors du traitement de votre paiement. Veuillez réessayer plus tard.";
    }
}

if (!$deposit) {
    try {
        $stmt = $conn->prepare("SELECT amount, status, created_at 
                               FROM deposits 
                               WHERE user_id = ? AND status = 'completed' 
                               ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $deposit = $stmt->fetch();
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Erreur lors de la récupération du dépôt : " . $e->getMessage() . "\n", FILE_APPEND);
        $error_message = "Une erreur est survenue lors du traitement de votre paiement. Veuillez réessayer plus tard.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - Applovin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0eafc, #cfdef3);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            animation: fadeIn 1s ease-in-out;
        }
        .success-icon {
            font-size: 3rem;
            color: #28a745;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
            border-radius: 25px;
            padding: 10px 20px;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .error-message {
            color: #dc3545;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card mx-auto" style="max-width: 500px;">
            <div class="card-body text-center p-5">
                <?php if (isset($error_message)): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">Retour au Tableau de Bord</a>
                <?php elseif ($deposit): ?>
                    <span class="success-icon mb-3 d-block">✓</span>
                    <h2 class="card-title mb-4">Paiement Réussi !</h2>
                    <p class="mb-2"><strong>Montant :</strong> <?php echo number_format($deposit['amount'], 0); ?> XOF 
                        (<?php echo number_format($deposit['amount'] * (defined('USD_RATE') ? USD_RATE : 0.00167), 2); ?> USD)</p>
                    <p class="mb-2"><strong>Statut :</strong> <?php echo htmlspecialchars($deposit['status']); ?></p>
                    <p class="mb-4"><strong>Date :</strong> <?php echo date('Y-m-d H:i:s', strtotime($deposit['created_at'])); ?></p>
                    <a href="dashboard.php" class="btn btn-primary">Voir le Tableau de Bord</a>
                <?php else: ?>
                    <span class="success-icon mb-3 d-block">✓</span>
                    <h2 class="card-title mb-4">Paiement Réussi !</h2>
                    <p class="mb-4">Votre paiement a été traité, mais les détails ne sont pas encore disponibles.</p>
                    <a href="dashboard.php" class="btn btn-primary">Voir le Tableau de Bord</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
