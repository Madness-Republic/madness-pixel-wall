#!/usr/bin/env python3
import sys
from google.ads.googleads.client import GoogleAdsClient

CUSTOMER_ID = "2122251825"

def get_client():
    import yaml
    with open("google-ads.yaml") as f:
        config = yaml.safe_load(f)
    config["login_customer_id"] = CUSTOMER_ID
    return GoogleAdsClient.load_from_dict(config)

def remove_sitelinks(client, customer_id):
    customer_asset_service = client.get_service("CustomerAssetService")
    
    # Resource names degli asset creati prima per errore
    resource_names = [
        f"customers/{customer_id}/customerAssets/372251691522~SITELINK",
        f"customers/{customer_id}/customerAssets/372251691525~SITELINK",
        f"customers/{customer_id}/customerAssets/372251691528~SITELINK",
        f"customers/{customer_id}/customerAssets/372251691531~SITELINK"
    ]
    
    operations = []
    for rn in resource_names:
        op = client.get_type("CustomerAssetOperation")
        op.remove = rn
        operations.append(op)
        
    try:
        response = customer_asset_service.mutate_customer_assets(
            customer_id=customer_id, operations=operations
        )
        print("✅ Sitelink errati scollegati con successo dall'account!")
    except Exception as e:
        print(f"Errore: {e}")

if __name__ == "__main__":
    remove_sitelinks(get_client(), CUSTOMER_ID)
