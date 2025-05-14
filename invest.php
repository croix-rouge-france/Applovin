<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

check_auth();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'];
    $transaction_hash = $_POST['transaction_hash'] ?? null;
    
    if ($amount < MIN_INVESTMENT) {
        $error = "L'investissement minimum est de ".MIN_INVESTMENT." USDT";
    } else {
        // Enregistrer l'investissement
        $stmt = $pdo->prepare("
            INSERT INTO investments (user_id, amount, payment_method, transaction_hash, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        
        if ($stmt->execute([$user_id, $amount, $payment_method, $transaction_hash])) {
            $success = "Votre investissement de $amount USDT a été enregistré avec succès. Il sera activé après confirmation.";
        } else {
            $error = "Une erreur s'est produite lors de l'enregistrement de votre investissement.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Nouvel Investissement</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Montant (USDT)</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                                   min="<?php echo MIN_INVESTMENT; ?>" required>
                            <div class="form-text">Investissement minimum: <?php echo MIN_INVESTMENT; ?> USDT</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Méthode de Paiement</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Sélectionnez une méthode</option>
                                <option value="usdt_erc20">USDT (ERC20)</option>
                                <option value="usdt_bep20">USDT (BEP20)</option>
                                <option value="flooz">Flooz</option>
                                <option value="mixx">Mixx by Yas</option>
                                <option value="mobile_money">Mobile Money</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="hashField" style="display: none;">
                            <label for="transaction_hash" class="form-label">Hash de Transaction</label>
                            <input type="text" class="form-control" id="transaction_hash" name="transaction_hash">
                        </div>
                        
                        <div class="mb-3">
                            <h5>Taux de Rendement Quotidien:</h5>
                            <ul class="list-group">
                                <?php foreach($investment_rates as $rate): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php 
                                    if ($rate['max'] > 0) {
                                        echo $rate['min'].' - '.$rate['max'].' USDT';
                                    } else {
                                        echo '> '.$rate['min'].' USDT';
                                    }
                                    ?>
                                    <span class="badge bg-primary rounded-pill"><?php echo $rate['rate']; ?>%</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Investir Maintenant</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('payment_method').addEventListener('change', function() {
    var hashField = document.getElementById('hashField');
    if (this.value.includes('usdt')) {
        hashField.style.display = 'block';
    } else {
        hashField.style.display = 'none';
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>