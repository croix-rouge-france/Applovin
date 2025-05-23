<?php
    session_start();
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/db.php';

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch latest completed deposit
    $sql = "SELECT amount, status, created_at 
            FROM deposits 
            WHERE user_id = :user_id AND status = 'completed' 
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->query($sql, [':user_id' => $user_id]);
    $deposit = $stmt->fetch();
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
