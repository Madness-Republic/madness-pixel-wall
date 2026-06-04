# 📊 STATUS CAMPAGNE GOOGLE ADS — MADNESS REPUBLIC
**Ultimo aggiornamento:** 4 Giugno 2026  
**Account ID:** `212-225-1825` (Ad Grants) | **MCC:** `345-226-6958`

---

## 🏗️ Architettura Implementata

### Stack di Tracciamento (Aruba → Hetzner → Google Ads)
```
Browser (utente) → ?gclid= catturato in localStorage + Cookie 30gg
       ↓
Aruba PHP (pixel-wall.js + create-payment-intent.php)
       → GCLID nei metadata Stripe
       ↓
wall-api.php → webhook cURL asincrono (2s timeout, fire-and-forget)
       ↓
https://community.madnessrepublic.com/webhook/conversion
(Nginx reverse proxy → Flask :5000 su VPS Hetzner 91.99.205.205)
       ↓
webhook_receiver.py → Google Ads API → Upload conversione offline
```

### File modificati su Aruba
| File | Modifica |
|------|----------|
| `assets/js/pixel-wall.js` | Cattura `gclid`, gestisce tracciamento GA4 ed evita doppie conversioni Ads (rimosso fallback legacy per `purchase`) |
| `gdpr/assets/js/consent_manager.js` | Gestione Google Consent Mode (GCM v2) con fix per ripristinare il consenso corretto ai visitatori di ritorno |
| `api/create-payment-intent.php` | Allega GCLID ai metadata Stripe |
| `api/wall-api.php` | Estrae GCLID, salva in transactions.json, spara webhook |
| `data/settings.json` | `hetzner_webhook_url` e `hetzner_webhook_token` |

### VPS Hetzner (91.99.205.205)
- **Servizio systemd:** `madness-webhook.service` — sempre attivo
- **Endpoint:** `https://community.madnessrepublic.com/webhook/conversion`
- **Health check:** `https://community.madnessrepublic.com/health`
- **Log conversioni:** `/root/ads-api/conversion_log.jsonl`
- **Env file:** `/etc/madness-ads.env` (WEBHOOK_TOKEN + credenziali Telegram)
- **Cronjob:** Alerting Telegram ogni giorno alle 09:00 UTC

---

## 📈 Stato Campagne & Conversioni (4 Giugno 2026)

| Campagna | ID | Tipo | Stato | Strategia |
|----------|-----|------|-------|-----------|
| Website traffic-Search-1 | 23563231848 | Search | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |
| Pixelwall-PMax-1 | 23518553228 | PMax | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |
| Pixelwall-PMax-Ita-Estero | 23518595507 | PMax | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |
| Pixelwall-PMax-US_GB | 23518645835 | PMax | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |

### Stato Azioni di Conversione (rilevato da UI Google Ads)
- ⚠️ **Acquisto Pixel Wall** (`7483141093`): "Richiede attenzione". Ottimizzazione: **Principale**.
- 🔴 **checkout_opened** (`AW-17847747259/uumuCJmQmrYcELuFvL5C`): "Inattivo" (in precedenza "Configurazione errata"). Ottimizzazione: **Principale**.

---

## 🔧 Interventi Eseguiti (4 Giugno 2026)

### 1. Fix Google Consent Mode (GCM) per visitatori ricorrenti
* **Problema:** I visitatori di ritorno che avevano già accettato i cookie in passato rimanevano bloccati con `ad_storage: denied` perché `initGoogleConsentMode()` ritornava in anticipo a causa della presenza del tag `gtag` globale inserito dalla pagina ospite.
* **Risoluzione:** Aggiunto un controllo esplicito per forzare `updateGCM()` se il consenso è già presente in memoria.
* **Repo allineati:** Modifica applicata sia nel submodule locale `gdpr/assets/js/consent_manager.js` di `madness-pixel-wall` sia nel repository sorgente standalone `madness-gdpr-consent-system` (commit `d6cdc39`).

### 2. Risoluzione eventi duplicati "purchase" in Tag Assistant
* **Problema:** Durante il processo di acquisto, Tag Assistant rilevava azioni doppie per la conversione `purchase`.
* **Risoluzione:** Rimosso il blocco legacy redundante in `pixel-wall.js` che inviava un evento `'conversion'` parallelo e con lo stesso parametro `'send_to'` del moderno evento `'purchase'`. Ora viene inviato solo l'evento `'purchase'` corretto.

### 3. Analisi eventi "checkout_opened"
* **Verifica:** Tag Assistant rileva correttamente l'inizio del checkout con due chiamate distinte ma complementari: una a GA4 (`checkout_opened`) e una a Google Ads (`conversion` con tag `uumuCJmQmrYcELuFvL5C`). Questo comportamento è corretto per popolare sia le analytics che il tracciamento delle campagne senza duplicare i conteggi delle conversioni di Google Ads.

---

## 📝 TODO — Prossima Sessione

### Priorità ALTA
- [ ] **Verifica automatica dello stato delle conversioni:** L'AI eseguirà direttamente gli script di diagnostica (`diagnostics.py`) per verificare se le azioni di conversione in Google Ads hanno cambiato stato (es. da "Inattivo/Richiede attenzione" ad attivo) dopo la ricezione dei dati reali delle sessioni di test/produzione.
- [ ] **Deploy file su Aruba (azione utente):** Verificare che i file `gdpr/assets/js/consent_manager.js` e `assets/js/pixel-wall.js` aggiornati siano caricati in produzione via FTP.
- [ ] **Monitoraggio logs VPS:** Eseguire `tail -n 20 /root/ads-api/conversion_log.jsonl` per verificare la ricezione delle conversioni reali sul server.

### Priorità MEDIA
- [ ] **Ottimizzare Asset Group PMax** — aggiungere immagini di qualità e descrizioni più specifiche.
- [ ] **Aggiungere Sitelink Extensions** alla campagna Search (migliora QS e CTR).
- [ ] **Keyword RARELY_SERVED** — valutare se rimuovere o mantenere.

---

## 🔑 Credenziali e Config

| Risorsa | Valore |
|---------|--------|
| Telegram Bot | `@axelware78_bot` |
| Credenziali | `private/madness-ads.env` (locale) e `/etc/madness-ads.env` (VPS) |
| Webhook Token | `madness_republic_conversion_secret_2026_t3` |
| Google Ads YAML | `scripts/ads-api/google-ads.yaml` |
| VPS SSH | `ssh root@91.99.205.205` |

---

## 🚀 Comandi Rapidi per la Prossima Sessione

```bash
# Diagnostica completa account (Stato conversioni e campagne)
cd /home/quantum/GDrive/amministrazione/Admin/Webdev/pixelwall/scripts/ads-api
./venv/bin/python3 diagnostics.py

# Deep dive keyword e targeting
./venv/bin/python3 deep_dive.py

# Log conversioni VPS
ssh root@91.99.205.205 "tail -n 20 /root/ads-api/conversion_log.jsonl"

# Status servizio VPS
ssh root@91.99.205.205 "systemctl status madness-webhook --no-pager"
```
