#!/usr/bin/env python3
"""
Webhook Receiver - VPS Hetzner
Riceve le notifiche di conversione da Aruba (Pixel Wall) e carica
immediatamente le conversioni offline su Google Ads API.

Deploy: eseguire con systemd o screen sul VPS Hetzner
Porta: 5000 (proteggere con nginx reverse proxy + HTTPS in produzione)

Avvio:
    pip install flask google-ads pyyaml
    python3 webhook_receiver.py

Crontab per avvio automatico al boot:
    @reboot /path/to/venv/bin/python3 /path/to/webhook_receiver.py >> /var/log/webhook_receiver.log 2>&1
"""

import json
import logging
import os
from datetime import datetime, timezone
from pathlib import Path

import yaml
from flask import Flask, request, jsonify
from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException

# ───────────────────────────────────────────────────────────────────────────────
# CONFIGURAZIONE
# ───────────────────────────────────────────────────────────────────────────────
CUSTOMER_ID           = "2122251825"
CONVERSION_ACTION_ID  = "7483141093"   # "Acquisto Pixel Wall"
WEBHOOK_TOKEN         = os.environ.get(
    "WEBHOOK_TOKEN",
    "madness_republic_conversion_secret_2026_t3"   # Stesso token in settings.json
)
GOOGLE_ADS_YAML       = Path(__file__).parent / "google-ads.yaml"
LOG_FILE              = Path(__file__).parent / "conversion_log.jsonl"
PORT                  = 5000

# ───────────────────────────────────────────────────────────────────────────────
# LOGGING
# ───────────────────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%dT%H:%M:%S"
)
log = logging.getLogger("webhook_receiver")

app = Flask(__name__)

# ───────────────────────────────────────────────────────────────────────────────
# HELPERS
# ───────────────────────────────────────────────────────────────────────────────
def get_ads_client() -> GoogleAdsClient:
    with open(GOOGLE_ADS_YAML) as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)


def upload_offline_conversion(gclid: str, amount_eur: float, txn_id: str) -> bool:
    """
    Carica una conversione offline su Google Ads tramite UploadClickConversions.
    Ritorna True se l'upload ha successo, False altrimenti.
    """
    try:
        client = get_ads_client()
        conversion_upload_service = client.get_service("ConversionUploadService")

        click_conversion = client.get_type("ClickConversion")
        click_conversion.gclid = gclid
        click_conversion.conversion_action = client.get_service(
            "ConversionActionService"
        ).conversion_action_path(CUSTOMER_ID, CONVERSION_ACTION_ID)

        # Timestamp ISO 8601 corrente
        click_conversion.conversion_date_time = datetime.now(timezone.utc).strftime(
            "%Y-%m-%d %H:%M:%S+00:00"
        )

        # Valore della conversione in EUR
        click_conversion.conversion_value = amount_eur
        click_conversion.currency_code = "EUR"

        # ID ordine per idempotenza (evita doppi upload)
        click_conversion.order_id = txn_id

        response = conversion_upload_service.upload_click_conversions(
            customer_id=CUSTOMER_ID,
            conversions=[click_conversion],
            partial_failure=True,
        )

        # Controlla errori parziali
        if response.partial_failure_error.code != 0:
            log.error(
                "Partial failure nel caricamento conversione %s: %s",
                txn_id,
                response.partial_failure_error.message,
            )
            return False

        log.info(
            "✅ Conversione offline caricata: txn=%s | gclid=%s... | EUR=%.2f",
            txn_id, gclid[:12], amount_eur
        )
        return True

    except GoogleAdsException as ex:
        for err in ex.failure.errors:
            log.error("Google Ads API Error: %s", err.message)
        return False
    except Exception as e:
        log.error("Errore generico upload conversione: %s", e)
        return False


def log_conversion(payload: dict, success: bool) -> None:
    """Salva ogni tentativo di upload in un log JSONL per audit."""
    record = {
        **payload,
        "uploaded": success,
        "logged_at": datetime.now(timezone.utc).isoformat(),
    }
    # Rimuovi token prima di loggare
    record.pop("token", None)
    with open(LOG_FILE, "a") as f:
        f.write(json.dumps(record) + "\n")


# ───────────────────────────────────────────────────────────────────────────────
# ENDPOINTS
# ───────────────────────────────────────────────────────────────────────────────
@app.route("/health", methods=["GET"])
def health():
    """Health-check endpoint per verificare che il server sia attivo."""
    return jsonify({"status": "ok", "service": "madness-webhook-receiver"}), 200


@app.route("/webhook/conversion", methods=["POST"])
def receive_conversion():
    """
    Endpoint principale: riceve i dati da Aruba e carica la conversione su Google Ads.

    Payload atteso (JSON):
    {
        "txnId":     "pi_xxxx",
        "amount":    25.00,
        "email":     "utente@email.com",
        "gclid":     "CjwKCAjw...",
        "timestamp": "2026-05-22T08:35:00+00:00",
        "token":     "shared_secret"
    }
    """
    # 1. Verifica Content-Type
    if not request.is_json:
        log.warning("Richiesta non-JSON ricevuta da %s", request.remote_addr)
        return jsonify({"error": "Content-Type deve essere application/json"}), 415

    payload = request.get_json(force=True, silent=True) or {}

    # 2. Validazione token di sicurezza
    if payload.get("token") != WEBHOOK_TOKEN:
        log.warning(
            "Token non valido da %s | txn=%s",
            request.remote_addr, payload.get("txnId", "?")
        )
        return jsonify({"error": "Unauthorized"}), 401

    # 3. Validazione campi obbligatori
    txn_id = payload.get("txnId", "").strip()
    gclid  = payload.get("gclid",  "").strip()
    amount = float(payload.get("amount", 0.0))

    if not txn_id or not gclid:
        log.warning("Payload incompleto: txnId=%s gclid=%s", txn_id, gclid)
        return jsonify({"error": "txnId e gclid sono obbligatori"}), 400

    log.info(
        "📥 Webhook ricevuto: txn=%s | gclid=%s... | amount=€%.2f",
        txn_id, gclid[:12], amount
    )

    # 4. Upload conversione su Google Ads
    success = upload_offline_conversion(
        gclid=gclid,
        amount_eur=amount,
        txn_id=txn_id,
    )

    # 5. Salva il log di audit
    log_conversion(payload, success)

    if success:
        return jsonify({"status": "ok", "txnId": txn_id}), 200
    else:
        return jsonify({"status": "error", "txnId": txn_id}), 500


# ───────────────────────────────────────────────────────────────────────────────
# AVVIO
# ───────────────────────────────────────────────────────────────────────────────
if __name__ == "__main__":
    log.info("🚀 Webhook Receiver avviato su porta %d", PORT)
    log.info("   Google Ads Customer: %s", CUSTOMER_ID)
    log.info("   Conversion Action:   %s", CONVERSION_ACTION_ID)
    # debug=False in produzione; usa gunicorn o waitress per deploy enterprise
    app.run(host="0.0.0.0", port=PORT, debug=False)
