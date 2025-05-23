<?php
// Activation du mode debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Vérification d'authentification
try {
    check_auth();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Session utilisateur invalide");
    }

    // Obtenir l'instance de PDO via la classe Database
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $user_id = $_SESSION['user_id'];
    
    // Requête sécurisée avec gestion du referral_code
    $stmt = $pdo->prepare("SELECT id, username, email, is_admin, 
                          IFNULL(referral_code, 'default_ref') AS referral_code 
                          FROM users WHERE id = ?");
    if (!$stmt->execute([$user_id])) {
        throw new PDOException("Erreur de récupération utilisateur");
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        throw new Exception("Utilisateur introuvable");
    }

    // Calcul du solde
    $balance = [
        'current' => 0,
        'invested' => 0,
        'profit' => 0,
        'withdrawals' => 0,
        'bonus' => 0
    ];
    
    try {
        $balance = calculate_user_balance($user_id);
    } catch (Exception $e) {
        error_log("Erreur calcul balance: " . $e->getMessage());
    }

    // Récupération des dépôts
    $deposits = [];
    try {
        $stmt = $pdo->prepare("SELECT amount, currency, created_at, status, network, country, phone, payment_method 
                              FROM deposits 
                              WHERE user_id = ? 
                              ORDER BY created_at DESC 
                              LIMIT 5");
        $stmt->execute([$user_id]);
        $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur récupération dépôts: " . $e->getMessage());
    }

    // Récupération des transactions
    $transactions = [];
    try {
        $transactions = get_recent_transactions($user_id, 5);
    } catch (PDOException $e) {
        error_log("Erreur récupération transactions: " . $e->getMessage());
    }

    // Récupération des statistiques d'équipe en temps réel
    $team_stats = [
        'team_recharge' => 0,
        'team_withdrawal' => 0,
        'new_team_first_recharge' => 0,
        'new_team_first_withdrawal' => 0
    ];
    
    try {
        // Recharges et retraits de l'équipe
        $stmt = $pdo->prepare("SELECT 
                              SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as team_recharge,
                              SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as team_withdrawal
                              FROM transactions 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $team_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($team_data) {
            $team_stats['team_recharge'] = $team_data['team_recharge'] ?? 0;
            $team_stats['team_withdrawal'] = $team_data['team_withdrawal'] ?? 0;
        }
        
        // Premières recharges et retraits
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM transactions 
                              WHERE type = 'deposit' AND user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $team_stats['new_team_first_recharge'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM transactions 
                              WHERE type = 'withdrawal' AND user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $team_stats['new_team_first_withdrawal'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Erreur récupération stats équipe: " . $e->getMessage());
    }

    // Récupération des statistiques d'invitation par niveau
    $invitation_stats = [
        'level1' => ['invited' => 0, 'validated' => 0, 'income' => 0],
        'level2' => ['invited' => 0, 'validated' => 0, 'income' => 0],
        'level3' => ['invited' => 0, 'validated' => 0, 'income' => 0]
    ];
    
    try {
        // Niveau 1 (direct referrals)
        $stmt = $pdo->prepare("SELECT COUNT(*) as invited, 
                              SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as validated,
                              SUM(referral_income) as income
                              FROM referrals 
                              WHERE referrer_id = ? AND level = 1");
        $stmt->execute([$user_id]);
        $level1 = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($level1) {
            $invitation_stats['level1'] = [
                'invited' => $level1['invited'],
                'validated' => $level1['validated'],
                'income' => $level1['income'] ?? 0
            ];
        }
        
        // Niveau 2
        $stmt = $pdo->prepare("SELECT COUNT(*) as invited, 
                              SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as validated,
                              SUM(referral_income) as income
                              FROM referrals 
                              WHERE referrer_id = ? AND level = 2");
        $stmt->execute([$user_id]);
        $level2 = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($level2) {
            $invitation_stats['level2'] = [
                'invited' => $level2['invited'],
                'validated' => $level2['validated'],
                'income' => $level2['income'] ?? 0
            ];
        }
        
        // Niveau 3
        $stmt = $pdo->prepare("SELECT COUNT(*) as invited, 
                              SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as validated,
                              SUM(referral_income) as income
                              FROM referrals 
                              WHERE referrer_id = ? AND level = 3");
        $stmt->execute([$user_id]);
        $level3 = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($level3) {
            $invitation_stats['level3'] = [
                'invited' => $level3['invited'],
                'validated' => $level3['validated'],
                'income' => $level3['income'] ?? 0
            ];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération stats invitation: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("ERREUR DASHBOARD: " . $e->getMessage());
    $_SESSION['error'] = "Erreur système. Veuillez réessayer.";
    header("Location: index.php");
    exit();
}

$page_title = "Tableau de bord";
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
        --secondary-gradient: linear-gradient(135deg, #f72585, #b5179e);
        --success-gradient: linear-gradient(135deg, #4cc9f0, #4895ef);
        --warning-gradient: linear-gradient(135deg, #f8961e, #f3722c);
        --danger-gradient: linear-gradient(135deg, #ef233c, #d90429);
        --dark-color: #2b2d42;
        --light-color: #f8f9fa;
        --glass-effect: rgba(255, 255, 255, 0.15);
    }
    
    body {
        font-family: 'Poppins', system-ui, sans-serif;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        color: var(--dark-color);
        line-height: 1.6;
        min-height: 100vh;
    }

    /* Conteneur responsive */
    .container {
        width: 95%;
        max-width: 1800px;
        padding: 2rem;
        margin: 0 auto;
    }

    /* Effet verre moderne */
    .glass-card {
        background: var(--glass-effect);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }

    /* En-tête avec animation de dégradé */
    .platform-header {
        composes: glass-card;
        padding: 2.5rem;
        margin-bottom: 3rem;
        background: var(--primary-gradient);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .platform-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
        transform: rotate(30deg);
        z-index: 0;
    }

    .platform-header h2 {
        position: relative;
        z-index: 1;
        font-weight: 700;
        font-size: 2.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Badges animés */
    .badge {
        display: inline-block;
        padding: 0.5em 1em;
        font-weight: 600;
        letter-spacing: 0.5px;
        border-radius: 50px;
        margin: 0 8px;
        background: var(--secondary-gradient);
        color: white;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    /* Cartes d'investissement uniques */
    .investment-card {
        composes: glass-card;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        margin-bottom: 2rem;
        overflow: hidden;
        background: white;
    }

    .investment-card:nth-child(odd) {
        background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(245,245,245,0.9));
    }

    .investment-card:nth-child(even) {
        background: linear-gradient(135deg, rgba(248,249,250,0.9), rgba(233,236,239,0.9));
    }

    .investment-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    }

    .investment-card .card-header {
        background: var(--primary-gradient);
        color: white;
        padding: 1.5rem;
        font-weight: 600;
        font-size: 1.3rem;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
    }

    /* Tableau avec effets uniques */
    .investment-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 1rem;
        background: white;
        border-radius: 12px;
        overflow: hidden;
    }

    .investment-table th {
        background: var(--primary-gradient);
        color: white;
        padding: 1.2rem;
        font-weight: 600;
        font-size: 0.9rem;
        letter-spacing: 1px;
        text-transform: uppercase;
        position: relative;
    }

    .investment-table th::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background: rgba(255,255,255,0.3);
    }

    .investment-table td {
        padding: 1.2rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }

    .investment-table tr:last-child td {
        border-bottom: none;
    }

    .investment-table tr:hover td {
        background: rgba(67, 97, 238, 0.05);
    }

    /* Section parrainage avec dégradé unique */
    .referral-section {
        composes: glass-card;
        padding: 2rem;
        margin-bottom: 2rem;
        background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(240,240,255,0.8));
    }

    .referral-code {
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: 4px;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        padding: 1.2rem;
        text-align: center;
        margin: 1.5rem 0;
        position: relative;
    }

    .referral-code::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border: 2px dashed #4361ee;
        border-radius: 12px;
        pointer-events: none;
    }

    /* Boutons avec effets spéciaux */
    .btn-conoco {
        background: var(--primary-gradient);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.8rem 2rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
        transition: all 0.4s ease;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
    }

    .btn-conoco::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: all 0.6s ease;
    }

    .btn-conoco:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(67, 97, 238, 0.4);
    }

    .btn-conoco:hover::after {
        left: 100%;
    }

    /* Carte de solde animée */
    .balance-card {
        composes: glass-card;
        padding: 2rem;
        margin-bottom: 2rem;
        background: var(--primary-gradient);
        color: white;
        position: relative;
        overflow: hidden;
        transition: all 0.4s ease;
    }

    .balance-card:hover {
        transform: scale(1.02);
    }

    .balance-card h2 {
        font-size: 2.8rem;
        margin: 1.5rem 0;
        font-weight: 700;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    /* Responsive design avancé */
    @media (max-width: 768px) {
        .container {
            padding: 1rem;
            width: 100%;
        }
        
        .platform-header h2 {
            font-size: 1.8rem;
        }
        
        .investment-table {
            font-size: 0.85rem;
        }
    }

    @media (min-width: 1600px) {
        .container {
            max-width: 90%;
        }
        
        .investment-card {
            padding: 2rem;
        }
        
        .investment-table th, 
        .investment-table td {
            padding: 1.5rem;
            font-size: 1.1rem;
        }
    }

    /* Effets spéciaux */
    .floating {
        animation: floating 3s ease-in-out infinite;
    }

    @keyframes floating {
        0% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
        100% { transform: translateY(0); }
    }

    .glow {
        animation: glow 2s ease-in-out infinite alternate;
    }

    @keyframes glow {
        from { box-shadow: 0 0 10px rgba(67, 97, 238, 0.5); }
        to { box-shadow: 0 0 20px rgba(67, 97, 238, 0.8); }
    }
    </style>
</head>
<body>
    <div class="container py-3">
        <!-- En-tête de la plateforme -->
        <div class="platform-header">
            <h2 class="text-center mb-3">
                <i>Bonjour, <?php echo htmlspecialchars($user['username']); ?> ! 👋</i>
                <i class="fas fa-oil-can me-2"></i>APPlovin Digital Currency investment Center
            </h2>
            <div class="text-center mb-3">
                <span class="badge bg-secondary">USDT (ERC20) (BEP20)</span>
                <span class="badge bg-secondary">Dépôt min: 5 USDT</span>
                <span class="badge bg-secondary">Retrait min: (En fonction du plan d'investissement choisi )</span>
            </div>
            <p class="text-center">
                Applovin s'engage à l'exploration des opportunités dans le domaines du blockchain  et à y investir avec le capital des différents investisseurs afin de récolter des bénefices conséquent.
                Nos efforts d'innovation créent des produits qui améliorent la qualité de vie dans le monde.
            </p>
        </div>

        <div class="row">
            <!-- Colonne gauche -->
            <div class="col-md-8">
                <!-- Carte de solde -->
                <div class="balance-card">
                    <h5 class="mb-3"><i class="fas fa-wallet me-2"></i>Solde USDT</h5>
                    <h2 class="mb-4"><?= number_format($balance['current'], 2) ?> USDT</h2>
                    <div class="d-flex justify-content-between">
                        <div>
                            <small>Investi</small>
                            <h5><?= number_format($balance['invested'], 2) ?> USDT</h5>
                        </div>
                        <div>
                            <small>Profit</small>
                            <h5><?= number_format($balance['profit'], 2) ?> USDT</h5>
                        </div>
                    </div>
                </div>

                <!-- Section Investissement -->
                <div class="card investment-card">
                    <div class="card-header">
                        <i class="fas fa-chart-line me-2"></i>Niveaux d'Investissement
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="investment-table">
                                <thead>
                                    <tr>
                                        <th>Niveau</th>
                                        <th>Investissement (USDT)</th>
                                        <th>Retour Quotidien (%)</th>
                                        <th>Gain Quotidien (USDT)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $investment_levels = [
                                        ['level' => 1, 'investment' => 5, 'return' => 8.00, 'earning' => 0.40],
                                        ['level' => 2, 'investment' => 35, 'return' => 10.00, 'earning' => 3.50],
                                        ['level' => 3, 'investment' => 120, 'return' => 12.00, 'earning' => 14.40],
                                        ['level' => 4, 'investment' => 300, 'return' => 14.00, 'earning' => 42.00],
                                        ['level' => 5, 'investment' => 700, 'return' => 16.00, 'earning' => 112.00],
                                        ['level' => 6, 'investment' => 1500, 'return' => 18.00, 'earning' => 270.00],
                                        ['level' => 7, 'investment' => 3000, 'return' => 20.00, 'earning' => 600.00],
                                        ['level' => 8, 'investment' => 5000, 'return' => 22.00, 'earning' => 1100.00],
                                        ['level' => 9, 'investment' => 7500, 'return' => 24.00, 'earning' => 1800.00],
                                        ['level' => 10, 'investment' => 10000, 'return' => 26.00, 'earning' => 2600.00]
                                    ];
                                    
                                    foreach ($investment_levels as $level) {
                                        echo "<tr>";
                                        echo "<td>{$level['level']}</td>";
                                        echo "<td>{$level['investment']}</td>";
                                        echo "<td>{$level['return']}%</td>";
                                        echo "<td>{$level['earning']}</td>";
                                        echo "<td><a href='deposit.php?plan_id={$level['level']}' class='btn btn-sm btn-conoco'>Investir</a></td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section Historique des Dépôts -->
                <div class="card investment-card mt-4">
                    <div class="card-header">
                        <i class="fas fa-money-bill-wave me-2"></i>Historique des Dépôts
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="investment-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Opérateur</th>
                                        <th>Pays</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deposits as $deposit): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($deposit['created_at'])) ?></td>
                                        <td>
                                            <?= number_format($deposit['amount'], 0) ?> XOF
                                            (≈ <?= number_format($deposit['amount'] / (defined('EXCHANGE_RATE') ? EXCHANGE_RATE : 600), 2) ?> USD)
                                        </td>
                                        <td><?= htmlspecialchars($deposit['network'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $deposit['country'] ?? 'N/A'))) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $deposit['status'] === 'completed' ? 'success' : 
                                                ($deposit['status'] === 'pending' ? 'warning' : 'danger') 
                                            ?>">
                                                <?= ucfirst($deposit['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($deposits)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Aucun dépôt effectué</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section Transactions -->
                <div class="card investment-card mt-4">
                    <div class="card-header">
                        <i class="fas fa-exchange-alt me-2"></i>Historique des Transactions
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="investment-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($transactions as $tx): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $tx['type'] === 'deposit' ? 'primary' : ($tx['type'] === 'withdrawal' ? 'warning' : 'success') ?>">
                                                <?= ucfirst($tx['type']) ?>
                                            </span>
                                        </td>
                                        <td><?= APP_CURRENCY ?> <?= number_format($tx['amount'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= get_status_badge($tx['status']) ?>">
                                                <?= ucfirst($tx['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne droite -->
            <div class="col-md-4">
                <!-- Section Parrainage -->
                <div class="referral-section">
                    <h5 class="mb-3"><i class="fas fa-users me-2"></i>Programme de Parrainage</h5>
                    <p class="text-muted">Code d'invitation :</p>
                    <div class="referral-code"><?= $user['referral_code'] ?></div>
                    
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="referralLink" 
                               value="<?= "https://applovin.kesug.com/register.php?ref=".$user['referral_code'] ?>" readonly>
                        <button class="btn btn-conoco" onclick="copyReferralLink()">
                            <i class="fas fa-copy me-1"></i>Copier
                        </button>
                    </div>
                    
                    <div class="tab-content" id="referralTabContent">
                        <div class="tab-pane fade show active" id="team-tab-pane" role="tabpanel">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="commission-level">
                                        <h5>Recharge d'équipe</h5>
                                        <h3 class="text-primary"><?= number_format($team_stats['team_recharge'], 2) ?> USDT</h3>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="commission-level">
                                        <h5>Retrait d'équipe</h5>
                                        <h3 class="text-primary"><?= number_format($team_stats['team_withdrawal'], 2) ?> USDT</h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="commission-level">
                                <h5>Nouvelle équipe</h5>
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <small>Première recharge</small>
                                        <h4><?= $team_stats['new_team_first_recharge'] ?></h4>
                                    </div>
                                    <div class="col-6">
                                        <small>Premier retrait</small>
                                        <h4><?= $team_stats['new_team_first_withdrawal'] ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="commission-tab-pane" role="tabpanel">
                            <div class="commission-level">
                                <h5>Niveau 1</h5>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <small>Invités/Validés</small>
                                        <h4><?= $invitation_stats['level1']['invited'] ?>/<?= $invitation_stats['level1']['validated'] ?></h4>
                                    </div>
                                    <div>
                                        <small>Revenu total</small>
                                        <h4><?= number_format($invitation_stats['level1']['income'], 2) ?> USDT</h4>
                                    </div>
                                    <div>
                                        <small>Commission</small>
                                        <h4 class="text-success">10%</h4>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="commission-level">
                                <h5>Niveau 2</h5>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <small>Invités/Validés</small>
                                        <h4><?= $invitation_stats['level2']['invited'] ?>/<?= $invitation_stats['level2']['validated'] ?></h4>
                                    </div>
                                    <div>
                                        <small>Revenu total</small>
                                        <h4><?= number_format($invitation_stats['level2']['income'], 2) ?> USDT</h4>
                                    </div>
                                    <div>
                                        <small>Commission</small>
                                        <h4 class="text-success">2%</h4>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="commission-level">
                                <h5>Niveau 3</h5>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <small>Invités/Validés</small>
                                        <h4><?= $invitation_stats['level3']['invited'] ?>/<?= $invitation_stats['level3']['validated'] ?></h4>
                                    </div>
                                    <div>
                                        <small>Revenu total</small>
                                        <h4><?= number_format($invitation_stats['level3']['income'], 2) ?> USDT</h4>
                                    </div>
                                    <div>
                                        <small>Commission</small>
                                        <h4 class="text-success">2%</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barre de navigation mobile -->
        <nav class="mobile-bottom-nav d-lg-none">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Accueil</span>
            </a>
            <a href="withdraw.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>Retrait</span>
            </a>
            <a href="referrals.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Équipe</span>
            </a>
            <a href="transactions.php" class="nav-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profil</span>
            </a>
        </nav>

        <style>
        .welcome-section {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e2e6ea 100%);
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }

        .toggle-password {
            cursor: pointer;
        }

        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: linear-gradient(135deg, #1a3e8c, #0d2b6b);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-around;
            padding: 0.5rem 0;
        }

        .mobile-bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            padding: 0.5rem;
            font-size: 0.8rem;
        }

        .mobile-bottom-nav .nav-item.active {
            color: white;
        }

        .mobile-bottom-nav .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }
        </style>

        <!-- Espace pour éviter que le contenu ne soit caché par le menu fixe -->
        <div style="height: 70px;"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function copyReferralLink() {
        const link = document.getElementById("referralLink");
        link.select();
        document.execCommand("copy");
        
        // Notification simple
        alert("Lien de parrainage copié avec succès !");
    }
    
    // Actualisation automatique toutes les 30 secondes
    setTimeout(function(){
        location.reload();
    }, 30000);
    
    // Fonction pour rafraîchir les données sans recharger la page
    function refreshData() {
        fetch('api/get_live_data.php?user_id=<?= $user_id ?>')
            .then(response => response.json())
            .then(data => {
                // Mettre à jour les données d'équipe
                document.querySelector('#team-tab-pane .text-primary:nth-child(1)').textContent = 
                    data.team_recharge.toFixed(2) + ' USDT';
                document.querySelector('#team-tab-pane .text-primary:nth-child(2)').textContent = 
                    data.team_withdrawal.toFixed(2) + ' USDT';
                document.querySelector('#team-tab-pane h4:nth-child(1)').textContent = 
                    data.new_team_first_recharge;
                document.querySelector('#team-tab-pane h4:nth-child(2)').textContent = 
                    data.new_team_first_withdrawal;
                
                // Mettre à jour les données d'invitation
                for (let level = 1; level <= 3; level++) {
                    const levelData = data['level' + level];
                    document.querySelector(`#commission-tab-pane h4:nth-child(${level * 3 - 2})`).textContent = 
                        `${levelData.invited}/${levelData.validated}`;
                    document.querySelector(`#commission-tab-pane h4:nth-child(${level * 3 - 1})`).textContent = 
                        levelData.income.toFixed(2) + ' USDT';
                }
            })
            .catch(error => console.error('Erreur:', error));
    }
    
    // Rafraîchir les données toutes les 10 secondes
    setInterval(refreshData, 300000);
    </script>
</body>
</html>
