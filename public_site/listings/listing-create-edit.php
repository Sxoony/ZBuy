<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$error     = '';
$editMode  = false;
$listing   = null;
$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

if ($listingId) {
    $stmt = $pdo->prepare('SELECT * FROM listings WHERE listing_id = ? AND seller_id = ?');
    $stmt->execute([$listingId, $_SESSION['user_id']]);
    $listing = $stmt->fetch();

    if (!$listing) {
        redirect('/public_site/index.php');
    }
    $editMode = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['postAd'])) {

    $uploadDir  = __DIR__ . '/../img/';
    $keptImages = trim($_POST['kept_images'] ?? '', '#');
    $mediaPath  = $editMode ? $keptImages : '';

    // PHP safety net: if kept_images is empty in edit mode, preserve original
    if ($editMode && $mediaPath === '' && empty($_FILES['images']['name'][0])) {
        $mediaPath = $listing['media_path'] ?? '';
    }

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!empty($_FILES['images']['name'][0])) {
        $newImages = '';
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;
            if (!is_uploaded_file($tmp_name)) continue;

            $fileName   = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($tmp_name, $targetFile)) {
                $newImages .= ($newImages === '' ? '' : '#') . $fileName;
            }
        }

        if ($newImages !== '') {
            $mediaPath = $mediaPath !== '' ? $mediaPath . '#' . $newImages : $newImages;
        }
    }

    $price       = (float)($_POST['adPrice']  ?? 0);
    $title       = trim($_POST['adTitle']     ?? '');
    $description = trim($_POST['adDesc']      ?? '');
    $amount      = (int)($_POST['adAmount']   ?? 1);

    if ($title !== '' && $price > 0) {

        if ($editMode) {
            $stmt = $pdo->prepare('
                UPDATE listings
                SET title = ?, description = ?, price = ?, amount = ?, media_path = ?
                WHERE listing_id = ? AND seller_id = ?
            ');
            $stmt->execute([$title, $description, $price, $amount, $mediaPath, $listingId, $_SESSION['user_id']]);
            redirect('/PROJECT/public_site/listings/listing-view.php?listing_id=' . $listingId);

        } else {
            $stmt = $pdo->prepare('
                INSERT INTO listings (seller_id, title, description, price, amount, status, media_path, created_at)
                VALUES (?, ?, ?, ?, ?, "available", ?, NOW())
            ');
            $stmt->execute([$_SESSION['user_id'], $title, $description, $price, $amount, $mediaPath]);
            redirect('/PROJECT/public_site/listings/listing-view.php?listing_id=' . $pdo->lastInsertId());
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
    <title><?= $editMode ? 'Edit Listing' : 'Post an Ad' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Upload box ── */
        #uploadBox {
            height: 260px;
            border: 2px dashed var(--border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-main);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        #uploadBox.dragover { border-color: var(--primary); background: #eff6ff; }
        #uploadBox img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }

        /* Thumbnail strip */
        #thumbs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        #thumbs .thumb-wrap {
            position: relative;
            display: inline-block;
        }
        #thumbs img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 8px;
            display: block;
            cursor: pointer;
            border: 2px solid transparent;
        }
        #thumbs img:hover { border-color: var(--primary); }

        .remove-btn {
            position: absolute;
            top: 3px; right: 3px;
            width: 20px; height: 20px;
            border-radius: 50%;
            background: rgba(0,0,0,0.55);
            color: #fff;
            border: none;
            font-size: 11px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            z-index: 10;
            line-height: 1;
        }
    </style>
</head>
<body>

<!-- ── NAV ── -->
<nav class="navbar-custom d-flex justify-content-between align-items-center px-4" style="height:56px;">
    <a href="/PROJECT/public_site/index.php" class="fw-bold text-decoration-none" style="color:var(--primary);">Marketplace</a>
    <a href="/PROJECT/public_site/profile/profile-view.php" class="text-muted text-decoration-none small">My Profile</a>
</nav>

<div class="container py-4" style="max-width:720px;">

    <h2 class="fw-bold mb-4"><?= $editMode ? 'Edit Your Advert' : 'Post Your Advert' ?></h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= sanitize_string($error) ?></div>
    <?php endif; ?>

    <form action="" method="POST" id="createListingForm" enctype="multipart/form-data">

        <!-- ── Ad Details ── -->
        <div class="card-custom mb-4">
            <h5 class="fw-semibold mb-3">Ad Details</h5>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Ad Title <span class="text-danger">*</span></label>
                <input type="text" name="adTitle" class="form-control"
                       placeholder="e.g. iPhone 14 Pro — Excellent Condition"
                       value="<?= $editMode ? sanitize_string($listing['title']) : '' ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Description</label>
                <textarea name="adDesc" class="form-control" rows="4"
                          placeholder="Describe your item..."><?= $editMode ? sanitize_string($listing['description']) : '' ?></textarea>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Type</label>
                    <select name="typeListing" id="type" class="form-select">
                        <option value="select">Select</option>
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                </div>

                <div class="col-md-4" id="amount">
                    <label class="form-label small fw-semibold">Quantity</label>
                    <input type="number" name="adAmount" class="form-control" min="1"
                           value="<?= $editMode ? (int)$listing['amount'] : 1 ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Price (R) <span class="text-danger">*</span></label>
                    <input type="number" name="adPrice" class="form-control" min="0" step="0.01"
                           placeholder="0.00"
                           value="<?= $editMode ? (float)$listing['price'] : '' ?>" required>
                </div>
            </div>
        </div>

        <!-- ── Media ── -->
        <div class="card-custom mb-4">
            <h5 class="fw-semibold mb-3">Photos</h5>

            <!-- Hidden inputs -->
            <input type="file" id="fileInput" name="images[]" multiple accept="image/*" style="display:none;">
            <input type="hidden" name="kept_images" id="keptImages"
                   value="<?= $editMode ? sanitize_string($listing['media_path'] ?? '') : '' ?>">

            <!-- Drop zone -->
            <div id="uploadBox">
                <span class="text-muted" id="uploadPlaceholder">
                    <span style="font-size:28px;">📷</span><br>
                    <span class="small">Click or drag images here</span>
                </span>
            </div>

            <!-- Thumbnails -->
            <div id="thumbs"></div>

            <?php if ($editMode): ?>
                <p class="text-muted small mt-2 mb-0">New uploads are added to existing photos.</p>
            <?php endif; ?>
        </div>

        <!-- ── Submit ── -->
        <div class="d-flex gap-2">
            <button type="submit" name="postAd" class="btn btn-primary flex-grow-1">
                <?= $editMode ? 'Save Changes' : 'Post Your Advert' ?>
            </button>
            <a href="<?= $editMode
                ? '/PROJECT/public_site/listings/listing-view.php?listing_id=' . $listingId
                : '/PROJECT/public_site/index.php' ?>"
               class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const box         = document.getElementById('uploadBox');
    const input       = document.getElementById('fileInput');
    const thumbs      = document.getElementById('thumbs');
    const keptInput   = document.getElementById('keptImages');
    const placeholder = document.getElementById('uploadPlaceholder');

    if (!box || !input || !thumbs) return;

    const keptVal      = keptInput ? keptInput.value.trim() : '';
    let existingImages = keptVal !== '' ? keptVal.split('#').filter(Boolean) : [];
    let selectedFiles  = [];

    // Open file picker
    box.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-btn') || e.target === input) return;
        input.click();
    });

    // Drag and drop
    box.addEventListener('dragover', (e) => { e.preventDefault(); box.classList.add('dragover'); });
    box.addEventListener('dragleave', () => box.classList.remove('dragover'));
    box.addEventListener('drop', (e) => {
        e.preventDefault();
        box.classList.remove('dragover');
        selectedFiles = selectedFiles.concat(Array.from(e.dataTransfer.files));
        render();
    });

    input.addEventListener('change', (e) => {
        selectedFiles = selectedFiles.concat(Array.from(e.target.files));
        render();
    });

    // Sync on submit
    const form = document.getElementById('createListingForm');
    if (form) {
        form.addEventListener('submit', () => {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            input.files = dt.files;
            if (keptInput) keptInput.value = existingImages.join('#');
        });
    }

    // ── Render ──
    function render() {
        thumbs.innerHTML = '';
        box.innerHTML    = '';

        const allExisting = existingImages.map(name => ({ type: 'existing', name }));
        const allNew      = selectedFiles.map((file, i) => ({ type: 'new', file, i }));
        const all         = [...allExisting, ...allNew];

        if (all.length === 0) {
            box.innerHTML = `<span class="text-muted" id="uploadPlaceholder">
                <span style="font-size:28px;">📷</span><br>
                <span class="small">Click or drag images here</span>
            </span>`;
            return;
        }

        // Resolve all srcs preserving order
        const srcs   = new Array(all.length);
        let resolved = 0;

        all.forEach((item, pos) => {
            if (item.type === 'existing') {
                srcs[pos] = { src: `../img/${item.name}`, item, pos };
                resolved++;
                if (resolved === all.length) placeAll(srcs);
            } else {
                const reader = new FileReader();
                reader.onload = (e) => {
                    srcs[pos] = { src: e.target.result, item, pos };
                    resolved++;
                    if (resolved === all.length) placeAll(srcs);
                };
                reader.readAsDataURL(item.file);
            }
        });
    }

    function placeAll(srcs) {
        box.innerHTML    = '';
        thumbs.innerHTML = '';

        srcs.forEach(({ src, item, pos }) => {
            if (pos === 0) placeMain(src, item);
            else           placeThumb(src, item);
        });
    }

    function placeMain(src, item) {
        const img = document.createElement('img');
        img.src = src;

        const btn = makeRemoveBtn(() => removeItem(item));
        btn.style.top   = '8px';
        btn.style.right = '8px';

        box.appendChild(img);
        box.appendChild(btn);
    }

    function placeThumb(src, item) {
        const wrap = document.createElement('div');
        wrap.className = 'thumb-wrap';

        const img = document.createElement('img');
        img.src = src;
        img.title = 'Click to make cover';
        img.addEventListener('click', () => {
            if (item.type === 'existing') {
                existingImages = existingImages.filter(n => n !== item.name);
                existingImages.unshift(item.name);
            } else {
                selectedFiles = selectedFiles.filter(f => f !== item.file);
                selectedFiles.unshift(item.file);
            }
            render();
        });

        const btn = makeRemoveBtn(() => removeItem(item));

        wrap.appendChild(img);
        wrap.appendChild(btn);
        thumbs.appendChild(wrap);
    }

    function removeItem(item) {
        if (item.type === 'existing') {
            existingImages = existingImages.filter(n => n !== item.name);
        } else {
            selectedFiles = selectedFiles.filter(f => f !== item.file);
        }
        render();
    }

    function makeRemoveBtn(onClick) {
        const btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'remove-btn';
        btn.innerHTML = '&#10005;';
        btn.addEventListener('click', (e) => { e.stopPropagation(); onClick(); });
        return btn;
    }

    // Initial render (draws existing images on edit mode)
    render();

    // Type select: hide quantity for services
    const typeSelect     = document.getElementById('type');
    const amountField    = document.getElementById('amount');
    if (typeSelect && amountField) {
        typeSelect.addEventListener('change', function () {
            amountField.style.display = this.value === 'service' ? 'none' : '';
        });
    }

    // Dark mode
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');
});
</script>
</body>
</html>