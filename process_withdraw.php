<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $withdraw_method = $_POST['withdraw_method'];

    // Récupérer l'utilisateur et ses informations
    $user_id = $_SESSION['user_id'];

    // Vérification du solde et du profit
    $balance = calculate_user_balance($user_id);
    $profit_threshold = $balance['invested'] * 0.12;

    if ($balance['profit'] < $profit_threshold) {
        $_SESSION['error'] = "Votre profit est insuffisant pour faire un retrait.";
        header("Location: withdraw.php");
        exit();
    }

    // Gestion du retrait
    if ($withdraw_method == 'usdt') {
        $usdt_address = $_POST['usdt_address'];

        // Ici, on peut ajouter la logique pour envoyer des USDT à l'adresse spécifiée
        // En utilisant une API externe (comme Binance, CoinPayments, etc.).

        $_SESSION['success'] = "Demande de retrait USDT soumise avec succès.";
    } elseif ($withdraw_method == 'mobile_money') {
        $mobile_number = $_POST['mobile_number'];

        // 85% du montant est transféré, 15% de frais
        $amount_to_transfer = $balance['profit'] * 0.85;

        // Appel à l'API CinetPay pour effectuer le transfert Mobile Money
        $response = send_mobile_money($mobile_number, $amount_to_transfer);

        if ($response['status'] === 'success') {
            $_SESSION['success'] = "Demande de retrait Mobile Money soumise avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors du transfert Mobile Money.";
        }
    } else {
        $_SESSION['error'] = "Méthode de retrait invalide.";
    }

    header("Location: withdraw.php");
    exit();
}

// Fonction d'envoi Mobile Money via CinetPay
function send_mobile_money($mobile_number, $amount) {
    // Paramètres CinetPay
    $api_key = CINETPAY_API_KEY;
    $site_id = CINETPAY_SITE_ID;

    $data = [
        'api_key' => $api_key,
        'site_id' => $site_id,
        'number' => $mobile_number,
        'amount' => $amount,
        'currency' => 'XOF',  // Exemple de devise
    ];

    $url = "https://pay.cinetpay.com/v1/mobilemoney";
    
    // Appel à l'API de CinetPay pour effectuer le paiement
    $response = file_get_contents($url . '?' . http_build_query($data));
    return json_decode($response, true);
}
?>
