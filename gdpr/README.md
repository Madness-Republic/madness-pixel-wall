# Madness GDPR Consent System
![Version](https://img.shields.io/badge/version-1.2.1-orange.svg)

Lightweight, dependency-free, and modular GDPR Cookie Consent system with multi-language support, Admin Panel, and Proof of Consent.

## Features
- **Strict Compliance (Basic Mode)**: Blocks Google Analytics and third-party scripts totally until explicit consent is given (satisfies Cookiebot/Iubenda scanners).
- **Proof of Consent Logging**: Server-side CSV logging of consent actions (Timestamp, Masked IP, Consent ID) for legal compliance.
- **Equal Prominence UI**: Compliant design with identical "Accept" and "Reject" buttons to avoid "dark patterns".
- **Google Consent Mode v2**: Full support for both Basic and Advanced GCM v2 signals.
- **Generic Script Blocking**: Easily block any 3rd party script (Pixel, LinkedIn, etc.) using `type="text/plain"`.
- **Dynamic Language Support**: Multi-language support out of the box (IT, EN, ES, etc.).
- **Admin Panel**: Full control over settings, styles, and policy content without touching code.
- **Universal Integration**: Automatically handles paths to work from root or subdirectories (e.g., `/pixelwall/`). Supports custom Privacy Policy paths via configuration.

## Installation

1. Create a folder named `gdpr` in your website's root directory and upload all the repository files into it.
   *Note: The system is designed to run from within the `/gdpr/` directory.*
2. Ensure `gdpr/logs/` has write permissions (chmod 755/775).
3. Access the Admin Panel at yourdomain.com/gdpr/dashboard/index.php (Default password: `admin`).
    *   *Security Note: Change the password immediately in settings.*
4. Configure your settings (Company info, GA4 ID, Enabled Languages).
5. Include the banner in your main layout file (e.g., `footer.php` or `index.php` before `</body>`):
   ```php
   <?php include_once 'gdpr/banner.php'; ?>
   ```

## Usage

### Third-Party Script Blocking
For scripts that don't support Consent Mode (like Facebook Pixel), modify the tag:
```html
<script type="text/plain" data-category="marketing">
  // Your tracking code
</script>
```

### Admin Panel
The admin panel (`gdpr/dashboard/index.php`) allows you to:
- Manage **Company Data** (automatically injected into policies).
- Toggle **Google Analytics 4** integration.
- Configure **Custom Privacy Policy URL** for dynamic linking.
- Enable/Disable supported languages.
- Customize **Banner Texts** for each language.
- Edit **Privacy & Cookie Policy** templates.
- Adjust **Styles & Colors** with live preview and **Equal Prominence** enforcement.

### Proof of Consent
Consent logs are stored daily in `gdpr/logs/` as CSV files. These logs are protected by an `.htaccess` file and contain anonymized data to prove compliance during audits.

## CSS Customization
The system uses CSS variables. You can override them in your main CSS:
```css
:root {
    --gdpr-primary: #f09100;
    --gdpr-btn-accept-1: #f09100;
    --gdpr-btn-accept-2: #ff4d4d;
}
```

## Update Log

### v1.2.1 (Current) - Transparency & Hardening
- **Dynamic Privacy Linking**: Added support for `{{privacy_url}}` placeholder in translation files and a configurable privacy URL in the Admin.
- **Prior Consent Hardening**: Ensured zero-tracking on landing pages; GCM and dataLayer are initialized only after explicit consent.
- **Rich Text Banner**: Modified `consent_manager.js` to support HTML in banner title and description for policy links.
- **Transparency**: Added direct links to the Privacy Policy within the main banner and the Preferences Modal.
- **Optimization**: Reduced non-essential storage writes to minimize the digital footprint.

### v1.2.0 - Compliance Pack
- **Total Script Blocking**: Switched to "Basic Consent Mode" for GA4. Tracking scripts are now injected only POST-consent.
- **Proof of Consent**: Implemented server-side logging of consent actions with IP anonymization.
- **Equal Prominence**: Updated UI to ensure Accept and Reject buttons have identical visual weight.
- **Path Auto-Detection**: Rewrote `banner.php` to handle universal paths, allowing the banner to work from any subdirectory.
- **Security**: Added `.htaccess` log protection and server-side session checks for all admin tools.
- **Generic Blocker**: Added a dynamic script loader for non-GCM scripts.

### v1.1.0
- **Generic Identity**: Removed all project-specific references.
- **Technical Documentation**: Added `technical_compliance.php`.
- **Admin UI Overhaul**: Improved style section and live preview.
- **Installation Guide**: Added internal `install_guide.php`.

### v1.0.0
- Initial release.
- Core cookie consent logic & GCM v2 support.
- JSON-based multi-language support.
