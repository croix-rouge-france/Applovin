<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

check_admin();

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: withdrawals.php");
    exit();
}

$withdrawal_id = intval($_GET['id']);
$action = $_GET['action'];

// Vérifier que le retrait existe et est en attente
$stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'");
$stmt->execute([$withdrawal_id]);
$withdrawal = $stmt->fetch();

if (!$withdrawal) {
    $_SESSION['error'] = "Retrait introuvable ou déjà traité";
    header("Location: withdrawals.php");
    exit();
}

// Traiter selon l'action
if ($action === 'approve') {
    // Approuver le retrait
    $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'completed', processed_at = NOW(), processed_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $withdrawal_id]);
    
    // Ajouter une transaction de retrait
    add_transaction($withdrawal['user_id'], 'withdrawal', $withdrawal['amount'], "Retrait approuvé #$withdrawal_id");
    
    $_SESSION['success'] = "Retrait #$withdrawal_id approuvé avec succès";
} elseif ($action === 'reject') {
    // Rejeter le retrait
    $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'rejected', processed_at = NOW(), processed_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $withdrawal_id]);
    
    // Rembourser le montant dans le solde de l'utilisateur
    add_transaction($withdrawal['user_id'], 'other', $withdrawal['amount'], "Retrait rejeté #$withdrawal_id - Montant remboursé");
    
    $_SESSION['success'] = "Retrait #$withdrawal_id rejeté avec succès";
} else {
    $_SESSION['error'] = "Action invalide";
}

header("Location: withdrawals.php");
exit();
?>