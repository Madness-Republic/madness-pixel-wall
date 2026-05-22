#!/usr/bin/env python3
"""
Diagnostica Account Google Ads - Madness Republic / Quantum Multisport
Esegue un report completo sullo stato delle campagne, parole chiave e conversioni.

Uso: ./venv/bin/python3 diagnostics.py
"""

from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException
import datetime

# ID account Ad Grants (quello "figlio", non il Manager)
CUSTOMER_ID = "2122251825"   # 212-225-1825 senza trattini
# ID account Manager (MCC) - usato nel google-ads.yaml come login_customer_id
MCC_ID = "3452266958"        # 345-226-6958 senza trattini

def get_client():
    """Inizializza il client Google Ads.
    L'utente ha accesso diretto all'account figlio (212-225-1825)
    senza passare per l'MCC — login_customer_id = CUSTOMER_ID.
    """
    import yaml
    with open("google-ads.yaml") as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)

def search(client, query):
    """Esegue una query GAQL e ritorna i risultati."""
    ga_service = client.get_service("GoogleAdsService")
    return ga_service.search(customer_id=CUSTOMER_ID, query=query)

def report_campaigns(client):
    """Report sullo stato delle campagne."""
    query = """
        SELECT
            campaign.id,
            campaign.name,
            campaign.status,
            campaign.bidding_strategy_type,
            metrics.impressions,
            metrics.clicks,
            metrics.cost_micros,
            metrics.conversions
        FROM campaign
        WHERE segments.date DURING LAST_30_DAYS
        ORDER BY metrics.impressions DESC
    """

    print("\n" + "=" * 60)
    print("  📊 REPORT CAMPAGNE (ultimi 30 giorni)")
    print("=" * 60)

    try:
        response = search(client, query)
        found = False
        for row in response:
            found = True
            c = row.campaign
            m = row.metrics
            status_icon = "🟢" if c.status.name == "ENABLED" else "⏸️"
            cost_eur = m.cost_micros / 1_000_000
            print(f"\n{status_icon} {c.name}")
            print(f"   ID: {c.id} | Strategia: {c.bidding_strategy_type.name}")
            print(f"   Impressioni: {m.impressions:,} | Click: {m.clicks:,}")
            print(f"   Spesa: ${cost_eur:.2f} | Conversioni: {m.conversions:.0f}")
        if not found:
            print("   ⚠️  Nessuna campagna trovata per il periodo selezionato.")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore API: {error.message}")

def report_keywords(client):
    """Report sulle parole chiave con Quality Score."""
    query = """
        SELECT
            ad_group_criterion.keyword.text,
            ad_group_criterion.keyword.match_type,
            ad_group_criterion.quality_info.quality_score,
            ad_group_criterion.status,
            ad_group.name,
            metrics.impressions,
            metrics.clicks
        FROM keyword_view
        WHERE segments.date DURING LAST_30_DAYS
            AND ad_group_criterion.status != 'REMOVED'
        ORDER BY metrics.impressions DESC
        LIMIT 20
    """

    print("\n" + "=" * 60)
    print("  🔑 TOP PAROLE CHIAVE (ultimi 30 giorni)")
    print("=" * 60)

    try:
        response = search(client, query)
        found = False
        for row in response:
            found = True
            kw = row.ad_group_criterion.keyword
            qi = row.ad_group_criterion.quality_info
            m = row.metrics
            status = row.ad_group_criterion.status.name
            qs = qi.quality_score if qi.quality_score > 0 else "N/D"
            status_icon = "🟢" if status == "ENABLED" else "⏸️"
            print(f"\n{status_icon} \"{kw.text}\" [{kw.match_type.name}]")
            print(f"   Gruppo: {row.ad_group.name} | Quality Score: {qs}/10")
            print(f"   Impressioni: {m.impressions:,} | Click: {m.clicks:,}")
        if not found:
            print("   ⚠️  Nessuna parola chiave attiva trovata.")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore API: {error.message}")

def report_conversions(client):
    """Report sulle azioni di conversione."""
    query = """
        SELECT
            conversion_action.id,
            conversion_action.name,
            conversion_action.status,
            conversion_action.type,
            conversion_action.primary_for_goal
        FROM conversion_action
    """

    print("\n" + "=" * 60)
    print("  🎯 AZIONI DI CONVERSIONE REGISTRATE")
    print("=" * 60)

    try:
        response = search(client, query)
        found = False
        for row in response:
            found = True
            ca = row.conversion_action
            status_icon = "🟢" if ca.status.name == "ENABLED" else "⏸️"
            primary = "Primaria" if ca.primary_for_goal else "Secondaria"
            print(f"\n{status_icon} {ca.name}")
            print(f"   ID: {ca.id} | Tipo: {ca.type_.name} | Uso: {primary}")
        if not found:
            print("   ⚠️  Nessuna azione di conversione trovata.")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore API: {error.message}")

def main():
    print("=" * 60)
    print("  🛰️  DIAGNOSTICA GOOGLE ADS API - MADNESS REPUBLIC")
    print(f"  📅 {datetime.datetime.now().strftime('%d/%m/%Y %H:%M:%S')}")
    print("=" * 60)
    print(f"\n  Account: 212-225-1825 (Ad Grants)")
    print(f"  Manager: 345-226-6958 (MCC)")

    client = get_client()
    print("\n  ✅ Connessione alle API riuscita!")
    report_campaigns(client)
    report_keywords(client)
    report_conversions(client)
    print("\n" + "=" * 60)
    print("  ✅ DIAGNOSTICA COMPLETATA")
    print("=" * 60 + "\n")

if __name__ == "__main__":
    main()
