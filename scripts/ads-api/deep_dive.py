#!/usr/bin/env python3
"""
Deep Dive Account Google Ads - Madness Republic / Quantum Multisport
Verifica lo stato di servizio delle keyword, i target geografici e le impostazioni
avanzate che potrebbero bloccare l'erogazione dopo 3 mesi.
"""

from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException

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

def check_keyword_serving_status(client):
    """Controlla se le keyword sono bloccate per 'Basso Volume di Ricerca'."""
    query = """
        SELECT
            ad_group_criterion.keyword.text,
            ad_group_criterion.system_serving_status,
            ad_group_criterion.approval_status
        FROM ad_group_criterion
        WHERE ad_group_criterion.type = 'KEYWORD'
          AND ad_group_criterion.status = 'ENABLED'
    """
    print("\n" + "=" * 60)
    print("  🔑 STATO EROGAZIONE DI SISTEMA DELLE PAROLE CHIAVE")
    print("=" * 60)
    try:
        response = search(client, query)
        found = False
        rarely_served_count = 0
        total_keywords = 0
        for row in response:
            found = True
            total_keywords += 1
            kw = row.ad_group_criterion
            status = kw.system_serving_status.name
            if status == "RARELY_SERVED":
                rarely_served_count += 1
            print(f"   - \"{kw.keyword.text}\" | Stato di sistema: {status} | Approvazione: {kw.approval_status.name}")
        if not found:
            print("   ⚠️  Nessuna keyword attiva trovata.")
        else:
            print(f"\n   Resoconto: {rarely_served_count} su {total_keywords} keyword sono RARELY_SERVED (Basso volume).")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore: {error.message}")

def check_campaign_targeting(client):
    """Verifica il targeting geografico e linguistico dettagliato delle campagne."""
    query = """
        SELECT
            campaign.name,
            campaign_criterion.type,
            campaign_criterion.location.geo_target_constant,
            campaign_criterion.language.language_constant,
            campaign_criterion.negative
        FROM campaign_criterion
        WHERE campaign.status = 'ENABLED'
    """
    print("\n" + "=" * 60)
    print("  🌍 TARGETING DETTAGLIATO CAMPAGNE")
    print("=" * 60)
    try:
        response = search(client, query)
        for row in response:
            c = row.campaign
            crit = row.campaign_criterion
            neg = "ESCLUSIONE" if crit.negative else "TARGET"
            
            if crit.type_.name == "LOCATION":
                loc_id = crit.location.geo_target_constant
                print(f"   Campagna: {c.name} | Location ({neg}): {loc_id}")
            elif crit.type_.name == "LANGUAGE":
                lang_id = crit.language.language_constant
                print(f"   Campagna: {c.name} | Language ({neg}): {lang_id}")
            else:
                print(f"   Campagna: {c.name} | Altro Target: {crit.type_.name}")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"   ❌ Errore: {error.message}")

def main():
    try:
        client = get_client()
        check_keyword_serving_status(client)
        check_campaign_targeting(client)
    except Exception as e:
        print(f"Errore: {e}")

if __name__ == "__main__":
    main()
