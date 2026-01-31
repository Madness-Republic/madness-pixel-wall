<?php
include_once __DIR__ . '/config.php';

// Get Language
$lang = isset($_GET['lang']) ? $_GET['lang'] : $gdpr_default_lang;

// Security check: only allow enabled languages or at least existing ones
if (!file_exists(__DIR__ . "/content/policy_$lang.html")) {
    $lang = $gdpr_default_lang;
}

// Load Template
$template = file_exists(__DIR__ . "/content/policy_$lang.html") ? file_get_contents(__DIR__ . "/content/policy_$lang.html") : "Policy template not found for $lang.";

// Dynamic Replacements
$replacements = [
    '{{company_name}}' => $gdpr_company_name,
    '{{company_address}}' => $gdpr_company_address,
    '{{company_vat}}' => $gdpr_company_vat,
    '{{company_email}}' => $gdpr_company_email,
    '{{last_updated}}' => date('d F Y')
];

// Localize Date based on common patterns
// We could load this from language files too in the future.
if ($lang === 'it') {
    $mesi = ['January' => 'Gennaio', 'February' => 'Febbraio', 'March' => 'Marzo', 'April' => 'Aprile', 'May' => 'Maggio', 'June' => 'Giugno', 'July' => 'Luglio', 'August' => 'Agosto', 'September' => 'Settembre', 'October' => 'Ottobre', 'November' => 'Novembre', 'December' => 'Dicembre'];
    $replacements['{{last_updated}}'] = date('d ') . $mesi[date('F')] . date(' Y');
} elseif ($lang === 'es') {
    $meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
    $replacements['{{last_updated}}'] = date('d ') . 'de ' . $meses[date('F')] . 'de ' . date(' Y');
}

// Apply
$html = str_replace(array_keys($replacements), array_values($replacements), $template);

// If called directly, wrap in minimal HTML or just echo
if (basename($_SERVER['PHP_SELF']) === 'policy.php') {
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $lang; ?>">

    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="assets/css/policy_style.css?v=<?php echo $gdpr_version ?? '1.2.1'; ?>">
        <title>Privacy Policy</title>
    </head>

    <body style="background:#f8fafc; padding:40px;">
        <div
            style="background:white; max-width:800px; margin:0 auto; padding:40px; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.05);">
            <?php echo $html; ?>
        </div>
    </body>

    </html>
    <?php
} else {
    // Included by another file
    echo $html;
}
?>