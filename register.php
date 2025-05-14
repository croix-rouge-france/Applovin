<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialisation session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirection si déjà connecté
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

// Initialisation variables avec valeurs par défaut
$errors = [];
$form_data = [
    'username' => '',
    'email' => '',
    'referral_code' => ''
];

// Récupération sécurisée du code de parrainage depuis l'URL
$referred_by = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$form_data['referral_code'] = $referred_by;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupération et nettoyage des données avec valeurs par défaut
        $form_data = [
            'username' => isset($_POST['username']) ? sanitize_input($_POST['username']) : '',
            'email' => isset($_POST['email']) ? sanitize_input($_POST['email']) : '',
            'referral_code' => isset($_POST['referral_code']) ? sanitize_input($_POST['referral_code']) : ''
        ];
        
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $terms_accepted = isset($_POST['terms']);

        // Validation
        if (empty($form_data['username'])) {
            $errors['username'] = "Le nom d'utilisateur est requis";
        }

        if (empty($form_data['email'])) {
            $errors['email'] = "L'email est requis";
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Format d'email invalide";
        }

        if (empty($password)) {
            $errors['password'] = "Le mot de passe est requis";
        } elseif (strlen($password) < 8) {
            $errors['password'] = "Le mot de passe doit contenir au moins 8 caractères";
        }

        if ($password !== $confirm_password) {
            $errors['confirm_password'] = "Les mots de passe ne correspondent pas";
        }

        if (!$terms_accepted) {
            $errors['terms'] = "Vous devez accepter les conditions d'utilisation";
        }

        if (empty($errors)) {
            $db = Database::getInstance();
            $db->beginTransaction();

            // Validation code parrainage
            $referred_by_id = null;
            if (!empty($form_data['referral_code'])) {
                $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
                $stmt->execute([$form_data['referral_code']]);
                $referrer = $stmt->fetch();
                $referred_by_id = $referrer ? $referrer['id'] : null;
                
                if (!$referred_by_id) {
                    throw new Exception("Code parrainage invalide");
                }
            }

            // Hachage mot de passe
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Génération code de parrainage
            $user_referral_code = generate_referral_code();
            
            // Insertion utilisateur
            $stmt = $db->prepare("INSERT INTO users 
                (username, email, password, referral_code, referred_by, is_active, created_at) 
                VALUES (:username, :email, :password, :referral_code, :referred_by, 1, NOW())");
                
            $insert_result = $stmt->execute([
                ':username' => $form_data['username'],
                ':email' => $form_data['email'],
                ':password' => $hashed_password,
                ':referral_code' => $user_referral_code,
                ':referred_by' => $referred_by_id
            ]);
            
            if (!$insert_result) {
                throw new Exception("Échec de l'insertion utilisateur");
            }
            
            $user_id = $db->lastInsertId();
            
            // Récupération des données utilisateur
            $stmt = $db->prepare("SELECT id, username, email, is_admin FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("Erreur lors de la récupération de l'utilisateur");
            }

            // Connexion automatique
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            $_SESSION['referral_code'] = $user_referral_code;
            
            session_regenerate_id(true);

            $db->commit();
            
            // Redirection vers le dashboard
            header("Location: dashboard.php");
            exit();
        }
    } catch (PDOException $e) {
        if (isset($db)) $db->rollBack();
        error_log("Database error: " . $e->getMessage());
        $errors['general'] = "Erreur lors de l'inscription. Veuillez réessayer.";
    } catch (Exception $e) {
        if (isset($db)) $db->rollBack();
        error_log("Registration error: " . $e->getMessage());
        $errors['general'] = $e->getMessage();
    }
}

$page_title = "Inscription";

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        .register-page {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }

        .register-logo {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeInDown 1s;
        }

        .register-logo img {
            max-width: 150px;
            margin-bottom: 15px;
        }

        .register-logo h1 {
            color: #fff;
            font-weight: 700;
            font-size: 2rem;
        }

        .register-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeInUp 1s;
        }

        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }

        .register-card .card-header {
            background: linear-gradient(to right, #1fa2ff, #12d8fa);
            border-bottom: none;
            padding: 20px;
        }

        .register-card .card-body {
            padding: 30px;
        }

        .register-btn {
            background: linear-gradient(to right, #1fa2ff, #12d8fa);
            border: none;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .register-btn:hover {
            background: linear-gradient(to right, #0d8de6, #00c4e6);
            transform: translateY(-2px);
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #1fa2ff;
            box-shadow: 0 0 0 0.25rem rgba(31, 162, 255, 0.25);
        }

        .register-links a {
            color: #1fa2ff;
            text-decoration: none;
            transition: color 0.3s;
        }

        .register-links a:hover {
            color: #0d8de6;
            text-decoration: underline;
        }

        .referral-badge {
            background: rgba(31, 162, 255, 0.1);
            border-left: 4px solid #1fa2ff;
            animation: pulse 2s infinite;
        }

        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(31, 162, 255, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(31, 162, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(31, 162, 255, 0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .register-page { padding: 20px; }
            .register-card { margin-top: 30px; }
        }
        .modal-lg {
        max-width: 900px;
    }

    .modal-content {
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        background: linear-gradient(135deg, #6c63ff, #4d44db);
        color: white;
        border-bottom: none;
        padding: 1.5rem;
    }

    .modal-title {
        font-size: 1.75rem;
        font-weight: 700;
    }

    .modal-body {
        padding: 2rem;
        background-color: #f8f9fa;
        max-height: 70vh;
        overflow-y: auto;
    }

    .terms-content {
        line-height: 1.8;
        color: #333;
    }

    .terms-title {
        font-size: 2rem;
        font-weight: 700;
        color: #6c63ff;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .terms-intro {
        font-size: 1.1rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background-color: #fff;
        border-left: 4px solid #6c63ff;
        border-radius: 5px;
    }

    .terms-section {
        margin-bottom: 2rem;
    }

    .terms-section h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #4d44db;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #eee;
    }
    </style>
</head>
<body class="register-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="register-logo">
                    <img src="image/logo.png" alt="Logo" class="img-fluid">
                    <h1 class="animate__animated animate__fadeIn">Inscription</h1>
                </div>
                
                <div class="register-card card">
                    <div class="card-header text-center">
                        <h4 class="mb-0 text-white"><i class="fas fa-user-plus"></i> Créer un compte</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger animate__animated animate__shakeX">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['general'] ?? ''); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($referred_by)): ?>
                        <div class="alert alert-info referral-badge mb-4">
                            <i class="fas fa-gift"></i> Vous êtes parrainé avec le code: <?php echo htmlspecialchars($referred_by); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="animate__animated animate__fadeIn">
                            <div class="mb-4">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                           id="username" name="username" value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required>
                                    <?php if (isset($errors['username'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username'] ?? ''); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">Adresse Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                           id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email'] ?? ''); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                           id="password" name="password" required>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password'] ?? ''); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="password-strength" id="password-strength"></div>
                                <small class="text-muted">Minimum 8 caractères</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                           id="confirm_password" name="confirm_password" required>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password'] ?? ''); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="referral_code" class="form-label">Code de parrainage (optionnel)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-friends"></i></span>
                                    <input type="text" class="form-control" id="referral_code" name="referral_code" 
                                           value="<?php echo htmlspecialchars($form_data['referral_code'] ?? ''); ?>" placeholder="Code de votre parrain">
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input <?php echo isset($errors['terms']) ? 'is-invalid' : ''; ?>" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    J'accepte les <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">conditions d'utilisation</a>
                                </label>
                                <?php if (isset($errors['terms'])): ?>
                                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['terms'] ?? ''); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-primary register-btn">
                                    <i class="fas fa-user-plus"></i> S'inscrire
                                </button>
                            </div>
                            
                            <div class="text-center register-links">
                                <p>Déjà un compte? <a href="index.php">Se connecter</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Conditions d'utilisation -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Conditions d'utilisation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body terms-body">
                <div class="terms-content">
                    <h1 class="terms-title">Conditions d'utilisation d'APPlovin</h1>
                    <p class="terms-intro">
                        Bienvenue sur le site d'investissement <strong>APPlovin</strong> (ci-après dénommé "le Site"), une plateforme conçue pour offrir des opportunités d'investissement à ses utilisateurs (ci-après dénommés "Vous" ou "l'Utilisateur"). En accédant ou en utilisant ce Site, Vous acceptez pleinement et sans réserve les présentes Conditions d'Utilisation (ci-après "les Conditions"), qui régissent l'ensemble des interactions entre Vous et APPlovin. Ces Conditions forment un contrat juridiquement contraignant entre Vous et APPlovin, et toute utilisation du Site implique votre accord explicite avec celles-ci. Si Vous n'acceptez pas ces termes, Vous êtes prié de ne pas utiliser le Site.
                    </p>

                    <section class="terms-section">
                        <h2>1. Objet du Site</h2>
                        <p>
                            Le Site APPlovin est une plateforme en ligne permettant aux Utilisateurs de découvrir et d'activer divers plans d'investissement proposés par APPlovin ou ses partenaires. Ces plans peuvent inclure, sans s'y limiter, des investissements dans des actifs numériques, des produits financiers, ou d'autres opportunités économiques présentées sur le Site. APPlovin se réserve le droit de modifier, suspendre ou retirer tout plan d'investissement à tout moment, sans préavis ni obligation de justification envers les Utilisateurs.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>2. Inscription et accès au Site</h2>
                        <p>
                            Pour utiliser les services d'APPlovin, Vous devez créer un compte en fournissant des informations précises, complètes et à jour, telles que votre nom, adresse e-mail, et autres données demandées lors du processus d'inscription. Vous êtes entièrement responsable de la confidentialité de vos identifiants de connexion et de toute activité effectuée sous votre compte. APPlovin ne saurait être tenu responsable en cas d'accès non autorisé à votre compte dû à une négligence de votre part concernant la sécurité de vos identifiants.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>3. Description des services</h2>
                        <p>
                            APPlovin met à disposition des Utilisateurs une interface permettant de consulter les plans d'investissement disponibles, d'activer ces plans en effectuant des paiements via les méthodes proposées (cryptomonnaies, mobile money, ou autres), et de suivre les performances supposées de ces investissements via un tableau de bord. Les informations fournies sur le Site, y compris les rendements potentiels et les descriptions des plans, sont basées sur des estimations et des projections qui peuvent varier en fonction de nombreux facteurs externes.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>4. Modalités de paiement</h2>
                        <p>
                            Les paiements pour activer un plan d'investissement doivent être effectués conformément aux instructions fournies sur le Site. APPlovin peut utiliser des prestataires de paiement tiers pour traiter ces transactions, et Vous acceptez que ces prestataires puissent appliquer leurs propres conditions et frais, sur lesquels APPlovin n’a aucun contrôle. Tout paiement effectué est considéré comme définitif et non remboursable, sauf disposition contraire explicite dans les présentes Conditions ou dans une politique spécifique de remboursement publiée sur le Site.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>5. Droits et obligations des Utilisateurs</h2>
                        <p>
                            En utilisant le Site, Vous vous engagez à :
                        </p>
                        <ul>
                            <li>Fournir des informations exactes et à jour lors de l'inscription et des transactions.</li>
                            <li>Ne pas tenter de contourner les mesures de sécurité du Site ou d'accéder à des zones non autorisées.</li>
                            <li>Respecter toutes les lois et réglementations applicables dans votre juridiction concernant les investissements financiers.</li>
                        </ul>
                        <p>
                            Vous reconnaissez que l’utilisation du Site est à vos propres risques et que Vous êtes seul responsable de vérifier la légalité de votre participation aux plans d’investissement dans votre pays ou région.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>6. Fonctionnement des plans d’investissement</h2>
                        <p>
                            Les plans d’investissement proposés par APPlovin sont conçus pour offrir des opportunités de rendement basées sur des stratégies financières diverses. Cependant, APPlovin ne garantit en aucun cas la réalisation de ces rendements, et Vous acceptez que les performances passées ou projetées ne constituent pas une assurance de résultats futurs. Les détails de chaque plan, y compris les montants minimums, les durées, et les rendements estimés, sont fournis à titre indicatif et peuvent être modifiés à la discrétion d’APPlovin.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>7. Propriété intellectuelle</h2>
                        <p>
                            Tous les contenus du Site, y compris les textes, graphiques, logos, images, logiciels, et autres éléments, sont la propriété exclusive d’APPlovin ou de ses partenaires et sont protégés par les lois sur la propriété intellectuelle. Vous vous engagez à ne pas reproduire, distribuer, modifier ou exploiter ces contenus sans l’autorisation écrite préalable d’APPlovin.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>8. Modifications des Conditions</h2>
                        <p>
                            APPlovin se réserve le droit de modifier ces Conditions à tout moment, avec ou sans préavis. Les modifications entrent en vigueur dès leur publication sur le Site. Votre utilisation continue du Site après ces modifications constitue une acceptation des nouvelles Conditions. Il est de votre responsabilité de consulter régulièrement cette page pour vous tenir informé des éventuelles mises à jour.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>9. Disponibilité du Site</h2>
                        <p>
                            APPlovin s’efforce de maintenir le Site accessible 24 heures sur 24, 7 jours sur 7, mais ne garantit pas une disponibilité ininterrompue. Le Site peut être temporairement indisponible pour des raisons de maintenance, de mises à jour, ou en cas de force majeure (panne technique, attaque informatique, etc.). APPlovin ne sera pas tenu responsable des pertes ou désagréments causés par une telle indisponibilité.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>10. Limitation de responsabilité</h2>
                        <p>
                            APPlovin fournit le Site et ses services "en l’état", sans aucune garantie, explicite ou implicite, quant à leur fiabilité, exactitude ou adéquation à un usage particulier. En aucun cas, APPlovin, ses dirigeants, employés, ou partenaires ne pourront être tenus responsables des pertes financières, directes ou indirectes, subies par Vous ou un tiers suite à l’activation d’un plan d’investissement, que ce soit par le biais de paiements en cryptomonnaies, mobile money, ou toute autre méthode de paiement acceptée sur le Site. Vous reconnaissez que les investissements comportent des risques inhérents, et Vous assumez l’entière responsabilité de toute perte d’argent, quelle qu’en soit la cause (fluctuations du marché, erreurs techniques, fraude, ou autres). APPlovin est expressément dégagé de toute poursuite ou réclamation en cas de perte financière découlant de votre utilisation du Site ou de ses services.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>11. Confidentialité et données personnelles</h2>
                        <p>
                            APPlovin collecte et traite vos données personnelles conformément à sa politique de confidentialité, accessible séparément sur le Site. Vous consentez à la collecte, au stockage et à l’utilisation de ces données pour les besoins du fonctionnement du Site, y compris la gestion des paiements et des communications. APPlovin peut partager ces données avec des tiers (prestataires de paiement, partenaires) dans le cadre de l’exécution des services.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>12. Résiliation et suspension</h2>
                        <p>
                            APPlovin se réserve le droit de suspendre ou de résilier votre accès au Site à tout moment, sans préavis, si Vous enfreignez ces Conditions, ou pour toute autre raison jugée nécessaire par APPlovin, y compris des soupçons de fraude ou d’activité illégale. En cas de résiliation, Vous ne pourrez prétendre à aucun remboursement ou compensation pour les fonds investis ou les services non utilisés.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>13. Divers</h2>
                        <p>
                            Ces Conditions constituent l’intégralité de l’accord entre Vous et APPlovin concernant l’utilisation du Site. Si une disposition des présentes Conditions est jugée invalide ou inapplicable, les autres dispositions resteront en vigueur. Toute renonciation à une disposition des présentes Conditions ne sera effective que si elle est écrite et signée par un représentant autorisé d’APPlovin.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>14. Droit applicable et juridiction</h2>
                        <p>
                            Les présentes Conditions sont régies par les lois du pays où APPlovin est enregistré (à préciser selon votre juridiction). Tout litige découlant de l’utilisation du Site sera soumis à la compétence exclusive des tribunaux de cette juridiction, sauf disposition légale contraire dans votre pays.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>15. Contact</h2>
                        <p>
                            Pour toute question concernant ces Conditions, Vous pouvez contacter APPlovin via l’adresse e-mail fournie sur le Site ou par tout autre moyen de communication indiqué. APPlovin s’engage à répondre dans un délai raisonnable, sans garantie de résolution immédiate de votre demande.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>16. Acceptation des risques financiers</h2>
                        <p>
                            En activant un plan d’investissement sur le Site, Vous reconnaissez avoir été informé des risques financiers associés à de telles activités. Les marchés financiers sont volatils, et les rendements ne sont jamais garantis. APPlovin met à disposition des informations générales, mais ne fournit pas de conseils financiers personnalisés. Vous acceptez que toute perte d’argent, totale ou partielle, résultant de l’activation d’un plan d’investissement, ne puisse donner lieu à une quelconque poursuite judiciaire ou réclamation contre APPlovin, ses affiliés ou ses partenaires, quelle que soit la méthode d’investissement utilisée ou les circonstances entourant cette perte.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>17. Force majeure</h2>
                        <p>
                            APPlovin ne sera pas tenu responsable des retards ou échecs dans l’exécution de ses obligations en cas de force majeure, incluant, sans s’y limiter, des catastrophes naturelles, des pannes de réseau, des cyberattaques, ou des décisions gouvernementales affectant l’accès au Site ou aux services financiers.
                        </p>
                    </section>

                    <section class="terms-section">
                        <h2>18. Dispositions finales</h2>
                        <p>
                            Votre utilisation du Site constitue une acceptation continue de ces Conditions. APPlovin peut, à sa discrétion, ajouter des fonctionnalités ou services supplémentaires, qui seront également soumis à ces Conditions sauf indication contraire. Toute communication entre Vous et APPlovin sera considérée comme officielle uniquement si elle est effectuée via les canaux désignés sur le Site.
                        </p>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des éléments
            const elements = document.querySelectorAll('.register-logo, .register-card, form');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s ease';
                    el.style.opacity = '1';
                }, index * 200);
            });
            
            // Animation au focus des champs
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
            
            // Indicateur de force du mot de passe
            const passwordInput = document.getElementById('password');
            const strengthBar = document.getElementById('password-strength');
            
            passwordInput.addEventListener('input', function() {
                const strength = calculatePasswordStrength(this.value);
                updateStrengthIndicator(strength);
            });
            
            function calculatePasswordStrength(password) {
                let strength = 0;
                
                if (password.length >= 8) strength += 1;
                if (password.length >= 12) strength += 1;
                if (password.match(/\d/)) strength += 1;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
                if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                
                return strength;
            }
            
            function updateStrengthIndicator(strength) {
                strengthBar.style.width = (strength * 20) + '%';
                
                const colors = [
                    '#ff5252',
                    '#ffab40',
                    '#ffd740',
                    '#69f0ae',
                    '#00e676'
                ];
                strengthBar.style.backgroundColor = colors[strength - 1] || '#e0e0e0';
            }
            
            // Si code de parrainage dans l'URL, on le conserve
            const urlParams = new URLSearchParams(window.location.search);
            const refCode = urlParams.get('ref');
            if (refCode) {
                document.getElementById('referral_code').value = refCode;
            }
        });
    </script>
</body>
</html>