<?php
require_once '../includes/security_headers.php';
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy & Cookie Policy - Pixel Wall</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Oswald:wght@400;500;700&family=Source+Sans+Pro:wght@400;600&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/pixel-wall.css?v=2.3.0">
    <link rel="stylesheet" href="../gdpr/policy_style.css">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">

    <script src="../assets/js/translations.js?v=2.3.0"></script>
    <style>
        .policy-container {
            padding: 120px 20px 60px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .pixel-header {
            background: rgba(10, 10, 10, 0.9);
            backdrop-filter: blur(10px);
        }

        /* Override pixel-wall.css overflow:hidden */
        body.pixel-page-scrollable {
            overflow: auto !important;
            height: auto !important;
        }
    </style>
</head>

<body class="pixel-page pixel-page-scrollable">
    <div class="site-bg"></div>

    <header class="pixel-header">
        <a href="../index.php" class="logo">
            <img src="../assets/images/logo.png" alt="Logo" class="logo-img">
        </a>
        <div class="header-links">
            <a href="../index.php" style="color: white; text-decoration: none; font-weight: 500;" data-i18n="pw-back">←
                Torna al Wall</a>
        </div>
    </header>

    <?php include_once '../gdpr/config.php'; ?>

    <main>
        <div class="container policy-container">
            <div class="policy-lang-tabs">
                <?php
                $count = 0;
                foreach ($gdpr_enabled_languages as $lang):
                    $name = ($lang === 'it') ? 'Italiano' : (($lang === 'en') ? 'English' : (($lang === 'es') ? 'Español' : strtoupper($lang)));
                    ?>
                    <button class="policy-lang-btn <?php echo $count === 0 ? 'active' : ''; ?>"
                        data-target-lang="<?php echo $lang; ?>">
                        <?php echo $name; ?>
                    </button>
                    <?php
                    $count++;
                endforeach; ?>
            </div>

            <div id="policy-wrapper">
                <?php
                foreach ($gdpr_enabled_languages as $lang):
                    echo "<div class='policy-item' id='policy-$lang' style='display: " . ($lang === $gdpr_enabled_languages[0] ? 'block' : 'none') . "'>";
                    $_GET['lang'] = $lang;
                    include '../gdpr/policy.php';
                    echo "</div>";
                endforeach;
                ?>
            </div>
        </div>
    </main>

    <script nonce="<?php echo $nonce; ?>">
        function switchPolicy(lang, btn) {
            document.querySelectorAll('.policy-item').forEach(p => p.style.display = 'none');
            const target = document.getElementById('policy-' + lang);
            if (target) target.style.display = 'block';

            document.querySelectorAll('.policy-lang-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Attach event listeners to buttons
            document.querySelectorAll('.policy-lang-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const lang = this.getAttribute('data-target-lang');
                    switchPolicy(lang, this);
                });
            });

            const stored = localStorage.getItem('mr_lang');
            const browserLang = (navigator.language || '').split('-')[0];
            const enabled = <?php echo json_encode($gdpr_enabled_languages); ?>;

            let target = '<?php echo $gdpr_default_lang; ?>';
            if (stored && enabled.includes(stored)) target = stored;
            else if (enabled.includes(browserLang)) target = browserLang;

            const targetBtn = document.querySelector(`[data-target-lang="${target}"]`);
            if (targetBtn) targetBtn.click();
        });
    </script>

    <?php
    if (file_exists('../gdpr/banner.php')) {
        include_once '../gdpr/banner.php';
    }
    ?>
</body>

</html>