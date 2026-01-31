<?php
session_start();
require_once '../includes/security_headers.php';

// CONFIGURATION
require_once __DIR__ . '/../includes/env_loader.php';
$ADMIN_PASSWORD = env('ADMIN_PASSWORD', 'a');

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$UPLOAD_DIR = __DIR__ . '/../data/uploads';
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0777, true);
    // Add .htaccess to allow public read but no execution if needed, 
    // but the main data folder already has protection.
}

// Require Login - Now simplified to share the main dashboard session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('<div style="color: #ff4d4d; font-family: sans-serif; padding: 20px; background: #111; height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center;">
            <div>
                <h2 data-i18n="password_err">Sessione Scaduta o Non Autorizzato</h2>
                <a href="index.php" target="_parent" style="color: #1de9b6; text-decoration: none; border: 1px solid #1de9b6; padding: 5px 15px; border-radius: 4px; display: inline-block; margin-top: 10px;">Login</a>
            </div>
         </div>
         <script src="admin-lang.js?v=2.3.0"></script>
         <script>document.addEventListener("DOMContentLoaded", updateInterface);</script>');
}

// --- AUTHENTICATED AREA BELOW ---
$file_path = __DIR__ . '/../data/updates.json';
$message = '';

// Helper for Atomic Writes
function atomicUpdate($filePath, $callback)
{
    if (!file_exists($filePath)) {
        file_put_contents($filePath, '[]');
        @chmod($filePath, 0666);
    }
    $fp = fopen($filePath, 'c+');
    if (!$fp)
        return false;
    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($filePath);
        $content = $filesize > 0 ? fread($fp, $filesize) : '[]';
        $data = json_decode($content, true) ?: [];
        $newData = $callback($data);
        if ($newData !== null) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT));
            fflush($fp);
            @chmod($filePath, 0666);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}

// Helper: Resize Image (Same as admin_news.php)
function resizeImage($sourcePath, $targetPath, $maxWidth = 800)
{
    if (!function_exists('imagecreatetruecolor')) {
        return move_uploaded_file($sourcePath, $targetPath);
    }
    list($width, $height, $type) = getimagesize($sourcePath);
    if ($width <= $maxWidth) {
        return move_uploaded_file($sourcePath, $targetPath);
    }
    $newWidth = $maxWidth;
    $newHeight = floor($height * ($maxWidth / $width));
    $image = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            return move_uploaded_file($sourcePath, $targetPath);
    }
    if (!$image)
        return move_uploaded_file($sourcePath, $targetPath);
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    $success = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($newImage, $targetPath, 85);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($newImage, $targetPath, 8);
            break;
        case IMAGETYPE_WEBP:
            $success = imagewebp($newImage, $targetPath, 80);
            break;
    }
    imagedestroy($image);
    imagedestroy($newImage);
    return $success;
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Validation Failed");
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // ... (rest of add logic)
            $new_entry = [
                'id' => uniqid(),
                'date' => $_POST['date'],
                'title' => $_POST['title'],
                // SECURITY: We allow HTML but should still be careful. 
                // Since this is ADMIN only input, we trust the admin not to XSS themselves,
                // BUT we should verify no scripts are injected if we want to be safe.
                // For now, raw content is saved as intended for flexibility.
                'content' => $_POST['content'],
                'image' => '', // Default
                'link' => trim($_POST['link'] ?? ''),
                'link_label' => trim($_POST['link_label'] ?? ''),
                'likes' => 0,
                'hearts' => 0
            ];

            // Handle Image Upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $target = $UPLOAD_DIR . '/' . $filename;
                if (resizeImage($_FILES['image']['tmp_name'], $target, 800)) {
                    $new_entry['image'] = 'data/uploads/' . $filename;
                }
            }

            atomicUpdate($file_path, function ($data) use ($new_entry) {
                array_unshift($data, $new_entry);
                return $data;
            });
            $message = "Entry added successfully!";
        } elseif ($_POST['action'] === 'edit') {
            $index = (int) $_POST['index'];
            $updated_entry = [
                'date' => $_POST['date'],
                'title' => $_POST['title'],
                'content' => $_POST['content'],
                'image' => $_POST['existing_image'] ?? '',
                'link' => trim($_POST['link'] ?? ''),
                'link_label' => trim($_POST['link_label'] ?? '')
            ];

            // Handle Image Removal
            if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                if (!empty($updated_entry['image'])) {
                    $img_path = str_replace('data/uploads/', $UPLOAD_DIR . '/', $updated_entry['image']);
                    @unlink($img_path);
                    $updated_entry['image'] = '';
                }
            }

            // Handle Image Upload (Optional replacement)
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Delete old image if exists
                if (!empty($updated_entry['image'])) {
                    $old_img_path = str_replace('data/uploads/', $UPLOAD_DIR . '/', $updated_entry['image']);
                    @unlink($old_img_path);
                }

                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $target = $UPLOAD_DIR . '/' . $filename;
                if (resizeImage($_FILES['image']['tmp_name'], $target, 800)) {
                    $updated_entry['image'] = 'data/uploads/' . $filename;
                }
            }

            atomicUpdate($file_path, function ($data) use ($index, $updated_entry) {
                if (isset($data[$index])) {
                    // Preserve ID and Reactions if they exist
                    $updated_entry['id'] = $data[$index]['id'] ?? uniqid();
                    $updated_entry['likes'] = $data[$index]['likes'] ?? 0;
                    $updated_entry['hearts'] = $data[$index]['hearts'] ?? 0;

                    $data[$index] = $updated_entry;
                    return $data;
                }
                return null;
            });
            $message = "Entry updated successfully!";
        } elseif ($_POST['action'] === 'delete') {
            $index = (int) $_POST['index'];
            atomicUpdate($file_path, function ($data) use ($index, $UPLOAD_DIR) {
                if (isset($data[$index])) {
                    if (!empty($data[$index]['image'])) {
                        $img_path = str_replace('data/uploads/', $UPLOAD_DIR . '/', $data[$index]['image']);
                        @unlink($img_path);
                    }
                    array_splice($data, $index, 1);
                    return $data;
                }
                return null;
            });
            $message = "Entry deleted!";
        }

        // Refresh to prevent resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Re-read data after updates
$entries = json_decode(file_get_contents($file_path), true) ?? [];

// Edit Mode detection
$edit_index = isset($_GET['edit']) ? (int) $_GET['edit'] : -1;
$edit_data = ($edit_index >= 0 && isset($entries[$edit_index])) ? $entries[$edit_index] : null;
$action_label = $edit_data ? 'EDIT POST' : 'ADD NEW POST';
$btn_label = $edit_data ? 'UPDATE POST' : 'PUBLISH POST';


?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Admin - Pixel Wall</title>
    <link rel="stylesheet" href="admin-style.css?v=2.3.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding: 0 !important;
            background: #0b0e14;
            overflow-y: auto !important;
            height: auto !important;
        }

        .admin-main-wrapper {
            max-width: 100%;
        }

        .form-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 15px 20px;
            margin-bottom: 30px;
            max-width: 800px;
        }

        .entry-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 12px 15px;
            position: relative;
            margin-bottom: 12px;
            display: flex;
            gap: 15px;
            max-width: 800px;
        }

        .entry-content-admin {
            flex: 1;
        }

        .entry-image-preview {
            width: 80px;
            height: 80px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid var(--border);
        }

        .msg {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
            background: rgba(29, 233, 182, 0.1);
            color: var(--accent);
            border: 1px solid var(--accent);
            max-width: 800px;
        }

        input,
        textarea {
            background: #0d1117 !important;
            border-color: var(--border) !important;
            color: white !important;
            padding: 10px !important;
            font-size: 0.9rem !important;
        }

        label {
            color: var(--text-dim) !important;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
            display: block;
        }

        .entry-item h3 {
            margin: 0 0 5px 0;
            color: white;
            font-size: 1rem;
        }

        .entry-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .entry-reactions-preview {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        .admin-form .form-group {
            margin-bottom: 15px;
        }

        .btn {
            padding: 8px 16px;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="admin-main-wrapper">
        <header class="content-header">
            <h1 data-i18n="news_title">Gestione News & Log</h1>
        </header>

        <?php if ($message): ?>
            <div class="msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="form-box">
            <h2 style="margin-top:0; color: var(--accent);"
                data-i18n="<?php echo $edit_data ? 'edit_post' : 'add_post'; ?>">
                <?php echo $action_label; ?>
            </h2>
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <input type="hidden" name="action" value="<?php echo $edit_data ? 'edit' : 'add'; ?>">
                <input type="hidden" name="index" value="<?php echo $edit_index; ?>">
                <input type="hidden" name="existing_image" value="<?php echo $edit_data['image'] ?? ''; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

                <div class="form-group">
                    <label data-i18n="date_hint">Data e Ora (GG/MM/AAAA hh:mm)</label>
                    <input type="text" name="date"
                        value="<?php echo $edit_data ? htmlspecialchars($edit_data['date']) : date('d/m/Y H:i'); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label data-i18n="title_label">Titolo</label>
                    <input type="text" name="title"
                        value="<?php echo $edit_data ? htmlspecialchars($edit_data['title']) : ''; ?>"
                        placeholder="Es. Aggiornamento Server" required>
                </div>

                <div class="form-group">
                    <label data-i18n="content_label">Contenuto (HTML Abilitato)</label>
                    <textarea name="content" rows="6" placeholder="Scrivi qui... Usa <a> per i link."
                        required><?php echo $edit_data ? htmlspecialchars($edit_data['content']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label data-i18n="img_label">Immagine (Opzionale)</label>
                    <?php if ($edit_data && !empty($edit_data['image'])): ?>
                        <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 15px;">
                            <img src="../<?php echo htmlspecialchars($edit_data['image']); ?>"
                                style="height: 60px; border-radius: 4px; border: 1px solid var(--border);">
                            <label
                                style="display: flex; align-items: center; gap: 8px; color: var(--danger) !important; cursor: pointer; border: 1px solid var(--danger); padding: 5px 12px; border-radius: 4px; font-size: 0.8rem; margin: 0; text-transform: none;">
                                <input type="checkbox" name="remove_image" value="1" style="width: auto; margin: 0;">
                                <span data-i18n="remove_img">Rimuovi Immagine</span>
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="file-input-wrapper" id="drop-zone"
                        style="border: 1px dashed var(--border); padding: 20px; text-align: center; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.02);">
                        <span id="file-label"
                            data-i18n="<?php echo $edit_data && !empty($edit_data['image']) ? 'click_change' : 'click_up'; ?>">📸
                            Clicca per caricare</span>
                        <input type="file" id="file-upload" name="image" accept="image/*" style="display:none">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group" style="margin-bottom:0">
                        <label data-i18n="ext_link">URL Link Esterno</label>
                        <input type="text" name="link"
                            value="<?php echo $edit_data ? htmlspecialchars($edit_data['link']) : ''; ?>"
                            placeholder="https://...">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label data-i18n="link_txt">Testo Link</label>
                        <input type="text" name="link_label"
                            value="<?php echo $edit_data ? htmlspecialchars($edit_data['link_label']) : ''; ?>"
                            data-i18n-placeholder="read_more" placeholder="Scopri di più">
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="flex: 2;"
                        data-i18n="<?php echo $edit_data ? 'update' : 'publish'; ?>"><?php echo $btn_label; ?></button>
                    <?php if ($edit_data): ?>
                        <a href="admin_log.php" class="btn btn-outline"
                            style="flex: 1; text-align: center; text-decoration: none;" data-i18n="cancel">Annulla</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h2 style="margin-bottom: 20px;" data-i18n="current_posts">Post Pubblicati</h2>
        <div class="entry-list">
            <?php foreach ($entries as $index => $entry): ?>
                <div class="entry-item">
                    <?php if (!empty($entry['image'])): ?>
                        <img src="../<?php echo htmlspecialchars($entry['image']); ?>" class="entry-image-preview">
                    <?php endif; ?>

                    <div class="entry-content-admin">
                        <div class="entry-meta">
                            <div style="color: var(--text-dim); font-size: 0.8rem;">
                                <i class="fa-solid fa-calendar-days"></i> <?php echo htmlspecialchars($entry['date']); ?>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <a href="?edit=<?php echo $index; ?>" class="btn btn-outline"
                                    style="padding: 4px 10px; font-size: 0.75rem;" data-i18n="edit">EDIT</a>
                                <form method="POST" class="delete-form" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="index" value="<?php echo $index; ?>">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button type="submit" class="btn btn-danger"
                                        style="padding: 4px 10px; font-size: 0.75rem;" data-i18n="delete">DELETE</button>
                                </form>
                            </div>
                        </div>
                        <h3><?php echo htmlspecialchars($entry['title']); ?></h3>
                        <div style="font-size: 0.95rem; color: #ccc; line-height: 1.5;">
                            <?php echo nl2br($entry['content']); ?>
                        </div>

                        <div class="entry-reactions-preview">
                            <span><i class="fa-solid fa-thumbs-up"></i> <?php echo (int) ($entry['likes'] ?? 0); ?></span>
                            <span><i class="fa-solid fa-heart"></i> <?php echo (int) ($entry['hearts'] ?? 0); ?></span>
                            <?php if (!empty($entry['link'])): ?>
                                <a href="<?php echo htmlspecialchars($entry['link']); ?>" target="_blank"
                                    style="color: var(--accent); text-decoration: none; margin-left: auto;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    <?php echo htmlspecialchars($entry['link_label'] ?: 'Dettagli'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script src="admin-lang.js?v=2.3.0"></script>
        <script nonce="<?php echo $nonce; ?>">
            document.addEventListener('DOMContentLoaded', function () {
                // Delete Confirmation - Re-implemented for CSP compliance
                document.querySelectorAll('.delete-form').forEach(form => {
                    form.addEventListener('submit', function (e) {
                        const msg = (typeof t === 'function') ? t('confirm_del') : 'Sei sicuro di voler eliminare questa notizia?';
                        if (!confirm(msg)) {
                            e.preventDefault();
                        }
                    });
                });
                // Image Upload Trigger
                const dropZone = document.getElementById('drop-zone');
                const fileInput = document.getElementById('file-upload');
                const fileLabel = document.getElementById('file-label');

                if (dropZone && fileInput) {
                    dropZone.addEventListener('click', () => fileInput.click());
                    fileInput.addEventListener('change', function () {
                        if (this.files && this.files[0]) {
                            fileLabel.innerText = '✅ ' + this.files[0].name;
                        }
                    });
                }



                // Localize if ready
                if (typeof updateInterface === 'function') updateInterface();
            });
        </script>
    </div>
</body>

</html>