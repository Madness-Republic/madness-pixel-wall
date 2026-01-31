<?php
session_start();
include_once __DIR__ . '/../config.php';
// Security check: only logged in admins can see this
if (!isset($_SESSION['gdpr_admin_logged_in']) || $_SESSION['gdpr_admin_logged_in'] !== true) {
    header('Location: ../dashboard/index.php');
    exit;
}

$ui_lang = $_GET['lang'] ?? 'it';
$lang_file = __DIR__ . "/../languages/$ui_lang.json";
if (!file_exists($lang_file))
    $ui_lang = 'it';
$t = json_decode(file_get_contents(__DIR__ . "/../languages/$ui_lang.json"), true);
$tech = $t['tech'];
?>
<!DOCTYPE html>
<html lang="<?php echo $ui_lang; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $tech['title']; ?> - Madness GDPR
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Oswald:wght@500;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --accent: #f59e0b;
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --success: #10b981;
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

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
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
        }

        h3 {
            color: var(--accent);
            margin-top: 30px;
        }

        p {
            margin-bottom: 15px;
        }

        .tech-box {
            background: rgba(0, 0, 0, 0.2);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--accent);
            margin: 20px 0;
        }

        ul {
            padding-left: 20px;
        }

        li {
            margin-bottom: 15px;
        }

        b {
            color: var(--accent);
        }

        code {
            background: #000;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            color: #d1d5db;
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
            <?php echo $tech['back']; ?></a>

        <header>
            <h1>
                <?php echo $tech['title']; ?>
            </h1>
            <p style="color: var(--text-dim);">
                <?php echo $tech['intro']; ?>
            </p>
        </header>

        <section class="card">
            <h2>
                <?php echo $tech['gdpr_title']; ?>
            </h2>
            <p>
                <?php echo $tech['gdpr_desc']; ?>
            </p>
            <ul>
                <li>
                    <?php echo $tech['gdpr_point1']; ?>
                </li>
                <li>
                    <?php echo $tech['gdpr_point2']; ?>
                </li>
                <li>
                    <?php echo $tech['gdpr_point3']; ?>
                </li>
                <li>
                    <?php echo $tech['gdpr_point4']; ?>
                </li>
            </ul>
        </section>

        <section class="card">
            <h2>
                <?php echo $tech['gcm_title']; ?>
            </h2>
            <p>
                <?php echo $tech['gcm_desc']; ?>
            </p>
            <div class="tech-box">
                <?php echo $tech['gcm_adv']; ?>
            </div>
            <p style="font-size: 0.9rem; font-style: italic;">
                Questo permette la conformità con il Digital Markets Act (DMA) e assicura che Google riceva i segnali di
                autorizzazione corretti per le finalità <code>ad_storage</code>, <code>analytics_storage</code>,
                <code>ad_user_data</code> e <code>ad_personalization</code>.
            </p>
        </section>

        <section class="card">
            <h2>
                <?php echo $tech['implementation_title']; ?>
            </h2>
            <ul>
                <li>
                    <?php echo $tech['impl_storage']; ?>
                </li>
                <li>
                    <?php echo $tech['impl_events']; ?>
                </li>
                <li>
                    <?php echo $tech['impl_blocking']; ?>
                </li>
            </ul>
        </section>

        <section class="card">
            <h2>
                <?php echo $tech['proof_title'] ?? '4. Proof of Consent'; ?>
            </h2>
            <p>
                <?php echo $tech['proof_desc'] ?? ''; ?>
            </p>
        </section>

        <section class="card">
            <h2>
                <?php echo $tech['generic_title'] ?? '5. Generic Script Blocking'; ?>
            </h2>
            <p>
                <?php echo $tech['generic_desc'] ?? ''; ?>
            </p>
        </section>

        <footer>
            <?php echo $gdpr_brand_name; ?> - Technical Specs
        </footer>
    </div>

</body>

</html>