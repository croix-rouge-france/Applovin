<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

check_admin();

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET['id']);

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("
    SELECT u.*, r.username as referrer_name 
    FROM users u
    LEFT JOIN users r ON u.referred_by = r.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "Utilisateur introuvable";
    header("Location: users.php");
    exit();
}

// Récupérer les investissements
$stmt = $pdo->prepare("SELECT * FROM investments WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$investments = $stmt->fetchAll();

// Récupérer les retraits
$stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$withdrawals = $stmt->fetchAll();

// Récupérer les transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Calculer le solde
$balance = calculate_user_balance($user_id);

$page_title = "Détails de l'Utilisateur";
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-user"></i> Détails de l'Utilisateur</h2>
            <hr>
            
            <div class="mb-3">
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Modifier
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Informations de base -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-info-circle"></i> Informations</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>ID</th>
                            <td><?php echo $user['id']; ?></td>
                        </tr>
                        <tr>
                            <th>Nom</th>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Code Parrainage</th>
                            <td><?php echo htmlspecialchars($user['referral_code']); ?></td>
                        </tr>
                        <tr>
                            <th>Parrain</th>
                            <td>
                                <?php if ($user['referred_by']): ?>
                                <?php echo htmlspecialchars($user['referrer_name']); ?> (ID: <?php echo $user['referred_by']; ?>)
                                <?php else: ?>
                                Aucun
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Date d'inscription</th>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Solde -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5><i class="fas fa-wallet"></i> Solde</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Solde Actuel</th>
                            <td><?php echo APP_CURRENCY; ?> <?php echo number_format($balance['current'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Investi</th>
                            <td><?php echo APP_CURRENCY; ?> <?php echo number_format($balance['invested'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Profits</th>
                            <td><?php echo APP_CURRENCY; ?> <?php echo number_format($balance['profit'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Bonus</th>
                            <td><?php echo APP_CURRENCY; ?> <?php echo number_format($balance['bonus'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Retraits</th>
                            <td><?php echo APP_CURRENCY; ?> <?php echo number_format($balance['withdrawals'], 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Investissements -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Investissements</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($investments)): ?>
                    <div class="alert alert-info">Aucun investissement trouvé.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Montant</th>
                                    <th>Méthode</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($investments as $investment): ?>
                                <tr>
                                    <td><?php echo $investment['id']; ?></td>
                                    <td><?php echo number_format($investment['amount'], 2); ?> USDT</td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $investment['payment_method'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo get_status_badge($investment['status']); ?>">
                                            <?php echo ucfirst($investment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($investment['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Retraits -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-money-bill-wave"></i> Retraits</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($withdrawals)): ?>
                    <div class="alert alert-info">Aucun retrait trouvé.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Montant</th>
                                    <th>Méthode</th>
                                    <th>Wallet/Numéro</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $withdrawal): ?>
                                <tr>
                                    <td><?php echo $withdrawal['id']; ?></td>
                                    <td><?php echo APP_CURRENCY; ?> <?php echo number_format($withdrawal['amount'], 2); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $withdrawal['method'])); ?></td>
                                    <td><?php echo htmlspecialchars($withdrawal['wallet_address']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo get_status_badge($withdrawal['status']); ?>">
                                            <?php echo ucfirst($withdrawal['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Dernières Transactions -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-exchange-alt"></i> Dernières Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                    <div class="alert alert-info">Aucune transaction trouvée.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo ucfirst($tx['type']); ?></td>
                                    <td><?php echo APP_CURRENCY; ?> <?php echo number_format($tx['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($tx['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>