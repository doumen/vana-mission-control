#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ingest_tour.py — Vana Mission Control
Cria ou atualiza um vana_tour via WP REST API (Application Password).
Tours NÃO têm handler HMAC real — usam wp/v2/vana_tour.

Uso:
    python ingest_tour.py tour-sample.json
    python ingest_tour.py tour-sample.json --dry-run
"""

import sys
import os
import json
import argparse
from pathlib import Path
from datetime import datetime
import requests

# ── Config ─────────────────────────────────────────────────────────────────
WP_URL       = os.getenv("WP_URL",       "https://beta.vanamadhuryamdaily.com")
WP_USER      = os.getenv("WP_USER",      "")
WP_APP_PASS  = os.getenv("WP_APP_PASS",  "")
BASE         = f"{WP_URL.rstrip('/')}/wp-json"

# ── Auth ───────────────────────────────────────────────────────────────────
def _auth() -> tuple:
    user = WP_USER or input("WP user: ").strip()
    pwd  = WP_APP_PASS or input("WP app password: ").strip()
    return (user, pwd)


# ══════════════════════════════════════════════════════════════════════════
# SCHEMA — tour-sample.json (todos os campos reais confirmados no banco)
# ══════════════════════════════════════════════════════════════════════════
TOUR_SCHEMA = {
    # Identificação
    "origin_key":   "tour:india-2026",          # _vana_origin_key
    "title":        "Tour Espiritual Índia 2026",
    "slug":         "india-2026",

    # Metas visuais
    "subtitle":     "Uma jornada para a alma",   # _tour_subtitle
    "theme":        "india",                     # _tour_theme
    "description":  "Descrição do tour.",        # _tour_description
    "dates":        "01 a 15 de Novembro",       # _tour_dates
    "organizers":   "Vana Team",                 # _tour_organizers

    # Datas de controle
    "start_date":   "2026-03-01",               # _vana_start_date (YYYY-MM-DD)
    "is_current":   True,                        # _tour_is_current

    # Itinerário (array de cidades)
    "itinerary": [
        {
            "cidade":        "Vrindavan",
            "datas_locais":  "01-05 Nov",
            "hospedagem":    "MVT",
            "contato_local": ""
        }
    ],
}


# ══════════════════════════════════════════════════════════════════════════
# LOOKUP — busca tour por origin_key no banco
# ══════════════════════════════════════════════════════════════════════════
def find_tour_by_origin_key(origin_key: str, auth: tuple) -> int | None:
    """Retorna WP post ID ou None."""
    r = requests.get(
        f"{BASE}/wp/v2/vana_tour",
        params={
            "per_page": 100,
            "status":   "any",
            "_fields":  "id,slug,meta",
        },
        auth=auth,
        timeout=15,
    )
    r.raise_for_status()
    tours = r.json()

    for t in tours:
        meta = t.get("meta", {})
        if meta.get("_vana_origin_key") == origin_key:
            return t["id"]

    # Fallback: busca via wp-json direto no postmeta
    return None


# ══════════════════════════════════════════════════════════════════════════
# BUILD — monta body WP REST
# ══════════════════════════════════════════════════════════════════════════
def build_wp_body(tour: dict) -> dict:
    """Converte tour-sample.json para body da WP REST API."""
    itinerary_json = json.dumps(
        tour.get("itinerary", []),
        ensure_ascii=False
    )

    return {
        "title":       tour.get("title", ""),
        "status":      "publish",
        "slug":        tour.get("slug", ""),
        "meta": {
            "_vana_origin_key":   tour.get("origin_key", ""),
            "_vana_start_date":   tour.get("start_date", ""),
            "_tour_subtitle":     tour.get("subtitle", ""),
            "_tour_theme":        tour.get("theme", ""),
            "_tour_description":  tour.get("description", ""),
            "_tour_dates":        tour.get("dates", ""),
            "_tour_organizers":   tour.get("organizers", ""),
            "_tour_is_current":   "1" if tour.get("is_current") else "",
            "_tour_itinerary":    itinerary_json,
        },
    }


# ══════════════════════════════════════════════════════════════════════════
# UPSERT
# ══════════════════════════════════════════════════════════════════════════
def upsert_tour(tour: dict, auth: tuple, dry_run: bool = False) -> dict:
    origin_key = tour.get("origin_key", "")
    body       = build_wp_body(tour)

    _banner(tour, origin_key)

    if dry_run:
        print("\n🧪 DRY-RUN — body que seria enviado:\n")
        print(json.dumps(body, ensure_ascii=False, indent=2))
        return {"dry_run": True}

    # Tenta encontrar tour existente pelo origin_key
    existing_id = None
    try:
        existing_id = find_tour_by_origin_key(origin_key, auth)
    except Exception as e:
        print(f"   ⚠️  Lookup falhou ({e}) — tentará criar novo.")

    if existing_id:
        print(f"\n🔄 Tour existente encontrado (ID {existing_id}) — atualizando...")
        r = requests.post(
            f"{BASE}/wp/v2/vana_tour/{existing_id}",
            json=body, auth=auth, timeout=20,
        )
        action = "updated"
    else:
        print("\n✨ Tour novo — criando...")
        r = requests.post(
            f"{BASE}/wp/v2/vana_tour",
            json=body, auth=auth, timeout=20,
        )
        action = "created"

    print(f"   HTTP {r.status_code}")

    if not r.ok:
        print(f"   ❌ {r.text[:400]}")
        return {"error": r.text}

    result = r.json()
    _print_success(result, action)
    return result


def _banner(tour: dict, origin_key: str):
    print(f"\n{'═'*60}")
    print(f"  📡 {BASE}/wp/v2/vana_tour")
    print(f"  🔑 origin_key  : {origin_key}")
    print(f"  📝 title       : {tour.get('title','—')}")
    print(f"  📅 start_date  : {tour.get('start_date','—')}")
    print(f"  🌍 theme       : {tour.get('theme','—')}")
    print(f"  ⭐ is_current  : {tour.get('is_current', False)}")
    print(f"{'═'*60}")


def _print_success(result: dict, action: str):
    icons = {"created": "✅ CRIADO", "updated": "🔄 ATUALIZADO"}
    print(f"\n  {icons.get(action,'✅')}")
    print(f"  tour_id   : {result.get('id','—')}")
    print(f"  slug      : {result.get('slug','—')}")
    print(f"  status    : {result.get('status','—')}")
    if result.get("link"):
        print(f"  link      : {result['link']}")


# ══════════════════════════════════════════════════════════════════════════
# VALIDAÇÃO
# ══════════════════════════════════════════════════════════════════════════
def validate(tour: dict) -> list[str]:
    errors = []
    for f in ["origin_key", "title", "start_date"]:
        if not tour.get(f):
            errors.append(f"Campo obrigatório ausente: '{f}'")
    ok = tour.get("origin_key", "")
    if ok and not ok.startswith("tour:"):
        errors.append(f"'origin_key' deve começar com 'tour:' — tem: '{ok}'")
    sd = tour.get("start_date", "")
    if sd:
        try:
            datetime.strptime(sd, "%Y-%m-%d")
        except ValueError:
            errors.append(f"'start_date' deve ser YYYY-MM-DD — tem: '{sd}'")
    return errors


# ══════════════════════════════════════════════════════════════════════════
# MAIN
# ══════════════════════════════════════════════════════════════════════════
def main():
    parser = argparse.ArgumentParser(
        description="Ingest de tour — wp/v2/vana_tour (Application Password)"
    )
    parser.add_argument(
        "json_file", nargs="?",
        help="JSON do tour. Omita para usar o TOUR_SCHEMA embutido."
    )
    parser.add_argument("--dry-run",         action="store_true")
    parser.add_argument("--skip-validation", action="store_true")
    args = parser.parse_args()

    # 1. Carrega payload
    if args.json_file:
        path = Path(args.json_file)
        if not path.exists():
            print(f"❌ Arquivo não encontrado: {path}")
            sys.exit(1)
        with open(path, "r", encoding="utf-8") as f:
            try:
                tour = json.load(f)
            except json.JSONDecodeError as e:
                print(f"❌ JSON inválido: {e}")
                sys.exit(1)
        print(f"✅ JSON carregado: {path.name}")
    else:
        tour = TOUR_SCHEMA
        print("✅ Usando TOUR_SCHEMA embutido")

    print(f"   origin_key : {tour.get('origin_key','—')}")
    print(f"   title      : {tour.get('title','—')}")

    # 2. Valida
    if not args.skip_validation:
        errors = validate(tour)
        if errors:
            print(f"\n⚠️  {len(errors)} erro(s):")
            for e in errors:
                print(f"   • {e}")
            sys.exit(1)
        print("   ✅ Validação OK")

    # 3. Auth
    auth = _auth()

    # 4. Upsert
    result = upsert_tour(tour, auth, dry_run=args.dry_run)

    # 5. Log
    if not args.dry_run and not result.get("error"):
        log = {
            "timestamp":  datetime.now().isoformat(),
            "origin_key": tour.get("origin_key"),
            "result":     result,
        }
        log_path = Path(f"tour_log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json")
        with open(log_path, "w", encoding="utf-8") as f:
            json.dump(log, f, ensure_ascii=False, indent=2)
        print(f"\n  📝 Log salvo → {log_path.name}")


if __name__ == "__main__":
    main()
