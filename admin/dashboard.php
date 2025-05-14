<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

//check_admin();

// Vérifiez si $pdo est défini, sinon reconnectez-vous
if (!isset($pdo)) {
    try {
        $pdo = new PDO("mysql:host=sql211.infinityfree.com;dbname=if0_38609766_applovin_db;charset=utf8mb4", "if0_38609766", "q5N2wOnaR79Ykg");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
}

// Statistiques générales
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as active_investments FROM investments WHERE status = 'active'");
$active_investments = $stmt->fetch()['active_investments'];

$stmt = $pdo->query("SELECT SUM(amount) as total_invested FROM investments WHERE status = 'active'");
$total_invested = $stmt->fetch()['total_invested'] ?? 0;

$stmt = $pdo->query("SELECT SUM(amount) as total_withdrawals FROM withdrawals WHERE status = 'completed'");
$total_withdrawals = $stmt->fetch()['total_withdrawals'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as pending_withdrawals FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals = $stmt->fetch()['pending_withdrawals'];

// Fonction pour déterminer la classe de badge selon le statut
function get_status_badge($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'completed': return 'success';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

require_once '../includes/header.php';
?>

<style>
/* Style moderne pour le dashboard */
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    font-family: 'Arial', sans-serif;
    color: #333;
}

.container-fluid {
    padding: 20px;
}

h2 {
    color: #2c3e50;
    font-weight: 700;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.card-header {
    background: linear-gradient(to right, #3498db, #2980b9);
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 15px;
    font-weight: 600;
}

.card-body {
    padding: 20px;
    background: white;
    border-radius: 0 0 15px 15px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    background: #f8f9fa;
}

.stat-card i {
    font-size: 2rem;
    color: #3498db;
}

.table-responsive {
    margin-top: 20px;
}

.table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
}

.table thead th {
    background: #3498db;
    color: white;
    border: none;
    padding: 15px;
    font-weight: 600;
}

.table tbody tr {
    transition: background 0.3s ease;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 8px 12px;
    border-radius: 10px;
    font-weight: 500;
}

.badge-warning { background: #ffca28; color: #333; }
.badge-success { background: #4caf50; color: white; }
.badge-danger { background: #f44336; color: white; }
.badge-secondary { background: #9e9e9e; color: white; }

.btn-modern {
    background: linear-gradient(to right, #3498db, #2980b9);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.btn-modern:hover {
    background: linear-gradient(to right, #2980b9, #3498db);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.action-btn {
    margin-right: 10px;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
}

.action-btn-danger {
    background: #f44336;
    color: white;
}

.action-btn-danger:hover {
    background: #da190b;
}

.action-btn-warning {
    background: #ffca28;
    color: #333;
}

.action-btn-warning:hover {
    background: #f57c00;
}

.action-btn-success {
    background: #4caf50;
    color: white;
}

.action-btn-success:hover {
    background: #45a049;
}

/* Modal styles */
.modal-content {
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.modal-header {
    background: #3498db;
    color: white;
    border-radius: 15px 15px 0 0;
}

.modal-footer {
    border-top: none;
    padding: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .card {
        margin-bottom: 15px;
    }
    .col-md-6 {
        width: 100%;
    }
}
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2>Tableau de Bord Administrateur</h2>
            <hr>
        </div>
    </div>

    <!-- Cartes de Statistiques -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <i class="fas fa-users"></i>
                <h5 class="mt-2">Utilisateurs</h5>
                <h3 class="text-primary"><?php echo $total_users; ?></h3>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <i class="fas fa-chart-line"></i>
                <h5 class="mt-2">Investissements Actifs</h5>
                <h3 class="text-success"><?php echo $active_investments; ?></h3>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h5 class="mt-2">Total Investi</h5>
                <h3 class="text-info"><?php echo number_format($total_invested, 2); ?> USDT</h3>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card">
                <i class="fas fa-comments"></i>
                <h5 class="mt-2">Retraits en Attente</h5>
                <h3 class="text-warning"><?php echo $pending_withdrawals; ?></h3>
            </div>
        </div>
    </div>

    <!-- Sections principales -->
    <div class="row">
        <!-- Gestion des Utilisateurs -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-white">Gestion des Utilisateurs</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC");
                                while ($user = $stmt->fetch()):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn action-btn action-btn-warning" onclick="stopRemuneration(<?php echo $user['id']; ?>)">Stopper Rémunération</button>
                                        <button class="btn action-btn action-btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">Supprimer</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gestion des Retraits -->
        <div class="col-lg-6 mb-4">
    <div class="card shadow">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-white">Demandes de Retraits</h6>
            <button class="btn btn-modern float-right" onclick="downloadWithdrawals()">Télécharger en Excel</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Détails</th>
                            <th>Réseau</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($withdrawal = $stmt->fetch()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($withdrawal['username']); ?></td>
                            <td><?php echo number_format($withdrawal['amount'], 2); ?> USD</td>
                            <td><?php echo htmlspecialchars($withdrawal['method'] === 'usdt' ? 'USDT' : 'Mobile Money'); ?></td>
                            <td>
                                <?php if ($withdrawal['method'] === 'usdt'): ?>
                                    Adresse: <?= htmlspecialchars($withdrawal['wallet_address'] ?? 'Non spécifié') ?>
                                <?php else: ?>
                                    Téléphone: <?= htmlspecialchars($withdrawal['phone'] ?? 'Non spécifié') ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($withdrawal['network'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo get_status_badge($withdrawal['status']); ?>">
                                    <?php echo ucfirst($withdrawal['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                            <td>
                                <?php if ($withdrawal['status'] === 'pending'): ?>
                                    <button class="btn action-btn action-btn-success" onclick="approveWithdrawal(<?php echo $withdrawal['id']; ?>)">Approuver</button>
                                    <button class="btn action-btn action-btn-danger" onclick="rejectWithdrawal(<?php echo $withdrawal['id']; ?>)">Rejeter</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (!$stmt->rowCount()): ?>
                        <tr>
                            <td colspan="8" class="text-center">Aucune demande de retrait</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

        <!-- Investisseurs et Filleuls -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-white">Investisseurs et Leurs Filleuls</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Investisseur</th>
                                    <th>Email</th>
                                    <th>Nombre de Filleuls</th>
                                    <th>Total Investi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $pdo->query("
                                    SELECT u.id, u.username, u.email, 
                                           (SELECT COUNT(*) FROM users ref WHERE ref.referred_by = u.id) as referrals,
                                           (SELECT SUM(i.amount) FROM investments i WHERE i.user_id = u.id AND i.status = 'active') as total_invested
                                    FROM users u
                                    WHERE EXISTS (SELECT 1 FROM investments i WHERE i.user_id = u.id)
                                    ORDER BY u.created_at DESC
                                ");
                                while ($investor = $stmt->fetch()):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($investor['username']); ?></td>
                                    <td><?php echo htmlspecialchars($investor['email']); ?></td>
                                    <td><?php echo $investor['referrals']; ?></td>
                                    <td><?php echo number_format($investor['total_invested'] ?? 0, 2); ?> USDT</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
<script>
function stopRemuneration(userId) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Voulez-vous arrêter la rémunération de cet utilisateur ?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, arrêter !'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('actions.php', { action: 'stop_remuneration', user_id: userId }, function(response) {
                Swal.fire('Succès!', 'Rémunération arrêtée.', 'success').then(() => location.reload());
            }).fail(function() {
                Swal.fire('Erreur!', 'Une erreur s’est produite.', 'error');
            });
        }
    });
}

function deleteUser(userId) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible ! Voulez-vous supprimer cet utilisateur ?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer !'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('actions.php', { action: 'delete_user', user_id: userId }, function(response) {
                Swal.fire('Succès!', 'Utilisateur supprimé.', 'success').then(() => location.reload());
            }).fail(function() {
                Swal.fire('Erreur!', 'Une erreur s’est produite.', 'error');
            });
        }
    });
}

function downloadWithdrawals() {
    Swal.fire({
        title: 'Téléchargement en cours...',
        text: 'Veuillez patienter pendant la génération du fichier Excel.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    }).then((result) => {
        if (result.isDismissed) {
            Swal.fire('Annulé', 'Le téléchargement a été annulé.', 'info');
        }
    });

    window.location.href = 'download_withdrawals.php';
}

function approveWithdrawal(withdrawalId) {
    $.post('actions.php', { action: 'approve_withdrawal', withdrawal_id: withdrawalId }, function(response) {
        Swal.fire('Succès!', 'Retrait approuvé.', 'success').then(() => location.reload());
    }).fail(function() {
        Swal.fire('Erreur!', 'Une erreur s’est produite.', 'error');
    });
}

function rejectWithdrawal(withdrawalId) {
    $.post('actions.php', { action: 'reject_withdrawal', withdrawal_id: withdrawalId }, function(response) {
        Swal.fire('Succès!', 'Retrait rejeté.', 'success').then(() => location.reload());
    }).fail(function() {
        Swal.fire('Erreur!', 'Une erreur s’est produite.', 'error');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>