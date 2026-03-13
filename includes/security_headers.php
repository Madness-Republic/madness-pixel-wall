<?php
// security_headers.php

// 1. Generate a secure random Nonce for this request
// This will be used to allow specific inline scripts while blocking others
$nonce = base64_encode(random_bytes(16));

// 2. Set Standard Security Headers
// HSTS: Enforce HTTPS for 1 year (including subdomains)
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Anti-Clickjacking: We'll use CSP frame-ancestors instead for better compatibility with GTM Preview
// header("X-Frame-Options: SAMEORIGIN");

// MIME Sniffing protection
header("X-Content-Type-Options: nosniff");

// Referrer Policy: Don't leak full URL to other sites, only origin
header("Referrer-Policy: strict-origin-when-cross-origin");

// 3. Define Content Security Policy (CSP)
$csp = [
    // Default: Block everything unless allowed
    "default-src 'self'",

    // Scripts: Allow self, secure nonce, Stripe, CDNJS, YouTube, and Google GTM/Ads/Analytics
    "script-src 'self' 'nonce-$nonce' 'unsafe-inline' https://js.stripe.com https://cdnjs.cloudflare.com https://www.youtube.com https://s.ytimg.com https://*.googletagmanager.com https://*.google-analytics.com https://tagmanager.google.com https://*.googlesyndication.com https://*.googleadservices.com",

    // Styles: Allow self, inline styles, Google Fonts, and CDNJS
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://tagmanager.google.com https://fonts.gstatic.com",

    // Images: Allow self, data URIs, Stripe, Madness site, YouTube, and Google tracking pixels
    "img-src 'self' data: https://*.stripe.com https://www.madnessrepublic.com https://i.ytimg.com https://*.ytimg.com https://*.google.com https://*.google.it https://*.google-analytics.com https://*.googletagmanager.com https://*.gstatic.com https://*.googlesyndication.com https://*.googleadservices.com",

    // Fonts: Allow self, Google Fonts, and CDNJS
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",

    // Connections (AJAX): Allow self, Stripe, YouTube, and Google Analytics/Ads/GTM
    "connect-src 'self' https://api.stripe.com https://*.youtube.com https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://*.g.doubleclick.net https://*.google.com https://*.googlesyndication.com https://*.googleadservices.com",

    // Frames (iframes): Allow self, Stripe, YouTube, and Tag Assistant Preview
    "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://vars.hotjar.com https://www.youtube.com https://youtube.com https://www.youtube-nocookie.com https://*.youtube.com https://*.youtube-nocookie.com https://*.googletagmanager.com https://tagmanager.google.com",

    // Frame Ancestors: Allow GTM to frame the site for Preview Mode
    "frame-ancestors 'self' https://*.googletagmanager.com https://tagmanager.google.com"
];

// Combine and set the CSP header
header("Content-Security-Policy: " . implode("; ", $csp));
?>