#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
sync_visit_to_github.py
Busca a visita no WordPress e salva o visit.json no GitHub.

Uso:
    python sync_visit_to_github.py sao-paulo-janeiro-2026
"""

import sys
import json
import tomllib
import requests
import base64
from pathlib import Path
from datetime import datetime, timezone

# ── Lê secrets.toml ───────────────────────────────────────────────────────
secrets_path = Path(__file__).parent / ".streamlit" / "secrets.toml"
if not secrets_path.exists():
    print(f"❌ Não encontrei: {secrets_path}")
    sys.exit(1)

with open(secrets_path, "rb") as f:
    secrets = tomllib.load(f)

WP_URL        = secrets["vana"]["api_base"].replace("/wp-json", "")
WP_USER       = secrets["vana"]["wp_user"]
WP_APP_PASS   = secrets["vana"]["wp_app_password"]
GITHUB_TOKEN  = secrets["github"]["token"]
GITHUB_REPO   = secrets["github"]["repo"]
GITHUB_BRANCH = secrets["github"].get("branch", "master")
GITHUB_API    = "https://api.github.com"

HEADERS_GH = {
    "Authorization":        "Bearer " + GITHUB_TOKEN,
    "Accept":               "application/vnd.github+json",
    "X-GitHub-Api-Version": "2022-11-28",
}


# ── 1. Busca post no WordPress ────────────────────────────────────────────
# ── 1. Busca post no WordPress ────────────────────────────────────────────
def fetch_from_wp(slug: str) -> dict:
    url = WP_URL + "/wp-json/wp/v2/vana_visit"   # ← era "posts", agora "vana_visit"
    r = requests.get(
        url,
        params={"slug": slug, "_fields": "id,title,slug,status,meta"},
        auth=(WP_USER, WP_APP_PASS),
        timeout=15,
    )
    r.raise_for_status()
    posts = r.json()
    if not posts:
        raise ValueError(f"Post '{slug}' não encontrado no WordPress!")
    return posts[0]

# ── 2. Busca meta completo ────────────────────────────────────────────────
def fetch_visit_meta(post_id: int) -> dict:
    url = f"{WP_URL}/wp-json/wp/v2/vana_visit/{post_id}"   # ← era "posts", agora "vana_visit"
    r = requests.get(
        url,
        params={"_fields": "id,slug,title,meta"},
        auth=(WP_USER, WP_APP_PASS),
        timeout=15,
    )
    r.raise_for_status()
    return r.json()


# ── 2. Busca meta completo ────────────────────────────────────────────────
def fetch_visit_meta(post_id: int) -> dict:
    url = f"{WP_URL}/wp-json/wp/v2/posts/{post_id}"
    r = requests.get(
        url,
        params={"_fields": "id,slug,title,meta"},
        auth=(WP_USER, WP_APP_PASS),
        timeout=15,
    )
    r.raise_for_status()
    return r.json()


# ── 3. Monta visit.json ───────────────────────────────────────────────────
def build_visit_json(post: dict, slug: str) -> dict:
    meta = post.get("meta", {})

    # Tenta extrair JSON completo do meta _vana_data
    vana_data = meta.get("_vana_data") or meta.get("vana_data") or {}
    if isinstance(vana_data, str):
        try:
            vana_data = json.loads(vana_data)
        except Exception:
            vana_data = {}

    if vana_data and vana_data.get("visit_id"):
        print("  ✅ Dados completos em _vana_data")
        return vana_data

    # Fallback mínimo
    print("  ⚠️  Meta vazio — estrutura mínima (preencha days manualmente)")
    return {
        "visit_id":       slug,
        "title_pt":       post.get("title", {}).get("rendered", slug),
        "title_en":       "",
        "timezone":       "America/Sao_Paulo",
        "schema_version": "3.1",
        "updated_at":     datetime.now(timezone.utc).isoformat(),
        "days":           [],
        "_note":          "Gerado por sync_visit_to_github.py — preencher days",
    }


# ── 4. Salva no GitHub ────────────────────────────────────────────────────
def save_to_github(visit_ref: str, visit_json: dict) -> bool:
    path    = f"visits/{visit_ref}/visit.json"
    url     = f"{GITHUB_API}/repos/{GITHUB_REPO}/contents/{path}"
    content = json.dumps(visit_json, ensure_ascii=False, indent=2)
    encoded = base64.b64encode(content.encode("utf-8")).decode("utf-8")

    existing = requests.get(
        url, headers=HEADERS_GH,
        params={"ref": GITHUB_BRANCH},
        timeout=10,
    )
    sha = existing.json().get("sha") if existing.status_code == 200 else None

    body = {
        "message": f"[sync] {visit_ref} via sync_visit_to_github.py",
        "content": encoded,
        "branch":  GITHUB_BRANCH,
    }
    if sha:
        body["sha"] = sha
        print(f"  🔄 Atualizando (sha: {sha[:8]}...)")
    else:
        print(f"  ✨ Criando novo arquivo")

    resp = requests.put(url, headers=HEADERS_GH, json=body, timeout=20)
    if resp.ok:
        print(f"  ✅ {resp.json()['content']['html_url']}")
        return True
    else:
        print(f"  ❌ {resp.status_code}: {resp.text[:300]}")
        return False


# ── Main ──────────────────────────────────────────────────────────────────
def main():
    if len(sys.argv) < 2:
        print("Uso: python sync_visit_to_github.py <slug>")
        print("Ex:  python sync_visit_to_github.py sao-paulo-janeiro-2026")
        sys.exit(1)

    slug = sys.argv[1].strip()
    print(f"\n{'═'*55}")
    print(f"  🔄 Sync: {slug}")
    print(f"  WP  → {WP_URL}")
    print(f"  GH  → {GITHUB_REPO} [{GITHUB_BRANCH}]")
    print(f"{'═'*55}")

    print("\n📡 Buscando no WordPress...")
    try:
        post = fetch_from_wp(slug)
        print(f"  ✅ ID={post['id']} | status={post['status']}")
    except Exception as e:
        print(f"  ❌ {e}")
        sys.exit(1)

    print("\n🔍 Carregando meta...")
    try:
        full_post = fetch_visit_meta(post["id"])
    except Exception as e:
        print(f"  ⚠️  {e}")
        full_post = post

    print("\n🔧 Montando visit.json...")
    visit_json = build_visit_json(full_post, slug)
    print(f"  visit_id : {visit_json.get('visit_id', '—')}")
    print(f"  title_pt : {visit_json.get('title_pt', '—')}")
    print(f"  days     : {len(visit_json.get('days', []))} dia(s)")

    print(f"\n📤 Salvando → visits/{slug}/visit.json ...")
    ok = save_to_github(slug, visit_json)

    if ok:
        print(f"\n🎉 Pronto! Abra a Revista com código: {slug}")
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
