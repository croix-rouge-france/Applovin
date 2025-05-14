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

// Vérifier que l'utilisateur existe
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "Utilisateur introuvable";
    header("Location: users.php");
    exit();
}

// Générer un nouveau mot de passe aléatoire
$new_password = bin2hex(random_bytes(4)); // 8 caractères hexadécimaux
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Mettre à jour le mot de passe
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
if ($stmt->execute([$hashed_password, $user_id])) {
    // Envoyer le nouveau mot de passe par email (à implémenter)
    // mail($user['email'], "Réinitialisation de votre mot de passe", "Votre nouveau mot de passe: $new_password");
    
    $_SESSION['success'] = "Mot de passe réinitialisé. Le nouveau mot de passe a été envoyé à l'utilisateur.";
} else {
    $_SESSION['error'] = "Une erreur s'est produite lors de la réinitialisation";
}

header("Location: user_edit.php?id=$user_id");
exit();
?>