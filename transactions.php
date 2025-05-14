<?php
// =============================================
// CONFIGURATION DU DÉBOGAGE
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/transactions_errors.log');

// =============================================
// INCLUSION DES FICHIERS REQUIS
// =============================================
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// =============================================
// INITIALISATION ET VÉRIFICATIONS
// =============================================
try {
    // Vérification de l'authentification
    check_auth();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Session utilisateur invalide");
    }

    // Connexion à la base de données
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        throw new Exception("Échec de la connexion à la base de données");
    }

    // Récupération des infos utilisateur
    $user_id = $_SESSION['user_id'];
    $user = get_user_details($pdo, $user_id);
    
    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }

    // =============================================
    // GESTION DES FILTRES
    // =============================================
    $filters = [
        'type' => isset($_GET['type']) ? clean_input($_GET['type']) : '',
        'status' => isset($_GET['status']) ? clean_input($_GET['status']) : '',
        'start_date' => isset($_GET['start_date']) ? clean_input($_GET['start_date']) : '',
        'end_date' => isset($_GET['end_date']) ? clean_input($_GET['end_date']) : ''
    ];

    // Validation des filtres
    $allowed_types = ['deposit', 'withdrawal', 'investment', 'profit', 'referral', ''];
    $allowed_statuses = ['completed', 'pending', 'failed', ''];
    
    if (!in_array($filters['type'], $allowed_types)) {
        $filters['type'] = '';
    }
    
    if (!in_array($filters['status'], $allowed_statuses)) {
        $filters['status'] = '';
    }

    // Récupération des transactions avec filtres
    $transactions = get_user_transactions($pdo, $user_id, $filters);
    
    // Calcul des statistiques
    $stats = calculate_transaction_stats($transactions);

} catch (PDOException $e) {
    error_log("PDOException in transactions.php: " . $e->getMessage());
    $_SESSION['error'] = "Une erreur de base de données est survenue";
    header("Location: dashboard.php");
    exit();
} catch (Exception $e) {
    error_log("Exception in transactions.php: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header("Location: dashboard.php");
    exit();
}

// =============================================
// FONCTIONS UTILITAIRES
// =============================================

/**
 * Récupère les détails de l'utilisateur
 */
function get_user_details($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère les transactions avec filtres
 */
function get_user_transactions($pdo, $user_id, $filters) {
    $sql = "SELECT * FROM transactions WHERE user_id = :user_id";
    $params = [':user_id' => $user_id];

    if (!empty($filters['type'])) {
        $sql .= " AND type = :type";
        $params[':type'] = $filters['type'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND status = :status";
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['start_date']) && validate_date($filters['start_date'])) {
        $sql .= " AND DATE(created_at) >= :start_date";
        $params[':start_date'] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date']) && validate_date($filters['end_date'])) {
        $sql .= " AND DATE(created_at) <= :end_date";
        $params[':end_date'] = $filters['end_date'];
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcule les statistiques des transactions
 */
function calculate_transaction_stats($transactions) {
    $stats = [
        'total' => 0,
        'deposit' => 0,
        'withdrawal' => 0,
        'investment' => 0,
        'profit' => 0,
        'referral' => 0,
        'current_balance' => 0
    ];

    foreach ($transactions as $tx) {
        $stats['total']++;
        $stats[strtolower($tx['type'])] += $tx['amount'];
        
        if ($stats['current_balance'] == 0) {
            $stats['current_balance'] = $tx['balance'];
        }
    }

    return $stats;
}

/**
 * Valide le format de date
 */
function validate_date($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

/**
 * Nettoie les entrées utilisateur
 */
function clean_input($data) {
    return htmlspecialchars(trim($data));
}

// =============================================
// AFFICHAGE HTML
// =============================================
$page_title = "Historique des Transactions";
require_once 'includes/header.php';
?>

<!-- Section Principale -->
<div class="container py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-exchange-alt me-2"></i>Historique des Transactions</h2>
            <p class="text-muted mb-0">Vos mouvements financiers</p>
        </div>
        <div class="badge bg-primary p-2">
            <i class="fas fa-wallet me-1"></i>
            Solde: <?= htmlspecialchars(APP_CURRENCY) ?> <?= number_format($stats['current_balance'], 2) ?>
        </div>
    </div>

    <!-- Cartes de statistiques -->
    <div class="row g-3 mb-4">
        <?php 
        $stat_cards = [
            ['title' => 'Total', 'value' => $stats['total'], 'bg' => 'primary', 'icon' => 'fas fa-list'],
            ['title' => 'Dépôts', 'value' => APP_CURRENCY.' '.number_format($stats['deposit'], 2), 'bg' => 'success', 'icon' => 'fas fa-arrow-down'],
            ['title' => 'Retraits', 'value' => APP_CURRENCY.' '.number_format($stats['withdrawal'], 2), 'bg' => 'danger', 'icon' => 'fas fa-arrow-up'],
            ['title' => 'Investissements', 'value' => APP_CURRENCY.' '.number_format($stats['investment'], 2), 'bg' => 'info', 'icon' => 'fas fa-chart-line'],
            ['title' => 'Profits', 'value' => APP_CURRENCY.' '.number_format($stats['profit'], 2), 'bg' => 'warning', 'icon' => 'fas fa-coins'],
            ['title' => 'Parrainage', 'value' => APP_CURRENCY.' '.number_format($stats['referral'], 2), 'bg' => 'purple', 'icon' => 'fas fa-users']
        ];
        
        foreach ($stat_cards as $card): ?>
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card bg-<?= $card['bg'] ?>-subtle border-0">
                <div class="card-body text-center">
                    <i class="<?= $card['icon'] ?> fs-4 text-<?= $card['bg'] ?> mb-2"></i>
                    <h6 class="card-subtitle mb-1 text-muted"><?= $card['title'] ?></h6>
                    <h5 class="card-title mb-0 text-<?= $card['bg'] ?>"><?= $card['value'] ?></h5>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtres</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="type" class="form-label">Type</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">Tous types</option>
                        <?php foreach (['deposit', 'withdrawal', 'investment', 'profit', 'referral'] as $t): ?>
                        <option value="<?= $t ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>>
                            <?= ucfirst($t) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Statut</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Tous statuts</option>
                        <?php foreach (['completed', 'pending', 'failed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Date début</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" 
                           value="<?= htmlspecialchars($filters['start_date']) ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Date fin</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" 
                           value="<?= htmlspecialchars($filters['end_date']) ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i> Appliquer
                    </button>
                    <a href="transactions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Liste des transactions -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($transactions)): ?>
                <div class="alert alert-info m-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucune transaction trouvée avec ces critères
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date/Heure</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Solde</th>
                                <th>Description</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span><?= date('d/m/Y', strtotime($tx['created_at'])) ?></span>
                                            <small class="text-muted"><?= date('H:i', strtotime($tx['created_at'])) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= get_type_badge($tx['type']) ?>">
                                            <?= ucfirst($tx['type']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold <?= $tx['amount'] < 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $tx['amount'] < 0 ? '' : '+' ?><?= htmlspecialchars(APP_CURRENCY) ?> <?= number_format($tx['amount'], 2) ?>
                                    </td>
                                    <td><?= htmlspecialchars(APP_CURRENCY) ?> <?= number_format($tx['balance'], 2) ?></td>
                                    <td><?= htmlspecialchars($tx['description']) ?></td>
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
            <?php endif; ?>
        </div>
        <div class="card-footer bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">
                        Affichage de <?= count($transactions) ?> transaction(s)
                    </small>
                </div>
                <?php if (!empty($transactions)): ?>
                    <div>
                        <small class="text-muted">
                            Dernière transaction: <?= date('d/m/Y H:i', strtotime($transactions[0]['created_at'])) ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Barre de navigation mobile -->
<nav class="mobile-bottom-nav d-lg-none">
    <a href="dashboard.php" class="nav-item">
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
    <a href="transactions.php" class="nav-item active">
        <i class="fas fa-exchange-alt"></i>
        <span>Transactions</span>
    </a>
    <a href="profile.php" class="nav-item ">
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

<?php
// =============================================
// FONCTIONS D'AFFICHAGE ET FOOTER
// =============================================

function get_type_badge($type) {
    $types = [
        'deposit' => 'success',
        'withdrawal' => 'danger',
        'investment' => 'info',
        'profit' => 'warning',
        'referral' => 'purple'
    ];
    return $types[strtolower($type)] ?? 'secondary';
}

function get_status_badge($status) {
    $statuses = [
        'completed' => 'success',
        'pending' => 'warning',
        'failed' => 'danger'
    ];
    return $statuses[strtolower($status)] ?? 'secondary';
}


?>

<style>
/* Styles personnalisés */
.bg-purple {
    background-color: #6f42c1;
}

.text-purple {
    color: #6f42c1;
}

.bg-purple-subtle {
    background-color: rgba(111, 66, 193, 0.1);
}

.stat-card {
    border-radius: 0.5rem;
    transition: all 0.2s;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
}

.table-responsive {
    min-height: 300px;
}
</style>