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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="checkout-page bg-light">
<?php require_once '../reuse/nav.php';?>
<div class="container py-4">
    <div class="row g-4">

        <!-- LEFT: FORM -->
        <div class="col-lg-8">
            <div class="checkout-main">

                <h1 class="checkout-title mb-4">Checkout</h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= sanitize_string($error) ?></div>
                <?php endif; ?>

                <form action="" method="POST" id="checkoutForm">

                    <!-- DELIVERY -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Delivery Details</h5>

                            <div class="mb-3">
                                <label for="deliveryAddress" class="form-label">Delivery Address</label>
                                <input type="text" class="form-control"
                                       name="deliveryAddress" id="deliveryAddress"
                                       placeholder="Street address, city, postal code" required>
                                <small class="text-danger error-message" id="addressError"></small>
                            </div>

                            <div class="mb-3">
                                <label for="deliveryNote" class="form-label">
                                    Note to Seller <span class="text-muted">(optional)</span>
                                </label>
                                <textarea class="form-control"
                                          name="deliveryNote" id="deliveryNote"
                                          rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- PAYMENT -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Payment Details</h5>
                            <p class="text-muted small">
                                This is a simulated payment. No real transaction will occur.
                            </p>

                            <div class="mb-3">
                                <label for="cardName" class="form-label">Name on Card</label>
                                <input type="text" class="form-control"
                                       name="cardName" id="cardName"
                                       value="<?= sanitize_string($_POST['cardName'] ?? '') ?>">
                                <small class="text-danger error-message" id="cardNameError"></small>
                            </div>

                            <div class="mb-3">
                                <label for="cardNumber" class="form-label">Card Number</label>
                                <div class="input-group">
                                    <input type="text" class="form-control"
                                           name="cardNumber" id="cardNumber"
                                           maxlength="19"
                                           value="<?= sanitize_string($_POST['cardNumber'] ?? '') ?>">
                                    <span class="input-group-text" id="cardIcon">💳</span>
                                </div>
                                <small class="text-danger error-message" id="cardNumberError"></small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cardExpiry" class="form-label">Expiry</label>
                                    <input type="text" class="form-control"
                                           name="cardExpiry" id="cardExpiry"
                                           maxlength="5"
                                           value="<?= sanitize_string($_POST['cardExpiry'] ?? '') ?>">
                                    <small class="text-danger error-message" id="cardExpiryError"></small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="cardCvv" class="form-label">CVV</label>
                                    <input type="text" class="form-control"
                                           name="cardCvv" id="cardCvv"
                                           maxlength="4"
                                           value="<?= sanitize_string($_POST['cardCvv'] ?? '') ?>">
                                    <small class="text-danger error-message" id="cardCvvError"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTON -->
                    <button type="submit"
                            name="confirmPayment"
                            id="confirmPayment"
                            class="btn btn-success w-100 py-2">
                        Pay <?= formatPrice($grandTotal) ?>
                    </button>

                </form>
            </div>
        </div>

        <!-- RIGHT: SUMMARY -->
        <div class="col-lg-4">
            <div class="card position-sticky" style="top: 20px;">
                <div class="card-body">

                    <h5 class="card-title">Order Summary</h5>

                    <!-- Listing -->
                    <div class="d-flex mb-3">
                        <img src="../img/<?= sanitize_string($images[0]) ?>"
                             class="rounded me-3"
                             style="width:80px; height:80px; object-fit:cover;">
                        <div>
                            <p class="mb-1 fw-bold"><?= sanitize_string($listing['title']) ?></p>
                            <small class="text-muted">
                                Sold by
                                <a href="/public_site/profile/profile-view.php?user_id=<?= (int)$seller['user_id'] ?>">
                                    <?= sanitize_string($seller['username']) ?>
                                </a>
                            </small>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <?php if ((int)$listing['amount'] > 1): ?>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <select class="form-select"
                                name="quantity"
                                id="quantitySelect"
                                form="checkoutForm"
                                onchange="this.form.submit()">
                            <?php for ($i = 1; $i <= min((int)$listing['amount'], 10); $i++): ?>
                                <option value="<?= $i ?>" <?= $quantity === $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Breakdown -->
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between">
                            <span>Items</span>
                            <span><?= formatPrice($itemTotal) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Escrow fee</span>
                            <span><?= formatPrice($escrowFee) ?></span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold mt-2">
                            <span>Total</span>
                            <span><?= formatPrice($grandTotal) ?></span>
                        </div>
                    </div>

                    <!-- Notice -->
                    <div class="alert alert-light mt-3 small">
                        🔒 Payment held in escrow until delivery confirmed.
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>
</body>
</html>