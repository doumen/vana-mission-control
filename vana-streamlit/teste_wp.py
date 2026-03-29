# teste_wp.py
import requests
import hmac
import hashlib
import time
import json

# ── Config (igual ao secrets.toml) ────────────────────────────────────────
BASE_URL      = "https://beta.vanamadhuryamdaily.com/wp-json"
INGEST_SECRET = "SEU_INGEST_SECRET_AQUI"   # ← cole o valor real
WP_USER       = "vana-streamlit"
WP_APP_PASS   = "SEU_APP_PASSWORD_AQUI"    # ← cole o valor real

# ── Teste 1: WP REST padrão (Basic Auth) ──────────────────────────────────
print("=== Teste 1: WP REST /wp/v2/posts (Basic Auth) ===")
r = requests.get(
    f"{BASE_URL}/wp/v2/posts",
    params={"slug": "sao-paulo-janeiro-2026", "_fields": "id,title,slug,status"},
    auth=(WP_USER, WP_APP_PASS),
)
print(f"Status: {r.status_code}")
print(r.json())

# ── Teste 2: Rota HMAC /vana/v1/visits ────────────────────────────────────
print("\n=== Teste 2: HMAC /vana/v1/visits ===")
timestamp = str(int(time.time()))
body      = json.dumps({"slug": "sao-paulo-janeiro-2026"})
signature = hmac.new(
    INGEST_SECRET.encode(),
    f"{timestamp}.{body}".encode(),
    hashlib.sha256,
).hexdigest()

r2 = requests.get(
    f"{BASE_URL}/vana/v1/visits",
    params={"slug": "sao-paulo-janeiro-2026"},
    headers={
        "X-Vana-Timestamp": timestamp,
        "X-Vana-Signature": signature,
        "Content-Type":     "application/json",
    },
)
print(f"Status: {r2.status_code}")
print(r2.json())
