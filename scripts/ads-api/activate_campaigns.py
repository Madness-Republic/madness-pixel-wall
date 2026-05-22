#!/usr/bin/env python3
"""
Attiva tutte le Campagne Google Ads - Madness Republic
Imposta lo stato di tutte le campagne in pausa su ENABLED tramite API.

Uso: ./venv/bin/python3 activate_campaigns.py
"""

from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException
from google.protobuf.field_mask_pb2 import FieldMask

CUSTOMER_ID = "2122251825"
# ID delle due campagne attualmente in pausa
CAMPAIGN_IDS_TO_ACTIVATE = ["23518595507", "23518645835"]

def get_client():
    import yaml
    with open("google-ads.yaml") as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)

def main():
    print("=" * 60)
    print("  🟢 ATTIVAZIONE CAMPAGNE GOOGLE ADS")
    print("=" * 60)

    try:
        client = get_client()
        campaign_service = client.get_service("CampaignService")
        
        operations = []
        for campaign_id in CAMPAIGN_IDS_TO_ACTIVATE:
            # Prepariamo l'operazione di aggiornamento per ciascuna campagna
            campaign_operation = client.get_type("CampaignOperation")
            campaign = campaign_operation.update
            
            campaign.resource_name = campaign_service.campaign_path(CUSTOMER_ID, campaign_id)
            
            # Cambiamo lo stato in ENABLED
            campaign.status = client.enums.CampaignStatusEnum.ENABLED
            
            # Specifichiamo l'update_mask per lo status
            campaign_operation.update_mask.paths.append("status")
            operations.append(campaign_operation)
        
        if not operations:
            print("  ℹ️ Nessuna campagna da attivare specificata.")
            return

        # Eseguiamo la mutazione in batch
        response = campaign_service.mutate_campaigns(
            customer_id=CUSTOMER_ID,
            operations=operations
        )
        
        print(f"\n  ✅ Campagne attivate con successo!")
        for result in response.results:
            print(f"  - Resource Name: {result.resource_name}")
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
