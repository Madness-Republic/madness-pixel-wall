# 📊 STATUS CAMPAGNE GOOGLE ADS — MADNESS REPUBLIC
**Ultimo aggiornamento:** 27 Maggio 2026  
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
| `assets/js/pixel-wall.js` | `captureGclid()` → localStorage + Cookie 30gg |
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

## 📈 Stato Campagne (27 Maggio 2026)

| Campagna | ID | Tipo | Stato | Strategia |
|----------|-----|------|-------|-----------|
| Website traffic-Search-1 | 23563231848 | Search | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |
| Pixelwall-PMax-1 | 23518553228 | PMax | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |
| Pixelwall-PMax-Ita-Estero | 23518595507 | PMax | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |
| Pixelwall-PMax-US_GB | 23518645835 | PMax | 🟢 ENABLED | MAXIMIZE_CONVERSIONS |

### Azioni di Conversione
| Nome | ID/Snippet | Tipo | Uso |
|------|-----|------|-----|
| Inizio procedura di pagamento (checkout_opened) | AW-17847747259/uumuCJmQmrYcELuFvL5C | EVENTO JS | Primaria ✅ (Micro-conversione per sbloccare algoritmo) |
| Acquisto Pixel Wall | 7483141093 | WEBPAGE | Primaria ✅ |
| Acquisto (Tag Rotto) | 7497198169 | WEBPAGE | Secondaria ❌ (Rimossa dall'ottimizzazione) |

---

## 🔧 Interventi Eseguiti (27 Maggio 2026)

### Root cause diagnosi (0 impressioni dopo settimane):
1. **Strategia TARGET_SPEND + cap $2 CPC** → perdita del 90% delle aste ("search_rank_lost_impression_share: 90%")
2. **Quality Score N/D** su tutti i keyword → Ad Rank troppo basso
3. **Impressioni reali trovate:** `raccolta fondi online` aveva 10% impression share ma perdeva il 90% per rank

### Fix applicati:
1. ✅ **Search Campaign** → cambiata da `TARGET_SPEND ($2 cap)` a `MAXIMIZE_CONVERSIONS` (nessun cap CPC)
2. ✅ **+10 keyword ad alto volume** aggiunte nel gruppo "Gruppo di annunci 1":
   - `donazione online`, `raccolta fondi`, `crowdfunding` (broad)
   - `aiutare bambini`, `sport per tutti` (broad)
   - `fare beneficenza online`, `donare a una causa` (phrase)
   - `sostieni una associazione`, `pixel art online`, `personalizzare un pixel` (broad/phrase)
3. ⚠️ **PMax temporaneamente messe in pausa per errore** → riattivate subito dopo

### Nota importante verificata — PMax su Ad Grants:
> **Le campagne Performance Max SONO consentite su Google Ad Grants** da Gennaio 2025.
> Differenze rispetto alle PMax a pagamento:
> - Eroga solo su **Search e Maps** (non YouTube/Display/Gmail)
> - **Esenti dal requisito CTR 5%** (vantaggio!)
> - Non supportano video asset
> - Budget condiviso con il grant ($10.000/mese)

---

## 📝 TODO — Prossima Sessione

### Priorità ALTA
- [ ] **Verificare se ci sono impressioni** dopo 24-48h dal cambio strategia (27 → 29 maggio)
- [ ] **Controllare Quality Score** delle nuove keyword aggiunte
- [ ] **Verificare `conversion_log.jsonl`** sul VPS per confermare che le conversioni reali vengano ricevute

### Priorità MEDIA
- [ ] **Ottimizzare Asset Group PMax** — aggiungere immagini di qualità e descrizioni più specifiche
- [ ] **Aggiungere Sitelink Extensions** alla campagna Search (migliora QS e CTR)
- [x] **Aggiungere Callout Extensions** (es: "100% gratuito", "Arte collettiva", "Sostieni lo sport") - COMPLETATO (30 Maggio)
- [ ] **Verificare "Acquisto" (ID 7497198169)** — è una conversione duplicata o separata?
- [ ] **Keyword RARELY_SERVED** (14 su 33) — valutare se rimuovere o lasciare
- [ ] **Verificare "Acquisto" (ID 7497198169)** — è una conversione duplicata o separata?
- [ ] **Keyword RARELY_SERVED** (14 su 33) — valutare se rimuovere o lasciare

### Priorità BASSA
- [ ] Configurare Target CPA su Maximize Conversions dopo 30+ conversioni reali
- [ ] Aggiungere negative keyword per filtrare traffico non pertinente

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
# Diagnostica completa account
cd /home/quantum/GDrive/amministrazione/Admin/Webdev/pixelwall/scripts/ads-api
./venv/bin/python3 diagnostics.py

# Deep dive keyword e targeting
./venv/bin/python3 deep_dive.py

# Report alerting + Telegram
# Report alerting + Telegram
TELEGRAM_BOT_TOKEN=$(grep TELEGRAM_BOT_TOKEN /home/quantum/GDrive/amministrazione/Admin/Webdev/pixelwall/private/madness-ads.env | cut -d= -f2) \
TELEGRAM_CHAT_ID=1833724171 \
./venv/bin/python3 google_ads_alerting.py

# Log conversioni VPS
ssh root@91.99.205.205 "tail -n 20 /root/ads-api/conversion_log.jsonl"

# Status servizio VPS
ssh root@91.99.205.205 "systemctl status madness-webhook --no-pager"
```
