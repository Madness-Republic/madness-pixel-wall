# Deploy Guide — VPS Hetzner (Ubuntu)
# Madness Republic — Google Ads Webhook Receiver

## 1. Copia i file sul VPS
```bash
scp -r scripts/ads-api/ ubuntu@91.99.205.205:~/ads-api/
```

## 2. Installa dipendenze Python
```bash
ssh ubuntu@91.99.205.205
cd ~/ads-api
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

## 3. Copia il file di configurazione Google Ads
```bash
# Il file google-ads.yaml non è in git — copialo manualmente
scp scripts/ads-api/google-ads.yaml ubuntu@91.99.205.205:~/ads-api/
```

## 4. Crea il file delle variabili d'ambiente segrete
```bash
sudo nano /etc/madness-ads.env
```
Contenuto del file:
```
WEBHOOK_TOKEN=madness_republic_conversion_secret_2026_t3
TELEGRAM_BOT_TOKEN=<token-del-tuo-bot>
TELEGRAM_CHAT_ID=<id-del-tuo-canale>
```

## 5. Installa e avvia il servizio systemd
```bash
sudo cp ~/ads-api/madness-webhook.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable madness-webhook
sudo systemctl start madness-webhook
sudo systemctl status madness-webhook
```

## 6. Configura il cronjob per l'alerting giornaliero
```bash
crontab -e
```
Aggiungi questa riga (report ogni giorno alle 09:00 ora italiana):
```
0 9 * * * /home/ubuntu/ads-api/venv/bin/python3 /home/ubuntu/ads-api/google_ads_alerting.py >> /var/log/ads_alerting.log 2>&1
```

## 7. Configura nginx come reverse proxy (opzionale ma consigliato per HTTPS)
```bash
sudo apt install nginx certbot python3-certbot-nginx
sudo nano /etc/nginx/sites-available/madness-webhook
```
Contenuto:
```nginx
server {
    listen 80;
    server_name 91.99.205.205;

    location /webhook/ {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    location /health {
        proxy_pass http://127.0.0.1:5000;
    }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/madness-webhook /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
# Per HTTPS con Let's Encrypt:
sudo certbot --nginx -d <DOMINIO>
```

## 8. Aggiorna l'URL webhook in settings.json (su Aruba)
```json
{
  "tracking": {
    "hetzner_webhook_url": "https://community.madnessrepublic.com/webhook/conversion",
    "hetzner_webhook_token": "madness_republic_conversion_secret_2026_t3"
  }
}
```

## 9. Verifica end-to-end
```bash
# Dal tuo PC locale — simula un webhook da Aruba:
curl -X POST https://community.madnessrepublic.com/webhook/conversion \
  -H "Content-Type: application/json" \
  -d '{
    "txnId": "pi_test_123",
    "amount": 5.00,
    "email": "test@madness.com",
    "gclid": "TEST_GCLID_FROM_ARUBA",
    "timestamp": "2026-05-22T08:00:00Z",
    "token": "madness_republic_conversion_secret_2026_t3"
  }'
# Risposta attesa: {"status": "ok", "txnId": "pi_test_123"}
# Controlla il log: tail -f ~/ads-api/conversion_log.jsonl
```
