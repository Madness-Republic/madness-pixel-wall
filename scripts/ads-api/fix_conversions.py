"""
fix_conversions.py
Corregge lo status (Primaria/Secondaria) delle azioni di conversione
via Google Ads API.

Obiettivo:
  - "Acquisto" (ID 7497198169)      → SECONDARIA  (tag rotto, 0 dati)
  - "Acquisto Pixel Wall" (7483141093) → PRIMARIA ✅
  - "checkout_opened" (7629867033)    → PRIMARIA ✅ (già ok, confermiamo)
"""

import sys
from google.ads.googleads.client import GoogleAdsClient
from google.ads.googleads.errors import GoogleAdsException
from google.protobuf import field_mask_pb2

CUSTOMER_ID = "2122251825"
YAML_PATH = "google-ads.yaml"

# Mappa: conversion_action_id -> include_in_conversions_metric (True=Primaria, False=Secondaria)
CONVERSIONS_TO_FIX = {
    "7497198169": False,   # Acquisto (tag rotto) → Secondaria
    "7483141093": True,    # Acquisto Pixel Wall  → Primaria
    "7629867033": True,    # checkout_opened      → Primaria (conferma)
}

LABELS = {
    "7497198169": "Acquisto (tag rotto)",
    "7483141093": "Acquisto Pixel Wall",
    "7629867033": "checkout_opened",
}


def fix_conversion(client, customer_id, conversion_id, include_in_conversions):
    ca_service = client.get_service("ConversionActionService")
    ca_op = client.get_type("ConversionActionOperation")

    ca = ca_op.update
    ca.resource_name = ca_service.conversion_action_path(customer_id, conversion_id)
    ca.include_in_conversions_metric = include_in_conversions

    # Cambia anche primary_for_goal in base al valore
    # primary_for_goal = True → Primaria; False → solo osservazione
    ca.primary_for_goal = include_in_conversions

    field_mask = field_mask_pb2.FieldMask(paths=["include_in_conversions_metric", "primary_for_goal"])
    ca_op.update_mask.CopyFrom(field_mask)

    try:
        response = ca_service.mutate_conversion_actions(
            customer_id=customer_id,
            operations=[ca_op]
        )
        label = LABELS.get(conversion_id, conversion_id)
        status = "PRIMARIA ✅" if include_in_conversions else "SECONDARIA ❌"
        print(f"  ✅ {label} → impostata a {status}")
        return True
    except GoogleAdsException as ex:
        label = LABELS.get(conversion_id, conversion_id)
        print(f"  ❌ Errore su {label}: {ex.error.code().name}")
        for error in ex.failure.errors:
            print(f"     {error.message}")
        return False


def main():
    print("=" * 60)
    print("  🔧 FIX AZIONI DI CONVERSIONE - MADNESS REPUBLIC")
    print("=" * 60)

    try:
        client = GoogleAdsClient.load_from_storage(YAML_PATH)
    except Exception as e:
        print(f"❌ Errore caricamento client: {e}")
        sys.exit(1)

    print(f"\n  Account: {CUSTOMER_ID}")
    print(f"\n  Correzioni da applicare:")
    for cid, include in CONVERSIONS_TO_FIX.items():
        label = LABELS.get(cid, cid)
        stato = "PRIMARIA" if include else "SECONDARIA"
        print(f"    • {label} (ID: {cid}) → {stato}")

    print("\n  Applicazione modifiche...\n")
    successes = 0
    for conversion_id, include in CONVERSIONS_TO_FIX.items():
        if fix_conversion(client, CUSTOMER_ID, conversion_id, include):
            successes += 1

    print(f"\n  {successes}/{len(CONVERSIONS_TO_FIX)} conversioni aggiornate con successo.")
    print("\n" + "=" * 60)
    print("  Riavvia diagnostics.py tra qualche minuto per verificare.")
    print("=" * 60)


if __name__ == "__main__":
    main()
