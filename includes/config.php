<?php
// ====================

// Sécurité
if (session_status() === PHP_SESSION_NONE) {
    // Configurations de session sécurisées
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Activez seulement si vous utilisez HTTPS
    ini_set('session.use_strict_mode', 1);
}  
// CONFIGURATION BASE DE DONNÉES
// =============================

// Charger les variables d'environnement (si tu utilises dotenv)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env');
    foreach ($lines as $line) {
        if (trim($line) && strpos($line, '=') !== false) {
            list($key, $value) = explode('=', trim($line), 2);
            putenv("$key=$value");
        }
    }
}

// Définir les constantes de connexion MySQL
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'defaultdb');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');


// ====================
// CONFIGURATION GÉNÉRALE
// ====================
if (!defined('APP_NAME')) define('APP_NAME', 'APPlovin Investment');
if (!defined('SITE_URL')) define('SITE_URL', 'https://applovin-invest.onrender.com');
if (!defined('APP_CURRENCY')) define('APP_CURRENCY', 'USDT');
if (!defined('SITE_EMAIL')) define('SITE_EMAIL', 'williamgreatford@gmail.com');
if (!defined('WELCOME_BONUS')) define('WELCOME_BONUS', 0); // 5 USDT
if (!defined('EXCHANGE_RATE')) define('EXCHANGE_RATE', 650); // 1 USD = 650 XOF

// ====================
// CONFIGURATION API DE PAIEMENT
// ====================
if (!defined('NOWPAYMENTS_API_KEY')) define('NOWPAYMENTS_API_KEY', '4YZX56R-WW5MXFM-Q5QARW2-784FC37');
if (!defined('NOWPAYMENTS_IPN_SECRET')) define('NOWPAYMENTS_IPN_SECRET', '/2fEckHwHRyy24C4GgBXAKqepv7ZXb2y');

// CinetPay (Mobile Money)
if (!defined('CINETPAY_API_KEY')) define('CINETPAY_API_KEY', '2356732867e6c01f425c81.87082936');
if (!defined('CINETPAY_SITE_ID')) define('CINETPAY_SITE_ID', '105890907');
if (!defined('CINETPAY_CALLBACK_URL')) define('CINETPAY_CALLBACK_URL', SITE_URL.'/cinetpay_callback.php');
if (!defined('CINETPAY_RETURN_URL')) define('CINETPAY_RETURN_URL', SITE_URL.'/deposit.php?status=success');

// ====================
// CONFIGURATION DES PLANS D'INVESTISSEMENT
// ====================
if (!defined('INVESTMENT_PLANS')) {
    define('INVESTMENT_PLANS', serialize([
        1 => [
            'name' => 'Starter',
            'min_amount' => 5,
            'daily_profit' => 8.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan de démarrage - 8% quotidien'
        ],
        2 => [
            'name' => 'Basic',
            'min_amount' => 35,
            'daily_profit' => 10.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan basique - 10% quotidien'
        ],
        3 => [
            'name' => 'Standard',
            'min_amount' => 120,
            'daily_profit' => 12.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan standard - 12% quotidien'
        ],
        4 => [
            'name' => 'Advanced',
            'min_amount' => 300,
            'daily_profit' => 14.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan avancé - 14% quotidien'
        ],
        5 => [
            'name' => 'Professional',
            'min_amount' => 700,
            'daily_profit' => 16.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan professionnel - 16% quotidien'
        ],
        6 => [
            'name' => 'Premium',
            'min_amount' => 1500,
            'daily_profit' => 18.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan premium - 18% quotidien'
        ],
        7 => [
            'name' => 'Gold',
            'min_amount' => 3000,
            'daily_profit' => 20.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan gold - 20% quotidien'
        ],
        8 => [
            'name' => 'Platinum',
            'min_amount' => 5000,
            'daily_profit' => 22.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan platinum - 22% quotidien'
        ],
        9 => [
            'name' => 'Diamond',
            'min_amount' => 7500,
            'daily_profit' => 24.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan diamond - 24% quotidien'
        ],
        10 => [
            'name' => 'VIP',
            'min_amount' => 10000,
            'daily_profit' => 26.00,
            'duration' => 96, // 96 = 4 jours de retraits possible sans parrainage
            'description' => 'Plan VIP - 26% quotidien'
        ]
    ]));
}

// ====================
// CONFIGURATION DES PARRAINAGES
// ====================
if (!defined('REFERRAL_LEVELS')) {
    define('REFERRAL_LEVELS', serialize([
        'level1' => 10,   // 10% pour le parrain direct
        'level2' => 2,    // 2% pour le niveau 2
        'level3' => 2     // 2% pour le niveau 3
    ]));
}

// ====================
// CONFIGURATION DES RETRAITS
// ====================
if (!defined('MIN_WITHDRAWAL_PERCENT')) define('MIN_WITHDRAWAL_PERCENT', 22); // 22% de la somme totale disponible
if (!defined('WITHDRAWAL_FEE')) define('WITHDRAWAL_FEE', 10); // 10% de frais
if (!defined('WITHDRAWAL_DAILY_LIMIT')) define('WITHDRAWAL_DAILY_LIMIT', 1); // 1 retraits max/jour
if (!defined('WITHDRAWAL_PROCESSING_TIME')) define('WITHDRAWAL_PROCESSING_TIME', 24); // Heures

// ====================
// CONFIGURATION DE SÉCURITÉ
// ====================
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', 15); // Minutes
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 1800); // 30 minutes en secondes

// ====================
// WALLETS CONFIGURATION
// ====================
if (!defined('USDT_TRC20_WALLET')) define('USDT_TRC20_WALLET', '0x36d9a57D9f6f4f7AA58d11ed97327846E316C866');
if (!defined('USDT_BEP20_WALLET')) define('USDT_BEP20_WALLET', '0x36d9a57D9f6f4f7AA58d11ed97327846E316C866');

// ====================
// INITIALISATION DE SESSION
// ====================
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 jour
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// ====================
// CONSTANTES ADMIN
// ====================
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'williamgreatford@gmail.com');
if (!defined('ADMIN_NOTIFICATIONS')) define('ADMIN_NOTIFICATIONS', true);
?>
