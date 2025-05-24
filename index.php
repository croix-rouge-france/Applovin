<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Redirection immédiate si connecté
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['is_admin'] ? 'admin/dashboard.php' : 'dashboard.php';
    header("Location: $redirect");
    exit;
}

// Connexion PDO
try {
    $conn = new PDO("mysql:host=mysql-applovin.alwaysdata.net;dbname=applovin_db;charset=utf8", 'applovin', '@Motdepasse0000', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Erreur connexion : " . $e->getMessage() . "\n", FILE_APPEND);
    $error = "Erreur serveur.";
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Admins prédéfinis
    $predifined_admins = [
        'admin@applovin.com' => 'applovin2025',
        'superadmin@applovin.com' => 'superpass123'
    ];

    if (isset($predifined_admins[$email]) && $predifined_admins[$email] === $password) {
        $_SESSION['user_id'] = $email;
        $_SESSION['username'] = explode('@', $email)[0];
        $_SESSION['is_admin'] = true;
        header("Location: admin/dashboard.php");
        exit;
    }

    // Vérification utilisateur normal
    try {
        $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            $redirect = $user['is_admin'] ? 'admin/dashboard.php' : 'dashboard.php';
            header("Location: $redirect");
            exit;
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " : Erreur SQL : " . $e->getMessage() . "\n", FILE_APPEND);
        $error = "Erreur lors de la connexion.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .login-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeInDown 1s;
        }
        .login-logo img {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .login-logo h1 {
            color: #fff;
            font-weight: 700;
            font-size: 2rem;
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeInUp 1s;
        }
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }
        .login-card .card-header {
            background: linear-gradient(to right, #4a6cf7, #6a11cb);
            border-bottom: none;
            padding: 20px;
        }
        .login-card .card-body {
            padding: 30px;
        }
        .login-btn {
            background: linear-gradient(to right, #4a6cf7, #6a11cb);
            border: none;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .login-btn:hover {
            background: linear-gradient(to right, #3a5bd9, #5a00b8);
            transform: translateY(-2px);
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #4a6cf7;
            box-shadow: 0 0 0 0.25rem rgba(74, 108, 247, 0.25);
        }
        .login-links a {
            color: #6a11cb;
            text-decoration: none;
        }
        .login-links a:hover {
            color: #4a6cf7;
            text-decoration: underline;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 768px) {
            .login-page { padding: 20px; }
            .login-card { margin-top: 30px; }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="login-logo">
                        <img src="image/logo.png" alt="Logo">
                        <h1>Login</h1>
                    </div>
                    <div class="login-card card">
                        <div class="card-header text-center">
                            <h4 class="mb-0 text-white"><i class="fas fa-sign-in-alt"></i> Connexion</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                            <div class="alert alert-danger animate__animated animate__shakeX">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                            <?php endif; ?>
                            <form method="POST" class="animate__animated animate__fadeIn">
                                <div class="mb-4">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Entrez votre email" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="password" class="form-label">Mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Entrez votre mot de passe" required>
                                    </div>
                                </div>
                                <div class="d-grid gap-2 mb-4">
                                    <button type="submit" class="btn btn-primary login-btn">
                                        <i class="fas fa-sign-in-alt"></i> Se connecter
                                    </button>
                                </div>
                                <div class="text-center login-links">
                                    <p class="mb-2">Pas de compte ? <a href="register.php">S'inscrire</a></p>
                                    <p class="mb-0"><a href="#">Mot de passe oublié ?</a></p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.login-logo, .login-card, form');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s ease';
                    el.style.opacity = '1';
                }, index * 200);
            });
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
