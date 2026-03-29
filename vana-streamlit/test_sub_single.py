import sys, json, toml, requests, base64
sys.path.insert(0, ".")

s = toml.load(".streamlit/secrets.toml")["vana"]
base = s["api_base"].rstrip("/")
creds = base64.b64encode(f"{s['wp_user']}:{s['wp_app_password']}".encode()).decode()
headers = {"Authorization": f"Basic {creds}"}

# Busca um item SEM _fields para ver tudo que a API expõe
r = requests.get(
    f"{base}/wp/v2/vana_submission/356",
    headers=headers,
    timeout=15,
)
data = r.json()
print(f"HTTP {r.status_code}")
print(f"Chaves raiz: {list(data.keys())}")
print(f"meta: {data.get('meta')}")
print(f"title: {data.get('title')}")
print()
print("=== FULL (primeiros 2000 chars) ===")
print(json.dumps(data, ensure_ascii=False, indent=2)[:2000])
