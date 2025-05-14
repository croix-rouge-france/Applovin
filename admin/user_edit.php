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
$error = '';
$success = '';

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "Utilisateur introuvable";
    header("Location: users.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($username) || empty($email)) {
        $error = "Le nom d'utilisateur et l'email sont obligatoires";
    } else {
        // Vérifier si l'email est déjà utilisé par un autre utilisateur
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé par un autre utilisateur";
        } else {
            // Mettre à jour l'utilisateur
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, email = ?, is_admin = ?, is_active = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$username, $email, $is_admin, $is_active, $user_id])) {
                $success = "Utilisateur mis à jour avec succès";
                // Recharger les données utilisateur
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error = "Une erreur s'est produite lors de la mise à jour";
            }
        }
    }
}

$page_title = "Modifier l'Utilisateur";
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-user-edit"></i> Modifier l'Utilisateur</h2>
            <hr>
            
            <div class="mb-3">
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5>Modifier les informations</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin" 
                                   <?php echo $user['is_admin'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_admin">Administrateur</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                   <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Compte actif</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning">Mettre à jour</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Section dangereuse -->
            <div class="card mt-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5>Zone dangereuse</h5>
                </div>
                <div class="card-body">
                    <p>Ces actions sont irréversibles. Soyez certain de ce que vous faites.</p>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                            <i class="fas fa-key"></i> Réinitialiser le mot de passe
                        </button>
                        
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                            <i class="fas fa-trash"></i> Supprimer cet utilisateur
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Réinitialisation mot de passe -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Réinitialiser le mot de passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur?</p>
                <p>Un nouveau mot de passe aléatoire sera généré et envoyé à l'email de l'utilisateur.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <a href="reset_password.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">Confirmer</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Suppression utilisateur -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Supprimer l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous ABSOLUMENT certain de vouloir supprimer cet utilisateur?</p>
                <p class="text-danger fw-bold">Cette action est irréversible et supprimera toutes les données associées à cet utilisateur.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger">Supprimer définitivement</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>