<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

check_admin();

// Filtres et statuts
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construire la requête
$sql = "
    SELECT w.*, u.username, u.email 
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
";
$count_sql = "SELECT COUNT(*) as total FROM withdrawals";
$params = [];
$conditions = [];

if (!empty($status)) {
    $conditions[] = "w.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR w.wallet_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY w.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Exécuter les requêtes
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

$stmt = $pdo->prepare($count_sql);
$stmt->execute(array_slice($params, 0, -2));
$total_withdrawals = $stmt->fetch()['total'];
$total_pages = ceil($total_withdrawals / $limit);

$page_title = "Gestion des Retraits";
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-money-bill-wave"></i> Gestion des Retraits</h2>
            <hr>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-filter"></i> Filtres</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Statut</label>
                            <select id="status" name="status" class="form-select">
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Complété</option>
                                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejeté</option>
                                <option value="" <?php echo empty($status) ? 'selected' : ''; ?>>Tous les statuts</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Rechercher par utilisateur ou wallet..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des retraits -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($withdrawals)): ?>
                    <div class="alert alert-info">Aucun retrait trouvé.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Utilisateur</th>
                                    <th>Montant</th>
                                    <th>Méthode</th>
                                    <th>Wallet/Numéro</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $withdrawal): ?>
                                <tr>
                                    <td><?php echo $withdrawal['id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($withdrawal['username']); ?><br>
                                        <small><?php echo htmlspecialchars($withdrawal['email']); ?></small>
                                    </td>
                                    <td><?php echo APP_CURRENCY; ?> <?php echo number_format($withdrawal['amount'], 2); ?></td>
                                    <td><?php echo strtoupper(str_replace('_', ' ', $withdrawal['method'])); ?></td>
                                    <td><?php echo htmlspecialchars($withdrawal['wallet_address']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo get_status_badge($withdrawal['status']); ?>">
                                            <?php echo ucfirst($withdrawal['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($withdrawal['status'] === 'pending'): ?>
                                        <div class="btn-group">
                                            <a href="process_withdrawal.php?id=<?php echo $withdrawal['id']; ?>&action=approve" 
                                               class="btn btn-sm btn-success" title="Approuver">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="process_withdrawal.php?id=<?php echo $withdrawal['id']; ?>&action=reject" 
                                               class="btn btn-sm btn-danger" title="Rejeter">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                    &laquo; Précédent
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                    Suivant &raquo;
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>