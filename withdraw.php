<?php
// Activation du mode debug (désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Inclure les dépendances
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

use Paydunya\Setup;
use Paydunya\Checkout\Store;
use Paydunya\Checkout\DirectPay;

// Vérification d'authentification
try {
    check_auth();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Session utilisateur invalide");
    }

    // Configurer PayDunya pour les retraits
    // TODO: Déplacer ces clés vers config.php
    Setup::setMasterKey('61UU2abw-fmvT-nNDA-GFMe-WcecHjEdfYoP'); // Exemple
    Setup::setPublicKey('live_public_5Uhdeo8oxHpBR5CwevG4juyZ4yF'); // Exemple
    Setup::setPrivateKey('live_private_omjNDYClxSRu8KZoDBSvLRo4QEm'); // Exemple
    Setup::setToken('X7R67BRbIbnthZ7BTyPr'); // Exemple
    Setup::setMode('live'); // 'test' pour développement

    Store::setName('Applovin');
    Store::setTagline('Investissement et Mobile Money');
    Store::setPhoneNumber('+9238846728');
    Store::setWebsiteUrl('https://applovin-invest.onrender.com');
    Store::setLogoUrl('https://applovin-invest.onrender.com/logo.png');

    // Obtenir l'instance de PDO
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $user_id = $_SESSION['user_id'];
    $error = '';
    $success = '';

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
        $balance['current'] = $balance['invested'] + $balance['profit'];
        $min_withdrawal_amount = $balance['invested'] * 0.18; // 18%
        $can_withdraw = ($balance['profit'] >= $min_withdrawal_amount);
    } catch (Exception $e) {
        error_log("Erreur calcul balance: " . $e->getMessage());
        $error = "Erreur de calcul du solde: " . $e->getMessage();
        $can_withdraw = false;
    }

    // Traitement du formulaire de retrait
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
        try {
            $amount = floatval($_POST['amount']);
            $method = $_POST['method'];
            $details = trim($_POST['details']);
            $network = $_POST['network'] ?? null;
            $country = $_POST['country'] ?? 'senegal'; // Par défaut Sénégal

            // Validation
            if (!$can_withdraw) {
                throw new Exception("Profit insuffisant (minimum 18% requis)");
            }
            if ($amount < 5) {
                throw new Exception("Montant minimum: 5 USD");
            }
            if ($amount > $balance['profit']) {
                throw new Exception("Montant supérieur à votre profit disponible");
            }
            if (empty($details)) {
                throw new Exception("Détails du paiement requis");
            }

            // Déterminer si c'est USDT ou Mobile Money
            $wallet_address = null;
            $phone = null;
            $status = 'pending';
            $transaction_id = null;

            if ($method === 'usdt') {
                $wallet_address = $details;
            } else {
                $phone = $details;

                // Convertir USD en XOF
                $xof_amount = $amount * (defined('EXCHANGE_RATE') ? EXCHANGE_RATE : 600);

                // Mapper le réseau et le pays au canal PayDunya
                $network_map = [
                    'Orange' => [
                        'senegal' => 'orange-money-senegal',
                        'ivory_coast' => 'orange-money-ci',
                        'burkina_faso' => 'orange-money-burkina',
                        'mali' => 'orange-money-mali'
                    ],
                    'MTN' => [
                        'benin' => 'mtn-benin'
                    ],
                    'Moov' => [
                        'ivory_coast' => 'moov-money-ci',
                        'benin' => 'moov-money-benin',
                        'togo' => 'flooz-togo'
                    ]
                ];

                $channel = $network_map[$network][$country] ?? null;
                if (!$channel) {
                    throw new Exception("Opérateur ou pays non supporté pour Mobile Money");
                }

                // Initiater le paiement via PayDunya
                $disbursement = new DirectPay();
                $payout_data = [
                    'phone_number' => $phone,
                    'amount' => $xof_amount,
                    'channel' => $channel,
                    'description' => "Retrait Mobile Money pour utilisateur $user_id"
                ];

                try {
                    $response = $disbursement->createDisbursement($payout_data);
                    if ($response['success']) {
                        $status = 'processing';
                        $transaction_id = $response['transaction_id'] ?? null;
                        $success = "Retrait initié avec succès. Traitement en cours.";
                    } else {
                        // Fallback : essayer avec un autre canal si disponible
                        $fallback_channel = 'orange-money-senegal'; // Canal de secours
                        if ($channel !== $fallback_channel) {
                            $payout_data['channel'] = $fallback_channel;
                            $response = $disbursement->createDisbursement($payout_data);
                            if ($response['success']) {
                                $status = 'processing';
                                $transaction_id = $response['transaction_id'] ?? null;
                                $success = "Retrait initié avec succès via canal de secours.";
                            } else {
                                throw new Exception("Erreur PayDunya: " . ($response['message'] ?? 'Échec du paiement'));
                            }
                        } else {
                            throw new Exception("Erreur PayDunya: " . ($response['message'] ?? 'Échec du paiement'));
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erreur PayDunya retrait: " . $e->getMessage());
                    throw new Exception("Échec de l'initiation du retrait: " . $e->getMessage());
                }
            }

            // Enregistrement sécurisé
            $stmt = $pdo->prepare("INSERT INTO withdrawals 
                                  (user_id, amount, method, wallet_address, phone, network, country, status, transaction_id, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt->execute([$user_id, $amount, $method, $wallet_address, $phone, $network, $country, $status, $transaction_id])) {
                throw new PDOException("Erreur lors de l'enregistrement");
            }

            // Mise à jour du solde après retrait
            $balance['profit'] -= $amount;
            $balance['current'] = $balance['invested'] + $balance['profit'];

            if (!$success) {
                $success = "Demande enregistrée. Traitement sous 24h.";
            }

        } catch (PDOException $e) {
            error_log("Erreur DB retrait: " . $e->getMessage());
            $error = "Erreur système. Veuillez réessayer.";
        } catch (Exception $e) {
            error_log("Erreur retrait: " . $e->getMessage());
            $error = $e->getMessage();
        }
    }

    // Récupération de l'historique des retraits
    $withdrawals = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM withdrawals 
                             WHERE user_id = ? 
                             ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur récupération historique: " . $e->getMessage());
        $error = $error ?: "Erreur lors de la récupération de l'historique";
    }

} catch (Exception $e) {
    error_log("ERREUR WITHDRAW: " . $e->getMessage());
    $_SESSION['error'] = "Erreur système. Veuillez vous reconnecter.";
    header("Location: login.php");
    exit();
}

$page_title = "Demande de retrait";
require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Affichage des messages -->
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Carte de solde -->
            <div class="card shadow-lg mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0"><i class="fas fa-wallet me-2"></i> Votre Solde</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center border-end">
                            <h5>Capital Investi</h5>
                            <h3 class="text-primary">$<?= number_format($balance['invested'], 2) ?></h3>
                        </div>
                        <div class="col-md-4 text-center border-end">
                            <h5>Profit Total</h5>
                            <h3 class="text-success">$<?= number_format($balance['profit'], 2) ?></h3>
                        </div>
                        <div class="col-md-4 text-center">
                            <h5>Minimum Requis</h5>
                            <h3 class="<?= $can_withdraw ? 'text-success' : 'text-danger' ?>">
                                $<?= number_format($min_withdrawal_amount, 2) ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire de retrait -->
            <div class="card shadow-lg">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" id="withdrawalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="usdt-tab" data-bs-toggle="tab" data-bs-target="#usdt" type="button" role="tab">
                                <i class="fab fa-ethereum me-2"></i> USDT
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="mobile-tab" data-bs-toggle="tab" data-bs-target="#mobile" type="button" role="tab">
                                <i class="fas fa-mobile-alt me-2"></i> Mobile Money
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="withdrawalTabsContent">
                        <!-- Onglet USDT -->
                        <div class="tab-pane fade show active" id="usdt" role="tabpanel">
                            <form method="POST" id="withdrawalForm">
                                <input type="hidden" name="method" value="usdt">
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-dollar-sign me-2"></i> Montant à retirer (USD)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="1" max="<?= htmlspecialchars($balance['profit']) ?>" 
                                               class="form-control" name="amount" required placeholder="Entrez le montant">
                                        <span class="input-group-text text-muted">Max: $<?= number_format($balance['profit'], 2) ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-network-wired me-2"></i> Réseau</label>
                                    <select class="form-select" name="network" required>
                                        <option value="">Sélectionnez un réseau</option>
                                        <option value="ERC20">ERC20 (Ethereum)</option>
                                        <option value="BEP20">BEP20 (Binance Smart Chain)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-wallet me-2"></i> Adresse USDT</label>
                                    <input type="text" class="form-control" name="details" 
                                           placeholder="Ex: 0x742d35Cc6634C0532925a3b844Bc454e4438f44e" required>
                                </div>
                                
                                <button type="submit" name="request_withdrawal" class="btn btn-primary w-100 py-2" <?= !$can_withdraw ? 'disabled' : '' ?>>
                                    <i class="fas fa-paper-plane me-2"></i> Demander le retrait
                                </button>
                            </form>
                        </div>
                        
                        <!-- Onglet Mobile Money -->
                        <div class="tab-pane fade" id="mobile" role="tabpanel">
                            <form method="POST" id="withdrawalFormMobile">
                                <input type="hidden" name="method" value="mobile_money">
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-dollar-sign me-2"></i> Montant à retirer (USD)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="1" max="<?= htmlspecialchars($balance['profit']) ?>" 
                                               class="form-control" name="amount" required placeholder="Entrez le montant">
                                        <span class="input-group-text text-muted">≈ <?= number_format(($amount ?? 0) * EXCHANGE_RATE, 0) ?> XOF</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-globe me-2"></i> Pays</label>
                                    <select class="form-select" name="country" required>
                                        <option value="">Sélectionnez un pays</option>
                                        <option value="senegal">Sénégal</option>
                                        <option value="ivory_coast">Côte d'Ivoire</option>
                                        <option value="benin">Bénin</option>
                                        <option value="togo">Togo</option>
                                        <option value="burkina_faso">Burkina Faso</option>
                                        <option value="mali">Mali</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-mobile-alt me-2"></i> Opérateur</label>
                                    <select class="form-select" name="network" required>
                                        <option value="">Sélectionnez un opérateur</option>
                                        <option value="Orange">Orange Money</option>
                                        <option value="MTN">MTN Mobile Money</option>
                                        <option value="Moov">Moov Money</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-phone me-2"></i> Numéro de téléphone</label>
                                    <input type="tel" class="form-control" name="details" 
                                           placeholder="Ex: +221701234567" required>
                                </div>
                                
                                <button type="submit" name="request_withdrawal" class="btn btn-primary w-100 py-2" <?= !$can_withdraw ? 'disabled' : '' ?>>
                                    <i class="fas fa-paper-plane me-2"></i> Demander le retrait
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Historique des retraits -->
            <div class="card shadow-lg mt-4">
                <div class="card-header bg-white">
                    <h3 class="mb-0"><i class="fas fa-history me-2"></i> Historique des retraits</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Méthode</th>
                                    <th>Montant</th>
                                    <th>Réseau</th>
                                    <th>Détails</th>
                                    <th>Statut</th>
                                    <th>Transaction ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $withdrawal): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($withdrawal['created_at'])) ?></td>
                                    <td><?= $withdrawal['method'] === 'usdt' ? 'USDT' : 'Mobile Money' ?></td>
                                    <td>$<?= number_format($withdrawal['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($withdrawal['network'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($withdrawal['method'] === 'usdt'): ?>
                                            Adresse: <?= htmlspecialchars($withdrawal['wallet_address'] ?? 'Non spécifié') ?>
                                        <?php else: ?>
                                            Téléphone: <?= htmlspecialchars($withdrawal['phone'] ?? 'Non spécifié') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $withdrawal['status'] === 'completed' ? 'success' : 
                                            ($withdrawal['status'] === 'pending' ? 'warning' : 
                                            ($withdrawal['status'] === 'processing' ? 'info' : 'danger')) 
                                        ?>">
                                            <?= ucfirst($withdrawal['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($withdrawal['transaction_id'] ?? 'N/A') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($withdrawals)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucun retrait effectué</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
    <a href="withdraw.php" class="nav-item active">
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
.container {
    max-width: 1140px;
    margin: auto;
}

.card {
    border-radius: 15px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.card-header {
    background: linear-gradient(45deg, #1a3e8c, #2c5282);
    color: white;
    font-weight: 600;
    padding: 1.5rem;
}

.card-body {
    background: #fff;
    padding: 1.5rem;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(45deg, #1a3e8c, #2c5282);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(45deg, #2c5282, #1a3e8c);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(26, 62, 140, 0.3);
}

.alert {
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: none;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
}

.form-control, .form-select {
    border-radius: 8px;
    border-color: #d4d4d4;
    padding: 0.75rem;
}

.form-label {
    font-weight: 500;
    color: #374151;
}

.table {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.table th {
    background: #1a3e8c;
    color: white;
    padding: 1rem;
}

.table td {
    padding: 1rem;
}

.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
}

.bg-success { background: #d1fae5; color: #065f46; }
.bg-warning { background: #fefcbf; color: #854d0e; }
.bg-info { background: #bfdbfe; color: #1e3a8a; }
.bg-danger { background: #fee2e2; color: #991b1b; }

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
    padding: 0.75rem 0;
}

.mobile-bottom-nav .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    padding: 0.5rem;
    font-size: 0.9rem;
}

.mobile-bottom-nav .nav-item.active {
    color: white;
}

.mobile-bottom-nav .nav-item i {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }

    .card {
        margin-bottom: 1rem;
    }

    .btn-primary {
        width: 100%;
    }

    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('#withdrawalForm, #withdrawalFormMobile');
    const rate = <?= EXCHANGE_RATE ?>;

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Envoi en cours...',
                text: 'Veuillez patienter.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            this.submit();
        });
    });

    const amountInputs = document.querySelectorAll('input[name="amount"]');
    amountInputs.forEach(input => {
        input.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const xofDisplay = this.closest('.input-group').querySelector('.input-group-text:last-child');
            if (xofDisplay) {
                xofDisplay.textContent = `≈ ${numberFormat(amount * rate)} XOF`;
            }
        });
    });

    function numberFormat(number) {
        return number.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
});
</script>

