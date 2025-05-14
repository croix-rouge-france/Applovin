<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Non authentifié']));
}

$data = [
    'payment_id' => $_POST['payment_id'],
    'user_id' => $_SESSION['user_id'],
    'plan_id' => intval($_POST['plan_id']),
    'amount' => floatval($_POST['amount']),
    'payment_method' => 'USDT',
    'payment_network' => $_POST['network'],
    'status' => 'pending',
    'created_at' => date('Y-m-d H:i:s')
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO investments (
            payment_id, user_id, plan_id, amount, 
            payment_method, payment_network, status, created_at
        ) VALUES (
            :payment_id, :user_id, :plan_id, :amount,
            :payment_method, :payment_network, :status, :created_at
        )
    ");
    
    $stmt->execute($data);
    echo json_encode(['status' => 'success', 'payment_id' => $data['payment_id']]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur base de données']);
}