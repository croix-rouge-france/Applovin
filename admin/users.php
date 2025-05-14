<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

check_admin();

// Filtres et pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construire la requête
$sql = "SELECT * FROM users";
$count_sql = "SELECT COUNT(*) as total FROM users";
$params = [];
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
    $count_sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Exécuter les requêtes
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$stmt = $pdo->prepare($count_sql);
$stmt->execute(array_slice($params, 0, -2));
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

$page_title = "Gestion des Utilisateurs";
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-users"></i> Gestion des Utilisateurs</h2>
            <hr>
        </div>
    </div>
    
    <!-- Barre de recherche -->
    <div class="row mb-4">
        <div class="col-md-12">
            <form method="GET" class="form-inline">
                <div class="input-group w-100">
                    <input type="text" class="form-control" name="search" placeholder="Rechercher par nom ou email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Liste des utilisateurs -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($users)): ?>
                    <div class="alert alert-info">Aucun utilisateur trouvé.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Code Parrainage</th>
                                    <th>Parrain</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['referral_code']); ?></td>
                                    <td>
                                        <?php if ($user['referred_by']): ?>
                                        <?php 
                                            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                                            $stmt->execute([$user['referred_by']]);
                                            $referrer = $stmt->fetch();
                                            echo $referrer ? htmlspecialchars($referrer['username']) : 'Inconnu';
                                        ?>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                                    &laquo; Précédent
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
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