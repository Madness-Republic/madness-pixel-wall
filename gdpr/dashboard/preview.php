<?php
include_once __DIR__ . '/../config.php';

// Discover Languages for labels
$available_langs = [];
foreach (glob(__DIR__ . '/../languages/*.json') as $file) {
    $code = basename($file, '.json');
    $lang_data = json_decode(file_get_contents($file), true);
    $l_name = $lang_data['language_name'] ?? strtoupper($code);
    $available_langs[$code] = $l_name;
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anteprima Generica - Madness GDPR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <?php $v = trim(@file_get_contents(__DIR__ . '/../VERSION') ?: '1.2.0'); ?>
    <link rel="stylesheet" href="../assets/css/policy_style.css?v=<?php echo $v; ?>">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .demo-notice {
            background: #1e293b;
            color: #f59e0b;
            padding: 10px 20px;
            border-radius: 99px;
            font-size: 0.8rem;
            margin-bottom: 30px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .policy-container {
            background: white;
            max-width: 800px;
            width: 100%;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .policy-lang-tabs {
            justify-content: center;
            margin-bottom: 30px;
        }
    </style>
</head>

<body>
    <div class="demo-notice">🧪 ANTEPRIMA GENERICA (SENZA STILI SITO HOST)</div>

    <div class="policy-container">
        <div class="policy-lang-tabs">
            <?php
            $enabled_langs = (isset($gdpr_enabled_languages) && !empty($gdpr_enabled_languages)) ? $gdpr_enabled_languages : ['it', 'en'];

            // Map for flags
            $flags = ['it' => '🇮🇹', 'en' => '🇬🇧', 'es' => '🇪🇸', 'fr' => '🇫🇷', 'de' => '🇩🇪'];

            foreach ($enabled_langs as $index => $lang):
                $flag = isset($flags[$lang]) ? $flags[$lang] : '🌐';
                $l_name = $flag . ' ' . (isset($available_langs[$lang]) ? $available_langs[$lang] : strtoupper($lang));
                ?>
                <button class="policy-lang-btn <?php echo $index === 0 ? 'active' : ''; ?>"
                    data-lang-btn="<?php echo $lang; ?>" onclick="switchPolicy('<?php echo $lang; ?>', this)">
                    <?php echo $l_name; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div id="policy-contents-wrapper">
            <?php
            foreach ($enabled_langs as $lang) {
                // Set lang for policy engine to correctly identify content inside policy.php
                $_GET['lang'] = $lang;
                echo "<div class='policy-item' id='policy-$lang' style='display: none;'>";
                include '../policy.php';
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <script>
        function switchPolicy(lang, btn) {
            // Hide all policy items
            document.querySelectorAll('.policy-item').forEach(p => p.style.display = 'none');

            // Show selected policy
            const selected = document.getElementById('policy-' + lang);
            if (selected) {
                selected.style.display = 'block';
            } else {
                console.error("Policy content not found for lang: " + lang);
            }

            // Update button states
            document.querySelectorAll('.policy-lang-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }

        // Initialize with the first available language
        document.addEventListener('DOMContentLoaded', () => {
            const firstBtn = document.querySelector('.policy-lang-btn');
            if (firstBtn) {
                const firstLang = firstBtn.getAttribute('data-lang-btn');
                switchPolicy(firstLang, firstBtn);
            }
        });
    </script>
</body>

</html>