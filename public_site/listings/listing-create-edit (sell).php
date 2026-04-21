<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['postAd'])) {

$uploadDir = __DIR__ . "/../img/";
$mediaPath = "";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
            continue;
        }

        if (!is_uploaded_file($tmp_name)) {
            continue;
        }

        $originalName = basename($_FILES['images']['name'][$key]);
        $fileName = uniqid() . "_" . $originalName;
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($tmp_name, $targetFile)) {
            $mediaPath .= ($mediaPath === '' ? '' : '#') . $fileName;
        }
    }
}
}


    $price       = (float)($_POST['adPrice']       ?? 0);
    $title       = trim($_POST['adTitle']          ?? '');
    $description = trim($_POST['adDesc']    ?? '');
    $amount      = (int)($_POST['adAmount']        ?? 1);
    $date        = (new DateTime())->format('Y-m-d H:i:s');

    if ($title !== '' && $price > 0) {
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
        redirect('/public_site/listings/listing-view.php?listing_id=' . $newListingId);
    } else {
        $error = 'Please fill in all required fields.';
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
        <form action="" method="POST" class="form-group"  enctype="multipart/form-data">
            <h1>Post Your Advert</h1>


            <h2>Ad Details</h2>
            <div class="ad-detail-form">
                <div class="ad-row">
                    <h3>Ad Title</h3>
                    <input type="text" placeholder="Ad Title" name="adTitle">
                </div>

                <div class="ad-row">
                    <h3>Description</h3>
                    <textarea name="adDesc" id="adDesc"></textarea>
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
                    <input type="number" name="adAmount">
                </div>

                <div class="ad-row">
                    <h3>Price</h3>
                    <input type="number" name="adPrice" id="adPrice">
                </div>
            </div>
            <h2>Media</h2>

            <div class="ad-detail-form">
                <div class="ad-row">



                    <div class="ad-row">
                        <div class="container">
                            <div class="main-box" id="uploadBox">
                                <span class="upload-text">Click or drop images</span>
                             
                            </div>
     <input type="file" id="fileInput"name="images[]" multiple accept="image/*" style="display: none;">
                        <input type="hidden" name="media_path" id="media_path_input">
                            <div class="thumbs" id="thumbs"></div>
                        </div>

                      
                    </div>
                   
                </div>
                 <button type="submit" name="postAd">Post Your Advert</button>
                <script src="../js/script.js"></script>

</body>

</html>