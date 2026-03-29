#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ingest_visit.py — Vana Mission Control
Insere ou atualiza uma visita via POST /vana/v1/ingest-visit (schema 3.1)

Uso:
    python ingest_visit.py visit.json tour:vrindavan-2026
    python ingest_visit.py visit.json tour:vrindavan-2026 --dry-run
    python ingest_visit.py visit.json tour:vrindavan-2026 --skip-validation
"""

import sys
import os
import time
import hmac
import hashlib
import secrets
import json
import argparse
from pathlib import Path
from datetime import datetime, timezone
import requests

# ── Config ─────────────────────────────────────────────────────────────────
WP_URL        = os.getenv("WP_URL",            "https://beta.vanamadhuryamdaily.com")
INGEST_SECRET = os.getenv("VANA_INGEST_SECRET", "57ab0c97f436f7ed6662db5632c8d6dcec58a0f810569cfa7bd328c4321f8a7d")
ENDPOINT      = f"{WP_URL.rstrip('/')}/wp-json/vana/v1/ingest-visit"


# ══════════════════════════════════════════════════════════════════════════
# HMAC — espelha exatamente o class-vana-hmac.php
# ══════════════════════════════════════════════════════════════════════════
def _sign(body_str: str) -> dict:
    timestamp = str(int(time.time()))
    nonce     = secrets.token_hex(16)
    message   = f"{timestamp}\n{nonce}\n{body_str}"
    signature = hmac.new(
        INGEST_SECRET.encode("utf-8"),
        message.encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()
    return {
        "vana_timestamp": timestamp,
        "vana_nonce":     nonce,
        "vana_signature": signature,
    }


# ══════════════════════════════════════════════════════════════════════════
# CLEANER — remove chaves _comment, _docs, _comment recursivamente
# ══════════════════════════════════════════════════════════════════════════
def _clean(obj):
    if isinstance(obj, dict):
        return {k: _clean(v) for k, v in obj.items() if not k.startswith("_")}
    if isinstance(obj, list):
        return [_clean(i) for i in obj]
    return obj


# ══════════════════════════════════════════════════════════════════════════
# BUILDER — monta envelope exato que o PHP espera (schema 3.1)
#
# Estrutura confirmada pelo handler:
#
#   {
#     "kind":               "visit",          ← validado no endpoint
#     "origin_key":         "visit:xxx",      ← prefixo obrigatório
#     "parent_origin_key":  "tour:yyy",       ← prefixo obrigatório
#     "title":              "Título PT",      ← $payload['title'] no handler
#     "slug_suggestion":    "slug-aqui",      ← $payload['slug_suggestion']
#     "data": {
#       "schema_version": "3.1",             ← OBRIGATÓRIO pelo handler
#       "updated_at":     "2026-02-23T...",  ← ISO 8601, usado para ordenação
#       ...campos v2.6 limpos...
#     }
#   }
# ══════════════════════════════════════════════════════════════════════════
def build_envelope(payload: dict, parent_tour_key: str) -> dict:
    visit_id = payload.get("visit_id", "").strip()
    if not visit_id:
        raise ValueError("❌ Campo 'visit_id' ausente no JSON.")

    # Garante prefixos corretos
    origin_key = f"visit:{visit_id}" if not visit_id.startswith("visit:") else visit_id
    if not parent_tour_key.startswith("tour:"):
        parent_tour_key = f"tour:{parent_tour_key}"

    # Data limpa (sem _comment/_docs)
    data_clean = _clean(payload)

    # Injeta campos obrigatórios do schema 3.1
    data_clean["schema_version"] = "3.1"
    data_clean["updated_at"]     = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    # Título e slug para o post WP (ficam no ENVELOPE, não em data)
    title_pt = payload.get("title_pt") or f"Visita: {visit_id}"
    slug      = payload.get("visit_id", "").replace(":", "-")

    return {
        "kind":               "visit",
        "origin_key":         origin_key,
        "parent_origin_key":  parent_tour_key,
        "title":              title_pt,
        "slug_suggestion":    slug,
        "data":               data_clean,
    }


# ══════════════════════════════════════════════════════════════════════════
# VALIDAÇÃO do schema v2.6 / 3.1
# ══════════════════════════════════════════════════════════════════════════
VALID_STATUSES = {"done", "live", "upcoming", ""}

def validate(payload: dict) -> list[str]:
    errors = []

    # Raiz
    for field in ["visit_id", "title_pt", "timezone", "days"]:
        if not payload.get(field):
            errors.append(f"Raiz: campo obrigatório ausente → '{field}'")

    days = payload.get("days", [])
    if not isinstance(days, list) or len(days) == 0:
        errors.append("'days' deve ser uma lista não-vazia.")
        return errors
    if len(days) > 400:
        errors.append(f"'days' excede 400 itens (tem {len(days)}).")

    for i, day in enumerate(days):
        label = day.get("date_local") or f"days[{i}]"

        # Campos obrigatórios do dia
        if not day.get("date_local"):
            errors.append(f"{label}: 'date_local' ausente.")
        if not day.get("label_pt"):
            errors.append(f"{label}: 'label_pt' ausente.")

        # Hero
        hero = day.get("hero")
        if not hero:
            errors.append(f"{label}: 'hero' ausente.")
        else:
            if not hero.get("title_pt"):
                errors.append(f"{label} > hero: 'title_pt' ausente.")
            media_fields = ["youtube_url", "instagram_url", "facebook_url", "drive_url"]
            if not any(hero.get(f) for f in media_fields):
                errors.append(f"{label} > hero: nenhuma fonte de mídia (youtube/instagram/facebook/drive).")

        # Schedule
        for j, s in enumerate(day.get("schedule", [])):
            ref = f"{label} > schedule[{j}]"
            if not s.get("time_local"):
                errors.append(f"{ref}: 'time_local' ausente.")
            if not s.get("title_pt"):
                errors.append(f"{ref}: 'title_pt' ausente.")
            status = s.get("status", "")
            if status not in VALID_STATUSES:
                errors.append(f"{ref}: status inválido '{status}' — use done/live/upcoming.")

        # VODs
        for j, vod in enumerate(day.get("vods", [])):
            ref = f"{label} > vods[{j}]"
            if not vod.get("title_pt"):
                errors.append(f"{ref}: 'title_pt' ausente.")
            media_fields = ["youtube_url", "facebook_url", "drive_url"]
            if not any(vod.get(f) for f in media_fields):
                errors.append(f"{ref}: nenhuma fonte de mídia.")

        # Photos
        for j, ph in enumerate(day.get("photos", [])):
            ref = f"{label} > photos[{j}]"
            if not ph.get("full_url") and not ph.get("thumb_url"):
                errors.append(f"{ref}: precisa de 'full_url' ou 'thumb_url'.")

        # Sangha moments
        for j, sm in enumerate(day.get("sangha_moments", [])):
            ref = f"{label} > sangha_moments[{j}]"
            if not sm.get("text_pt"):
                errors.append(f"{ref}: 'text_pt' ausente.")
            if not sm.get("author"):
                errors.append(f"{ref}: 'author' ausente.")

    return errors


# ══════════════════════════════════════════════════════════════════════════
# SEND
# ══════════════════════════════════════════════════════════════════════════
def send(envelope: dict, dry_run: bool = False) -> dict:
    body_str = json.dumps(envelope, ensure_ascii=False, separators=(",", ":"))
    params   = _sign(body_str)

    _banner(envelope, body_str)

    if dry_run:
        print("\n🧪 DRY-RUN — payload NÃO enviado ao WordPress.")
        print("\n📋 Envelope preview:\n")
        # Preview com data truncada para legibilidade
        preview = {**envelope, "data": {
            **{k: v for k, v in envelope["data"].items() if k != "days"},
            "days": f"[{len(envelope['data'].get('days', []))} dias — omitido no preview]"
        }}
        print(json.dumps(preview, ensure_ascii=False, indent=2))
        return {"dry_run": True, "body_bytes": len(body_str)}

    print("\n🚀 Enviando...")
    try:
        resp = requests.post(
            ENDPOINT,
            params=params,
            data=body_str.encode("utf-8"),
            headers={"Content-Type": "application/json"},
            timeout=30,
        )
    except requests.exceptions.ConnectionError:
        print(f"❌ Não foi possível conectar a {ENDPOINT}")
        sys.exit(1)
    except requests.exceptions.Timeout:
        print("❌ Timeout após 30s")
        sys.exit(1)

    print(f"   HTTP {resp.status_code}")

    try:
        result = resp.json()
    except Exception:
        result = {"raw": resp.text[:500]}

    if resp.ok:
        _print_success(result)
    else:
        print(f"\n❌ Erro HTTP {resp.status_code}")
        print(f"   {resp.text[:500]}")

    return result


def _banner(envelope: dict, body_str: str):
    print(f"\n{'═'*62}")
    print(f"  📡 {ENDPOINT}")
    print(f"  🔑 origin_key        : {envelope['origin_key']}")
    print(f"  🗺️  parent_origin_key : {envelope['parent_origin_key']}")
    print(f"  📝 title             : {envelope.get('title','—')}")
    print(f"  📦 dias              : {len(envelope['data'].get('days', []))}")
    print(f"  📏 payload           : {len(body_str):,} bytes")
    print(f"  🕐 updated_at        : {envelope['data'].get('updated_at','—')}")
    print(f"{'═'*62}")


def _print_success(result: dict):
    data = result.get("data", result)
    action = data.get("action", "?")
    icons  = {"created": "✅ CRIADO", "updated": "🔄 ATUALIZADO", "noop": "💤 SEM MUDANÇAS"}

    print(f"\n  {icons.get(action, '✅')} — {result.get('message','')}")
    print(f"  visit_id   : {data.get('visit_id','—')}")
    print(f"  origin_key : {data.get('origin_key','—')}")
    print(f"  tour_id    : {data.get('tour_id','—')} (tour_updated={data.get('tour_updated')})")
    print(f"  hash       : {data.get('hash','—')[:16]}...")
    if data.get("permalink"):
        print(f"  permalink  : {data['permalink']}")


# ══════════════════════════════════════════════════════════════════════════
# LOG
# ══════════════════════════════════════════════════════════════════════════
def save_log(json_path: Path, payload: dict, tour_key: str, result: dict):
    log_path = json_path.parent / f"ingest_log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
    log = {
        "timestamp":  datetime.now().isoformat(),
        "visit_id":   payload.get("visit_id"),
        "tour_key":   tour_key,
        "endpoint":   ENDPOINT,
        "result":     result,
    }
    with open(log_path, "w", encoding="utf-8") as f:
        json.dump(log, f, ensure_ascii=False, indent=2)
    print(f"\n  📝 Log salvo → {log_path.name}")


# ══════════════════════════════════════════════════════════════════════════
# MAIN
# ══════════════════════════════════════════════════════════════════════════
def main():
    parser = argparse.ArgumentParser(
        description="Ingest de visita — /vana/v1/ingest-visit (schema 3.1)"
    )
    parser.add_argument("json_file",  help="Arquivo JSON da visita (schema v2.6/3.1)")
    parser.add_argument("tour_key",   help="Origin key do tour pai. Ex: tour:vrindavan-2026")
    parser.add_argument("--dry-run",         action="store_true", help="Valida sem enviar")
    parser.add_argument("--skip-validation", action="store_true", help="Força envio sem validar")
    args = parser.parse_args()

    # 1. Lê JSON
    json_path = Path(args.json_file)
    if not json_path.exists():
        print(f"❌ Arquivo não encontrado: {json_path}")
        sys.exit(1)

    with open(json_path, "r", encoding="utf-8") as f:
        try:
            payload = json.load(f)
        except json.JSONDecodeError as e:
            print(f"❌ JSON inválido: {e}")
            sys.exit(1)

    print(f"\n✅ JSON carregado: {json_path.name}")
    print(f"   visit_id : {payload.get('visit_id','—')}")
    print(f"   title_pt : {payload.get('title_pt','—')}")
    print(f"   dias     : {len(payload.get('days', []))}")

    # 2. Valida schema
    if not args.skip_validation:
        errors = validate(payload)
        if errors:
            print(f"\n⚠️  {len(errors)} erro(s) de validação:")
            for e in errors:
                print(f"   • {e}")
            print("\n   Use --skip-validation para forçar o envio.")
            sys.exit(1)
        print("   ✅ Validação OK")

    # 3. Monta envelope
    try:
        envelope = build_envelope(payload, args.tour_key)
    except ValueError as e:
        print(e)
        sys.exit(1)

    # 4. Envia
    result = send(envelope, dry_run=args.dry_run)

    # 5. Salva log (apenas envio real)
    if not args.dry_run:
        save_log(json_path, payload, args.tour_key, result)


if __name__ == "__main__":
    main()
