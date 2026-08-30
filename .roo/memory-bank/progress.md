# Progress Report - Google Ads API

## 📝 Storico dei Progressi

### 22 Maggio 2026 - Attivazione Totale e Sblocco
* **Attivazione in Batch**: Creato ed eseguito lo script `activate_campaigns.py` per portare lo stato di `Pixelwall-PMax-Ita-Estero` (ID: 23518595507) e `Pixelwall-PMax-US_GB` (ID: 23518645835) su **`ENABLED`**.
* **Stato dell'Account**: Tutte e 4 le campagne dell'account Ad Grants sono ora contemporaneamente attive e nello stato **`SERVING`** con **`Nessun Blocco`**.
* **Bidding Strategy Sbloccata**: La campagna Search principale è sbloccata in modalità `LEARNING` con la strategia temporanea Maximize Clicks ($2.00 cap) per innescare le impressioni storiche.
* **Aggiornamento Memory Bank**: Creati e configurati i file di persistenza `.roo/memory-bank/activeContext.md` e `progress.md` per le prossime sessioni AI.

### 4 Giugno 2026 - Risoluzione Bug Consenso GCM e Duplicazione Eventi
* **Risoluzione Bug Consenso Visitatori Ricorrenti**: Risolto un bug in `consent_manager.js` per cui i visitatori che avevano già dato il consenso precedentemente non vedevano l'aggiornamento dello stato del consenso in GCM (rimanendo con `ad_storage: denied`). Fix applicato sia sul repository principale del sito sia su `madness-gdpr-consent-system`.
* **Fix Duplicazione Conversioni Purchase**: Risolto il problema delle azioni doppie/duplicate dell'evento `purchase` rimuovendo la chiamata legacy `gtag('event', 'conversion')` che veniva sparata in parallelo a `gtag('event', 'purchase', { 'send_to': ... })` con le medesime informazioni e lo stesso identificatore di conversione.

### 13 Giugno 2026 - Ottimizzazione Keyword e Sitelinks
* **Sfoltimento Keyword RARELY_SERVED**: Creato script `clean_keywords.py` ed eseguito per mettere in pausa 18 keyword che generavano basso volume di traffico, pulendo il target.
* **Aggiunta Sitelink Extensions**: Creato script `add_sitelinks.py` per creare ed associare a livello account 4 Sitelink (Pixel Wall, I Progetti Sociali, Top Donatori, Community) per migliorare il Quality Score ed il CTR della campagna Search.
* **Primi Click in Search**: Constatato l'ottenimento del primissimo click sulla parola chiave `"madness republic"` a fronte della strategia Maximize Clicks impostata sulla campagna Search.

### 8 Agosto 2026 - Riprogettazione Modello di Prezzo (Zoning e Scaglioni)
* **Implementazione Zoning Y**: Diviso il muro di 4m in 3 fasce Y (Top Y: 0-149, VIP Y: 150-279, Standard Y: 280-399) con tariffe distinte.
* **Implementazione Scaglioni FOMO**: Aggiunti moltiplicatori dinamici sul costo del suolo basati sulla scarsità (occupazione totale della griglia > 100k cm² -> +25%, > 250k cm² -> +50%).
* **Pannello Admin & settings.json**: Modificato settings.json e admin-logic.js per gestire in autonomia i 6 prezzi (Suolo e Inchiostro) per ciascuna zona.
* **Modale Informativa e Traduzioni**: Aggiornato translations.js (IT, EN, ES) per spiegare i dettagli del modello ibrido a zone/scaglioni.
