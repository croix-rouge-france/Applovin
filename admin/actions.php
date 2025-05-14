<?php
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

check_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['error' => 'Requête invalide']);
    exit();
}

$action = $_POST['action'];

try {
    if (!isset($pdo)) {
        $pdo = new PDO("mysql:host=sql211.infinityfree.com;dbname=if0_38609766_applovin_db;charset=utf8mb4", "if0_38609766", "q5N2wOnaR79Ykg");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    switch ($action) {
        case 'delete_user':
            if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
                throw new Exception('ID utilisateur invalide');
            }

            $user_id = intval($_POST['user_id']);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Utilisateur introuvable');
            }

            $pdo->beginTransaction();

            $delete_statements = [
                "DELETE FROM transactions WHERE user_id = ?" => [$user_id],
                "DELETE FROM investments WHERE user_id = ?" => [$user_id],
                "DELETE FROM withdrawals WHERE user_id = ?" => [$user_id],
                "DELETE FROM users WHERE id = ?" => [$user_id]
            ];

            foreach ($delete_statements as $query => $params) {
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'stop_remuneration':
            if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
                throw new Exception('ID utilisateur invalide');
            }

            $user_id = intval($_POST['user_id']);

            $stmt = $pdo->prepare("UPDATE investments SET status = 'stopped' WHERE user_id = ? AND status = 'active'");
            $stmt->execute([$user_id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Aucun investissement actif trouvé pour cet utilisateur');
            }

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Action non reconnue');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur: " . $e->getMessage());
    echo json_encode(['error' => 'Une erreur s’est produite : ' . $e->getMessage()]);
}
exit();