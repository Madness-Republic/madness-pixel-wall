#!/usr/bin/env python3
"""
Aggiunge Callout Extensions a livello di Account (Customer)
per migliorare il Quality Score nell'account Ad Grants.
"""

import sys
from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException

CUSTOMER_ID = "2122251825"

def get_client():
    import yaml
    with open("google-ads.yaml") as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)

def add_callouts(client, customer_id):
    asset_service = client.get_service("AssetService")
    customer_asset_service = client.get_service("CustomerAssetService")
    
    callout_texts = ["100% Gratuito", "Arte Collettiva", "Sostieni Lo Sport", "Lascia Il Tuo Segno"]
    
    # 1. Crea gli Asset
    asset_operations = []
    for text in callout_texts:
        asset_operation = client.get_type("AssetOperation")
        asset = asset_operation.create
        asset.callout_asset.callout_text = text
        asset_operations.append(asset_operation)
    
    print("Creazione degli Asset Callout...")
    asset_response = asset_service.mutate_assets(
        customer_id=customer_id, operations=asset_operations
    )
    
    # 2. Associa gli Asset al Customer (Account-level)
    customer_asset_operations = []
    for result in asset_response.results:
        customer_asset_operation = client.get_type("CustomerAssetOperation")
        customer_asset = customer_asset_operation.create
        customer_asset.asset = result.resource_name
        customer_asset.field_type = client.enums.AssetFieldTypeEnum.CALLOUT
        customer_asset_operations.append(customer_asset_operation)
        
    print("Collegamento degli Asset all'Account...")
    customer_asset_response = customer_asset_service.mutate_customer_assets(
        customer_id=customer_id, operations=customer_asset_operations
    )
    
    print("\n✅ Estensioni Callout aggiunte con successo all'account!")
    for res in customer_asset_response.results:
        print(f" - {res.resource_name}")

def main():
    print("=" * 60)
    print("  🚀 SETUP ESTENSIONI GOOGLE AD GRANTS")
    print("=" * 60)
    try:
        client = get_client()
        add_callouts(client, CUSTOMER_ID)
    except GoogleAdsException as ex:
        print("\n❌ Errore Google Ads API:")
        for error in ex.failure.errors:
            print(f" - {error.message}")
    except Exception as e:
        print(f"\n❌ Errore generico: {e}")

if __name__ == "__main__":
    main()
