<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$error      = '';
$modal_error  = '';
$listingId  = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

if (!$listingId) {
    redirect('/PROJECT/public_site/index.php');
}

// Fetch the listing
$stmt = $pdo->prepare('SELECT * FROM listings WHERE listing_id = ? AND status = "available"');
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    redirect('/public_site/index.php');
}

// Prevent seller buying their own listing
if ($listing['seller_id'] === (int)$_SESSION['user_id']) {
    redirect('/public_site/listings/listing-view.php?listing_id=' . $listingId);
}

// Fetch seller
$stmt = $pdo->prepare('SELECT user_id, username, profile_picture_path FROM users WHERE user_id = ?');
$stmt->execute([$listing['seller_id']]);
$seller = $stmt->fetch();

// Build image array for cover photo
$images = !empty($listing['media_path'])
    ? explode('#', trim($listing['media_path'], '#'))
    : ['placeholder.png'];

// Calculate totals
$quantity    = max(1, min((int)($_POST['quantity'] ?? 1), (int)$listing['amount']));
$itemTotal   = $listing['price'] * $quantity;
$escrowFee   = round($itemTotal * 0.02, 2); // 2% simulated escrow fee
$grandTotal  = $itemTotal + $escrowFee;

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmPayment'])) {

    // Basic card validation (purely visual — no real processing)
    $cardName   = trim(post('cardName'));
    $cardNumber = preg_replace('/\s+/', '', post('cardNumber'));
    $cardExpiry = trim(post('cardExpiry'));
    $cardCvv    = trim(post('cardCvv'));

    if (!$cardName || strlen($cardNumber) < 13 || !$cardExpiry || strlen($cardCvv) < 3) {
        $error = 'Please fill in all payment details correctly.';
    } else {

        try {
            $pdo->beginTransaction();

            // 1. Insert transaction
            $stmt = $pdo->prepare('
                INSERT INTO transactions (listing_id, buyer_id, price, quantity, status, date)
                VALUES (?, ?, ?, ?, "pending", NOW())
            ');
            $stmt->execute([
                $listingId,
                $_SESSION['user_id'],
                
                $grandTotal,
                $quantity
            ]);
            $transactionId = $pdo->lastInsertId();

            // 2. Insert escrow record
            $stmt = $pdo->prepare('
                INSERT INTO escrow (transaction_id, transaction_status, transaction_fee, bank_details)
                VALUES (?, "held", ?, ?)
            ');
            $stmt->execute([
                $transactionId,
                $escrowFee,
                'SIMULATED_ESCROW_REF_' . strtoupper(uniqid())
            ]);

            // 3. Reduce listing stock
            $stmt = $pdo->prepare('
                UPDATE listings
                SET amount = amount - ?
                WHERE listing_id = ? AND amount >= ?
            ');
            $stmt->execute([$quantity, $listingId, $quantity]);

            // 4. If stock hits 0, mark listing as sold
            $stmt = $pdo->prepare('
                UPDATE listings SET status = "sold"
                WHERE listing_id = ? AND amount = 0
            ');
            $stmt->execute([$listingId]);

            $pdo->commit();

        redirect('/PROJECT/public_site/transactions/checkout.php?new=' . $transactionId);

            

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Something went wrong processing your order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="checkout-page">

<div class="checkout-wrapper">

    <!-- LEFT: payment form -->
    <div class="checkout-main">

        <h1 class="checkout-title">Checkout</h1>

        <?php if ($error): ?>
            <div class="error-banner"><?= sanitize_string($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="checkoutForm">

            <!-- Delivery info -->
            <div class="checkout-card">
                <h2 class="checkout-card-heading">Delivery Details</h2>

                <div class="checkout-field">
                    <label for="deliveryAddress">Delivery Address</label>
                    <input type="text" name="deliveryAddress" id="deliveryAddress"
                           placeholder="Street address, city, postal code" required>
                    <small class="error-message" id="addressError"></small>
                </div>

                <div class="checkout-field">
                    <label for="deliveryNote">Note to Seller <span class="optional-tag">optional</span></label>
                    <textarea name="deliveryNote" id="deliveryNote"
                              placeholder="Any special instructions..."
                              rows="3"></textarea>
                </div>
            </div>

            <!-- Fake card form -->
            <div class="checkout-card">
                <h2 class="checkout-card-heading">Payment Details</h2>
                <p class="checkout-card-sub">This is a simulated payment. No real transaction will occur.</p>

                <div class="checkout-field">
                    <label for="cardName">Name on Card</label>
                    <input type="text" name="cardName" id="cardName"
                           placeholder="e.g. John Smith" required
                           value="<?= sanitize_string($_POST['cardName'] ?? '') ?>">
                    <small class="error-message" id="cardNameError"></small>
                </div>

                <div class="checkout-field">
                    <label for="cardNumber">Card Number</label>
                    <div class="card-number-wrap">
                        <input type="text" name="cardNumber" id="cardNumber"
                               placeholder="1234 5678 9012 3456"
                               maxlength="19" required
                               value="<?= sanitize_string($_POST['cardNumber'] ?? '') ?>">
                        <span class="card-icon" id="cardIcon">💳</span>
                    </div>
                    <small class="error-message" id="cardNumberError"></small>
                </div>

                <div class="checkout-row">
                    <div class="checkout-field">
                        <label for="cardExpiry">Expiry Date</label>
                        <input type="text" name="cardExpiry" id="cardExpiry"
                               placeholder="MM/YY" maxlength="5" required
                               value="<?= sanitize_string($_POST['cardExpiry'] ?? '') ?>">
                        <small class="error-message" id="cardExpiryError"></small>
                    </div>

                    <div class="checkout-field">
                        <label for="cardCvv">CVV</label>
                        <input type="text" name="cardCvv" id="cardCvv"
                               placeholder="123" maxlength="4" required
                               value="<?= sanitize_string($_POST['cardCvv'] ?? '') ?>">
                        <small class="error-message" id="cardCvvError"></small>
                    </div>
                </div>
            </div>

            <button type="submit" name="confirmPayment" class="btn-confirm-payment" id="confirmPayment">
                Pay <?= formatPrice($grandTotal) ?>
            </button>

        </form>
    </div>

    <!-- RIGHT: order summary -->
    <div class="checkout-sidebar">

        <div class="checkout-card">
            <h2 class="checkout-card-heading">Order Summary</h2>

            <!-- Listing preview -->
            <div class="order-listing-preview">
                <img src="../img/<?= sanitize_string($images[0]) ?>"
                     alt="<?= sanitize_string($listing['title']) ?>"
                     class="order-listing-img">
                <div class="order-listing-info">
                    <p class="order-listing-title"><?= sanitize_string($listing['title']) ?></p>
                    <p class="order-listing-seller">
                        Sold by
                        <a href="/public_site/profile/profile-view.php?user_id=<?= (int)$seller['user_id'] ?>">
                            <?= sanitize_string($seller['username']) ?>
                        </a>
                    </p>
                </div>
            </div>

            <!-- Quantity selector -->
            <?php if ((int)$listing['amount'] > 1): ?>
            <div class="checkout-field">
                <label for="quantitySelect">Quantity</label>
                <select name="quantity" id="quantitySelect" form="checkoutForm"
                        onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= min((int)$listing['amount'], 10); $i++): ?>
                        <option value="<?= $i ?>" <?= $quantity === $i ? 'selected' : '' ?>>
                            <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Price breakdown -->
            <div class="order-breakdown">
                <div class="order-breakdown-row">
                    <span>Item<?= $quantity > 1 ? 's (' . $quantity . ')' : '' ?></span>
                    <span><?= formatPrice($itemTotal) ?></span>
                </div>
                <div class="order-breakdown-row">
                    <span>Escrow fee <small>(2%)</small></span>
                    <span><?= formatPrice($escrowFee) ?></span>
                </div>
                <div class="order-breakdown-row total">
                    <span>Total</span>
                    <span><?= formatPrice($grandTotal) ?></span>
                </div>
            </div>

            <!-- Escrow notice -->
            <div class="escrow-notice">
                <span class="escrow-icon">🔒</span>
                <p>Your payment is held securely in escrow and only released to the seller once you confirm delivery.</p>
            </div>
        </div>

    </div>

     


</div>

<script src="../js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Card number formatting ──
    const cardNumber = document.getElementById('cardNumber');
    if (cardNumber) {
        cardNumber.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '').substring(0, 16);
            this.value = val.replace(/(.{4})/g, '$1 ').trim();

            // Basic card type detection for icon
            const icon = document.getElementById('cardIcon');
            if (icon) {
                if (val.startsWith('4'))       icon.textContent = '💳'; // Visa
                else if (val.startsWith('5'))  icon.textContent = '💳'; // Mastercard
                else                           icon.textContent = '💳';
            }
        });
    }

    // ── Expiry formatting ──
    const cardExpiry = document.getElementById('cardExpiry');
    if (cardExpiry) {
        cardExpiry.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '').substring(0, 4);
            if (val.length >= 2) val = val.substring(0, 2) + '/' + val.substring(2);
            this.value = val;
        });
    }

    // ── CVV numbers only ──
    const cardCvv = document.getElementById('cardCvv');
    if (cardCvv) {
        cardCvv.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 4);
        });
    }

    // ── Client-side validation ──
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {

            // Don't validate on quantity change (no confirmPayment in that submit)
            if (!document.activeElement.name === 'confirmPayment') return;

            let valid = true;
            document.querySelectorAll('.error-message').forEach(m => m.textContent = '');

            const address = document.getElementById('deliveryAddress').value.trim();
            if (!address) {
                document.getElementById('addressError').textContent = 'Delivery address is required';
                valid = false;
            }

            const name = document.getElementById('cardName').value.trim();
            if (!name) {
                document.getElementById('cardNameError').textContent = 'Name on card is required';
                valid = false;
            }

            const num = document.getElementById('cardNumber').value.replace(/\s/g, '');
            if (num.length < 13) {
                document.getElementById('cardNumberError').textContent = 'Enter a valid card number';
                valid = false;
            }

            const expiry = document.getElementById('cardExpiry').value;
            if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                document.getElementById('cardExpiryError').textContent = 'Enter expiry as MM/YY';
                valid = false;
            }

            const cvv = document.getElementById('cardCvv').value;
            if (cvv.length < 3) {
                document.getElementById('cardCvvError').textContent = 'Enter a valid CVV';
                valid = false;
            }

            if (!valid) e.preventDefault();

        });
    }
});
</script>
</body>
</html>