# Active Context - Google Ads Integration & Automazioni

## 📍 Stato Attuale (22 Maggio 2026)
**TUTTO COMPLETATO** — Sessione chiusa con successo.

### Campagne Google Ads
- ✅ Tutte e 4 attive: `ENABLED` + `SERVING`
- ✅ `Website traffic-Search-1` → **Maximize Clicks** ($2.00 cap) — in `LEARNING`
- ✅ `Pixelwall-PMax-1` → ELIGIBLE
- ✅ `Pixelwall-PMax-Ita-Estero` → ELIGIBLE (riattivata)
- ✅ `Pixelwall-PMax-US_GB` → ELIGIBLE (riattivata)

### Architettura Ibrida Implementata
- ✅ **Aruba (PHP/JS)**: cattura `gclid`, lo passa a Stripe, spara webhook a Hetzner
- ✅ **Hetzner (Python)**: riceve webhook, carica conversioni offline su Google Ads API
- ✅ **Telegram**: alerting giornaliero testato e funzionante (`@axelware78_bot`)

### File da Caricare su Aruba (ancora da fare)
- `pixel-wall.js` — cattura GCLID
- `create-payment-intent.php` — GCLID nei metadata Stripe
- `wall-api.php` — salva GCLID + spara webhook
- `data/settings.json` — URL e token Hetzner

### VPS Hetzner (ancora da fare)
- Seguire `DEPLOY_HETZNER.md` per installare il webhook receiver come servizio systemd
- Aggiornare `hetzner_webhook_url` con IP/dominio reale del VPS

## 🔑 Credenziali e Config
- CustomerID Google Ads: `2122251825`
- Conversion Action ID: `7483141093` (Acquisto Pixel Wall)
- Telegram Bot: `@axelware78_bot` — credenziali in `private/madness-ads.env`
- Webhook Token: vedi `private/madness-ads.env`

## 🔜 Prossimo Milestone
Quando l'account raggiunge 15-30 conversioni reali → **tornare su `Website traffic-Search-1` e ripristinare Maximize Conversions** per togliere il limite di $2.00 CPC.
