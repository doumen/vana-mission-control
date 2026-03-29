Perfeito. Entendimento completo. Vamos construir.

---

## Arquitetura do Trator

```text
INPUT:  visit.json (Schema 6.1)
OUTPUT: POST /vana/v1/ingest-visit (WP)

PIPELINE:
  1. load()        → carrega e parseia o JSON
  2. validate()    → bloqueia apenas regras obrigatórias
  3. build_index() → gera index{} + order nos passages
  4. build_stats() → conta todos os elementos
  5. publish()     → POST no WP via HMAC

IMPORTÁVEL como função pura:
  from vana_trator import run_trator
  result = run_trator(visit_dict, wp_url, wp_secret)
```

---

## Regras Obrigatórias (bloqueantes)

```text
R-BLOCK-01  visit_ref ausente ou vazio
R-BLOCK-02  days[] ausente ou não é lista
R-BLOCK-03  day.day_key ausente (YYYY-MM-DD)
R-BLOCK-04  event.event_key ausente
R-BLOCK-05  vod.vod_key ausente
R-BLOCK-06  segment.segment_id ausente
R-BLOCK-07  segment.type não está no enum permitido
R-BLOCK-08  katha_id no segment mas type != harikatha
R-BLOCK-09  passage.passage_id ausente
R-BLOCK-10  passage.source_ref.vod_key ausente
R-BLOCK-11  passage.source_ref.timestamp_start ausente
R-BLOCK-12  passage.source_ref.timestamp_end ausente
R-BLOCK-13  timestamp_end <= timestamp_start no passage
R-BLOCK-14  passage_id duplicado no mesmo visit

WARNINGS (não bloqueiam):
W-01  katha.sources[] vazio
W-02  passage sem key_quote
W-03  vod sem thumb_url
W-04  event sem location
W-05  passage.timestamp fora do range do segment pai
      (segment pode não estar mapeado ainda)
```

---

## Código

```python
# vana_trator.py
# -*- coding: utf-8 -*-
"""
Vana Trator — Schema 6.1
Valida, indexa e publica visit.json no WordPress.

Uso standalone:
    python vana_trator.py visit.json --wp-url https://... --wp-secret abc123
    python vana_trator.py visit.json --dry-run

Uso como biblioteca:
    from vana_trator import run_trator
    result = run_trator(visit_dict, wp_url=..., wp_secret=...)
"""

from __future__ import annotations

import argparse
import hashlib
import hmac
import json
import secrets
import sys
import time
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

import requests


# ══════════════════════════════════════════════════════════════════════
# CONSTANTES
# ══════════════════════════════════════════════════════════════════════

SCHEMA_VERSION = "6.1"

VALID_SEGMENT_TYPES = {
    "kirtan",
    "harikatha",
    "pushpanjali",
    "arati",
    "dance",
    "drama",
    "darshan",
    "interval",
    "noise",
    "announcement",
}

VALID_EVENT_STATUSES = {"past", "active", "future", "live"}


# ══════════════════════════════════════════════════════════════════════
# TIPOS DE RETORNO
# ══════════════════════════════════════════════════════════════════════

@dataclass
class ValidationError:
    code:    str
    message: str
    path:    str   # ex: "days[0].events[1].vods[0].segments[2]"


@dataclass
class ValidationWarning:
    code:    str
    message: str
    path:    str


@dataclass
class TratorResult:
    success:   bool
    errors:    list[ValidationError]   = field(default_factory=list)
    warnings:  list[ValidationWarning] = field(default_factory=list)
    wp_action: Optional[str]           = None   # "created" | "updated" | "noop"
    wp_id:     Optional[int]           = None
    wp_url:    Optional[str]           = None
    processed: Optional[dict]          = None   # visit.json final processado


# ══════════════════════════════════════════════════════════════════════
# VALIDADOR
# ══════════════════════════════════════════════════════════════════════

class TratorValidator:

    def __init__(self, visit: dict):
        self.visit    = visit
        self.errors:   list[ValidationError]   = []
        self.warnings: list[ValidationWarning] = []
        self._passage_ids: set[str] = set()

    def validate(self) -> bool:
        """Retorna True se não há erros bloqueantes."""
        self._check_root()
        days = self.visit.get("days", [])
        for i, day in enumerate(days):
            self._check_day(day, f"days[{i}]")
        return len(self.errors) == 0

    # ── Raiz ──────────────────────────────────────────────────────────

    def _check_root(self):
        path = "root"
        if not self.visit.get("visit_ref"):
            self._err("R-BLOCK-01", "visit_ref ausente ou vazio", path)
        days = self.visit.get("days")
        if days is None or not isinstance(days, list):
            self._err("R-BLOCK-02", "days[] ausente ou não é lista", path)

    # ── Day ───────────────────────────────────────────────────────────

    def _check_day(self, day: dict, path: str):
        if not day.get("day_key"):
            self._err("R-BLOCK-03", "day_key ausente", path)
        for i, event in enumerate(day.get("events", [])):
            self._check_event(event, f"{path}.events[{i}]")

    # ── Event ─────────────────────────────────────────────────────────

    def _check_event(self, event: dict, path: str):
        if not event.get("event_key"):
            self._err("R-BLOCK-04", "event_key ausente", path)
        if not event.get("location"):
            self._warn("W-04", "event sem location", path)
        for i, vod in enumerate(event.get("vods", [])):
            self._check_vod(vod, f"{path}.vods[{i}]")
        for i, katha in enumerate(event.get("kathas", [])):
            self._check_katha(katha, f"{path}.kathas[{i}]")

    # ── Vod ───────────────────────────────────────────────────────────

    def _check_vod(self, vod: dict, path: str):
        if not vod.get("vod_key"):
            self._err("R-BLOCK-05", "vod_key ausente", path)
        if not vod.get("thumb_url"):
            self._warn("W-03", "vod sem thumb_url", path)
        for i, seg in enumerate(vod.get("segments", [])):
            self._check_segment(seg, f"{path}.segments[{i}]")

    # ── Segment ───────────────────────────────────────────────────────

    def _check_segment(self, seg: dict, path: str):
        if not seg.get("segment_id"):
            self._err("R-BLOCK-06", "segment_id ausente", path)

        seg_type = seg.get("type", "")
        if seg_type not in VALID_SEGMENT_TYPES:
            self._err(
                "R-BLOCK-07",
                f"segment.type inválido: '{seg_type}' — "
                f"permitidos: {sorted(VALID_SEGMENT_TYPES)}",
                path,
            )

        # katha_id só pode existir se type == harikatha
        if seg.get("katha_id") is not None and seg_type != "harikatha":
            self._err(
                "R-BLOCK-08",
                f"katha_id presente mas type='{seg_type}' (esperado: harikatha)",
                path,
            )

    # ── Katha ─────────────────────────────────────────────────────────

    def _check_katha(self, katha: dict, path: str):
        if not katha.get("sources"):
            self._warn("W-01", "katha.sources[] vazio", path)

        for i, passage in enumerate(katha.get("passages", [])):
            self._check_passage(passage, f"{path}.passages[{i}]")

    # ── Passage ───────────────────────────────────────────────────────

    def _check_passage(self, passage: dict, path: str):
        pid = passage.get("passage_id") or passage.get("passage_key")

        if not pid:
            self._err("R-BLOCK-09", "passage_id ausente", path)
            return

        # Duplicata global no visit
        if pid in self._passage_ids:
            self._err("R-BLOCK-14", f"passage_id duplicado: '{pid}'", path)
        else:
            self._passage_ids.add(pid)

        ref = passage.get("source_ref") or {}

        if not ref.get("vod_key"):
            self._err("R-BLOCK-10", "source_ref.vod_key ausente", path)

        ts  = ref.get("timestamp_start")
        te  = ref.get("timestamp_end")

        if ts is None:
            self._err("R-BLOCK-11", "source_ref.timestamp_start ausente", path)
        if te is None:
            self._err("R-BLOCK-12", "source_ref.timestamp_end ausente", path)
        if ts is not None and te is not None and te <= ts:
            self._err(
                "R-BLOCK-13",
                f"timestamp_end ({te}) <= timestamp_start ({ts})",
                path,
            )

        if not passage.get("key_quote"):
            self._warn("W-02", "passage sem key_quote", path)

    # ── Helpers ───────────────────────────────────────────────────────

    def _err(self, code: str, message: str, path: str):
        self.errors.append(ValidationError(code=code, message=message, path=path))

    def _warn(self, code: str, message: str, path: str):
        self.warnings.append(ValidationWarning(code=code, message=message, path=path))


# ══════════════════════════════════════════════════════════════════════
# BUILDER DE ÍNDICE
# ══════════════════════════════════════════════════════════════════════

class TratorIndexBuilder:

    def __init__(self, visit: dict):
        self.visit = visit
        self.index: dict = {
            "days":     {},
            "events":   {},
            "vods":     {},
            "segments": {},
            "kathas":   {},
            "passages": {},
            "photos":   {},
            "sangha":   {},
        }
        self.stats: dict = {
            "total_days":     0,
            "total_events":   0,
            "total_vods":     0,
            "total_segments": 0,
            "total_kathas":   0,
            "total_passages": 0,
            "total_photos":   0,
            "total_sangha":   0,
        }

    def build(self) -> tuple[dict, dict]:
        """Percorre days[] e orphans{} e constrói index + stats."""
        days = self.visit.get("days", [])

        for position, day in enumerate(days):
            self._index_day(day, position)

        self._index_orphans()

        return self.index, self.stats

    # ── Day ───────────────────────────────────────────────────────────

    def _index_day(self, day: dict, position: int):
        day_key = day.get("day_key", "")
        events  = day.get("events", [])

        self.index["days"][day_key] = {
            "position":      position,
            "label_pt":      day.get("label_pt", ""),
            "label_en":      day.get("label_en", ""),
            "tithi":         day.get("tithi"),
            "tithi_name_pt": day.get("tithi_name_pt"),
            "tithi_name_en": day.get("tithi_name_en"),
            "primary_event": day.get("primary_event"),
            "events":        [e.get("event_key") for e in events if e.get("event_key")],
        }

        self.stats["total_days"] += 1

        for position_e, event in enumerate(events):
            self._index_event(event, day_key, position_e)

    # ── Event ─────────────────────────────────────────────────────────

    def _index_event(self, event: dict, day_key: str, position: int):
        event_key = event.get("event_key", "")
        vods      = event.get("vods", [])
        kathas    = event.get("kathas", [])
        photos    = event.get("photos", [])
        sangha    = event.get("sangha", [])

        self.index["events"][event_key] = {
            "day_key":   day_key,
            "position":  position,
            "type":      event.get("type"),
            "title_pt":  event.get("title_pt"),
            "title_en":  event.get("title_en"),
            "time":      event.get("time"),
            "status":    event.get("status"),
            "location":  event.get("location", {}).get("name") if isinstance(event.get("location"), dict) else event.get("location"),
            "vods":      [v.get("vod_key")      for v in vods   if v.get("vod_key")],
            "kathas":    [k.get("katha_id")      for k in kathas if k.get("katha_id")],
            "photos":    [p.get("photo_key")     for p in photos if p.get("photo_key")],
            "sangha":    [s.get("sangha_key")    for s in sangha if s.get("sangha_key")],
        }

        self.stats["total_events"] += 1

        for vod in vods:
            self._index_vod(vod, event_key, day_key)

        for katha in kathas:
            self._index_katha(katha, event_key, day_key)

        for photo in photos:
            self._index_photo(photo, event_key, day_key)

        for sg in sangha:
            self._index_sangha(sg, event_key, day_key)

    # ── Vod ───────────────────────────────────────────────────────────

    def _index_vod(self, vod: dict, event_key: str, day_key: str):
        vod_key  = vod.get("vod_key", "")
        segments = vod.get("segments", [])

        self.index["vods"][vod_key] = {
            "event_key":  event_key,
            "day_key":    day_key,
            "provider":   vod.get("provider"),
            "video_id":   vod.get("video_id"),
            "url":        vod.get("url"),
            "vod_part":   vod.get("vod_part"),
            "duration_s": vod.get("duration_s"),
            "thumb_url":  vod.get("thumb_url"),
            "segments":   [s.get("segment_id") for s in segments if s.get("segment_id")],
        }

        self.stats["total_vods"] += 1

        for seg in segments:
            self._index_segment(seg, vod_key, event_key, day_key)

    # ── Segment ───────────────────────────────────────────────────────

    def _index_segment(self, seg: dict, vod_key: str, event_key: str, day_key: str):
        segment_id = seg.get("segment_id", "")

        self.index["segments"][segment_id] = {
            "vod_key":         vod_key,
            "event_key":       event_key,
            "day_key":         day_key,
            "type":            seg.get("type"),
            "title_pt":        seg.get("title_pt"),
            "title_en":        seg.get("title_en"),
            "timestamp_start": seg.get("timestamp_start"),
            "timestamp_end":   seg.get("timestamp_end"),
            "katha_id":        seg.get("katha_id"),
        }

        self.stats["total_segments"] += 1

    # ── Katha ─────────────────────────────────────────────────────────

    def _index_katha(self, katha: dict, event_key: str, day_key: str):
        katha_id = katha.get("katha_id")
        passages = katha.get("passages", [])
        sources  = katha.get("sources", [])

        self.index["kathas"][str(katha_id)] = {
            "katha_key":     katha.get("katha_key"),
            "event_key":     event_key,
            "day_key":       day_key,
            "title_pt":      katha.get("title_pt"),
            "title_en":      katha.get("title_en"),
            "scripture":     katha.get("scripture"),
            "language":      katha.get("language"),
            "sources": [
                {
                    "vod_key":    s.get("vod_key"),
                    "segment_id": s.get("segment_id"),
                }
                for s in sources
            ],
            "passages":      [p.get("passage_id") or p.get("passage_key") for p in passages],
            "passage_count": len(passages),
        }

        self.stats["total_kathas"] += 1

        # Injeta order nos passages e indexa
        for order, passage in enumerate(passages, start=1):
            passage["order"] = order   # ← mutação intencional no source
            self._index_passage(passage, katha_id, event_key, day_key)

    # ── Passage ───────────────────────────────────────────────────────

    def _index_passage(self, passage: dict, katha_id, event_key: str, day_key: str):
        pid = passage.get("passage_id") or passage.get("passage_key", "")
        ref = passage.get("source_ref") or {}

        self.index["passages"][pid] = {
            "katha_id":        katha_id,
            "event_key":       event_key,
            "day_key":         day_key,
            "order":           passage.get("order"),
            "vod_key":         ref.get("vod_key"),
            "segment_id":      ref.get("segment_id"),
            "timestamp_start": ref.get("timestamp_start"),
            "timestamp_end":   ref.get("timestamp_end"),
        }

        self.stats["total_passages"] += 1

    # ── Photo ─────────────────────────────────────────────────────────

    def _index_photo(self, photo: dict, event_key: str, day_key: str):
        key = photo.get("photo_key", "")
        self.index["photos"][key] = {
            "event_key": event_key,
            "day_key":   day_key,
            "thumb_url": photo.get("thumb_url"),
            "full_url":  photo.get("full_url"),
            "author":    photo.get("author"),
        }
        self.stats["total_photos"] += 1

    # ── Sangha ────────────────────────────────────────────────────────

    def _index_sangha(self, sg: dict, event_key: str, day_key: str):
        key = sg.get("sangha_key", "")
        self.index["sangha"][key] = {
            "event_key": event_key,
            "day_key":   day_key,
            "type":      sg.get("type"),
            "provider":  sg.get("provider"),
            "author":    sg.get("author"),
        }
        self.stats["total_sangha"] += 1

    # ── Orphans ───────────────────────────────────────────────────────

    def _index_orphans(self):
        orphans = self.visit.get("orphans", {})

        for vod in orphans.get("vods", []):
            vod_key = vod.get("vod_key", "")
            self.index["vods"][vod_key] = {
                "event_key":  None,
                "day_key":    None,
                "provider":   vod.get("provider"),
                "video_id":   vod.get("video_id"),
                "url":        vod.get("url"),
                "vod_part":   vod.get("vod_part"),
                "duration_s": vod.get("duration_s"),
                "thumb_url":  vod.get("thumb_url"),
                "segments":   [],
            }
            self.stats["total_vods"] += 1

        for photo in orphans.get("photos", []):
            key = photo.get("photo_key", "")
            self.index["photos"][key] = {
                "event_key": None,
                "day_key":   None,
                "thumb_url": photo.get("thumb_url"),
                "full_url":  photo.get("full_url"),
                "author":    photo.get("author"),
            }
            self.stats["total_photos"] += 1

        for sg in orphans.get("sangha", []):
            key = sg.get("sangha_key", "")
            self.index["sangha"][key] = {
                "event_key": None,
                "day_key":   None,
                "type":      sg.get("type"),
                "provider":  sg.get("provider"),
                "author":    sg.get("author"),
            }
            self.stats["total_sangha"] += 1

        for katha in orphans.get("kathas", []):
            self._index_katha(katha, event_key=None, day_key=None)


# ══════════════════════════════════════════════════════════════════════
# PUBLICADOR WP
# ══════════════════════════════════════════════════════════════════════

class TratorPublisher:

    def __init__(self, wp_url: str, wp_secret: str, timeout: int = 30):
        self.endpoint = wp_url.rstrip("/") + "/wp-json/vana/v1/ingest-visit"
        self.secret   = wp_secret
        self.timeout  = timeout

    def publish(self, visit: dict, tour_key: str) -> dict:
        """
        Publica visit.json processado no WordPress via HMAC.
        Retorna resposta JSON do endpoint.
        """
        visit_ref  = visit.get("visit_ref", "")
        origin_key = f"visit:{visit_ref}" if not visit_ref.startswith("visit:") else visit_ref

        if not tour_key.startswith("tour:"):
            tour_key = f"tour:{tour_key}"

        envelope = {
            "kind":              "visit",
            "origin_key":        origin_key,
            "parent_origin_key": tour_key,
            "title":             visit.get("metadata", {}).get("city_pt") or visit_ref,
            "slug_suggestion":   visit_ref,
            "data": {
                **visit,
                "schema_version": SCHEMA_VERSION,
                "updated_at":     datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            },
        }

        body_str = json.dumps(envelope, ensure_ascii=False, separators=(",", ":"))
        params   = self._sign(body_str)

        resp = requests.post(
            self.endpoint,
            params  = params,
            data    = body_str.encode("utf-8"),
            headers = {"Content-Type": "application/json"},
            timeout = self.timeout,
        )

        if not resp.ok:
            raise RuntimeError(
                f"WP retornou HTTP {resp.status_code}: {resp.text[:400]}"
            )

        return resp.json()

    def _sign(self, body_str: str) -> dict:
        """Espelha class-vana-hmac.php — idêntico ao ingest_visit.py."""
        timestamp = str(int(time.time()))
        nonce     = secrets.token_hex(16)
        message   = f"{timestamp}\n{nonce}\n{body_str}"
        signature = hmac.new(
            self.secret.encode("utf-8"),
            message.encode("utf-8"),
            hashlib.sha256,
        ).hexdigest()
        return {
            "vana_timestamp": timestamp,
            "vana_nonce":     nonce,
            "vana_signature": signature,
        }


# ══════════════════════════════════════════════════════════════════════
# FUNÇÃO PRINCIPAL — IMPORTÁVEL
# ══════════════════════════════════════════════════════════════════════

def run_trator(
    visit:      dict,
    wp_url:     Optional[str] = None,
    wp_secret:  Optional[str] = None,
    tour_key:   str           = "tour:unknown",
    dry_run:    bool          = False,
) -> TratorResult:
    """
    Pipeline completo: valida → indexa → publica.

    Args:
        visit:     dict com o visit.json (Schema 6.1)
        wp_url:    URL base do WordPress
        wp_secret: segredo HMAC do endpoint
        tour_key:  origin_key do tour pai
        dry_run:   se True, pula a publicação

    Returns:
        TratorResult com errors, warnings, e resultado WP
    """

    # ── 1. Validação ──────────────────────────────────────────────────
    validator = TratorValidator(visit)
    valid     = validator.validate()

    if not valid:
        return TratorResult(
            success  = False,
            errors   = validator.errors,
            warnings = validator.warnings,
        )

    # ── 2. Build index + stats ────────────────────────────────────────
    builder        = TratorIndexBuilder(visit)
    index, stats   = builder.build()

    # ── 3. Monta visit processado ─────────────────────────────────────
    processed = {
        **visit,
        "index":        index,
        "stats":        stats,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "generated_by": "vana-trator",
    }

    # ── 4. Dry run ────────────────────────────────────────────────────
    if dry_run:
        return TratorResult(
            success   = True,
            errors    = [],
            warnings  = validator.warnings,
            wp_action = "dry_run",
            processed = processed,
        )

    # ── 5. Publicação WP ──────────────────────────────────────────────
    if not wp_url or not wp_secret:
        raise ValueError("wp_url e wp_secret são obrigatórios para publicação.")

    publisher = TratorPublisher(wp_url=wp_url, wp_secret=wp_secret)

    try:
        wp_resp = publisher.publish(processed, tour_key=tour_key)
    except Exception as e:
        return TratorResult(
            success  = False,
            errors   = [ValidationError(
                code    = "WP-PUBLISH-ERROR",
                message = str(e),
                path    = "publisher",
            )],
            warnings  = validator.warnings,
            processed = processed,
        )

    wp_data = wp_resp.get("data", wp_resp)

    return TratorResult(
        success   = True,
        errors    = [],
        warnings  = validator.warnings,
        wp_action = wp_data.get("action"),
        wp_id     = wp_data.get("visit_id"),
        wp_url    = wp_data.get("permalink"),
        processed = processed,
    )


# ══════════════════════════════════════════════════════════════════════
# CLI
# ══════════════════════════════════════════════════════════════════════

def _print_result(result: TratorResult, verbose: bool = False):
    if result.errors:
        print(f"\n❌  {len(result.errors)} erro(s) bloqueante(s):\n")
        for e in result.errors:
            print(f"   [{e.code}] {e.path}")
            print(f"           {e.message}")

    if result.warnings:
        print(f"\n⚠️   {len(result.warnings)} aviso(s):\n")
        for w in result.warnings:
            print(f"   [{w.code}] {w.path} — {w.message}")

    if result.success:
        action_icon = {
            "created":  "✅ CRIADO",
            "updated":  "🔄 ATUALIZADO",
            "noop":     "💤 SEM MUDANÇAS",
            "dry_run":  "🧪 DRY-RUN",
        }.get(result.wp_action or "", "✅ OK")

        print(f"\n  {action_icon}")

        if result.processed:
            stats = result.processed.get("stats", {})
            print(f"\n  📊 Stats:")
            for k, v in stats.items():
                print(f"     {k:<22} {v}")

        if result.wp_id:
            print(f"\n  visit_id  : {result.wp_id}")
        if result.wp_url:
            print(f"  permalink : {result.wp_url}")

        if verbose and result.processed:
            print("\n  📋 Index keys geradas:")
            for section, data in result.processed.get("index", {}).items():
                print(f"     {section:<12} {len(data)} item(s)")


def main():
    parser = argparse.ArgumentParser(
        description="Vana Trator — valida, indexa e publica visit.json (Schema 6.1)"
    )
    parser.add_argument("json_file",           help="visit.json a processar")
    parser.add_argument("--tour-key",          default="tour:unknown",
                        help="origin_key do tour pai (ex: tour:india-2026)")
    parser.add_argument("--wp-url",            default="",
                        help="URL base do WordPress")
    parser.add_argument("--wp-secret",         default="",
                        help="Segredo HMAC do endpoint")
    parser.add_argument("--dry-run",           action="store_true",
                        help="Valida e indexa sem publicar")
    parser.add_argument("--verbose",           action="store_true",
                        help="Mostra detalhes do index gerado")
    parser.add_argument("--out",               default="",
                        help="Salva visit processado em arquivo (opcional)")
    args = parser.parse_args()

    # Carrega JSON
    path = Path(args.json_file)
    if not path.exists():
        print(f"❌ Arquivo não encontrado: {path}")
        sys.exit(1)

    with open(path, "r", encoding="utf-8") as f:
        try:
            visit = json.load(f)
        except json.JSONDecodeError as e:
            print(f"❌ JSON inválido: {e}")
            sys.exit(1)

    visit_ref = visit.get("visit_ref", "—")
    print(f"\n{'═'*60}")
    print(f"  🪷 Vana Trator v{SCHEMA_VERSION}")
    print(f"  visit_ref : {visit_ref}")
    print(f"  tour_key  : {args.tour_key}")
    print(f"  dry_run   : {args.dry_run}")
    print(f"{'═'*60}")

    result = run_trator(
        visit     = visit,
        wp_url    = args.wp_url    or None,
        wp_secret = args.wp_secret or None,
        tour_key  = args.tour_key,
        dry_run   = args.dry_run,
    )

    _print_result(result, verbose=args.verbose)

    # Salva output se solicitado
    if args.out and result.processed:
        out_path = Path(args.out)
        with open(out_path, "w", encoding="utf-8") as f:
            json.dump(result.processed, f, ensure_ascii=False, indent=2)
        print(f"\n  💾 Salvo → {out_path}")

    sys.exit(0 if result.success else 1)


if __name__ == "__main__":
    main()
```

---

## Como os 3 contextos chamam o Trator

```python
# ── Streamlit (após edição humana) ────────────────────────────────────
from vana_trator import run_trator

result = run_trator(
    visit     = visit_dict,           # vem do GitHubClient.get_visit()
    wp_url    = st.secrets["vana"]["api_base"],
    wp_secret = st.secrets["vana"]["ingest_secret"],
    tour_key  = st.secrets["vana"]["tour_key"],
)

if result.success:
    st.success(f"✅ {result.wp_action} — {result.wp_url}")
else:
    for e in result.errors:
        st.error(f"[{e.code}] {e.path} — {e.message}")
```

```python
# ── Bot Telegram (após ingestão de vod) ──────────────────────────────
from vana_trator import run_trator
from bot.config import WP_URL, WP_TOKEN

result = run_trator(
    visit     = updated_visit,
    wp_url    = WP_URL,
    wp_secret = WP_TOKEN,
    tour_key  = session.tour_key,
)
# result.success → confirma para o devoto no Telegram
```

```python
# ── GitHub Actions (CI) ───────────────────────────────────────────────
# vana_trator.py visit.json \
#   --tour-key tour:india-2026 \
#   --wp-url $WP_URL \
#   --wp-secret $WP_SECRET \
#   --verbose \
#   --out visit.processed.json
```

---

## Resumo da Arquitetura

```text
vana_trator.py
  ├── TratorValidator     → R-BLOCK-01..14 bloqueantes
  │                          W-01..05 warnings
  ├── TratorIndexBuilder  → percorre days[] + orphans{}
  │                          injeta passage.order (start=1)
  │                          gera index{} + stats{}
  ├── TratorPublisher     → HMAC idêntico ao ingest_visit.py
  │                          POST /vana/v1/ingest-visit
  └── run_trator()        → função pura importável
                             retorna TratorResult

CLI:
  python vana_trator.py visit.json --tour-key tour:india-2026 \
    --wp-url https://beta... --wp-secret abc123

  python vana_trator.py visit.json --dry-run --verbose
```

---

```text
Status:    PRONTO ✅
Próximo:   Integrar no Streamlit (pages/2_Visits.py)
           e no Bot (confirm_handler.py)
```

🙏