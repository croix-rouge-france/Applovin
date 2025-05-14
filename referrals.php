<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

check_auth();

// Obtenir l'instance PDO via la classe Database
$db = Database::getInstance();
$pdo = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT username, referral_code, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fonction améliorée pour récupérer les filleuls avec statistiques
function get_referrals($pdo, $user_id, $level = 1) {
    if ($level > 3) return [];
    
    $stmt = $pdo->prepare("SELECT 
        u.id, 
        u.username, 
        u.referral_code, 
        u.created_at,
        (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'deposit') AS total_deposit,
        (SELECT SUM(amount) FROM transactions WHERE user_id = u.id AND type = 'withdrawal') AS total_withdrawal,
        (SELECT COUNT(*) FROM users WHERE referred_by = u.id) AS referral_count
    FROM users u WHERE u.referred_by = ?");
    
    $stmt->execute([$user_id]);
    $referrals = $stmt->fetchAll();
    
    foreach ($referrals as &$referral) {
        $referral['level'] = $level;
        $referral['sub_referrals'] = get_referrals($pdo, $referral['id'], $level + 1);
    }
    
    return $referrals;
}

$referral_tree = get_referrals($pdo, $user_id);

// Calculer les statistiques globales
$total_referrals = 0;
$total_invested = 0;
$total_commission = 0;

function calculate_stats($referrals) {
    $stats = ['count' => 0, 'invested' => 0, 'commission' => 0];
    
    foreach ($referrals as $referral) {
        $stats['count']++;
        $stats['invested'] += $referral['total_deposit'] ?? 0;
        $stats['commission'] += ($referral['total_deposit'] ?? 0) * (0.05 * (1 - ($referral['level'] - 1) * 0.5));
        
        $sub_stats = calculate_stats($referral['sub_referrals']);
        $stats['count'] += $sub_stats['count'];
        $stats['invested'] += $sub_stats['invested'];
        $stats['commission'] += $sub_stats['commission'];
    }
    
    return $stats;
}

$stats = calculate_stats($referral_tree);

$page_title = "Programme de Parrainage";
require_once 'includes/header.php';
?>

<!-- Section Bienvenue -->
<div class="welcome-section mb-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-1"><i class="fas fa-users me-2"></i>Programme de Parrainage</h2>
                <p class="mb-0 text-muted">Gérez votre équipe et vos commissions</p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-success">
                    <i class="fas fa-user-plus me-1"></i>
                    <?php echo $stats['count']; ?> filleul(s)
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container mt-3">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Carte Statistiques -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Statistiques de votre équipe</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">Total Filleuls</h5>
                                    <p class="card-text display-6"><?php echo $stats['count']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-success">Investissements</h5>
                                    <p class="card-text display-6"><?php echo number_format($stats['invested'], 2); ?> $</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-warning">Commissions</h5>
                                    <p class="card-text display-6"><?php echo number_format($stats['commission'], 2); ?> $</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carte Lien de parrainage -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-link me-2"></i>Votre Lien de Parrainage</h4>
                </div>
                <div class="card-body">
                    <p class="mb-3">Partagez ce lien pour inviter des membres et gagner des commissions :</p>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-user-plus"></i></span>
                        <input type="text" class="form-control" id="referralLink" 
                               value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $user['referral_code']; ?>" readonly>
                        <button class="btn btn-primary" onclick="copyReferralLink()">
                            <i class="fas fa-copy me-1"></i>Copier
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="shareOnWhatsApp()">
                            <i class="fab fa-whatsapp me-1"></i>WhatsApp
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="shareOnTelegram()">
                            <i class="fab fa-telegram me-1"></i>Telegram
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="shareByEmail()">
                            <i class="fas fa-envelope me-1"></i>Email
                        </button>
                    </div>
                </div>
            </div>

            <!-- Carte Arbre de parrainage -->
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-network-wired me-2"></i>Arbre de Parrainage</h4>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle" type="button" id="levelFilter" data-bs-toggle="dropdown">
                                Niveau 1
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="filterReferrals(1)">Niveau 1</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterReferrals(2)">Niveau 2</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterReferrals(3)">Niveau 3</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="referralTable">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Date</th>
                                    <th>Investissement</th>
                                    <th>Niveau</th>
                                </tr>
                            </thead>
                            <tbody id="referralTableBody">
                                <!-- Rempli par JavaScript -->
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
    <a href="withdraw.php" class="nav-item">
        <i class="fas fa-wallet"></i>
        <span>Retrait</span>
    </a>
    <a href="referrals.php" class="nav-item active">
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

.referral-level-1 { border-left: 4px solid #28a745; }
.referral-level-2 { border-left: 4px solid #17a2b8; }
.referral-level-3 { border-left: 4px solid #6c757d; }

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

<script>
// Fonction pour copier le lien de parrainage
function copyReferralLink() {
    const copyText = document.getElementById("referralLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    
    // Afficher un toast ou une notification
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 p-3';
    toast.innerHTML = `
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Succès</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Lien copié dans le presse-papiers!
            </div>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Fonctions de partage
function shareOnWhatsApp() {
    const link = document.getElementById("referralLink").value;
    window.open(`https://wa.me/?text=Rejoins-moi%20sur%20cette%20plateforme%20:%20${encodeURIComponent(link)}`);
}

function shareOnTelegram() {
    const link = document.getElementById("referralLink").value;
    window.open(`https://t.me/share/url?url=${encodeURIComponent(link)}&text=Rejoins-moi%20sur%20cette%20plateforme`);
}

function shareByEmail() {
    const link = document.getElementById("referralLink").value;
    window.location.href = `mailto:?subject=Rejoins-moi&body=Je%20t'invite%20à%20me%20rejoindre%20sur%20cette%20plateforme:%20${encodeURIComponent(link)}`;
}

// Gestion de l'arbre de parrainage
document.addEventListener("DOMContentLoaded", function() {
    const referralTree = <?php echo json_encode($referral_tree); ?>;
    const tableBody = document.getElementById("referralTableBody");
    
    function populateTable(referrals, level = 1) {
        tableBody.innerHTML = '';
        
        referrals.forEach(ref => {
            const row = document.createElement('tr');
            row.className = `referral-level-${level}`;
            
            row.innerHTML = `
                <td>
                    <strong>${ref.username}</strong>
                    <small class="text-muted d-block">${ref.referral_code}</small>
                </td>
                <td>${new Date(ref.created_at).toLocaleDateString()}</td>
                <td>${ref.total_deposit ? parseFloat(ref.total_deposit).toFixed(2) + ' $' : '0.00 $'}</td>
                <td><span class="badge bg-${level === 1 ? 'success' : level === 2 ? 'info' : 'secondary'}">Niveau ${level}</span></td>
            `;
            
            tableBody.appendChild(row);
            
            // Ajouter les sous-niveaux si nécessaire
            if (ref.sub_referrals && ref.sub_referrals.length > 0) {
                ref.sub_referrals.forEach(subRef => {
                    const subRow = document.createElement('tr');
                    subRow.className = `referral-level-${level + 1}`;
                    
                    subRow.innerHTML = `
                        <td>
                            <i class="fas fa-level-down-alt me-2"></i>
                            ${subRef.username}
                            <small class="text-muted d-block">${subRef.referral_code}</small>
                        </td>
                        <td>${new Date(subRef.created_at).toLocaleDateString()}</td>
                        <td>${subRef.total_deposit ? parseFloat(subRef.total_deposit).toFixed(2) + ' $' : '0.00 $'}</td>
                        <td><span class="badge bg-${level + 1 === 2 ? 'info' : 'secondary'}">Niveau ${level + 1}</span></td>
                    `;
                    
                    tableBody.appendChild(subRow);
                });
            }
        });
    }
    
    // Fonction pour filtrer par niveau
    window.filterReferrals = function(level) {
        document.getElementById('levelFilter').textContent = `Niveau ${level}`;
        
        if (level === 1) {
            populateTable(referralTree);
        } else {
            const filtered = [];
            
            function extractLevel(referrals, targetLevel, currentLevel = 1) {
                if (currentLevel === targetLevel) {
                    filtered.push(...referrals);
                } else {
                    referrals.forEach(ref => {
                        if (ref.sub_referrals && ref.sub_referrals.length > 0) {
                            extractLevel(ref.sub_referrals, targetLevel, currentLevel + 1);
                        }
                    });
                }
            }
            
            extractLevel(referralTree, level);
            populateTable(filtered, level);
        }
    };
    
    // Initialiser le tableau avec le niveau 1
    populateTable(referralTree);
});
</script>

