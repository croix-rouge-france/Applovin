<?php

/**
 * Enregistre un nouvel utilisateur
 */
function register_user($username, $email, $password, $referred_by = null) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username_db = 'applovin';
    $password_db = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username_db, $password_db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referred_by) 
                             VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashed_password, $referred_by]);

        // Log pour débogage
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Inscription user_id {$pdo->lastInsertId()} : username=$username\n", FILE_APPEND);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur inscription: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur inscription: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Retourne la classe de badge en fonction du statut
 */
function get_status_badge($status) {
    return $status === 'completed' ? 'success' :
           ($status === 'pending' ? 'warning' :
           ($status === 'processing' ? 'info' : 'danger'));
}

/**
 * Valide les données d'inscription
 */
function validate_registration($data, $password, $confirm_password, $terms_accepted) {
    $errors = [];
    
    // Validation username
    if (empty($data['username'])) {
        $errors['username'] = "Nom d'utilisateur requis";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data['username'])) {
        $errors['username'] = "3-20 caractères alphanumériques seulement";
    }
    
    // Validation email
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Email invalide";
    }
    
    // Validation téléphone
    if (!empty($data['phone']) && !preg_match('/^[0-9]{10,15}$/', $data['phone'])) {
        $errors['phone'] = "Format de téléphone invalide";
    }
    
    // Validation mot de passe
    if (strlen($password) < 8) {
        $errors['password'] = "8 caractères minimum";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = "Doit contenir 1 majuscule et 1 chiffre";
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = "Les mots de passe ne correspondent pas";
    }
    
    // Validation conditions
    if (!$terms_accepted) {
        $errors['terms'] = "Vous devez accepter les conditions";
    }
    
    return $errors;
}

/**
 * Calcule le solde de l'utilisateur avec plus de détails
 */
function calculate_user_balance($user_id) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';
    
    // Initialisation du tableau de balance
    $balance = [
        'current' => 0,
        'invested' => 0,
        'profit' => 0,
        'withdrawals' => 0,
        'bonus' => 0,
        'team_recharge' => 0,
        'team_withdrawal' => 0,
        'available_for_withdrawal' => 0
    ];
    
    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Taux de change
        $exchange_rate = defined('EXCHANGE_RATE') ? EXCHANGE_RATE : 600;

        // 1. Dépôts confirmés (en USD)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount / ?), 0) as total_deposits
            FROM deposits 
            WHERE user_id = ? AND status = 'completed' AND currency = 'XOF'
        ");
        $stmt->execute([$exchange_rate, $user_id]);
        $balance['invested'] = (float) $stmt->fetchColumn();
        $balance['current'] = $balance['invested'];

        // Log pour débogage
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Dépôts pour user_id $user_id : {$balance['invested']} USD\n", FILE_APPEND);

        // 2. Retraits effectués
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_withdrawals 
            FROM withdrawals 
            WHERE user_id = ? AND status = 'success'
        ");
        $stmt->execute([$user_id]);
        $balance['withdrawals'] = (float) $stmt->fetchColumn();
        $balance['current'] -= $balance['withdrawals'];

        // 3. Profits
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(profit), 0) as total_profit 
            FROM investment_transactions 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $balance['profit'] = (float) $stmt->fetchColumn();
        $balance['current'] += $balance['profit'];

        // 4. Bonus
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_bonus 
            FROM referral_bonuses 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $balance['bonus'] = (float) $stmt->fetchColumn();
        $balance['current'] += $balance['bonus'];

        // 5. Recharges équipe
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount / ?), 0) as team_recharge
            FROM deposits 
            WHERE user_id IN (
                SELECT referred_id FROM referrals WHERE referrer_id = ?
            ) AND status = 'completed' AND currency = 'XOF'
        ");
        $stmt->execute([$exchange_rate, $user_id]);
        $balance['team_recharge'] = (float) $stmt->fetchColumn();

        // 6. Retraits équipe
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as team_withdrawal
            FROM withdrawals 
            WHERE user_id IN (
                SELECT referred_id FROM referrals WHERE referrer_id = ?
            ) AND status = 'success'
        ");
        $stmt->execute([$user_id]);
        $balance['team_withdrawal'] = (float) $stmt->fetchColumn();

        // Montant disponible pour retrait
        $balance['available_for_withdrawal'] = max(0, 
            ($balance['profit'] + $balance['bonus']) - $balance['withdrawals']
        );

        // Log final
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Balance finale pour user_id $user_id : " . json_encode($balance) . "\n", FILE_APPEND);

        return $balance;
        
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur calcul balance (UID:$user_id): " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur calcul balance (UID:$user_id): " . $e->getMessage() . "\n", FILE_APPEND);
        return $balance;
    }
}

/**
 * Récupère les transactions récentes avec pagination
 */
function get_recent_transactions($user_id, $limit = 5, $type = null) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $sql = "SELECT * FROM transactions WHERE user_id = ?";
        $params = [$user_id];

        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_recent_transactions: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_recent_transactions: " . $e->getMessage() . "\n", FILE_APPEND);
        return [];
    }
}

/**
 * Récupère les statistiques de parrainage complètes
 */
function get_user_referrals($user_id) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    $stats = [
        'level1' => ['count' => 0, 'active' => 0, 'income' => 0],
        'level2' => ['count' => 0, 'active' => 0, 'income' => 0],
        'level3' => ['count' => 0, 'active' => 0, 'income' => 0],
        'team_recharge' => 0,
        'team_withdrawal' => 0,
        'first_recharge_count' => 0,
        'first_withdrawal_count' => 0
    ];

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Niveau 1 (directs)
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as count,
                              SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active,
                              SUM(r.referral_income) as income
                              FROM referrals r
                              JOIN users u ON r.referred_id = u.id
                              WHERE r.referrer_id = ? AND r.level = 1");
        $stmt->execute([$user_id]);
        $level1 = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($level1) {
            $stats['level1'] = [
                'count' => $level1['count'],
                'active' => $level1['active'],
                'income' => $level1['income'] ?? 0
            ];
        }

        // Niveau 2
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as count,
                              SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active,
                              SUM(r.referral_income) as income
                              FROM referrals r
                              JOIN users u ON r.referred_id = u.id
                              WHERE r.referrer_id = ? AND r.level = 2");
        $stmt->execute([$user_id]);
        $level2 = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($level2) {
            $stats['level2'] = [
                'count' => $level2['count'],
                'active' => $level2['active'],
                'income' => $level2['income'] ?? 0
            ];
        }

        // Niveau 3
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as count,
                              SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active,
                              SUM(r.referral_income) as income
                              FROM referrals r
                              JOIN users u ON r.referred_id = u.id
                              WHERE r.referrer_id = ? AND r.level = 3");
        $stmt->execute([$user_id]);
        $level3 = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($level3) {
            $stats['level3'] = [
                'count' => $level3['count'],
                'active' => $level3['active'],
                'income' => $level3['income'] ?? 0
            ];
        }

        // Recharges d'équipe
        $stmt = $pdo->prepare("SELECT SUM(amount) as total
                              FROM deposits 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              ) AND status = 'completed'");
        $stmt->execute([$user_id]);
        $stats['team_recharge'] = $stmt->fetchColumn() ?? 0;

        // Retraits d'équipe
        $stmt = $pdo->prepare("SELECT SUM(amount) as total
                              FROM withdrawals 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              ) AND status = 'success'");
        $stmt->execute([$user_id]);
        $stats['team_withdrawal'] = $stmt->fetchColumn() ?? 0;

        // Premières recharges
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count
                              FROM deposits 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $stats['first_recharge_count'] = $stmt->fetchColumn() ?? 0;

        // Premiers retraits
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count
                              FROM withdrawals 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $stats['first_withdrawal_count'] = $stmt->fetchColumn() ?? 0;

        return $stats;
        
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_user_referrals: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_user_referrals: " . $e->getMessage() . "\n", FILE_APPEND);
        return $stats;
    }
}

/**
 * Génère un code de parrainage unique
 */
function generate_referral_code() {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Récupère les informations d'un utilisateur par ID
 */
function get_user_by_id($user_id) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT 
                              id, username, email, is_admin, 
                              IFNULL(referral_code, 'default_ref') AS referral_code,
                              is_active, created_at, last_login
                              FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_user_by_id: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_user_by_id: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Traite un transfert via CinetPay
 */
function process_cinetpay_transfer($phone, $network, $amount, $user_id) {
    $api_url = "https://api.cinetpay.com/v2/transfer";
    
    $data = [
        'prefix' => substr($phone, 0, 3),
        'phone' => substr($phone, 3),
        'amount' => $amount,
        'currency' => 'XOF',
        'operator' => $network,
        'client_transaction_id' => 'WITHDRAW_' . $user_id . '_' . time(),
        'notify_url' => CINETPAY_CALLBACK_URL
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . CINETPAY_API_KEY
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($http_code !== 200 || !isset($result['data']['transaction_id'])) {
        error_log("CinetPay API Error: " . $response);
        throw new Exception("Erreur lors du transfert: " . ($result['message'] ?? "Code $http_code"));
    }

    return $result['data'];
}

/**
 * Initialise un paiement via CinetPay
 */
function init_cinetpay_payment($transaction_id, $amount, $phone, $network) {
    $api_url = "https://api.cinetpay.com/v2/payment";
    
    $data = [
        'amount' => $amount,
        'currency' => 'XOF',
        'description' => 'Dépôt sur mon compte',
        'customer_name' => 'Client',
        'customer_phone_number' => $phone,
        'customer_email' => 'client@example.com',
        'transaction_id' => $transaction_id,
        'payment_method' => $network,
        'return_url' => CINETPAY_RETURN_URL,
        'notify_url' => CINETPAY_CALLBACK_URL
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . CINETPAY_API_KEY
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($http_code !== 200 || !isset($result['data']['payment_url'])) {
        error_log("CinetPay Payment Error: " . $response);
        throw new Exception("Erreur lors du paiement: " . ($result['message'] ?? "Code $http_code"));
    }

    return $result['data'];
}

/**
 * Récupère les retraits d'un utilisateur
 */
function get_user_withdrawals($user_id, $limit = 10) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM withdrawals 
                              WHERE user_id = ? 
                              ORDER BY created_at DESC 
                              LIMIT ?");
        $stmt->execute([$user_id, (int)$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_user_withdrawals: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_user_withdrawals: " . $e->getMessage() . "\n", FILE_APPEND);
        return [];
    }
}

/**
 * Vérifie les nouveaux dépôts
 */
function check_new_deposits($user_id) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions 
                              WHERE user_id = ? AND type = 'deposit' 
                              AND created_at >= NOW() - INTERVAL 1 DAY");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur check_new_deposits: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur check_new_deposits: " . $e->getMessage() . "\n", FILE_APPEND);
        return 0;
    }
}

/**
 * Récupère les statistiques d'équipe complètes
 */
function get_team_stats($user_id) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    $stats = [
        'team_recharge' => 0,
        'team_withdrawal' => 0,
        'first_recharge_count' => 0,
        'first_withdrawal_count' => 0,
        'active_members' => 0,
        'total_members' => 0
    ];

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Membres de l'équipe
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total_members,
                              SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_members
                              FROM users 
                              WHERE id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $members = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($members) {
            $stats['total_members'] = $members['total_members'];
            $stats['active_members'] = $members['active_members'];
        }

        // Recharges d'équipe
        $stmt = $pdo->prepare("SELECT SUM(amount) as total
                              FROM deposits 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              ) AND status = 'completed'");
        $stmt->execute([$user_id]);
        $stats['team_recharge'] = $stmt->fetchColumn() ?? 0;

        // Retraits d'équipe
        $stmt = $pdo->prepare("SELECT SUM(amount) as total
                              FROM withdrawals 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              ) AND status = 'success'");
        $stmt->execute([$user_id]);
        $stats['team_withdrawal'] = $stmt->fetchColumn() ?? 0;

        // Premières recharges
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count
                              FROM deposits 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $stats['first_recharge_count'] = $stmt->fetchColumn() ?? 0;

        // Premiers retraits
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count
                              FROM withdrawals 
                              WHERE user_id IN (
                                  SELECT referred_id FROM referrals WHERE referrer_id = ?
                              )");
        $stmt->execute([$user_id]);
        $stats['first_withdrawal_count'] = $stmt->fetchColumn() ?? 0;

        return $stats;
        
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_team_stats: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_team_stats: " . $e->getMessage() . "\n", FILE_APPEND);
        return $stats;
    }
}

/**
 * Récupère le nombre total d'utilisateurs
 */
function get_total_users() {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_total_users: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_total_users: " . $e->getMessage() . "\n", FILE_APPEND);
        return 0;
    }
}

/**
 * Récupère le total des dépôts
 */
function get_total_deposits() {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT SUM(usd_amount) FROM deposits WHERE status = 'completed'");
        return $stmt->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_total_deposits: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_total_deposits: " . $e->getMessage() . "\n", FILE_APPEND);
        return 0;
    }
}

/**
 * Récupère le total des retraits
 */
function get_total_withdrawals() {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT SUM(amount) FROM withdrawals WHERE status = 'success'");
        return $stmt->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_total_withdrawals: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_total_withdrawals: " . $e->getMessage() . "\n", FILE_APPEND);
        return 0;
    }
}

/**
 * Récupère le nombre d'investissements actifs
 */
function get_active_investments() {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT COUNT(*) FROM investments WHERE status = 'active'");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_active_investments: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_active_investments: " . $e->getMessage() . "\n", FILE_APPEND);
        return 0;
    }
}

/**
 * Récupère les activités récentes pour le tableau de bord admin
 */
function get_recent_activities($limit = 10) {
    // Configuration de la connexion à la base de données
    $host = 'mysql-applovin.alwaysdata.net';
    $dbname = 'applovin_db';
    $username = 'applovin';
    $password = '@Motdepasse0000';

    try {
        // Connexion PDO
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT a.*, u.username 
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([(int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("[".date('Y-m-d H:i:s')."] Erreur get_recent_activities: " . $e->getMessage());
        file_put_contents(__DIR__ . '/payment.log', date('Y-m-d H:i:s') . " : Erreur get_recent_activities: " . $e->getMessage() . "\n", FILE_APPEND);
        return [];
    }
}

/**
 * Sanitize user input to prevent XSS and other injections
 */
function sanitize_input($input) {
    if (is_null($input)) {
        return '';
    }
    
    // Trim whitespace
    $input = trim($input);
    // Remove HTML/XML tags
    $input = strip_tags($input);
    // Convert special characters to HTML entities
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $input;
}
?>
