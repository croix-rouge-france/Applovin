<?php
require_once 'config.php';
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo $page_title ?? 'Tableau de bord'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico">
    
    <style>
        /* Custom Styles for Mobile Navigation */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100vh;
            background: #0d6efd;
            color: white;
            transition: left 0.3s ease-in-out;
            padding-top: 60px;
            overflow-y: auto;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .mobile-menu.active {
            left: 0;
        }
        .mobile-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            width: 100%;
        }
        .mobile-menu ul li {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .mobile-menu ul li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .mobile-menu ul li a i {
            margin-right: 10px;
        }
        .menu-toggle {
            position: absolute;
            top: 15px;
            left: 15px;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            z-index: 1100;
        }
        .content {
            transition: filter 0.3s ease-in-out;
        }
        .menu-open .content {
            filter: blur(5px);
        }
        .navbar-brand {
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .navbar-brand img {
            max-height: 40px;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation -->
    <div class="mobile-menu" id="mobileMenu">
        <button class="menu-toggle" onclick="toggleMenu()"><i class="fas fa-times"></i></button>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
            <li><a href="withdraw.php"><i class="fas fa-money-bill-wave"></i> Retrait</a></li>
            <li><a href="referrals.php"><i class="fas fa-users"></i> Parrainage</a></li>
            <li><a href="transactions.php"><i class="fas fa-history"></i> Transactions</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profil</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </div>

    <!-- Barre de navigation -->
    <nav class="navbar navbar-dark bg-primary d-flex justify-content-between">
        <button class="menu-toggle" onclick="toggleMenu()"><i class="fas fa-bars"></i></button>
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../image/logo.png" alt="Logo" class="img-fluid">
            </a>
        </div>
    </nav>

    <div class="content" id="content">
        <!-- Contenu principal -->
    </div>

    <script>
        function toggleMenu() {
            document.getElementById("mobileMenu").classList.toggle("active");
            document.body.classList.toggle("menu-open");
        }
    </script>
</body>
</html>


