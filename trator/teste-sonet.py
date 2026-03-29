# diagnostico_beta.py
# -*- coding: utf-8 -*-
"""
Diagnóstico determinístico do beta.vanamadhuryamdaily.com
Roda ANTES de qualquer tentativa de correção.
"""

import os
import requests
from requests.auth import HTTPBasicAuth

# ─── CONFIG ──────────────────────────────────────────────
WP_URL      = os.getenv("WP_URL", "https://beta.vanamadhuryamdaily.com")
USERNAME    = os.getenv("WP_USER", "")
PASSWORD    = os.getenv("WP_APP_PASSWORD", "")
AUTH        = HTTPBasicAuth(USERNAME, PASSWORD)
# ─────────────────────────────────────────────────────────

PROVAS = [
    # (descrição, url, requer_auth)
    ("1️⃣  Servidor responde?",          f"{WP_URL}",                              False),
    ("2️⃣  WP-JSON raiz acessível?",     f"{WP_URL}/wp-json/",                     False),
    ("3️⃣  WP/v2 acessível?",            f"{WP_URL}/wp-json/wp/v2/",               False),
    ("4️⃣  Autenticação válida?",         f"{WP_URL}/wp-json/wp/v2/users/me",       True),
    ("5️⃣  Namespace /vana/v1 existe?",  f"{WP_URL}/wp-json/vana/v1/",             True),
    ("6️⃣  Endpoint /ingest existe?",    f"{WP_URL}/wp-json/vana/v1/ingest",       True),
]

print("=" * 60)
print("🔍 DIAGNÓSTICO BETA — RESULTADOS")
print("=" * 60)

for descricao, url, auth in PROVAS:
    try:
        resp = requests.get(
            url,
            auth=AUTH if auth else None,
            timeout=10,
            allow_redirects=True
        )
        status = resp.status_code
        final_url = resp.url  # URL final após redirects

        # Detecta se houve redirect inesperado
        redirect_flag = f" ⚠️ REDIRECT → {final_url}" if final_url != url else ""

        if status == 200:
            emoji = "✅"
        elif status in (401, 403):
            emoji = "🔐"
        elif status == 404:
            emoji = "❌"
        else:
            emoji = "⚠️"

        print(f"{emoji} {descricao}")
        print(f"   URL:    {url}")
        print(f"   STATUS: {status}{redirect_flag}")

        # Detecta se o body menciona /beta_html (o bug!)
        if "beta_html" in resp.text:
            print(f"   🚨 BODY contém '/beta_html' — rewrite acontecendo aqui!")

    except Exception as e:
        print(f"💥 {descricao}")
        print(f"   ERRO: {e}")

    print()

print("=" * 60)
print("📋 INTERPRETAÇÃO:")
print("  ✅ = OK  |  🔐 = Auth  |  ❌ = 404  |  ⚠️ = Redirect/Outro")
print("  🚨 = '/beta_html' no body = bug confirmado nessa camada")
print("=" * 60)

# Adicione ao final do teste-sonet.py
print("\n🧪 TESTE EXTRA — POST no /ingest")
resp = requests.post(
    f"{WP_URL}/wp-json/vana/v1/ingest",
    auth=AUTH,
    json={"test": True},
    timeout=10
)
print(f"   STATUS: {resp.status_code}")
print(f"   BODY:   {resp.text[:300]}")
