# Madness Crowdfunding Pixel Wall 🎨

An interactive, high-performance web application designed for creative crowdfunding. Users can support a real-world sports facility project by purchasing digital pixels, which will ultimately be printed on a massive 10x4 meter physical board.

## 🚀 Overview

The **Pixel Wall** turns digital support into a permanent monument. It features a custom-built 2D Canvas engine that allows users to draw pixel art or upload images directly onto a shared grid. Every contribution fuels the redevelopment of the Madness Republic sports complex in Iglesias, Sardinia.

## ✨ Key Features

-   **Interactive Canvas Engine:** High-performance rendering using HTML5 Canvas. It employs an **Offscreen Cache Canvas** technique to pre-render thousands of confirmed pixels, ensuring smooth 60FPS interaction (panning and zooming) even as the wall grows.
-   **Image-to-Pixel Conversion:** Integrated tool to upload logos, photos, or memes, automatically converting them into pixel art based on user-defined dimensions while maintaining aspect ratios.
-   **Atomic Data Integrity:** Backend uses PHP's `flock()` for exclusive file locking during JSON updates. This ensures data consistency and prevents race conditions when multiple users contribute simultaneously.
-   **Session Persistence:** Drafts are automatically saved to `LocalStorage`. Users can leave the page and return later to find their artwork exactly where they left it.
-   **High-DPI & Pixel-Perfection:** Support for Retina displays via dynamic scaling (`devicePixelRatio`) and disabled image smoothing to maintain crisp, 1:1 pixel art quality.
-   **Hybrid Pricing Model:** Accurate server-side cost calculation based on both occupied area (Land Tax) and total pixels drawn (Ink Fee).
-   **Treasure Hunt:** Gamified experience with hidden **Golden** and **Silver** pixels that reward users with prizes and shares of the final pool.
-   **Security First:** Full Stripe API integration with server-side transaction verification and protection against pricing manipulation via re-calculation during the final write.
-   **Multilingual Support:** Fully localized in Italian (IT) and English (EN).
-   **Responsive HUD:** Real-time community statistics and progress tracking optimized for mobile and desktop.

## 🛠️ Tech Stack

-   **Frontend:** HTML5, CSS3, Vanilla JavaScript.
-   **Backend:** PHP (API and server-side verification).
-   **Payments:** Stripe API (Payment Intents).
-   **Storage:** Secure flat-file JSON database with atomic write protection.

## 🔒 Security Requirements

This repository is configured to exclude sensitive financial data and private keys. 
**Never commit the following:**
-   `private/` directory (Stripe Secret Keys, Secret Pixel Locations).
-   `data/` directory (Customer transaction IDs and emails).
-   `vendor/` directory (PHP dependencies).

## 🌍 Impact

Every pixel drawn today helps build a real brick tomorrow. The final artwork will be permanently installed at the entrance of the Piazza Bruno Buozzi sports facility, creating a historical tribute to the community that made it possible.

---
*Created with ❤️ by Madness Republic.*
