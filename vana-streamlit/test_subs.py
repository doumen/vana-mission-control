import sys, json, toml, requests, base64
sys.path.insert(0, ".")

s = toml.load(".streamlit/secrets.toml")["vana"]
base = s["api_base"].rstrip("/")
creds = base64.b64encode(f"{s['wp_user']}:{s['wp_app_password']}".encode()).decode()
headers = {"Authorization": f"Basic {creds}"}

for status in ["pending", "publish", "any", "draft", "trash"]:
    r = requests.get(
        f"{base}/wp/v2/vana_submission",
        params={"per_page": 3, "status": status, "_fields": "id,title,status,meta"},
        headers=headers,
        timeout=15,
    )
    try:
        data = r.json()
        count = len(data) if isinstance(data, list) else "?"
        print(f"  {status:10} → HTTP {r.status_code} → {count} item(s)")
        if isinstance(data, list) and data:
            meta_keys = list(data[0].get("meta", {}).keys())
            print(f"    meta keys: {meta_keys}")
            print(f"    primeiro:  id={data[0].get('id')} status={data[0].get('status')}")
    except Exception as e:
        print(f"  {status:10} → ERRO: {e}")
        print(f"    raw: {r.text[:200]}")
