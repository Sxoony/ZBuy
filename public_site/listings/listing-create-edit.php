<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$error      = '';
$editMode   = false;
$listing    = null;
$listingId  = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

// If a listing_id is in the URL, we're editing
if ($listingId) {
    $stmt = $pdo->prepare('SELECT * FROM listings WHERE listing_id = ? AND seller_id = ?');
    $stmt->execute([$listingId, $_SESSION['user_id']]);
    $listing = $stmt->fetch();

    // If listing doesn't exist or doesn't belong to this user, boot them out
    if (!$listing) {
        redirect('/public_site/index.php');
    }

    $editMode = true;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['postAd'])) {

    $uploadDir = __DIR__ . "/../img/";
   $keptImages = trim($_POST['kept_images'] ?? '', '#');
    $mediaPath  = $editMode ? $keptImages : '';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!empty($_FILES['images']['name'][0])) {
        $newImages = '';
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;
            if (!is_uploaded_file($tmp_name)) continue;

            $originalName = basename($_FILES['images']['name'][$key]);
            $fileName     = uniqid() . "_" . $originalName;
            $targetFile   = $uploadDir . $fileName;

            if (move_uploaded_file($tmp_name, $targetFile)) {
                $newImages .= ($newImages === '' ? '' : '#') . $fileName;
            }
        }

        if ($newImages !== '') {
            $mediaPath = $mediaPath !== '' ? $mediaPath . '#' . $newImages : $newImages;
        }
    }

    $price       = (float)($_POST['adPrice'] ?? 0);
    $title       = trim($_POST['adTitle']    ?? '');
    $description = trim($_POST['adDesc']     ?? '');
    $amount      = (int)($_POST['adAmount']  ?? 1);

    if ($title !== '' && $price > 0) {

        if ($editMode) {
            // UPDATE existing listing
            $stmt = $pdo->prepare('
                UPDATE listings
                SET title = ?, description = ?, price = ?, amount = ?, media_path = ?
                WHERE listing_id = ? AND seller_id = ?
            ');
            $stmt->execute([
                $title,
                $description,
                $price,
                $amount,
                $mediaPath,
                $listingId,
                $_SESSION['user_id']
            ]);

            redirect('/PROJECT/public_site/listings/listing-view.php?listing_id=' . $listingId);

        } else {
            // INSERT new listing
            $date = (new DateTime())->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare('
                INSERT INTO listings (seller_id, title, description, price, amount, status, media_path, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $description,
                $price,
                $amount,
                'available',
                $mediaPath,
                $date
            ]);

            $newListingId = $pdo->lastInsertId();
            redirect('/PROJECT/public_site/listings/listing-view.php?listing_id=' . $newListingId);
        }

    } else {
        $error = 'Please fill in all required fields.';
    }

}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="form-container">
    <div class="form-container">
        <form action="" method="POST"id="createListingForm" class="form-group"  enctype="multipart/form-data">
            <h1><?= $editMode ? 'Edit Your Advert' : 'Post Your Advert' ?></h1>


            <h2>Ad Details</h2>
            <div class="ad-detail-form">
                <div class="ad-row">
                    <h3>Ad Title</h3>
                    <input type="text" placeholder="Ad Title" name="adTitle"
       value="<?= $editMode ? sanitize_string($listing['title']) : '' ?>">
                </div>

                <div class="ad-row">
                    <h3>Description</h3>
                    <textarea name="adDesc" id="adDesc"><?= $editMode ? sanitize_string($listing['description']) : '' ?></textarea>
                </div>
                <div class="ad-row">
                    <h3>Type of Listing</h3>
                    <select id="type" name="typeListing">
                        <option value="select">Select</option>
                        <option id="product" value="product">Product</option>
                        <option id="service" value="service">Service</option>
                    </select>
                </div>

                <div class="ad-row" id="amount">

                    <h3>Quantity</h3>
                    <input type="number" name="adAmount"
       value="<?= $editMode ? (int)$listing['amount'] : '' ?>">
                </div>

                <div class="ad-row">
                    <h3>Price</h3>
                    <input type="number" name="adPrice" id="adPrice"
       value="<?= $editMode ? (float)$listing['price'] : '' ?>">
                </div>
            </div>
            <h2>Media</h2>

            <div class="ad-detail-form">
                <div class="ad-row">



                        <div class="container">
                            <div class="main-box" id="uploadBox">
                                
                                <span class="upload-text">Click or drop images</span>
                                  
                            </div>
<input type="file" id="fileInput"name="images[]" multiple accept="image/*" style="display: none;">
                        <input type="hidden" name="media_path" id="media_path_input">
                       
                        <input type="hidden" name="kept_images" id="keptImages" value="<?= $editMode ? sanitize_string($listing['media_path'] ?? '') : '' ?>">
                        
<div class="thumbs" id="thumbs">
    <?php if ($editMode && !empty($listing['media_path'])): ?>
        <?php foreach (explode('#', trim($listing['media_path'], '#')) as $img): ?>
            <img src="../img/<?= sanitize_string($img) ?>" class="existing-thumb">
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($editMode): ?>
    <p style="font-size:12px;color:#777;">Uploading new images will be added to the existing ones.</p>
<?php endif; ?>
                        </div>

                      
                  
                   
                </div>
                
                 <button type="submit" name="postAd">
    <?= $editMode ? 'Save Changes' : 'Post Your Advert' ?>
</button>
</div>
                <script src="../js/script.js"></script>

</body>

</html>