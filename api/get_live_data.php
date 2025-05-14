<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    check_auth();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Non autorisé");
    }

    $user_id = $_SESSION['user_id'];
    
    // Initialiser les tableaux de données comme dans le fichier principal
    $response = [
        'team_recharge' => 0,
        'team_withdrawal' => 0,
        'new_team_first_recharge' => 0,
        'new_team_first_withdrawal' => 0,
        'level1' => ['invited' => 0, 'validated' => 0, 'income' => 0],
        'level2' => ['invited' => 0, 'validated' => 0, 'income' => 0],
        'level3' => ['invited' => 0, 'validated' => 0, 'income' => 0]
    ];
    
    // [Insérer ici les mêmes requêtes SQL que dans le fichier principal]
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}