<?php
session_start();
include_once __DIR__ . '/../config.php';

// Security check
if (!isset($_SESSION['gdpr_admin_logged_in']) || $_SESSION['gdpr_admin_logged_in'] !== true) {
    header('Location: ../dashboard/index.php');
    exit;
}

$ui_lang = $_GET['lang'] ?? 'it';
$lang_file = __DIR__ . "/../languages/$ui_lang.json";
if (!file_exists($lang_file))
    $ui_lang = 'it';
$t = json_decode(file_get_contents(__DIR__ . "/../languages/$ui_lang.json"), true);
$guide = $t['guide'];
?>
<!DOCTYPE html>
<html lang="<?php echo $ui_lang; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $guide['title']; ?> - Madness GDPR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Oswald:wght@500;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --accent: #f09100;
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --code-bg: #000;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
            margin: 0;
            padding: 40px 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        header {
            text-align: center;
            margin-bottom: 50px;
        }

        header h1 {
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-size: 2.5rem;
            color: var(--accent);
            margin: 0;
        }

        .card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 30px;
        }

        h2 {
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            color: var(--accent);
            border-bottom: 2px solid var(--accent);
            padding-bottom: 10px;
            margin-top: 0;
            font-size: 1.4rem;
        }

        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: var(--accent);
            color: #000;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
            font-weight: bold;
        }

        code {
            background: var(--code-bg);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            color: #d1d5db;
            display: block;
            margin: 10px 0;
            padding: 15px;
            overflow-x: auto;
        }

        .note {
            background: rgba(240, 145, 0, 0.1);
            border-left: 4px solid var(--accent);
            padding: 15px;
            margin: 20px 0;
            font-size: 0.95rem;
        }

        footer {
            text-align: center;
            margin-top: 50px;
            font-size: 0.9rem;
            color: var(--text-dim);
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="../dashboard/index.php?lang=<?php echo $ui_lang; ?>" class="back-link">←
            <?php echo $guide['back']; ?></a>

        <header>
            <h1><?php echo $guide['title']; ?></h1>
        </header>

        <section class="card">
            <h2><span class="step-number">1</span> <?php echo $guide['step1_title']; ?></h2>
            <p><?php echo $guide['step1_desc']; ?></p>
            <code>&lt;?php include_once 'gdpr/banner.php'; ?&gt;</code>
            <div class="note">
                <?php echo $guide['step1_note']; ?>
            </div>
        </section>

        <section class="card">
            <h2><span class="step-number">2</span> <?php echo $guide['step2_title']; ?></h2>
            <p><?php echo $guide['step2_desc']; ?></p>
            <code>&lt;?php include_once 'gdpr/policy.php'; ?&gt;</code>
            <div class="note">
                <?php echo $guide['step2_note']; ?>
                <br><br>
                <strong>Novità v1.2.1:</strong> Puoi configurare un URL personalizzato nell'Admin. Nelle traduzioni
                JSON, usa il segnaposto <code>{{privacy_url}}</code> per generare link dinamici.
            </div>
        </section>

        <section class="card">
            <h2><span class="step-number">3</span> <?php echo $guide['step3_title']; ?></h2>
            <p><?php echo $guide['step3_desc']; ?></p>
            <ul>
                <li><?php echo $guide['step3_li1']; ?></li>
                <li><?php echo $guide['step3_li2']; ?></li>
            </ul>
            <div class="note" style="border-color: #ef4444; background: rgba(239, 68, 68, 0.1);">
                <?php echo $guide['step3_warn']; ?>
            </div>
        </section>

        <section class="card">
            <h2><span class="step-number">4</span> <?php echo $guide['step4_title']; ?></h2>
            <p><?php echo $guide['step4_desc']; ?></p>
            <code>:root {<br>&nbsp;&nbsp;--gdpr-primary: #yourcolor;<br>&nbsp;&nbsp;--gdpr-font: 'Your Font', sans-serif;<br>}</code>
        </section>

        <section class="card">
            <h2><span class="step-number">5</span> <?php echo $guide['step5_title']; ?></h2>
            <p><?php echo $guide['step5_desc']; ?></p>
            <code>&lt;script type="text/plain" data-category="marketing"&gt;<br>&nbsp;&nbsp;// Your tracking code here (Pixel, etc.)<br>&lt;/script&gt;</code>
        </section>

        <section class="card">
            <h2><span class="step-number">6</span> <?php echo $guide['step6_title']; ?></h2>
            <p><?php echo $guide['step6_desc']; ?></p>
            <div class="note" style="border-color: #10b981; background: rgba(16, 185, 129, 0.1);">
                Directory: <code>gdpr/logs/</code> <br>
                Chmod: <code>755</code> (o <code>775</code>)
            </div>
        </section>

        <footer>
            <?php echo $gdpr_brand_name; ?> - Installation Manual
        </footer>
    </div>
</body>

</html>