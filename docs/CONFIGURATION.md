# ⚙️ Configuration & Setup Guide

This guide covers the installation, security configuration, and customization of the **Pixel Wall**.

## 🚀 Installation

1.  **Clone the Repository**
    Since this project uses submodules, use the recursive flag:
    ```bash
    git clone --recursive https://github.com/acamboni/madness-pixel-wall.git
    ```
    *If you already cloned without `--recursive`, run: `git submodule update --init --recursive`*

2.  **Permissions**
    Ensure the `data/` folder and `gdpr/logs/` are writable by the web server (usually `www-data`):
    ```bash
    chmod -R 775 data/
    chmod -R 775 gdpr/logs/
    ```

3.  **Environment Setup**
    Copy the example environment file and configure it:
    ```bash
    cp .env.example .env
    ```
    Edit `.env` to add your Stripe Keys and Admin Password.

---

## 📂 Directory Structure

The project has been reorganized for better maintainability:

-   `admin/`: Dashboard and admin logic.
-   `api/`: API endpoints (`wall-api.php`, Stripe intents).
-   `assets/`: Static resources (CSS, JS, Images).
-   `data/`: JSON data storage (Writable).
-   `docs/`: Documentation.
-   `gdpr/`: Consent management submodule.
-   `includes/`: PHP helper scripts.
-   `pages/`: Public auxiliary pages (Winners, WordCloud, Privacy).
-   `private/`: Sensitive configuration (Secrets).

---

## 🔒 Security Requirements

This repository excludes sensitive financial data and private keys by default.
**Never commit the following:**
-   `private/` directory (Stripe Secret Keys, Secret Pixel Locations).
-   `data/` directory (Customer transaction IDs and emails).
-   `.env` file (Environment variables).

### File Protection
Ensure your web server (Apache/Nginx) blocks access to the `data/` and `private/` directories. This project includes an `.htaccess` file for Apache that handles this automatically.

---

## 🍪 GDPR & Privacy Configuration

The Pixel Wall comes with the **Madness GDPR System** pre-installed as a submodule.

### Smart Detection
The system automatically checks for a root-level GDPR installation (`../gdpr/`) on your server.
-   **If found:** It uses the root system (shared cookies, styles, and logs).
-   **If not found:** It falls back to the local submodule (`gdpr/`) ensuring standalone functionality.

### Customizing the Banner
Edit `gdpr/config.php` (create it from `config.php.sample` if missing) to change:
-   Brand Name & Infos
-   Colors & Styles
-   Cookie Duration
-   Google Analytics 4 ID

### Using a different CMP (Iubenda, Cookiebot, etc.)
If you prefer to use an external Consent Provider:

1.  **Disable the Built-in Banner:**
    Open `index.php` and remove/comment the inclusion line at the bottom:
    ```php
    // include_once 'gdpr/banner.php';
    ```

2.  **Unlock Analytics:**
    By default, the internal tracker code in `index.php` waits for `ConsentManager`. You must update/remove this check to work with your provider.

---

## 📊 Analytics Tracker

The project includes a call to `../tracker.php` in `index.php`.
**Note:** This file is external to this repository (it belongs to the parent website).

-   **If you host this on `madnessrepublic.com`:** It works out of the box.
-   **If you host this elsewhere:** You will see a silent 404 error in the console. You can either:
    -   Remove the fetch call in `index.php`.
    -   Implement your own `tracker.php` endpoint.
    -   Replace it with your preferred analytics solution (e.g., GA4, Plausible).
