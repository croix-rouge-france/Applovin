<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Session user_id non définie\n", FILE_APPEND);
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$token = $_GET['token'] ?? null;
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Paramètres GET : " . print_r($_GET, true) . "\n", FILE_APPEND);

try {
    $conn = new PDO("mysql:host=mysql-applovin.alwaysdata.net;dbname=applovin_db;charset=utf8", 'applovin', '@Motdepasse0000', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Erreur de connexion : " . $e->getMessage() . "\n", FILE_APPEND);
    $error_message = "Erreur serveur.";
}

$deposit = false;
if ($token) {
    try {
        $stmt = $conn->prepare("SELECT amount, status, created_at FROM deposits WHERE user_id = ? AND invoice_token = ?");
        $stmt->execute([$user_id, $token]);
        $deposit = $stmt->fetch();
        if ($deposit) {
            if ($deposit['status'] !== 'completed') {
                $stmt = $conn->prepare("UPDATE deposits SET status = 'completed', updated_at = NOW() WHERE user_id = ? AND invoice_token = ?");
                $stmt->execute([$user_id, $token]);
                $deposit['status'] = 'completed';
            }
            file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Transaction trouvée, user_id $user_id, token $token, montant {$deposit['amount']} XOF\n", FILE_APPEND);
        } else {
            $error_message = "Transaction non trouvée.";
        }
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Erreur SQL : " . $e->getMessage() . "\n", FILE_APPEND);
        $error_message = "Erreur traitement.";
    }
} else {
    $error_message = "Token manquant.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #e0eafc, #cfdef3); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); animation: fadeIn 1s ease-in-out; }
        .success-icon { font-size: 3rem; color: #28a745; }
        .btn-primary { background-color: #007bff; border: none; border-radius: 25px; padding: 10px 20px; }
        .btn-primary:hover { background-color: #0056b3; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .error-message { color: #dc3545; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card mx-auto" style="max-width: 500px;">
            <div class="card-body text-center p-5">
                <?php if (isset($error_message)): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">Retour</a>
                <?php elseif ($deposit): ?>
                    <span class="success-icon mb-3 d-block">✓</span>
                    <h2 class="card-title mb-4">Paiement Réussi !</h2>
                    <p class="mb-2"><strong>Montant :</strong> <?php echo number_format($deposit['amount'], 0); ?> XOF 
                        (<?php echo number_format($deposit['amount'] * 0.00167, 2); ?> USD)</p>
                    <p class="mb-2"><strong>Statut :</strong> completed</p>
                    <p class="mb-4"><strong>Date :</strong> <?php echo date('Y-m-d H:i:s', strtotime($deposit['created_at'])); ?></p>
                    <a href="dashboard.php" class="btn btn-primary">Tableau de Bord</a>
                <?php else: ?>
                    <p class="error-message">Transaction non traitée.</p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">Retour</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
