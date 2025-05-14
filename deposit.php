<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Activation du mode debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Vérification et sécurisation du plan_id
$plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;

$investment_plans = [
    1 => ['amount' => 5, 'daily_return' => 8.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=6157357616', 'bep20_link' => 'https://nowpayments.io/payment/?iid=5963899091'],
    2 => ['amount' => 35, 'daily_return' => 10.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=5923922659', 'bep20_link' => 'https://nowpayments.io/payment/?iid=6054018746'],
    3 => ['amount' => 120, 'daily_return' => 12.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=5656395027', 'bep20_link' => 'https://nowpayments.io/payment/?iid=4673418638'],
    4 => ['amount' => 300, 'daily_return' => 14.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=4342349747', 'bep20_link' => 'https://nowpayments.io/payment/?iid=4359770845'],
    5 => ['amount' => 700, 'daily_return' => 16.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=5793568418', 'bep20_link' => 'https://nowpayments.io/payment/?iid=5702345758'],
    6 => ['amount' => 1500, 'daily_return' => 18.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=4324634587', 'bep20_link' => 'https://nowpayments.io/payment/?iid=5515567321'],
    7 => ['amount' => 3000, 'daily_return' => 20.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=4737132021', 'bep20_link' => 'https://nowpayments.io/payment/?iid=6262331768'],
    8 => ['amount' => 5000, 'daily_return' => 22.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=5751083256', 'bep20_link' => 'https://nowpayments.io/payment/?iid=4391878721'],
    9 => ['amount' => 7500, 'daily_return' => 24.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=6436003035', 'bep20_link' => 'https://nowpayments.io/payment/?iid=5358065240'],
    10 => ['amount' => 10000, 'daily_return' => 26.00, 'erc20_link' => 'https://nowpayments.io/payment/?iid=4997746752', 'bep20_link' => 'https://nowpayments.io/payment/?iid=5045740854']
];

// Vérification si le plan existe
if (!array_key_exists($plan_id, $investment_plans) || $plan_id <= 0) {
    echo '<div class="error-container"><i class="fas fa-exclamation-triangle"></i> Erreur : Plan invalide ou non spécifié. <a href="investment.php">Retour aux plans</a></div>';
    exit();
}

$plan = $investment_plans[$plan_id];
$xof_amount = $plan['amount'] * EXCHANGE_RATE;
$daily_earning = $plan['amount'] * $plan['daily_return'] / 100;

// Vérification que EXCHANGE_RATE est défini et valide
if (!defined('EXCHANGE_RATE') || EXCHANGE_RATE <= 0) {
    echo '<div class="error-container"><i class="fas fa-exclamation-triangle"></i> Erreur : Taux de change non défini ou invalide. Contactez l\'administrateur.</div>';
    exit();
}

// Générer un ID unique pour ce paiement
$payment_id = 'INVEST_' . $plan_id . '_' . time() . '_' . bin2hex(random_bytes(4));

// Définir l'URL de retour
$return_url = "https://applovin.kesug.com/return.php"; // Remplacez par votre domaine réel
$social_shop_url = "https://ecommerce.paydunya.com/applovinpaiementmobilemoney?payment_id=$payment_id&plan_id=$plan_id&amount=$xof_amount&return_url=" . urlencode($return_url);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investissement - Plan Niveau <?= $plan_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6c63ff;
            --secondary-color: #4d44db;
            --accent-color: #ff6584;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .payment-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            border: none;
            overflow: hidden;
        }

        .payment-card:hover {
            transform: translateY(-5px);
        }

        .payment-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .payment-body {
            padding: 2rem;
        }

        .payment-method {
            border: 2px solid #eee;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(108, 99, 255, 0.1);
        }

        .payment-method.active {
            border-color: var(--primary-color);
            background-color: rgba(108, 99, 255, 0.05);
        }

        .payment-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .btn-payment {
            background-color: var(--primary-color);
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-payment:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-payment-network {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            padding: 8px 15px;
            font-size: 0.9rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-payment-network:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .plan-summary {
            background-color: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .progress-container {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin: 1rem 0;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            height: 100%;
            border-radius: 4px;
            width: 0;
            transition: width 2s ease;
        }

        .tab-content {
            padding: 1.5rem 0;
        }

        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 600;
            border: none;
            padding: 0.75rem 1.5rem;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: transparent;
        }

        .conversion-rate {
            font-size: 0.9rem;
            color: #6c757d;
            text-align: center;
            margin-top: 1rem;
        }

        .nowpayments-badge {
            background-color: #0a1f44;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            margin-top: 10px;
        }

        .paydunya-badge {
            background-color: #00a651;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            margin-top: 10px;
        }

        .error-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 15px;
            border-radius: 8px;
            background-color: #ffebee;
            border-left: 4px solid #d32f2f;
            color: #d32f2f;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .error-container i {
            margin-right: 10px;
        }

        .error-container a {
            color: #d32f2f;
            text-decoration: underline;
        }

        .error-container a:hover {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="payment-card mb-4">
                    <div class="payment-header">
                        <h2 class="mb-0">Investissement - Plan Niveau <?= $plan_id ?></h2>
                    </div>
                    <div class="payment-body">
                        <div class="plan-summary mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Investissement:</span>
                                        <strong><?= number_format($plan['amount'], 2) ?> USD</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Montant XOF:</span>
                                        <strong><?= number_format($xof_amount) ?> XOF</strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Retour quotidien:</span>
                                        <strong><?= $plan['daily_return'] ?>%</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Gain quotidien:</span>
                                        <strong><?= number_format($daily_earning, 2) ?> USD</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="conversion-rate">
                                Taux de conversion: 1 USD = <?= EXCHANGE_RATE ?> XOF
                            </div>
                        </div>

                        <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="crypto-tab" data-bs-toggle="tab" data-bs-target="#crypto" type="button" role="tab">Crypto (USDT)</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="mobile-tab" data-bs-toggle="tab" data-bs-target="#mobile" type="button" role="tab">Mobile Money</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="paymentTabsContent">
                            <div class="tab-pane fade show active" id="crypto" role="tabpanel">
                                <div class="text-center mb-4">
                                    <i class="fab fa-ethereum payment-icon"></i>
                                    <h4>Paiement en USDT</h4>
                                    <p class="text-muted">Choisissez le réseau pour votre paiement</p>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="payment-method active" id="erc20-method">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="cryptoNetwork" id="erc20" value="erc20" checked>
                                                <label class="form-check-label" for="erc20">
                                                    <strong>ERC20 (Ethereum)</strong>
                                                </label>
                                            </div>
                                            <small class="text-muted">Frais de réseau moyens</small>
                                            <div class="text-center mt-3">
                                                <a href="<?= $plan['erc20_link'] ?>" class="btn btn-outline-primary btn-sm btn-payment-network" target="_blank">
                                                    Payer via ERC20
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="payment-method" id="bep20-method">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="cryptoNetwork" id="bep20" value="bep20">
                                                <label class="form-check-label" for="bep20">
                                                    <strong>BEP20 (Binance Smart Chain)</strong>
                                                </label>
                                            </div>
                                            <small class="text-muted">Frais de réseau bas</small>
                                            <div class="text-center mt-3">
                                                <a href="<?= $plan['bep20_link'] ?>" class="btn btn-outline-primary btn-sm btn-payment-network" target="_blank">
                                                    Payer via BEP20
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="nowpayments-widget" class="text-center mt-4">
                                    <p>Montant à payer: <strong><?= number_format($plan['amount'], 2) ?> USDT</strong></p>
                                    
                                    <div class="nowpayments-badge mt-2">
                                        <i class="fas fa-lock me-1"></i> Paiement sécurisé par NowPayments
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="mobile" role="tabpanel">
                                <div class="text-center mb-4">
                                    <i class="fas fa-mobile-alt payment-icon"></i>
                                    <h4>Paiement Mobile Money</h4>
                                    <p class="text-muted">Accédez à la boutique pour effectuer votre paiement</p>
                                </div>
                                <div class="text-center">
                                    <a href="<?= htmlspecialchars($social_shop_url) ?>" target="_blank" class="btn btn-payment btn-lg">
                                        <i class="fas fa-shopping-cart me-2"></i> Aller à la boutique APPlovin Invest Shop
                                    </a>
                                    <div class="paydunya-badge mt-2">
                                        <i class="fas fa-lock me-1"></i> Paiement sécurisé par PayDunya
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <a href="dashboard.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i> Retour aux plans d'investissement
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de redirection pour NowPayments -->
        <div class="modal fade" id="nowpaymentsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Redirection vers NowPayments</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="nowpaymentsModalBody">
                        <p>Vous allez être redirigé vers la plateforme sécurisée NowPayments.</p>
                        <div class="alert alert-info">
                            <p><strong>ID de paiement:</strong> <?= $payment_id ?></p>
                            <p>Conservez cet ID en cas de problème.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <a href="#" class="btn btn-primary" target="_blank" id="confirmNowPayments">Confirmer</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal d'erreur générique -->
        <div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Erreur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="errorModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                window.showErrorModal = function(message) {
                    const modal = new bootstrap.Modal(document.getElementById('errorModal'));
                    document.getElementById('errorModalBody').textContent = message;
                    modal.show();
                };

                const checkModalExistence = () => {
                    return document.getElementById('nowpaymentsModal') && document.getElementById('errorModal');
                };

                function selectPaymentMethod(method) {
                    $('.payment-method').removeClass('active');
                    $(`#${method}-method`).addClass('active');
                    $('input[name="cryptoNetwork"]').prop('checked', false);
                    $(`#${method}`).prop('checked', true);
                }

                $('#initCryptoPayment').click(function() {
                    if (!checkModalExistence()) {
                        showErrorModal('Erreur système : Impossible d\'initialiser le paiement. Veuillez actualiser la page.');
                        return;
                    }

                    const network = $('input[name="cryptoNetwork"]:checked').val();
                    const paymentLink = network === 'erc20' ? '<?= $plan["erc20_link"] ?>' : '<?= $plan["bep20_link"] ?>';

                    $.ajax({
                        url: 'create_payment.php',
                        method: 'POST',
                        data: {
                            payment_id: '<?= $payment_id ?>',
                            plan_id: <?= $plan_id ?>,
                            amount: <?= $plan['amount'] ?>,
                            network: network,
                            status: 'pending'
                        },
                        success: function(response) {
                            console.log('Réponse NowPayments :', response);
                            if (response.success) {
                                const nowpaymentsModal = new bootstrap.Modal(document.getElementById('nowpaymentsModal'));
                                $('#nowpaymentsModalBody').html(`
                                    <p>Vous allez être redirigé pour payer <strong><?= number_format($plan['amount'], 2) ?> USDT</strong> via ${network.toUpperCase()}.</p>
                                    <div class="alert alert-info">
                                        <p><strong>ID de paiement:</strong> <?= $payment_id ?></p>
                                        <p>Conservez cet ID en cas de problème.</p>
                                    </div>
                                    <p>Vérification automatique dans <span id="countdown">30</span> secondes...</p>
                                `);
                                $('#confirmNowPayments').attr('href', paymentLink);
                                nowpaymentsModal.show();
                                startPaymentVerification('<?= $payment_id ?>');
                            } else {
                                showErrorModal(response.message || 'Erreur lors de la création de la transaction.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erreur AJAX (NowPayments) :', xhr.responseText);
                            showErrorModal('Erreur de connexion au serveur (NowPayments) : ' + xhr.status + ' - ' + xhr.statusText);
                        }
                    });
                });

                function startPaymentVerification(payment_id) {
                    let countdown = 30;
                    const countdownElement = $('#countdown');
                    const countdownInterval = setInterval(() => {
                        countdown--;
                        countdownElement.text(countdown);

                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            verifyPayment(payment_id);
                        }
                    }, 1000);
                }

                function verifyPayment(payment_id) {
                    $.ajax({
                        url: 'verify_payment.php',
                        method: 'POST',
                        data: { payment_id: payment_id },
                        success: function(response) {
                            console.log('Vérification NowPayments :', response);
                            if (response.status === 'completed') {
                                $('#nowpaymentsModalBody').html(`
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                        <h4>Paiement confirmé!</h4>
                                        <p>Votre investissement de ${response.amount} USDT a été validé.</p>
                                        <a href="dashboard.php" class="btn btn-primary">Aller au tableau de bord</a>
                                    </div>
                                `);
                                $('#confirmNowPayments').hide();
                            } else {
                                $('#nowpaymentsModalBody').html(`
                                    <div class="alert alert-warning">
                                        <p>Paiement non encore confirmé. Vérification automatique dans <span id="countdown">30</span> secondes...</p>
                                        <p>Si vous avez déjà payé, le système détectera bientôt votre paiement.</p>
                                    </div>
                                `);
                                startPaymentVerification(payment_id);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Erreur vérification NowPayments :', xhr.responseText);
                            showErrorModal('Erreur lors de la vérification du paiement : ' + xhr.status + ' - ' + xhr.statusText);
                        }
                    });
                }

                $('.btn-payment-network').click(function(e) {
                    e.preventDefault();
                    const paymentLink = $(this).attr('href');
                    const network = paymentLink.includes('erc20') ? 'ERC20' : 'BEP20';

                    if (!checkModalExistence()) {
                        showErrorModal('Erreur système : Impossible d\'ouvrir la modal. Veuillez actualiser la page.');
                        return;
                    }

                    const nowpaymentsModal = new bootstrap.Modal(document.getElementById('nowpaymentsModal'));
                    $('#nowpaymentsModalBody').html(`
                        <p>Vous allez être redirigé pour payer <strong><?= number_format($plan['amount'], 2) ?> USDT</strong> via ${network}.</p>
                        <p>Après paiement, vous serez redirigé vers votre tableau de bord.</p>
                    `);
                    $('#confirmNowPayments').attr('href', paymentLink);
                    nowpaymentsModal.show();
                });
            });
        </script>
</body>
</html>