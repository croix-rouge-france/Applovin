<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

check_auth();

// Obtenir l'instance PDO via la classe Database
$db = Database::getInstance();
$pdo = $db->getConnection();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email)) {
        $error = "Le nom d'utilisateur et l'email sont obligatoires";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "Les nouveaux mots de passe ne correspondent pas";
    } elseif (!empty($new_password) && strlen($new_password) < 8) {
        $error = "Le nouveau mot de passe doit contenir au moins 8 caractères";
    } else {
        // Vérifier le mot de passe actuel si changement demandé
        if (!empty($new_password) && !password_verify($current_password, $user['password'])) {
            $error = "Mot de passe actuel incorrect";
        } else {
            // Mettre à jour les informations
            $update_data = [
                'username' => $username,
                'email' => $email,
                'id' => $user_id
            ];
            
            $sql = "UPDATE users SET username = :username, email = :email";
            
            // Mettre à jour le mot de passe si fourni
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_data['password'] = $hashed_password;
                $sql .= ", password = :password";
            }
            
            $sql .= " WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($update_data)) {
                // Mettre à jour la session
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                
                $success = "Profil mis à jour avec succès!";
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

$page_title = "Profil";
require_once 'includes/header.php';
?>

<!-- Section Bienvenue -->
<div class="welcome-section mb-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-1"><i class="fas fa-user-circle me-2"></i>Mon Profil</h2>
                <p class="mb-0 text-muted">Gérez vos informations personnelles et votre sécurité</p>
            </div>
            <div class="col-md-4 text-md-end">
                <span class="badge bg-info">
                    <i class="fas fa-id-card me-1"></i>
                    Membre depuis <?php echo date('m/Y', strtotime($user['created_at'])); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container mt-3">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Informations personnelles</h4>
                </div>
                
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                <div class="invalid-feedback">
                                    Veuillez saisir un nom d'utilisateur valide
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Adresse email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                <div class="invalid-feedback">
                                    Veuillez saisir une adresse email valide
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Changer le mot de passe</h5>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 8 caractères</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">
                            Dernière mise à jour: <?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?>
                        </small>
                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Retour
                        </a>
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
    <a href="referrals.php" class="nav-item">
        <i class="fas fa-users"></i>
        <span>Équipe</span>
    </a>
    <a href="transactions.php" class="nav-item">
        <i class="fas fa-exchange-alt"></i>
        <span>Transactions</span>
    </a>
    <a href="profile.php" class="nav-item active">
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

<script>
// Fonction pour basculer la visibilité du mot de passe
document.querySelectorAll('.toggle-password').forEach(function(button) {
    button.addEventListener('click', function() {
        const input = this.parentNode.querySelector('input');
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});

// Validation du formulaire
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms)
        .forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
})();
</script>

