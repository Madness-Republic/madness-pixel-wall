# Madness Crowdfunding Pixel Wall 🎨
![Version](https://img.shields.io/badge/version-v2.3.2-blue)
![Last Updated](https://img.shields.io/badge/last%20updated-2026--01--31-green)

An interactive, high-performance web application designed for creative crowdfunding. Users can support projects of any kind by purchasing digital pixels, creating a collaborative visual experience that can be showcased online or donated to physical art installations.

🔗 **Live Project:** [https://www.madnessrepublic.com/pixelwall/](https://www.madnessrepublic.com/pixelwall/)

## ✨ Key Features (User Experience)

-   **Interactive Drawing:** A smooth, intuitive 2D canvas that allows users to paint pixel-by-pixel or erase drafts with full undo/redo support.
-   **Mobile Pinch-to-Zoom (v1.2.0):** Native multi-touch gesture support in "Naviga" (Pan) mode, enabling seamless magnification using two-finger pinch—perfect for mobile precision.
-   **Image-to-Pixel Conversion (Serverless & Secure):** Upload logos, photos, or memes. The engine automatically converts them into pixel art locally. **Original files are never uploaded or stored on the server**, ensuring absolute immunity to malicious file attacks and protecting user privacy.
-   **Treasure Hunt:** Gamified experience with hidden **Golden (10x)** and **Silver (50x)** pixels that reward lucky supporters with prizes.
-   **Winner Inspection:** Gain recognition for your finds. Clicking on any pulsing star displays a floating label with the supporter's name and the corresponding 🪙/🪙 icon.
-   **Net Area Tax Model:** A fair pricing system that deducts already occupied space from the total area, ensuring you only pay for the net empty space your artwork encloses.
-   **Highlight Focus Mode:** Toggle "Star Focus" to dim the entire board (0.85 opacity), making special pixel highlights pop—perfect for finding your mark on bright backgrounds.
-   **Madness Log (v1.4.0):** Stay updated with the project's evolution through a multimedia news feed. Supports **YouTube video embeds** (responsive, respects original dimensions, and left-aligned), high-res **image galleries** with a dedicated **Lightbox**, and rich text updates. Integrated with the **Madness GDPR system** for compliant cookie handling.
-   **Reaction System (New v1.4.2):** High-engagement interactive buttons for "Likes" (👍) and "Hearts" (❤️) on news posts. Features mutual exclusivity (switching between types) and atomic backend updates for performance and data integrity. Choice persistence via `localStorage`.
-   **Wall of Fame Word Cloud:** Every contributor earns a place of honor. The system dynamically generates a word cloud based on the donation amount for a real 3x1m banner.
-   **Multilingual Interface (v1.1.7):** Full localization in IT, EN, ES with intelligent cache management.
-   **Secure Payments & Anti-Fraud (v1.3.0):** Transactional integrity guaranteed by server-side verification. Prevents "Pay-Less-Paint-More" and Replay Attacks.

-   **Unified Dashboard (v2.3.0):** Comprehensive admin panel to manage grid settings, branding, pricing models, gamification rules, and news content. Fully localized (IT/EN/ES) with instant language switching and versioned assets.
-   **Branding System (v2.3.0):** Effortlessly customize your project's identity (titles, intro texts, support emails, and privacy policy URL) through the dashboard without editing raw files.
-   **GDPR Submodule Integration (v1.5.1):** The system now includes the *Madness GDPR Consent System* as a Git Submodule (v1.2.1). Features **Basic Consent Mode Hardening** with zero-tracking on landing and deferred loading of all non-essential assets.
-   **Dynamic Asset Loading:** Stripe JS and WordCloud iframes are now loaded on-demand, ensuring 100% compliance with "Prior Consent" regulations while keeping features fully functional.

## 🏗️ Architectural Highlights

-   **Architectural Hardening (v1.3.0):**
    -   **Replay Attack Prevention:** Atomic recording of the transaction ID *before* the wall is updated.
    -   **Strict Payment Validation:** Cross-check between submitted pixels and Stripe metadata (area and pixel count) to prevent "Pay-Less-Paint-More" fraud.
    -   **Admin CSRF Protection:** Defense against unauthorized form submissions in the news panel.
-   **High-Performance Rendering:** Uses an **Offscreen Cache Canvas** strategy. Thousands of static pixels are pre-rendered to a hidden buffer, allowing the main engine to pan and zoom at 60FPS regardless of the wall's complexity.
-   **Data Integrity (Atomic Rename - v2.3.1):** Replaces standard file writes with a robust `Lock -> Write Tmp -> Rename` strategy. This guarantees that the JSON database is never corrupted, even in the event of a sudden power loss or server crash during a write operation.
-   **Hardened Security (CSP & CORS):** Implements strict **Content Security Policy** and **CORS** restrictions. usage is limited to authorized domains, preventing unauthorized API access and XSS attacks.
-   **High-DPI / Retina Optimization:** Optimized for Retina and 4K displays. Uses dynamic scaling based on `devicePixelRatio` combined with disabled image smoothing for crisp, 1:1 pixel art quality.
-   **Privacy-First Metadata:** Strategically separates public display data (pixels, names) from sensitive PII (emails, transaction IDs). Sensible data is stored in protected server-side structures accessible only via secure verification.
-   **Server-Side Transaction Verification (v1.3.0):** Secure backend verification of Stripe payment success and metadata integrity before committing any changes.
-   **Non-Destructive UI Layering:** Efficiently manages multiple rendering layers (Static Wall, Session Draft, Floating Previews, and Constellation Effects) to minimize CPU/GPU load while maintaining a responsive interface.

## 🛠️ Tech Stack

-   **Frontend:** HTML5, CSS3, Vanilla JavaScript.
-   **Backend:** PHP (API and atomic write management).
-   **Payments:** Stripe API (Secure Checkout Flow).
-   **Storage:** Secure flat-file JSON storage with transaction verification.

📄 **[View Full Technical Specifications](docs/TECH_SPECS.md)**

## 📚 Documentation

-   **[Configuration Guide](docs/CONFIGURATION.md):** Installation, Security, and GDPR setup.
-   **[Technical Specifications](docs/TECH_SPECS.md):** Deep dive into architecture and data structures.
-   **[Changelog](docs/CHANGELOG.md):** Complete history of updates and releases.

## 🔒 Security Note

This project handles payments and user data. **Never commit** the `private/` folder, `.env` file, or `data/` backup files. See the [Configuration Guide](CONFIGURATION.md) for details.

---
*Created with ❤️ by Madness Republic.*
