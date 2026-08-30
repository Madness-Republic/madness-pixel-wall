# Active Context - Google Ads, Pixel Wall & Community App

## 📍 Stato Attuale (1 Giugno 2026)

### Sistema di Tracciamento
✅ **Aruba → Hetzner webhook TESTATO E FUNZIONANTE**
- GCLID catturato correttamente da JS e passato a Stripe
- wall-api.php legge GCLID e spara webhook a Hetzner
- VPS riceve il payload e lo registra in conversion_log.jsonl
- Servizio systemd `madness-webhook` attivo e in auto-restart

### Campagne Google Ads — Post Intervento
- Tutte e 4 ENABLED.
- ✅ Campagna Search principale (`Website traffic-Search-1`) impostata su **`TARGET_SPEND` (Maximize Clicks)** con cap CPC a **$2.00** per sbloccare lo stallo dovuto alle 0 conversioni storiche (cold start).
- 🔴 Campagne PMax temporaneamente ferme a 0 impressioni in quanto impostate su `MAXIMIZE_CONVERSIONS` (richiedono uno storico di conversioni per iniziare ad erogare). La campagna Search fungerà da volano.
- ✅ Micro-conversione `checkout_opened` creata su Google Ads e passata a "Nessuna conversione recente" (rilevata con successo).
- ✅ Risolto bug GCM per visitatori ricorrenti in `consent_manager.js` (sia in pixel-wall che in `madness-gdpr-consent-system`).
- ✅ Rimossa doppia chiamata di conversione `purchase` legacy in `pixel-wall.js`.

### Community App SEO & Tracciamento — 30 Maggio ✅ COMPLETATO
- ✅ Google Analytics GA4 (G-5HL3QJT8MT) + Ads (AW-17847747259) aggiunto a `community_v2.0/src/app/layout.tsx`
- ✅ `sitemap.ts` creato (Next.js nativo) con rotte pubbliche: /, /classifica, /progetti, /network, /login, /register
- ✅ `robots.ts` creato — blocca /api/, /community/, /uploads/
- ✅ Cross-domain tracking configurato su GA4: "Contiene: madnessrepublic.com"
- ✅ Title template SEO: `%s | Madness Republic Community`

### Modello di Prezzo Dinamico (Zoning & Scaglioni) — 8 Agosto 2026 ✅ COMPLETATO
- ✅ **Zoning Y (Top, VIP, Standard)**: Prezzi suolo e inchiostro suddivisi in base all'altezza della coordinata.
- ✅ **Scaglioni Progressivi (FOMO)**: Rincari dinamici del suolo (+25%, +50%) in base all'area totale occupata (confermati + preset).
- ✅ **Admin Panel Integrato**: Exposti i 6 parametri di configurazione dei prezzi delle zone in `/admin/index.php#settings`.
- ✅ **Traduzioni Modale Aggiornate**: I testi della modale "Perché questo sistema?" illustrano chiaramente la ripartizione dei costi.
- ✅ **Backend Intent Sicuro**: La creazione del PaymentIntent Stripe valida i prezzi ed esegue il calcolo in totale sicurezza lato server.

### File chiave
- `scripts/ads-api/STATUS_CAMPAGNE.md` — riepilogo campagne
- `community_v2.0/src/app/layout.tsx` — Google Tags
- `community_v2.0/src/app/sitemap.ts` — sitemap.xml automatica
- `community_v2.0/src/app/robots.ts` — robots.txt automatico
- `assets/js/pixel-wall.js` — Core frontend del canvas con calcolo costi a zone/scaglioni
- `api/create-payment-intent.php` — Calcolo prezzi Stripe sicuro lato backend
- `admin/admin-logic.js` — Form di gestione prezzi zone admin
- `assets/js/translations.js` — Traduzioni del nuovo listino prezzi zone/scaglioni
- VPS: `ssh root@91.99.205.205`

## 🔜 Prossimi Check
1. **Verifica Impressioni e Click (48-72h):** Monitorare la campagna Search dopo il passaggio a Maximize Clicks per verificare la ripresa delle impressioni.
2. **Deploy su Aruba** — Verificare deploy FTP di `gdpr/assets/js/consent_manager.js`, `assets/js/pixel-wall.js`, `assets/js/translations.js`, `api/create-payment-intent.php`, `data/settings.json`, `admin/admin-logic.js`.
3. **Deploy community app** — Eseguire `npm run build` e fare deploy su Hetzner per attivare sitemap e robots in produzione.
4. **Google Search Console** — Inviare `https://community.madnessrepublic.com/sitemap.xml` per accelerare l'indicizzazione.

