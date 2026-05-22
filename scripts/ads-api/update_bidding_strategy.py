#!/usr/bin/env python3
"""
Sblocca Campagne Google Ad Grants - Cambia Strategia a Maximize Clicks (TargetSpend)
Aggiorna la strategia di offerta di una campagna a TARGET_SPEND (Maximize Clicks)
con un limite di $2.00 per superare lo stallo delle 0 impressioni.

Uso: ./venv/bin/python3 update_bidding_strategy.py
"""

import sys
from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException
from google.protobuf.field_mask_pb2 import FieldMask

CUSTOMER_ID = "2122251825"
CAMPAIGN_ID = "23563231848" # ID di "Website traffic-Search-1"

def get_client():
    import yaml
    with open("google-ads.yaml") as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)

def main():
    print("=" * 60)
    print("  🚀 SBLOCCO CAMPAGNA GOOGLE AD GRANTS")
    print("  🔄 Configurazione Bidding: Maximize Clicks (TargetSpend - $2.00 limit)")
    print("=" * 60)

    try:
        client = get_client()
        campaign_service = client.get_service("CampaignService")
        
        # Prepariamo l'operazione
        campaign_operation = client.get_type("CampaignOperation")
        campaign = campaign_operation.update
        
        campaign.resource_name = campaign_service.campaign_path(CUSTOMER_ID, CAMPAIGN_ID)
        
        # Impostiamo la strategia target_spend (che è l'equivalente di Maximize Clicks)
        # e definiamo il cpc_bid_ceiling_micros a $2.00 (2.000.000 micros)
        campaign.target_spend.cpc_bid_ceiling_micros = 2_000_000
        
        # Aggiungiamo il path esatto all'update_mask per evitare l'errore sui subfields
        campaign_operation.update_mask.paths.append("target_spend.cpc_bid_ceiling_micros")
        
        # Eseguiamo la modifica
        response = campaign_service.mutate_campaigns(
            customer_id=CUSTOMER_ID,
            operations=[campaign_operation]
        )
        
        print(f"\n  ✅ Campagna aggiornata con successo!")
        print(f"  Resource Name: {response.results[0].resource_name}")
        print("\n  ℹ️ Google Ads inizierà ora a cercare il massimo numero di click entro il limite di $2.00.")
        print("     Questo innescherà la pubblicazione degli annunci dopo 3 mesi di stallo!")
        print("=" * 60 + "\n")

    except GoogleAdsException as ex:
        print("\n  ❌ Errore Google Ads API:")
        for error in ex.failure.errors:
            print(f"     - {error.message}")
        print("=" * 60 + "\n")
    except Exception as e:
        print(f"\n  ❌ Errore generico: {e}\n")

if __name__ == "__main__":
    main()
