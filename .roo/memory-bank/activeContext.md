# Active Context - Google Ads, Pixel Wall & Community App

## 📍 Stato Attuale (30 Maggio 2026)

### Sistema di Tracciamento
✅ **Aruba → Hetzner webhook TESTATO E FUNZIONANTE**
- GCLID catturato correttamente da JS e passato a Stripe
- wall-api.php legge GCLID e spara webhook a Hetzner
- VPS riceve il payload e lo registra in conversion_log.jsonl
- Servizio systemd `madness-webhook` attivo e in auto-restart

### Campagne Google Ads — Post Intervento 30 Maggio ✅ COMPLETATO
- Tutte e 4 ENABLED con **MAXIMIZE_CONVERSIONS**
- ✅ Micro-conversione `checkout_opened` creata su Google Ads (snippet AW-17847747259/uumuCJmQmrYcELuFvL5C)
- ✅ Snippet evento integrato in `pixel-wall.js` → logEvent() → conversione sparata al click su "Vai al pagamento"
- ✅ Vecchia conversione "Acquisto" morta (108gg) → declassata a Secondaria per sbloccare l'algoritmo
- ⏳ Attesa 24-48h per prime impressioni

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
1. **1-2 Giugno** — Verificare prime impressioni Google Ads: `./venv/bin/python3 diagnostics.py`
2. **Deploy community app** — Eseguire `npm run build` e fare deploy su Hetzner per attivare sitemap e robots in produzione
3. **Google Search Console** — Inviare `https://community.madnessrepublic.com/sitemap.xml` per accelerare l'indicizzazione
