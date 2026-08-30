#!/usr/bin/env python3
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

def clean_rarely_served_keywords(client, customer_id):
    ga_service = client.get_service("GoogleAdsService")
    agc_service = client.get_service("AdGroupCriterionService")
    
    query = """
        SELECT
            ad_group_criterion.resource_name,
            ad_group_criterion.keyword.text,
            ad_group_criterion.system_serving_status
        FROM ad_group_criterion
        WHERE ad_group_criterion.type = 'KEYWORD'
          AND ad_group_criterion.status = 'ENABLED'
    """
    
    print("Ricerca keyword RARELY_SERVED...")
    response = ga_service.search(customer_id=customer_id, query=query)
    
    operations = []
    
    for row in response:
        kw = row.ad_group_criterion
        if kw.system_serving_status.name == "RARELY_SERVED":
            print(f"Keyword da mettere in pausa: {kw.keyword.text}")
            
            operation = client.get_type("AdGroupCriterionOperation")
            criterion = operation.update
            criterion.resource_name = kw.resource_name
            criterion.status = client.enums.AdGroupCriterionStatusEnum.PAUSED
            
            from google.api_core import protobuf_helpers
            client.copy_from(operation.update_mask, protobuf_helpers.field_mask(None, criterion._pb))
            operations.append(operation)
            
    if not operations:
        print("Nessuna keyword RARELY_SERVED trovata.")
        return
        
    print(f"Eseguo pausa per {len(operations)} keywords...")
    try:
        response = agc_service.mutate_ad_group_criteria(
            customer_id=customer_id, operations=operations
        )
        print("Successo!")
    except GoogleAdsException as ex:
        for error in ex.failure.errors:
            print(f"Errore API: {error.message}")

if __name__ == "__main__":
    client = get_client()
    clean_rarely_served_keywords(client, CUSTOMER_ID)
