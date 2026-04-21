<?php



$uploadDir = "../img/";
$uploadDir = "uploads/";
$mediaPath = "";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
    
    $originalName = basename($_FILES['images']['name'][$key]);
    
    // unique filename
    $fileName = uniqid() . "_" . $originalName;

    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file($tmp_name, $targetFile)) {
        $mediaPath .= "#" . $fileName;
    }
}

echo $mediaPath;
?>