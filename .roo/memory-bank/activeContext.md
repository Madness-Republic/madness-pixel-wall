# Active Context - Google Ads, Pixel Wall & Community App

## 📍 Stato Attuale (1 Giugno 2026)

### Sistema di Tracciamento
✅ **Aruba → Hetzner webhook TESTATO E FUNZIONANTE**
- GCLID catturato correttamente da JS e passato a Stripe
- wall-api.php legge GCLID e spara webhook a Hetzner
- VPS riceve il payload e lo registra in conversion_log.jsonl
- Servizio systemd `madness-webhook` attivo e in auto-restart

### Campagne Google Ads — Post Intervento
- Tutte e 4 ENABLED con **MAXIMIZE_CONVERSIONS**
- ✅ Micro-conversione `checkout_opened` creata su Google Ads e verificata.
- 🔴 "checkout_opened" mostra "Inattivo" e "Acquisto Pixel Wall" mostra "Richiede attenzione" nella UI.
- ✅ Risolto bug GCM per visitatori ricorrenti in `consent_manager.js` (sia in pixel-wall che in `madness-gdpr-consent-system`).
- ✅ Rimossa doppia chiamata di conversione `purchase` legacy in `pixel-wall.js`.

### Community App SEO & Tracciamento — 30 Maggio ✅ COMPLETATO
- ✅ Google Analytics GA4 (G-5HL3QJT8MT) + Ads (AW-17847747259) aggiunto a `community_v2.0/src/app/layout.tsx`
- ✅ `sitemap.ts` creato (Next.js nativo) con rotte pubbliche: /, /classifica, /progetti, /network, /login, /register
- ✅ `robots.ts` creato — blocca /api/, /community/, /uploads/
- ✅ Cross-domain tracking configurato su GA4: "Contiene: madnessrepublic.com"
- ✅ Title template SEO: `%s | Madness Republic Community`

### File chiave
- `scripts/ads-api/STATUS_CAMPAGNE.md` — riepilogo campagne
- `community_v2.0/src/app/layout.tsx` — Google Tags
- `community_v2.0/src/app/sitemap.ts` — sitemap.xml automatica
- `community_v2.0/src/app/robots.ts` — robots.txt automatico
- VPS: `ssh root@91.99.205.205`

## 🔜 Prossimi Check
1. **Deploy su Aruba** — Verificare deploy FTP di `gdpr/assets/js/consent_manager.js` e `assets/js/pixel-wall.js`.
2. **Verifica Stato Conversioni** — L'AI verificherà via script (`diagnostics.py`) se lo stato delle azioni di conversione in Google Ads è cambiato.
3. **Deploy community app** — Eseguire `npm run build` e fare deploy su Hetzner per attivare sitemap e robots in produzione.
4. **Google Search Console** — Inviare `https://community.madnessrepublic.com/sitemap.xml` per accelerare l'indicizzazione.
