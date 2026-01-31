# 📝 Changelog

All notable changes to the **Pixel Wall** project will be documented in this file.

## [v2.3.2] - 2026-01-31
### Security & Deployment
- **Production Ready**: Full security audit completed. Admin panel secured against CSRF and Session Hijacking. 
- **Payment Verification**: Verified and patched `env_loader.php` to correctly load Admin-configured Stripe keys (`private/stripe_config.php`) in production.
- **CSRF Protection**: Implemented robust anti-CSRF token system for all Admin Panel actions (Reset, Save Settings, Branding).
- **Session Hardening**: Enforced `HttpOnly`, `Secure`, and `Strict` (SameSite) flags on all admin session cookies.
- **Git Hygiene**: Updated `.gitignore` to strictly exclude `*.lock` files and ensuring `private/stripe_config.php` never leaks.

## [v2.3.1] - 2026-01-31
### Security
- **Data Integrity**: Implemented "Atomic Rename" strategy for JSON Database writes. Writes are now performed on a temporary file and atomically renamed, protected by an exclusive lock file to prevent data corruption during server crashes.
- **API Hardening**: Restricted CORS headers in `wall-api.php`. The API now strictly accepts requests only from the production domain and localhost, blocking unauthorized cross-origin access.

## [v2.3.0] - 2026-01-14
### Added
- **Dynamic Privacy**: Upgraded to **Madness GDPR v1.2.1** with support for dynamic privacy policy linking and localized dashboard.
- **File Organization**: Moved public pages (`winners.php`, `wordcloud.html`, `privacy.php`) to a dedicated `pages/` directory for better structure.

### Changed
- **Asset Versioning**: Standardized all frontend and admin assets to **v2.3.0** for cache busting.
- **GDPR Configuration**: Migrated GDPR config to use `$gdpr_privacy_url` pointing to `pages/privacy.php`.
- **Admin Dashboard**: Synchronized and versioned admin scripts (`admin-lang.js`, `admin-logic.js`).

### Fixed
- **Cleanup**: Removed obsolete GDPR CSS/JS links from `index.php`.
- **Integrity**: Fixed relative paths in relocated pages to ensure standalone independence.
- **Documentation**: Updated `TECH_SPECS.md` and `CONFIGURATION.md` with new project structure and version tracks.


## [v2.2.0] - 2026-01-14
### Added
- **Secure Stripe Configuration**: Added a dedicated, secure Admin UI to manage Stripe API keys. Keys are now stored in `private/stripe_config.php` and masked in the frontend.
- **Admin UI Improvements**: Added "Modal Opacity" control in the Branding section.
- **Backend**: Implemented `get_stripe_config` and `save_stripe_config` API actions.

### Changed
- **Admin Dashboard**: Refactored the Settings tab to split General Settings and Payment Configuration.
- **Frontend**: Removed "Beta Testing" banners from all pages and modals.
- **Styling**: Cleaned up obsolete CSS related to beta banners.
- **Core**: `wall-api.php` now prioritizes secure configuration files over environment variables for Stripe init.

### Fixed
- **Backup System**: Fixed "Scarica Backup" HTTP 500 error in Admin (ZipArchive dependency).
- **Admin Layout**: Fixed alignment issues in the Admin Sidebar footer.
- **Welcome Modal**: Fixed a broken HTML structure issue in the welcome popup.

 
## [v2.1.0] - 2026-01-14
### Admin Dashboard Improvements
- **UI Unification:** Standardized layout, headers, and spacing across all admin sections (News, Contributions, Winners, Overview).
- **Adaptive Layout:** Implemented flexbox-based architecture for admin tabs to ensure iframes dynamically fill vertical space without double scrollbars.
- **CSP Strengthening:** Hardened security by removing inline Javascript handlers in favor of nonce-authorized event listeners.
- **Robust Interactions:** Improved Delete Confirmation logic to be CSP-compliant and reliable.
- **Header Alignment:** Fixed specific misalignment in "Contributions" header using absolute positioning.
- **View Wall:** Added a direct link to the frontend in the admin sidebar.

## [v2.0.0] - 2026-01-14
### Major Refactoring & Localization
- **Unified Dashboard:** Centralized admin interface to configure core parameters (Grid Size, Pricing, Maintenance, Preset Rules) without touching code.
- **Unified Authentication:** Standardized `admin_logged_in` session across all admin sub-pages, removing redundant login forms.
- **Branding Customization:** New "Branding" section in the dashboard to personalize project-specific texts (Wall Title, Welcome Modal, Support Email, etc.) via `data/custom_branding.json`.
- **Instant Language Switching:** Full English/Italian localization for the dashboard with instant UI updates (including statistical and dynamic sections) without page reloads.
- **Iframe Navigation:** Improved inter-iframe communication allowing navigation links inside iframes to update the parent dashboard's active tab via URL hash.
- **Full Localization Audit:** Systematically replaced hardcoded text with `data-i18n` attributes across all project PHP files (frontend and backend).
- **UI Refinement:** Cleaner table headers (removed date format hints) and enhanced tool descriptions in the admin panel.

## [v1.5.2] - 2026-01-14
### Added
- **Standalone Independence:** Decoupled Pixel Wall from the root directory by including local asset fallbacks (logo, renders).
- **GDPR Language Sync:** Integrated GDPR banner language with the site-wide `mr_lang` key.
- **Standalone Privacy:** Created a dedicated `privacy.php` and optimized it for CSP compliance and scrolling.

### Fixed
- **Navigation:** Fixed broken logo links and renders when project is served on a standalone port (e.g. 8080).
- **Analytics:** Added dynamic tracker path detection.

## [v1.5.1] - 2026-01-14
### Added
- **Dynamic Stripe Loader:** Stripe JS library is now loaded on-demand during the checkout flow, ensuring zero pre-consent footprint.
- **Lazy WordCloud:** Implemented `data-src` for the WordCloud iframe to defer resource loading until explicit user interaction.

### Changed
- **Prior Consent Hardening:** Removed all static script tags for Stripe to resolve Cookiebot compliance warnings.
- **Submodule Update:** Synchronized with **Madness GDPR v1.2.1** for improved transparency (policy links in banner).
- **Optimization:** Minimized `localStorage` writes for theme and session flags.

## [v1.5.0] - 2026-01-13
### Added
- **GDPR Submodule:** Integrated the **Madness GDPR Consent System** as a Git Submodule.
- **GDPR Submodule:** Integrated the **Madness GDPR Consent System** as a Git Submodule.
- **Smart Detection:** Added logic to detect and utilize a root-level GDPR installation if available.
- **Nonce Support:** Full Content Security Policy (CSP) nonce support for GDPR banner scripts.

### Changed
- **Migration:** Completely removed Iubenda integration.
- **Privacy:** `tracker.php` and Stripe scripts are now "Consent-Aware" (respecting analytics/marketing choices).
- **Docs:** Restructured documentation into `README.md`, `CHANGELOG.md`, and `CONFIGURATION.md`.

## [v1.4.2] - 2026-01-12
### Added
- **Reaction System:** Interactive "Like" (👍) and "Heart" (❤️) buttons on news posts.
- **Atomic Updates:** High-performance backend handling for mutually exclusive reactions.

### Changed
- **Infrastructure:** Extended reaction API to share logic with the main website.
- **UI:** Refined button sizing for a cleaner look.

## [v1.4.1] - 2026-01-08
### Fixed
- **YouTube:** Resolved CSP conflicts and layout shifts for embedded videos.
- **Admin:** Restored image upload/delete functionality compliant with strict CSP.

## [v1.3.3] - 2026-01-05
### Added
- **CSV Export:** New functionality to export contributions data from the dashboard.

## [v1.3.2] - 2026-01-03
### Added
- **News Editing:** Full edit capabilities for news posts.
- **Image Management:** Manual removal/replacement of post images.

## [v1.3.1] - 2026-01-02
### Added
- **HUD:** Separated stats footer into a synchronized DOM HUD.
- **Dashboard:** New `admin_contributions.php` for tracking revenue and logs.
- **Security:** Centralized admin password via `.env`.

## [v1.3.0] - 2025-12-28
### Security
- **Anti-Fraud:** strict pixel count verification against Stripe metadata.
- **Anti-Replay:** Prevention of transaction replay attacks.
- **CSRF:** Added protection to admin panels.

### Added
- **Multimedia:** YouTube support (responsive) and Lightbox gallery.

## [v1.2.1]
### Changed
- **UX:** Optimized mobile landscape scaling.
- **Performance:** Reduced Welcome modal latency.

## [v1.2.0]
### Added
- **Mobile:** Implemented Pinch-to-Zoom gesture support.

## [v1.1.9]
### Fixed
- **Critical:** Fixed ReferenceError in payment flow.

## [v1.1.8]
### Fixed
- **Mobile:** Resolved sticky hover states on Focus Mode button.

## [v1.1.7]
### Changed
- **UX:** Auto-deactivate Focus mode on canvas interaction.

## [v1.1.6]
### Added
- **Inspection:** Winner popup with specific 🪙/🪙 icons.
- **Backend:** Join logic for supporter names.

## [v1.0.11]
### Security
- **Env:** Migrated secrets to `.env` file.

## [v1.0.6 - v1.0.10]
### Added
- **Features:** Constellation effect, Eraser tool, Madness Log.
