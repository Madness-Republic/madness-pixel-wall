#!/usr/bin/env python3
"""
Google Ads Account Health Alerter - Madness Republic
Monitora le campagne Google Ads e invia notifiche via Telegram o Slack
se rileva anomalie (0 impressioni, campagne in pausa, CTR sotto soglia).

Uso cronjob su Hetzner VPS (ogni giorno alle 09:00):
    0 9 * * * /path/to/venv/bin/python3 /path/to/google_ads_alerting.py >> /var/log/ads_alerting.log 2>&1

Configurazione:
    Impostare le variabili d'ambiente o modificare la sezione CONFIG qui sotto.
"""

import os
import json
import requests
import yaml
from datetime import date, timedelta
from pathlib import Path
from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException

# ───────────────────────────────────────────────────────────────────────────────
# CONFIG — modifica questi valori o impostali come variabili d'ambiente
# ───────────────────────────────────────────────────────────────────────────────
CUSTOMER_ID      = "2122251825"
GOOGLE_ADS_YAML  = Path(__file__).parent / "google-ads.yaml"

# --- Telegram (consigliato) ---
# Crea un bot su @BotFather, aggiungi il bot a un canale e usa l'ID del canale
TELEGRAM_BOT_TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", "")   # es. "123456:ABC-DEF..."
TELEGRAM_CHAT_ID   = os.environ.get("TELEGRAM_CHAT_ID", "")     # es. "-1001234567890"

# --- Slack (alternativa) ---
SLACK_WEBHOOK_URL  = os.environ.get("SLACK_WEBHOOK_URL", "")    # es. "https://hooks.slack.com/..."

# Soglie di allerta
CTR_THRESHOLD_PERCENT = 5.0   # Google Ad Grants richiede CTR >= 5%
MIN_IMPRESSIONS_7D    = 10    # Alert se impressioni ultimi 7gg sono sotto questa soglia

# ───────────────────────────────────────────────────────────────────────────────
# HELPERS
# ───────────────────────────────────────────────────────────────────────────────
def get_client() -> GoogleAdsClient:
    with open(GOOGLE_ADS_YAML) as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)


def send_telegram(message: str) -> bool:
    """Invia un messaggio Markdown via Telegram Bot API."""
    if not TELEGRAM_BOT_TOKEN or not TELEGRAM_CHAT_ID:
        return False
    url = f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage"
    try:
        r = requests.post(url, json={
            "chat_id": TELEGRAM_CHAT_ID,
            "text": message,
            "parse_mode": "Markdown",
            "disable_web_page_preview": True
        }, timeout=10)
        return r.status_code == 200
    except Exception as e:
        print(f"Errore Telegram: {e}")
        return False


def send_slack(message: str) -> bool:
    """Invia un messaggio via Slack Incoming Webhook."""
    if not SLACK_WEBHOOK_URL:
        return False
    try:
        r = requests.post(SLACK_WEBHOOK_URL, json={"text": message}, timeout=10)
        return r.status_code == 200
    except Exception as e:
        print(f"Errore Slack: {e}")
        return False


def notify(message: str):
    """Invia la notifica al canale configurato (Telegram + Slack se entrambi configurati)."""
    sent = False
    if TELEGRAM_BOT_TOKEN:
        sent = send_telegram(message)
    if SLACK_WEBHOOK_URL:
        sent = send_slack(message) or sent
    if not sent:
        print("⚠️  Nessun canale di notifica configurato. Messaggio:\n" + message)
    return sent


def search(client, query: str):
    ga_service = client.get_service("GoogleAdsService")
    return ga_service.search(customer_id=CUSTOMER_ID, query=query)


# ───────────────────────────────────────────────────────────────────────────────
# DIAGNOSTICHE
# ───────────────────────────────────────────────────────────────────────────────
def check_campaign_status(client) -> list[str]:
    """Controlla se ci sono campagne non ENABLED."""
    alerts = []
    query = """
        SELECT campaign.name, campaign.status, campaign.primary_status
        FROM campaign
        WHERE campaign.status != 'REMOVED'
    """
    for row in search(client, query):
        name    = row.campaign.name
        status  = row.campaign.status.name
        primary = row.campaign.primary_status.name
        if status != "ENABLED":
            alerts.append(f"⛔ Campagna *{name}* è in stato `{status}` (non ENABLED)!")
        elif primary not in ("ELIGIBLE", "LEARNING", "SERVING"):
            alerts.append(f"⚠️ Campagna *{name}*: primary_status = `{primary}`")
    return alerts


def check_impressions_last_7_days(client) -> list[str]:
    """Controlla le impressioni degli ultimi 7 giorni per ogni campagna."""
    alerts = []
    today = date.today()
    start = (today - timedelta(days=7)).strftime("%Y-%m-%d")
    end   = today.strftime("%Y-%m-%d")

    query = f"""
        SELECT
            campaign.name,
            metrics.impressions,
            metrics.clicks,
            metrics.ctr
        FROM campaign
        WHERE
            campaign.status = 'ENABLED'
            AND segments.date BETWEEN '{start}' AND '{end}'
    """
    # Aggrega per campagna
    totals: dict[str, dict] = {}
    try:
        for row in search(client, query):
            name = row.campaign.name
            if name not in totals:
                totals[name] = {"impressions": 0, "clicks": 0}
            totals[name]["impressions"] += row.metrics.impressions
            totals[name]["clicks"]      += row.metrics.clicks
    except GoogleAdsException as ex:
        for err in ex.failure.errors:
            alerts.append(f"❌ Errore query metriche: {err.message}")
        return alerts

    for name, data in totals.items():
        imp = data["impressions"]
        clk = data["clicks"]
        ctr = (clk / imp * 100) if imp > 0 else 0.0

        if imp < MIN_IMPRESSIONS_7D:
            alerts.append(
                f"🚨 Campagna *{name}*: solo `{imp}` impressioni negli ultimi 7gg! "
                "(Possibile stallo dell'algoritmo)"
            )
        elif ctr < CTR_THRESHOLD_PERCENT and imp > 100:
            alerts.append(
                f"⚠️ Campagna *{name}*: CTR = `{ctr:.1f}%` (sotto soglia minima {CTR_THRESHOLD_PERCENT}% Ad Grants) "
                f"su {imp} impressioni"
            )

    return alerts


def get_summary(client) -> str:
    """Genera un riassunto testuale dello stato dell'account."""
    today = date.today()
    start = (today - timedelta(days=7)).strftime("%Y-%m-%d")
    end   = today.strftime("%Y-%m-%d")

    query = f"""
        SELECT
            campaign.name,
            campaign.status,
            campaign.primary_status,
            metrics.impressions,
            metrics.clicks,
            metrics.ctr,
            metrics.conversions
        FROM campaign
        WHERE
            campaign.status != 'REMOVED'
            AND segments.date BETWEEN '{start}' AND '{end}'
    """

    totals: dict[str, dict] = {}
    try:
        for row in search(client, query):
            name = row.campaign.name
            if name not in totals:
                totals[name] = {
                    "status": row.campaign.status.name,
                    "primary": row.campaign.primary_status.name,
                    "impressions": 0, "clicks": 0, "conversions": 0.0
                }
            totals[name]["impressions"]  += row.metrics.impressions
            totals[name]["clicks"]       += row.metrics.clicks
            totals[name]["conversions"]  += row.metrics.conversions
    except Exception:
        return "❌ Errore nel recupero del sommario."

    lines = [f"📊 *Report Madness Republic* — {today.strftime('%d/%m/%Y')} (ultimi 7gg)\n"]
    for name, d in totals.items():
        status_icon = "🟢" if d["status"] == "ENABLED" else "🔴"
        ctr = (d["clicks"] / d["impressions"] * 100) if d["impressions"] > 0 else 0.0
        lines.append(
            f"{status_icon} *{name}*\n"
            f"   Impressioni: `{d['impressions']:,}` | Click: `{d['clicks']:,}` | "
            f"CTR: `{ctr:.1f}%` | Conversioni: `{d['conversions']:.0f}`"
        )
    return "\n".join(lines)


# ───────────────────────────────────────────────────────────────────────────────
# MAIN
# ───────────────────────────────────────────────────────────────────────────────
def main():
    print(f"[{date.today()}] Avvio controllo salute account Google Ads...")

    try:
        client = get_client()
    except Exception as e:
        notify(f"❌ *Madness Republic Ads*: impossibile connettersi alle API Google Ads.\n`{e}`")
        return

    all_alerts = []
    all_alerts += check_campaign_status(client)
    all_alerts += check_impressions_last_7_days(client)

    summary = get_summary(client)

    if all_alerts:
        alert_block = "\n".join(all_alerts)
        message = (
            f"🔔 *ALERT — Madness Republic Google Ads*\n\n"
            f"{alert_block}\n\n"
            f"─────────────────────\n"
            f"{summary}"
        )
    else:
        message = f"✅ *Madness Republic Ads — Tutto OK!*\n\n{summary}"

    notify(message)
    print("Notifica inviata.")


if __name__ == "__main__":
    main()
