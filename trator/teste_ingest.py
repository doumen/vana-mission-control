# teste_ingest.py
import os
from vana_hmac_signer import VanaIngestClient

os.environ["WP_URL"]             = "https://beta.vanamadhuryamdaily.com"
os.environ["VANA_INGEST_SECRET"] = "3708fe96095c12b3e45e2461b26178e6a19e9f62e5f8667db829dd2dc5ae5860"  # mesmo valor do wp-config.php

client = VanaIngestClient()
client.ingest(
    kind       = "tour",
    origin_key = "teste-diagnostico-001",
    data       = {"titulo": "Teste diagnóstico", "ativo": True}
)
