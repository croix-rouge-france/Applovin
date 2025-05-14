<?php
// Inclusion des fichiers nécessaires
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Vérification des droits administrateur
//check_admin();

// Vérification de l'ID dans l'URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID utilisateur manquant";
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET['id']);

// Vérification de l'existence de l'utilisateur
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['error'] = "Utilisateur introuvable";
        header("Location: users.php");
        exit();
    }

    // Démarrage de la transaction
    $pdo->beginTransaction();

    // Supprimer les enregistrements associés dans l'ordre dépendant (parent -> enfant)
    $delete_statements = [
        "DELETE FROM transactions WHERE user_id = ?" => [$user_id],
        "DELETE FROM investments WHERE user_id = ?" => [$user_id],
        "DELETE FROM withdrawals WHERE user_id = ?" => [$user_id],
        "DELETE FROM users WHERE id = ?" => [$user_id]
    ];

    foreach ($delete_statements as $query => $params) {
        $stmt = $pdo->prepare($query);
        $success = $stmt->execute($params);
        
        if (!$success) {
            throw new Exception("Erreur lors de la suppression des données associées");
        }
    }

    // Valider la transaction
    $pdo->commit();
    $_SESSION['success'] = "Utilisateur supprimé avec succès";

} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    $pdo->rollBack();
    $_SESSION['error'] = "Une erreur s'est produite lors de la suppression: " . $e->getMessage();
    
    // Log de l'erreur pour debugging
    error_log("Erreur de suppression - " . $e->getMessage());
}

// Redirection après traitement
header("Location: users.php");
exit();
?>