#!/usr/bin/env python3
"""
Analisi Dettagliata Account e Budget - Google Ads API
Verifica lo stato dell'account, dei budget giornalieri (regole Ad Grants)
e del reale stato di approvazione/attivazione delle campagne.

Uso: ./venv/bin/python3 check_alerts.py
"""

from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException
import datetime

CUSTOMER_ID = "2122251825"

def get_client():
    import yaml
    with open("google-ads.yaml") as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)

def search(client, query):
    ga_service = client.get_service("GoogleAdsService")
    return ga_service.search(customer_id=CUSTOMER_ID, query=query)

def check_customer_status(client):
    """Verifica lo stato generale dell'account cliente."""
    query = """
        SELECT
            customer.id,
            customer.descriptive_name,
            customer.status,
            customer.time_zone,
            customer.currency_code
        FROM customer
        LIMIT 1
    """
    print("\n" + "=" * 60)
    print("  🏢 STATO ACCOUNT CLIENTE")
    print("=" * 60)
    try:
        response = search(client, query)
        for row in response:
            c = row.customer
            print(f"   Nome Account : {c.descriptive_name}")
            print(f"   ID Account   : {c.id}")
            print(f"   Stato        : {c.status.name}")
            print(f"   Fuso Orario  : {c.time_zone}")
            print(f"   Valuta       : {c.currency_code}")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore: {error.message}")

def check_budgets(client):
    """Verifica i budget attivi. Gli account Ad Grants devono avere budget specifici."""
    query = """
        SELECT
            campaign_budget.id,
            campaign_budget.name,
            campaign_budget.amount_micros,
            campaign_budget.status,
            campaign_budget.delivery_method,
            campaign_budget.period
        FROM campaign_budget
        WHERE campaign_budget.status = 'ENABLED'
    """
    print("\n" + "=" * 60)
    print("  💰 STATO BUDGET (Regola Ad Grants)")
    print("=" * 60)
    try:
        response = search(client, query)
        found = False
        for row in response:
            found = True
            b = row.campaign_budget
            daily_amount = b.amount_micros / 1_000_000
            print(f"\n   Budget: {b.name}")
            print(f"   ID: {b.id} | Stato: {b.status.name}")
            print(f"   Importo Giornaliero: ${daily_amount:.2f} ({b.period.name})")
            print(f"   Metodo di erogazione: {b.delivery_method.name}")
        if not found:
            print("   ⚠️  Nessun budget attivo trovato. Questo blocca l'erogazione degli annunci!")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore: {error.message}")

def check_campaign_detailed_status(client):
    """Verifica lo stato dettagliato e le motivazioni di blocco delle campagne."""
    # Cerchiamo di interrogare le informazioni sullo stato primario se disponibili
    query = """
        SELECT
            campaign.id,
            campaign.name,
            campaign.status,
            campaign.primary_status,
            campaign.primary_status_reasons,
            campaign.serving_status
        FROM campaign
    """
    print("\n" + "=" * 60)
    print("  🚨 STATO EROGAZIONE E ALERT CAMPAGNE")
    print("=" * 60)
    try:
        response = search(client, query)
        found = False
        for row in response:
            found = True
            c = row.campaign
            reasons = [r.name for r in c.primary_status_reasons]
            reasons_str = ", ".join(reasons) if reasons else "Nessuno (Ok)"
            
            print(f"\n   Campagna: {c.name}")
            print(f"   Status Principale : {c.status.name}")
            print(f"   Serving Status    : {c.serving_status.name}")
            print(f"   Primary Status    : {c.primary_status.name}")
            print(f"   Dettaglio Blocchi : {reasons_str}")
        if not found:
            print("   ⚠️  Nessuna campagna trovata.")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore: {error.message}")

def main():
    print("=" * 60)
    print("  🛰️  ISPEZIONE E ALERT GOOGLE ADS - MADNESS REPUBLIC")
    print(f"  📅 {datetime.datetime.now().strftime('%d/%m/%Y %H:%M:%S')}")
    print("=" * 60)
    try:
        client = get_client()
        check_customer_status(client)
        check_budgets(client)
        check_campaign_detailed_status(client)
        print("\n" + "=" * 60 + "\n")
    except Exception as e:
        print(f"\n  ❌ Errore di connessione: {e}\n")

if __name__ == "__main__":
    main()
