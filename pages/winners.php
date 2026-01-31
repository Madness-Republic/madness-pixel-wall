<?php
// winners.php
// Public list of Golden Pixel winners with privacy masking

$winnersFile = __DIR__ . '/../data/winners.json';
$winners = [];
if (file_exists($winnersFile)) {
    $winners = json_decode(file_get_contents($winnersFile), true) ?: [];
}

$winnersSilverFile = __DIR__ . '/../data/winners_silver.json';
$winnersSilver = [];
if (file_exists($winnersSilverFile)) {
    $winnersSilver = json_decode(file_get_contents($winnersSilverFile), true) ?: [];
}

// Function to mask email (e.g., "mario.rossi@gmail.com" -> "ma***@gmail.com")
function maskEmail($email)
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return '***';

    $parts = explode('@', $email);
    $username = $parts[0];
    $domain = $parts[1];

    $len = strlen($username);
    if ($len <= 3) {
        $maskedUser = substr($username, 0, 1) . str_repeat('*', $len - 1);
    } else {
        $visible = floor($len / 3); // Show roughly 1/3
        $maskedUser = substr($username, 0, $visible) . str_repeat('*', $len - $visible);
    }

    return $maskedUser . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall of Gold - Madness Republic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Oswald:wght@500;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0d1117;
            color: #fff;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            overflow-y: scroll;
            /* Allow scrolling */
            scrollbar-width: none;
            /* Firefox */
            -ms-overflow-style: none;
            /* IE 10+ */
        }

        body::-webkit-scrollbar {
            width: 0;
            height: 0;
            background: transparent;
            /* Chrome/Safari/Webkit */
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        h1 {
            font-family: 'Oswald', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            background: linear-gradient(135deg, #FFD700, #ffaa00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: #8b949e;
            font-size: 1rem;
        }

        .stat-card {
            background: rgba(255, 215, 0, 0.1);
            border: 1px dashed #FFD700;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .stat-number {
            font-family: 'Oswald', sans-serif;
            font-size: 3rem;
            color: #FFD700;
            line-height: 1;
        }

        .stat-label {
            color: #e3b341;
            text-transform: uppercase;
            font-size: 0.8rem;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        .winners-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .winner-item {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s;
        }

        .winner-item:hover {
            transform: translateY(-2px);
            border-color: #FFD700;
        }

        .winner-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .winner-icon {
            font-size: 1.5rem;
        }

        .winner-details h3 {
            margin: 0;
            font-size: 1rem;
            color: #ffffff;
        }

        .winner-details span {
            font-size: 0.8rem;
            color: #8b949e;
        }

        .pixel-coord {
            font-family: monospace;
            background: #21262d;
            padding: 4px 8px;
            border-radius: 4px;
            color: #FFD700;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #8b949e;
            font-style: italic;
        }

        .back-btn {
            display: inline-block;
            margin-top: 30px;
            color: #58a6ff;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-btn:hover {
            text-decoration: underline;
        }

        /* Silver Theme */
        .theme-silver .stat-card {
            background: rgba(192, 192, 192, 0.1);
            border-color: #C0C0C0;
        }

        .theme-silver .stat-number {
            color: #C0C0C0;
        }

        .theme-silver .stat-label {
            color: #a9a9a9;
        }

        .theme-silver .winner-item:hover {
            border-color: #C0C0C0;
        }

        .theme-silver .pixel-coord {
            color: #C0C0C0;
        }

        .theme-silver h2 {
            font-family: 'Oswald', sans-serif;
            font-size: 2rem;
            margin-bottom: 20px;
            text-transform: uppercase;
            text-align: center;
            background: linear-gradient(135deg, #C0C0C0, #808080);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-top: 50px;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <h1 data-i18n="hall-title"></h1>
            <div class="subtitle" data-i18n="hall-subtitle"></div>
        </div>

        <div class="stat-card">
            <div class="stat-number">
                <?php echo count($winners); ?>/10
            </div>
            <div class="stat-label" data-i18n="hall-found"></div>
        </div>

        <?php if (empty($winners)): ?>
            <div class="empty-state">
                <p data-i18n="hall-empty"></p>
            </div>
        <?php else: ?>
            <ul class="winners-list">
                <?php foreach (array_reverse($winners) as $w): ?>
                    <li class="winner-item">
                        <div class="winner-info">
                            <div class="winner-icon">🏆</div>
                            <div class="winner-details">
                                <h3>
                                    <?php echo htmlspecialchars(maskEmail($w['email'])); ?>
                                </h3>
                                <span><span data-i18n="hall-date">Trovato il</span>
                                    <?php echo date('d/m/Y', strtotime($w['date'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="pixel-coord">
                            <?php echo htmlspecialchars($w['pixel']); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- SILVER SECTION -->
        <div class="theme-silver" id="silver">
            <h2 data-i18n="hall-silver-title"></h2>
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo count($winnersSilver); ?>/50
                </div>
                <div class="stat-label" data-i18n="hall-silver-found"></div>
            </div>

            <?php if (empty($winnersSilver)): ?>
                <div class="empty-state">
                    <p data-i18n="hall-silver-empty"></p>
                </div>
            <?php else: ?>
                <ul class="winners-list">
                    <?php foreach (array_reverse($winnersSilver) as $w): ?>
                        <li class="winner-item">
                            <div class="winner-info">
                                <div class="winner-icon" style="filter: grayscale(100%) contrast(1.2) brightness(1.3);">🪙</div>
                                <div class="winner-details">
                                    <h3>
                                        <?php echo htmlspecialchars(maskEmail($w['email'])); ?>
                                    </h3>
                                    <span><span data-i18n="hall-date">Trovato il</span>
                                        <?php echo date('d/m/Y', strtotime($w['date'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="pixel-coord">
                                <?php echo htmlspecialchars($w['pixel']); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>


        <div style="text-align: center; margin-top: 40px;">
            <a href="../index.php" class="back-btn" data-i18n="pw-back">← Home</a>
        </div>

    </div>

    <script src="../assets/js/translations.js?v=2.3.0"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Read lang from parent or localstorage
            let lang = localStorage.getItem('mr_lang');
            if (!lang) {
                // Try to infer from browser
                lang = navigator.language.split('-')[0];
                lang = (lang === 'it') ? 'it' : 'en';
            }

            // Apply translations
            if (window.translations && window.translations[lang]) {
                document.querySelectorAll('[data-i18n]').forEach(el => {
                    const key = el.getAttribute('data-i18n');
                    if (window.translations[lang][key]) {
                        el.innerHTML = window.translations[lang][key];
                    }
                });
            }
        });
    </script>
</body>

</html>