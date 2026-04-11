# ingest_katha.py
# -*- coding: utf-8 -*-
"""
Ingesta um JSON de katha (schema 3.2) para o WordPress.

Uso:
  py ingest_katha.py seu-katha.json

.env esperado:
  VANA_API_URL=https://seusite.com/wp-json/vana/v1/ingest-katha
  VANA_SECRET=sua_chave_aqui
"""

import os
import sys
import json
from dotenv import load_dotenv
from client import VanaClient


def main():
    load_dotenv()

    # ── Validar argumentos ────────────────────────────────────────
    if len(sys.argv) < 2:
        print("Uso: py ingest_katha.py <caminho-do-json>")
        sys.exit(1)

    json_file = sys.argv[1]

    if not os.path.isfile(json_file):
        print(f"❌ Arquivo não encontrado: {json_file}")
        sys.exit(1)

    # ── Carregar variáveis de ambiente ────────────────────────────
    api_url = os.getenv("VANA_API_KATHA_URL")
    secret  = os.getenv("VANA_SECRET")

    if not api_url or not secret:
        print("❌ VANA_API_URL ou VANA_SECRET não definidos no .env")
        sys.exit(1)

    # ── Ler o JSON ────────────────────────────────────────────────
    try:
        with open(json_file, "r", encoding="utf-8") as f:
            katha_data = json.load(f)
    except json.JSONDecodeError as e:
        print(f"❌ JSON inválido: {e}")
        sys.exit(1)

    # ── Validações mínimas ────────────────────────────────────────
    schema = katha_data.get("schema_version")
    # Accept both legacy 3.2 and newer 4.1 schema versions for ingestion
    if schema not in ("3.2", "4.1"):
        print(f"⚠️  schema_version esperado: 3.2 or 4.1 — encontrado: {schema}")
        sys.exit(1)

    katha_ref = katha_data.get("context", {}).get("katha_ref", "")
    if not katha_ref:
        print("❌ context.katha_ref ausente no JSON.")
        sys.exit(1)

    # ── Serializar e enviar ───────────────────────────────────────
    client        = VanaClient(api_url=api_url, secret=secret)
    payload_bytes = VanaClient._dumps_deterministic(katha_data)  # ← staticmethod, sem self

    print(f"🚜 Iniciando ingestão da katha...")
    print(f"📌 katha_ref : {katha_ref}")
    print(f"📁 Arquivo   : {json_file}")
    print(f"🌍 Destino   : {api_url}")

    response = client.send_raw(payload_bytes)

    # ── Analisar resposta ─────────────────────────────────────────
    if response.get("success"):
        data = response.get("data", {})
        print(f"\n✅ SUCESSO!")
        print(f"   katha_id         : {data.get('katha_id')}")
        print(f"   katha_ref        : {data.get('katha_ref')}")
        print(f"   action           : {data.get('action')}")
        print(f"   visit_id         : {data.get('visit_id')}")
        print(f"   passages_upserted: {data.get('passages_upserted')}")
        print(f"   passages_created : {data.get('passages_created')}")
        print(f"   passages_updated : {data.get('passages_updated')}")
    else:
        print(f"\n❌ FALHA NA INGESTÃO.")
        print(json.dumps(response, indent=2, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
