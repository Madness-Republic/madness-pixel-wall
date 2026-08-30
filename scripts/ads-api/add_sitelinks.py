#!/usr/bin/env python3
"""
Aggiunge Sitelink Extensions a livello di Account
per migliorare il Quality Score e il CTR nell'account Ad Grants.
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

def add_sitelinks(client, customer_id):
    asset_service = client.get_service("AssetService")
    customer_asset_service = client.get_service("CustomerAssetService")
    
    sitelinks_data = [
        {
            "text": "Pixel Wall",
            "desc1": "Lascia il tuo segno indelebile",
            "desc2": "Dona e componi il mosaico",
            "url": "https://madnessrepublic.com"
        },
        {
            "text": "I Progetti Sociali",
            "desc1": "Scopri cosa finanziamo",
            "desc2": "Impatto reale nello sport",
            "url": "https://community.madnessrepublic.com/progetti"
        },
        {
            "text": "Top Donatori",
            "desc1": "Classifica in tempo reale",
            "desc2": "Entra nella hall of fame",
            "url": "https://community.madnessrepublic.com/classifica"
        },
        {
            "text": "Community",
            "desc1": "Unisciti al network solidale",
            "desc2": "Partecipa alla rivoluzione",
            "url": "https://community.madnessrepublic.com"
        }
    ]
    
    # 1. Crea gli Asset
    asset_operations = []
    for item in sitelinks_data:
        asset_operation = client.get_type("AssetOperation")
        asset = asset_operation.create
        asset.sitelink_asset.link_text = item["text"]
        asset.sitelink_asset.description1 = item["desc1"]
        asset.sitelink_asset.description2 = item["desc2"]
        asset.final_urls.append(item["url"])
        asset_operations.append(asset_operation)
    
    print("Creazione degli Asset Sitelink...")
    try:
        asset_response = asset_service.mutate_assets(
            customer_id=customer_id, operations=asset_operations
        )
    except GoogleAdsException as ex:
        print("\n❌ Errore API durante la creazione degli asset:")
        for error in ex.failure.errors:
            print(f" - {error.message}")
        return
        
    # 2. Associa gli Asset al Customer (Account-level)
    customer_asset_operations = []
    for result in asset_response.results:
        customer_asset_operation = client.get_type("CustomerAssetOperation")
        customer_asset = customer_asset_operation.create
        customer_asset.asset = result.resource_name
        customer_asset.field_type = client.enums.AssetFieldTypeEnum.SITELINK
        customer_asset_operations.append(customer_asset_operation)
        
    print("Collegamento degli Asset all'Account come Sitelinks...")
    try:
        customer_asset_response = customer_asset_service.mutate_customer_assets(
            customer_id=customer_id, operations=customer_asset_operations
        )
        
        print("\n✅ Estensioni Sitelink aggiunte con successo all'account!")
        for res in customer_asset_response.results:
            print(f" - {res.resource_name}")
    except GoogleAdsException as ex:
        print("\n❌ Errore API durante il collegamento degli asset:")
        for error in ex.failure.errors:
            print(f" - {error.message}")

def main():
    print("=" * 60)
    print("  🚀 SETUP SITELINK EXTENSIONS GOOGLE AD GRANTS")
    print("=" * 60)
    try:
        client = get_client()
        add_sitelinks(client, CUSTOMER_ID)
    except Exception as e:
        print(f"\n❌ Errore generico: {e}")

if __name__ == "__main__":
    main()
