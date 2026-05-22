#!/usr/bin/env python3
"""
Script per generare il Refresh Token per le API di Google Ads.
Eseguire UNA SOLA VOLTA. Il token generato viene salvato in google-ads.yaml.

Uso: ./venv/bin/python3 generate_refresh_token.py
"""

from google_auth_oauthlib.flow import InstalledAppFlow

# Scope necessari per le Google Ads API
SCOPES = ["https://www.googleapis.com/auth/adwords"]

# Percorso al file JSON scaricato da Google Cloud Console
CLIENT_SECRET_FILE = "../../private/client_secret_41146330462-9bt92o4uqr84mk3f2ml839rmi9076ktm.apps.googleusercontent.com.json"

def main():
    print("=" * 60)
    print("  Generazione Refresh Token - Google Ads API")
    print("=" * 60)
    print("\n1. Si aprirà il browser per l'autenticazione Google.")
    print("2. Accedi con: amministrazione@quantumsport.it")
    print("   (l'account collegato all'MCC Manager 345-226-6958)")
    print("3. Clicca 'Consenti' su tutte le autorizzazioni richieste.")
    print("\nAvvio flusso OAuth2...\n")

    flow = InstalledAppFlow.from_client_secrets_file(
        CLIENT_SECRET_FILE,
        scopes=SCOPES
    )

    # Avvia il server locale per catturare il redirect OAuth2
    credentials = flow.run_local_server(port=8080, prompt="consent")

    print("\n" + "=" * 60)
    print("✅ AUTENTICAZIONE RIUSCITA!")
    print("=" * 60)
    print(f"\nClient ID     : {credentials.client_id}")
    print(f"Client Secret : {credentials.client_secret}")
    print(f"Refresh Token : {credentials.refresh_token}")

    # Salva automaticamente il file di configurazione
    config_content = f"""# Configurazione Google Ads API - Madness Republic / Quantum Multisport
# File generato automaticamente da generate_refresh_token.py
# QUESTO FILE E' NEL .gitignore - NON CONDIVIDERE MAI

developer_token: khlf66uYEofg_uAcEhU8ig
client_id: {credentials.client_id}
client_secret: {credentials.client_secret}
refresh_token: {credentials.refresh_token}
login_customer_id: 345226695800
use_proto_plus: True
"""
    config_path = "google-ads.yaml"
    with open(config_path, "w") as f:
        f.write(config_content)

    print(f"\n✅ File di configurazione salvato in: scripts/ads-api/google-ads.yaml")
    print("\nPasso successivo: esegui './venv/bin/python3 diagnostics.py'\n")

if __name__ == "__main__":
    main()
