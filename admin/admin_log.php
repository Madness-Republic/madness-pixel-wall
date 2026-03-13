<?php
session_start();
require_once '../includes/security_headers.php';
require_once __DIR__ . '/../includes/env_loader.php';

// CONFIG
$UPLOAD_DIR = __DIR__ . '/../data/uploads';
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
    @chmod($UPLOAD_DIR, 0755);
}

// Auth Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Accesso Negato.');
}

$file_path = __DIR__ . '/../data/updates.json';

// Atomic update helper
function atomicUpdate($filePath, $callback)
{
    if (!file_exists($filePath))
        file_put_contents($filePath, '[]');
    $fp = fopen($filePath, 'c+');
    if (!$fp)
        return false;
    if (flock($fp, LOCK_EX)) {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true) ?: [];
        $newData = $callback($data);
        if ($newData !== null) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT));
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}

// Image resize helper
function resizeImage($src, $dst, $maxW = 800)
{
    $size = @getimagesize($src);
    if (!$size) {
        return move_uploaded_file($src, $dst);
    }
    list($w, $h, $type) = $size;

    if ($w <= $maxW) {
        return move_uploaded_file($src, $dst);
    }

    // Check if GD is available
    if (!function_exists('imagecreatetruecolor')) {
        return move_uploaded_file($src, $dst);
    }

    $newW = $maxW;
    $newH = floor($h * ($maxW / $w));
    $img = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $img = @imagecreatefromjpeg($src);
            break;
        case IMAGETYPE_PNG:
            $img = @imagecreatefrompng($src);
            break;
        case IMAGETYPE_WEBP:
            $img = @imagecreatefromwebp($src);
            break;
    }

    if (!$img) {
        return move_uploaded_file($src, $dst);
    }

    $newImg = imagecreatetruecolor($newW, $newH);
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($newImg, false);
        imagesavealpha($newImg, true);
    }
    imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = @imagejpeg($newImg, $dst, 85);
            break;
        case IMAGETYPE_PNG:
            $result = @imagepng($newImg, $dst, 8);
            break;
        case IMAGETYPE_WEBP:
            $result = @imagewebp($newImg, $dst, 80);
            break;
    }
    if ($result)
        @chmod($dst, 0644);
    imagedestroy($img);
    imagedestroy($newImg);
    return $result;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = $_POST['id'] ?? '';
        $entry = [
            'id' => $id ?: uniqid(),
            'date' => $_POST['date'],
            'title' => $_POST['title'],
            'content' => $_POST['content'],
            'link' => $_POST['link'] ?? '',
            'image' => $_POST['existing_image'] ?? '',
            'likes' => (int) ($_POST['likes'] ?? 0),
            'hearts' => (int) ($_POST['hearts'] ?? 0)
        ];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fname = time() . '_' . uniqid() . '.' . $ext;
            $destination = $UPLOAD_DIR . '/' . $fname;
            if (resizeImage($_FILES['image']['tmp_name'], $destination)) {
                @chmod($destination, 0644);
                if (!empty($entry['image']) && strpos($entry['image'], 'data/uploads/') === 0) {
                    @unlink(__DIR__ . '/../' . $entry['image']);
                }
                $entry['image'] = 'data/uploads/' . $fname;
            } else {
                error_log("News Upload: Failed to save image to $destination");
            }
        } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            if (!empty($entry['image']) && strpos($entry['image'], 'data/uploads/') === 0) {
                @unlink(__DIR__ . '/../' . $entry['image']);
            }
            $entry['image'] = '';
        }

        atomicUpdate($file_path, function ($data) use ($id, $entry) {
            if ($id) {
                $found = false;
                foreach ($data as &$it) {
                    if ($it['id'] === $id) {
                        $it = $entry;
                        $found = true;
                        break;
                    }
                }
                if (!$found)
                    array_unshift($data, $entry);
            } else {
                array_unshift($data, $entry);
            }
            return $data;
        });
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        atomicUpdate($file_path, function ($data) use ($id) {
            $filtered = array_filter($data, function ($it) use ($id) {
                if ($it['id'] === $id) {
                    if (!empty($it['image']) && strpos($it['image'], 'data/uploads/') === 0)
                        @unlink(__DIR__ . '/../' . $it['image']);
                    return false;
                }
                return true;
            });
            return array_values($filtered);
        });
    }
    header("Location: admin_log.php");
    exit;
}

$entries = json_decode(file_get_contents($file_path), true) ?: [];
$editId = $_GET['edit'] ?? '';
$editData = null;
if ($editId) {
    foreach ($entries as $e) {
        if ($e['id'] === $editId) {
            $editData = $e;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>News Feed Admin</title>
    <link rel="stylesheet" href="admin-style.css?v=2.5.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #0b0e14;
            color: #fff;
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            overflow-y: auto;
        }

        .main-workspace {
            display: flex;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto 50px auto;
            align-items: flex-start;
        }

        /* Editor Section */
        .editor-section {
            flex: 1;
            background: #161b22;
            border: 1px solid #30363d;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        /* Preview Phone */
        .preview-section {
            width: 320px;
            position: sticky;
            top: 20px;
        }

        .phone-mockup {
            width: 100%;
            height: 600px;
            border: 10px solid #222;
            border-radius: 40px;
            background: #000;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .phone-mockup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 20px;
            background: #222;
            border-radius: 0 0 15px 15px;
            z-index: 10;
        }

        .phone-screen {
            height: 100%;
            padding: 15px;
            padding-top: 35px;
            overflow-y: auto;
            background: #111;
            color: #fff;
        }

        /* UI Components */
        .toolbar {
            display: flex;
            gap: 5px;
            background: #21262d;
            padding: 10px;
            border-radius: 8px 8px 0 0;
            border: 1px solid #30363d;
            border-bottom: none;
        }

        .t-btn {
            background: #30363d;
            border: none;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s;
        }

        .t-btn:hover {
            background: #1de9b6;
            color: #0b0e14;
        }

        textarea {
            width: 100%;
            background: #0d1117;
            color: #fff;
            border: 1px solid #30363d;
            border-radius: 0 0 10px 10px;
            padding: 15px;
            font-family: inherit;
            font-size: 1rem;
            min-height: 220px;
            box-sizing: border-box;
            line-height: 1.6;
        }

        input[type="text"] {
            width: 100%;
            padding: 14px;
            background: #0d1117;
            border: 1px solid #30363d;
            color: #fff;
            border-radius: 10px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #8b949e;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            border: none;
            transition: opacity 0.2s;
            font-size: 0.9rem;
        }

        .btn-green {
            background: #1de9b6;
            color: #0b0e14;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #30363d;
            color: #c9d1d9;
            border-radius: 8px;
        }

        .btn-red {
            background: #ff4d4d;
            color: #fff;
        }

        /* Mock Elements */
        .mock-post {
            background: #1a1d23;
            border: 1px solid #2d333b;
            border-radius: 12px;
            padding: 15px;
        }

        .m-date {
            font-size: 0.7rem;
            color: #8b949e;
            margin-bottom: 8px;
            font-family: monospace;
        }

        .m-title {
            font-weight: bold;
            font-size: 1.1rem;
            color: #1de9b6;
            margin-bottom: 10px;
        }

        .m-text {
            font-size: 0.85rem;
            line-height: 1.5;
            color: #c9d1d9;
        }

        .m-img {
            width: 100%;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
        }

        .m-cta {
            display: block;
            margin-top: 15px;
            background: #1de9b6;
            color: #0b0e14;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
        }

        .m-reactions {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            font-size: 0.8rem;
            color: #8b949e;
            border-top: 1px solid #333;
            padding-top: 10px;
        }

        /* Chronology Section */
        .chronology-section {
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: 50px;
        }

        .section-header {
            border-bottom: 1px solid #30363d;
            padding-bottom: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .entries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .entry-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
            position: relative;
        }

        .entry-card:hover {
            border-color: #1de9b6;
            transform: translateY(-5px);
        }

        .entry-card.editing {
            border-color: #1de9b6;
            background: rgba(29, 233, 182, 0.05);
        }
    </style>
</head>

<body>

    <!-- TOP: EDITOR & PREVIEW -->
    <div class="main-workspace">
        <div class="editor-section">
            <h2 style="margin-top:0; margin-bottom:30px; color:#1de9b6; font-size:1.5rem;">
                <?= $editData ? '<i class="fas fa-edit"></i> Modifica' : '<i class="fas fa-plus-circle"></i> Crea Nuova News' ?>
            </h2>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">
                <input type="hidden" name="existing_image" value="<?= $editData['image'] ?? '' ?>">
                <input type="hidden" name="likes" value="<?= $editData['likes'] ?? 0 ?>">
                <input type="hidden" name="hearts" value="<?= $editData['hearts'] ?? 0 ?>">

                <div class="form-row">
                    <div class="form-col" style="flex:1;">
                        <label>Data Pubblicazione</label>
                        <input type="text" name="date" id="in-date"
                            value="<?= $editData ? $editData['date'] : date('d/m/Y H:i') ?>">
                    </div>
                    <div class="form-col" style="flex:2;">
                        <label>Titolo</label>
                        <input type="text" name="title" id="in-title" placeholder="Es: Novità!"
                            value="<?= htmlspecialchars($editData['title'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Messaggio</label>
                    <div class="toolbar">
                        <button type="button" class="t-btn" data-tag="b" title="Grassetto"><i
                                class="fas fa-bold"></i></button>
                        <button type="button" class="t-btn" data-tag="i" title="Corsivo"><i
                                class="fas fa-italic"></i></button>
                        <button type="button" class="t-btn" data-tag="lnk" title="Aggiungi Link"><i
                                class="fas fa-link"></i> Link</button>
                        <button type="button" class="t-btn" data-tag="tpl-c" title="Template Cantiere">🏗️
                            Cantiere</button>
                        <button type="button" class="t-btn" data-tag="tpl-t" title="Template Traguardo">🏆
                            Traguardo</button>
                    </div>
                    <textarea name="content" id="in-content"
                        placeholder="Scrivi qui..."><?= htmlspecialchars($editData['content'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label>Link Esterno (Opzionale)</label>
                        <input type="text" name="link" id="in-link" placeholder="https://..."
                            value="<?= htmlspecialchars($editData['link'] ?? '') ?>">
                    </div>
                    <div class="form-col">
                        <label>Immagine</label>
                        <input type="file" name="image" id="in-img" accept="image/*" style="padding:10px;">
                        <?php if (!empty($editData['image'])): ?>
                            <div style="font-size:0.75rem; color:#8b949e; margin-top:8px;">
                                <i class="fas fa-image"></i> <?= basename($editData['image']) ?>
                                <label style="margin-left:10px; cursor:pointer;"><input type="checkbox" name="remove_image"
                                        value="1"> Rimuovi</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:30px; display:flex; gap:15px;">
                    <button type="submit" class="btn btn-green" style="flex:2;"><i class="fas fa-paper-plane"></i>
                        <?= $editData ? 'SALVA MODIFICHE' : 'PUBBLICA NEWS' ?></button>
                    <?php if ($editData): ?>
                        <a href="admin_log.php" class="btn btn-outline"
                            style="flex:1; text-align:center; text-decoration:none;">ANNULLA</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Preview Phone -->
        <aside class="preview-section">
            <div class="phone-mockup">
                <div class="phone-screen">
                    <div class="mock-post">
                        <div class="m-date" id="p-date">> 01/02/2026 09:13</div>
                        <div class="m-title" id="p-title">Titolo Anteprima</div>
                        <div class="m-text" id="p-content">Inizia a scrivere...</div>
                        <img id="p-img" class="m-img">
                        <a href="#" class="m-cta" id="p-cta" style="display:none;">SCOPRI DI PIÙ</a>
                        <div class="m-reactions">
                            <span>👍 <?= $editData['likes'] ?? 0 ?></span>
                            <span>❤️ <?= $editData['hearts'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <!-- BOTTOM: CHRONOLOGY -->
    <section class="chronology-section">
        <div class="section-header">
            <h3 style="margin:0;"><i class="fas fa-history" style="color:#1de9b6;"></i> Cronologia Notizie</h3>
            <?php if ($editData): ?>
                <a href="admin_log.php" class="btn btn-outline" style="padding:6px 15px; font-size:0.8rem;">+ NUOVA
                    NOTIZIA</a>
            <?php endif; ?>
        </div>

        <div class="entries-grid">
            <?php foreach ($entries as $e): ?>
                <div class="entry-card <?= ($editId === $e['id']) ? 'editing' : '' ?>">
                    <div style="font-size:0.7rem; color:#8b949e; margin-bottom:5px;"><?= $e['date'] ?></div>
                    <div style="font-weight:bold; color:#1de9b6; margin-bottom:10px;"><?= htmlspecialchars($e['title']) ?>
                    </div>
                    <div style="font-size:0.85rem; color:#ccc; margin-bottom:15px;">
                        <?= mb_strimwidth(strip_tags($e['content']), 0, 120, '...') ?>
                    </div>

                    <div
                        style="display:flex; justify-content:space-between; align-items:center; margin-top:15px; border-top:1px solid #333; padding-top:15px;">
                        <div style="display:flex; gap:12px; font-size:0.8rem; color:#8b949e;">
                            <span><i class="fas fa-thumbs-up" style="color:#1de9b6;"></i> <?= $e['likes'] ?></span>
                            <span><i class="fas fa-heart" style="color:#ff6b6b;"></i> <?= $e['hearts'] ?></span>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <a href="?edit=<?= $e['id'] ?>" class="btn btn-outline"
                                style="padding:5px 10px; font-size:0.7rem;">MODIFICA</a>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Vuoi eliminare questo post?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-red"
                                    style="padding:5px 10px; font-size:0.7rem;">ELIMINA</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <script nonce="<?= $nonce ?>">
        const iT = document.getElementById('in-title'), pT = document.getElementById('p-title');
        const iD = document.getElementById('in-date'), pD = document.getElementById('p-date');
        const iC = document.getElementById('in-content'), pC = document.getElementById('p-content');
        const iL = document.getElementById('in-link'), pB = document.getElementById('p-cta');
        const iM = document.getElementById('in-img'), pM = document.getElementById('p-img');

        function sync() {
            if (pT) pT.innerText = iT.value || 'Titolo Notizia';
            if (pD) pD.innerText = '> ' + (iD.value || 'Adesso');
            if (pC) pC.innerHTML = iC.value.replace(/\n/g, '<br>') || 'Contenuto...';
            if (pB) pB.style.display = iL.value.trim() ? 'block' : 'none';

            if (iM && iM.files && iM.files[0]) {
                pM.src = URL.createObjectURL(iM.files[0]);
                pM.style.display = 'block';
            } else if ("<?= $editData['image'] ?? '' ?>") {
                pM.src = "../<?= $editData['image'] ?? '' ?>";
                pM.style.display = 'block';
            } else {
                pM.style.display = 'none';
            }
        }

        if (iT) [iT, iD, iC, iL].forEach(el => el.addEventListener('input', sync));
        if (iM) iM.addEventListener('change', sync);

        document.querySelectorAll('.t-btn').forEach(btn => {
            btn.onclick = () => {
                const tag = btn.getAttribute('data-tag');
                iC.focus();
                const start = iC.selectionStart, end = iC.selectionEnd, text = iC.value;
                const selection = text.substring(start, end);

                if (tag === 'b' || tag === 'i') {
                    const out = `<${tag}>${selection}</${tag}>`;
                    iC.value = text.substring(0, start) + out + text.substring(end);
                    iC.selectionStart = iC.selectionEnd = start + out.length;
                } else if (tag === 'lnk') {
                    const url = prompt("Inserisci URL:");
                    if (url) {
                        const out = `<a href="${url}" target="_blank">${selection || 'clicca qui'}</a>`;
                        iC.value = text.substring(0, start) + out + text.substring(end);
                        iC.selectionStart = iC.selectionEnd = start + out.length;
                    }
                } else if (tag.startsWith('tpl-')) {
                    const c = tag === 'tpl-c'
                        ? '🏗️ <b>Aggiornamento!</b><br>I lavori avanzano velocemente...'
                        : '🏆 <b>Traguardo!</b><br>Obiettivo centrato con successo!';
                    iC.value = text.substring(0, start) + c + text.substring(end);
                    iC.selectionStart = iC.selectionEnd = start + c.length;
                }
                sync();
            };
        });

        sync();
    </script>

</body>

</html>