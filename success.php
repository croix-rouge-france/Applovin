<?php
// Activer le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test d'affichage
echo "Début de success.php<br>";

session_start();
require_once __DIR__ . '/includes/config.php';

echo "Après inclusion de config.php<br>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";

// Vérifier la session
if (!isset($_SESSION['user_id'])) {
    echo "Session user_id non définie, redirection vers login.php<br>";
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
echo "User ID: $user_id<br>";

// Connexion à la base de données en code brut
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connexion à la base de données réussie<br>";
} catch (PDOException $e) {
    echo "Erreur de connexion à la base de données : " . $e->getMessage() . "<br>";
    file_put_contents('/var/www/html/debug.log', "Erreur de connexion : " . $e->getMessage() . "\n", FILE_APPEND);
    exit;
}

// Récupérer le dernier dépôt complété
try {
    $sql = "SELECT amount, status, created_at 
            FROM deposits 
            WHERE user_id = :user_id AND status = 'completed' 
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $deposit = $stmt->fetch();
    echo "Requête exécutée, dépôt récupéré<br>";
} catch (PDOException $e) {
    echo "Erreur lors de la requête : " . $e->getMessage() . "<br>";
    file_put_contents('/var/www/html/debug.log', "Erreur de requête : " . $e->getMessage() . "\n", FILE_APPEND);
    $deposit = false;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - Applovin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="/public/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <h1 class="text-center mb-4">Paiement Réussi</h1>
        <div class="card mx-auto" style="max-width: 500px;">
            <div class="card-body text-center">
                <?php if ($deposit): ?>
                    <p>Montant: <?php echo number_format($deposit['amount'], 0); ?> XOF 
                       (<?php echo number_format($deposit['amount'] * USD_RATE, 2); ?> USD)</p>
                    <p>Statut: <?php echo htmlspecialchars($deposit['status']); ?></p>
                    <p>Date: <?php echo date('Y-m-d H:i:s', strtotime($deposit['created_at'])); ?></p>
                <?php else: ?>
                    <p>Votre paiement a été traité, mais les détails ne sont pas encore disponibles.</p>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-primary">Voir le Tableau de Bord</a>
            </div>
        </div>
    </div>
    <script src="/public/js/bootstrap.bundle.min.js"></script>
</body>
</html>
