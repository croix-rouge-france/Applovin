<?php
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

function check_admin_auth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        header('Location: ../index.php?error=admin_required');
        exit();
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function validate_referral_code($code) {
    if (empty($code)) return false;
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ? AND is_active = 1");
        $stmt->execute([$code]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Referral code error: " . $e->getMessage());
        return false;
    }
}

function login_user($email, $password) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            session_regenerate_id(true);
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}
function process_referral_commissions($referrer_id, $new_user_id) {
    global $pdo, $referral_rates;
    
    // Niveau 1 (direct)
    $stmt = $pdo->prepare("SELECT id, referred_by FROM users WHERE id = ?");
    $stmt->execute([$referrer_id]);
    $referrer = $stmt->fetch();
    
    if ($referrer) {
        // Commission niveau 1
        $amount = (MIN_INVESTMENT * $referral_rates['level1']) / 100;
        add_transaction($referrer['id'], 'referral', $amount, "Commission parrainage niveau 1 pour utilisateur #$new_user_id");
        
        // Niveau 2
        if ($referrer['referred_by']) {
            $amount = (MIN_INVESTMENT * $referral_rates['level2']) / 100;
            add_transaction($referrer['referred_by'], 'referral', $amount, "Commission parrainage niveau 2 pour utilisateur #$new_user_id");
            
            // Niveau 3
            $stmt = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
            $stmt->execute([$referrer['referred_by']]);
            $level2 = $stmt->fetch();
            
            if ($level2 && $level2['referred_by']) {
                $amount = (MIN_INVESTMENT * $referral_rates['level3']) / 100;
                add_transaction($level2['referred_by'], 'referral', $amount, "Commission parrainage niveau 3 pour utilisateur #$new_user_id");
            }
        }
    }
}

function add_transaction($user_id, $type, $amount, $description) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, amount, description, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    return $stmt->execute([$user_id, $type, $amount, $description]);
}
?>