<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/db.php';

header('Content-Type: application/json');

if (empty($_GET['user_id'])) {
    die(json_encode(['error' => 'User ID required']));
}

try {
    $pdo = getPDO();
    
    // Compter les dépôts des dernières 24h
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions 
                          WHERE user_id = ? AND created_at >= NOW() - INTERVAL 1 DAY");
    $stmt->execute([$_GET['user_id']]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'new_deposits' => $count,
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}