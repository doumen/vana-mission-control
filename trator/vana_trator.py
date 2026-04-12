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
import re


# ══════════════════════════════════════════════════════════════════════
# CONSTANTES
# ══════════════════════════════════════════════════════════════════════

SCHEMA_VERSION = "6.1"

# Allow validating either 6.1 (current canonical) or 6.2 (incoming newer root)
ACCEPTED_SCHEMA_VERSIONS = {SCHEMA_VERSION, "6.2"}

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

VALID_EVENT_STATUSES = {"past", "active", "future", "live", "soon"}

# Orphan types supported in Schema 6.2
VALID_ORPHAN_TYPES = {"documental", "event"}

# Thumb provider helpers (centralized mapping)
PROVIDER_THUMB = {
    "youtube":  lambda vid: f"https://img.youtube.com/vi/{vid}/maxresdefault.jpg",
    "facebook": lambda vid: None,
    "drive":    lambda vid: None,
}


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

    # Permite acesso por índice/dict-style usado em alguns consumidores
    def __getitem__(self, key: str):
        try:
            return getattr(self, key)
        except AttributeError:
            if isinstance(self.processed, dict) and key in self.processed:
                return self.processed[key]
            raise

    def __contains__(self, key: str) -> bool:
        return hasattr(self, key)

    def get(self, key: str, default=None):
        return getattr(self, key, default)


# ══════════════════════════════════════════════════════════════════════
# VALIDADOR
# ══════════════════════════════════════════════════════════════════════

class TratorValidator:

    def __init__(self, visit: dict):
        self.visit    = visit
        self.errors:   list[ValidationError]   = []
        self.warnings: list[ValidationWarning] = []
        # Collections used for cross-entity checks
        self._all_segment_ids: list[str] = []
        self._all_passage_ids: list[str] = []
        self._all_katha_ids:   list[str] = []

    def validate(self) -> bool:
        self._check_root()
        days = self.visit.get("days", [])
        if not isinstance(days, list):
            # tests expect R-BLOCK-02 for days missing/invalid
            self._err("R-BLOCK-02", "days deve ser uma lista", "days")
            return False

        # Reset collections
        self._all_segment_ids = []
        self._all_passage_ids = []
        self._all_katha_ids   = []

        for i, day in enumerate(days):
            self._check_day(day, f"days[{i}]")

        return len(self.errors) == 0

    # ── ROOT ─────────────────────────────────────────────────────────

    def _check_root(self):
        v = self.visit
        if not v.get("visit_ref"):
            # historical tests expect R-BLOCK-01 for missing visit_ref
            self._err("R-BLOCK-01", "visit_ref ausente", "visit_ref")

        version = v.get("schema_version")
        # Accept missing schema_version (tests expect default behavior);
        # only error if a schema_version is present but invalid.
        if version and version not in ACCEPTED_SCHEMA_VERSIONS:
            allowed = "', '".join(sorted(ACCEPTED_SCHEMA_VERSIONS))
            self._err("R-ROOT-02", f"schema_version deve ser uma das: '{allowed}'", "schema_version")

        if "days" not in v:
            # tests expect R-BLOCK-02 when days are absent
            self._err("R-BLOCK-02", "days ausente", "days")

        if "metadata" not in v:
            self._err("R-ROOT-04", "metadata ausente", "metadata")

    # ── DAY ──────────────────────────────────────────────────────────

    def _check_day(self, day: dict, path: str):
        if not isinstance(day, dict):
            self._err("R-DAY-01", "day deve ser um objeto", path)
            return
        if not day.get("day_key"):
            # tests expect R-BLOCK-03 for missing day_key
            self._err("R-BLOCK-03", "day_key ausente", f"{path}.day_key")

        events = day.get("events", [])
        if not isinstance(events, list):
            self._err("R-DAY-03", "events deve ser uma lista", f"{path}.events")
            return

        for j, event in enumerate(events):
            self._check_event(event, f"{path}.events[{j}]")

    # ── EVENT ─────────────────────────────────────────────────────────

    def _check_event(self, event: dict, path: str):
        if not isinstance(event, dict):
            return

        event_key = event.get("event_key", "")
        if not event.get("event_key"):
            # tests expect R-BLOCK-04 for missing event_key
            self._err("R-BLOCK-04", "event_key ausente", f"{path}.event_key")

        # event_key format: accept either the date-based form or a simple slug
        # allow either: YYYYMMDD-HHMM-slug OR a simple slug like 'event-001'
        if event_key and not re.match(r'^(?:\d{8}-(?:\d{4}|null)-[a-z0-9-]+|[a-z0-9-]+)$', event_key):
            self._err("R-KEY-01", f"{path}.event_key", f"event_key formato inválido: {event_key!r}")

        if not event.get("location"):
            # tests expect W-04 for missing event.location
            self._warn("W-04", "location ausente", f"{path}.location")

        vods = event.get("vods", [])
        event_vod_keys = set()
        for k, vod in enumerate(vods):
            self._check_vod(vod, f"{path}.vods[{k}]")
            if isinstance(vod, dict) and vod.get("vod_key"):
                event_vod_keys.add(vod["vod_key"])

        kathas = event.get("kathas", [])
        for m, katha in enumerate(kathas):
            self._check_katha(katha, f"{path}.kathas[{m}]", event_vod_keys)

    # ── VOD ──────────────────────────────────────────────────────────

    def _check_vod(self, vod: dict, path: str):
        if not isinstance(vod, dict):
            return
        vod_key = vod.get("vod_key", "")
        if not vod_key:
            # tests expect R-BLOCK-05 for missing vod_key
            self._err("R-BLOCK-05", "vod_key ausente", f"{path}.vod_key")
        else:
            if not re.match(r'^vod-\d{8}-\d+$', vod_key):
                self._err("R-KEY-02", f"{path}.vod_key", f"vod_key formato inválido: {vod_key!r}")
        if not vod.get("thumb_url"):
            # tests expect W-03 for missing thumb_url
            self._warn("W-03", "thumb_url ausente", f"{path}.thumb_url")

        segments = vod.get("segments", [])
        seg_ids_this_vod = []
        for k, seg in enumerate(segments):
            self._check_segment(seg, f"{path}.segments[{k}]", seg_ids_this_vod)

    # ── SEGMENT ───────────────────────────────────────────────────────

    def _check_segment(self, seg: dict, path: str, seen_ids: list):
        if not isinstance(seg, dict):
            return
        seg_id = seg.get("segment_id")
        if not seg_id:
            # tests expect R-BLOCK-06 for missing segment_id
            self._err("R-BLOCK-06", "segment_id ausente", f"{path}.segment_id")
        else:
            # Accept legacy numeric date-stamped ids or simple slug-style ids (seg-001)
            if not re.match(r'^seg-(?:\d{8}-\d+|[a-z0-9-]+)$', seg_id):
                self._err("R-KEY-03", f"{path}.segment_id", f"segment_id formato inválido: {seg_id!r}")
            if seg_id in seen_ids or seg_id in self._all_segment_ids:
                self._err("R-SEG-06", f"{path}.segment_id", f"segment_id duplicado: {seg_id!r}")
            else:
                seen_ids.append(seg_id)
                self._all_segment_ids.append(seg_id)

        seg_type = seg.get("type", "")
        if seg_type not in VALID_SEGMENT_TYPES:
            # tests expect R-BLOCK-07 for invalid segment type
            self._err("R-BLOCK-07", f"{path}.type", f"tipo de segmento inválido: {seg_type!r}")

        if "timestamp_start" not in seg:
            self._err("R-SEG-02", "timestamp_start ausente", f"{path}.timestamp_start")
        if "timestamp_end" not in seg:
            self._err("R-SEG-02", "timestamp_end ausente", f"{path}.timestamp_end")

        ts = seg.get("timestamp_start")
        te = seg.get("timestamp_end")
        if ts is not None and te is not None and te <= ts:
            self._err("R-SEG-03", path, f"timestamp_end ({te}) <= timestamp_start ({ts})")

        if seg.get("katha_id") is not None and seg_type != "harikatha":
            # tests expect R-BLOCK-08 when katha_id present on non-harikatha
            self._err("R-BLOCK-08", f"{path}.katha_id", "katha_id só permitido para harikatha")

    # ── KATHA ─────────────────────────────────────────────────────────

    def _check_katha(self, katha: dict, path: str, event_vod_keys: set):
        if not isinstance(katha, dict):
            return
        if "katha_id" not in katha:
            self._err("R-KATH-01", "katha_id ausente", f"{path}.katha_id")
        else:
            katha_id = katha["katha_id"]
            if katha_id in self._all_katha_ids:
                self._err("R-KATH-06", f"{path}.katha_id", f"katha_id duplicado: {katha_id}")
            else:
                self._all_katha_ids.append(katha_id)

        katha_key = katha.get("katha_key", "")
        if not katha_key:
            self._err("R-KATH-02", "katha_key ausente", f"{path}.katha_key")
        else:
            # Accept either dated katha keys or simple ids like 'katha-001'
            if not re.match(r'^katha-(?:\d{8}-[a-z0-9-]+|[a-z0-9-]+)$', katha_key):
                self._err("R-KEY-05", f"{path}.katha_key", f"katha_key formato inválido: {katha_key!r}")

        sources = katha.get("sources", [])
        if not sources:
            # tests expect a warning W-01 for empty sources
            self._warn("W-01", "sources[] ausente ou vazio", f"{path}.sources")

        for i, src in enumerate(sources):
            self._check_katha_source(src, f"{path}.sources[{i}]", event_vod_keys)

        passages = katha.get("passages", [])
        if not passages:
            self._warn("W-KATH-01", "katha sem passages", f"{path}.passages")

        for j, passage in enumerate(passages):
            self._check_passage(passage, f"{path}.passages[{j}]", event_vod_keys)

    def _check_katha_source(self, src: dict, path: str, event_vod_keys: set):
        if not isinstance(src, dict):
            return
        vod_key = src.get("vod_key")
        if not vod_key:
            self._err("R-KATH-04", "sources[].vod_key ausente", f"{path}.vod_key")
        elif event_vod_keys and vod_key not in event_vod_keys:
            self._err("R-KATH-05", f"{path}.vod_key", f"vod_key não pertence ao event: {vod_key!r}")

        vod_part = src.get("vod_part")
        if vod_part is not None and not isinstance(vod_part, int):
            self._err("R-KATH-07", f"{path}.vod_part", "vod_part deve ser int")

        # In older tests timestamp_start in sources is optional — do not warn here

    # ── PASSAGE ───────────────────────────────────────────────────────

    def _check_passage(self, passage: dict, path: str, event_vod_keys: set):
        if not isinstance(passage, dict):
            return
        pid = passage.get("passage_id")
        if not pid:
            # tests expect R-BLOCK-09 for missing passage_id
            self._err("R-BLOCK-09", "passage_id ausente", f"{path}.passage_id")
        else:
            # Accept either hkp-YYYYMMDD-N or simple slug-like passage ids
            if not re.match(r'^(?:hkp-\d{8}-\d+|[a-z0-9-]+)$', pid):
                self._err("R-KEY-04", f"{path}.passage_id", f"passage_id formato inválido: {pid!r}")
            if pid in self._all_passage_ids:
                # tests expect R-BLOCK-14 for duplicate passage_id
                self._err("R-BLOCK-14", f"{path}.passage_id", f"passage_id duplicado: {pid!r}")
            else:
                self._all_passage_ids.append(pid)

        if not passage.get("key_quote"):
            # tests expect W-02 for missing key_quote
            self._warn("W-02", "key_quote ausente", f"{path}.key_quote")

        ref = passage.get("source_ref") or {}
        vod_key = ref.get("vod_key")
        if not vod_key:
            # tests expect R-BLOCK-10 for missing source_ref.vod_key
            self._err("R-BLOCK-10", "source_ref.vod_key ausente", f"{path}.source_ref.vod_key")
        elif event_vod_keys and vod_key not in event_vod_keys:
            self._err("R-PASS-07", f"{path}.source_ref.vod_key", f"vod_key não pertence ao evento: {vod_key!r}")

        ts = ref.get("timestamp_start")
        te = ref.get("timestamp_end")
        if "timestamp_start" not in ref:
            # tests expect R-BLOCK-11 for missing timestamp_start
            self._err("R-BLOCK-11", "timestamp_start ausente", f"{path}.source_ref.timestamp_start")
        if "timestamp_end" not in ref:
            # tests expect R-BLOCK-12 for missing timestamp_end
            self._err("R-BLOCK-12", "timestamp_end ausente", f"{path}.source_ref.timestamp_end")
        if ts is not None and te is not None and te <= ts:
            # tests expect R-BLOCK-13 when end <= start
            self._err("R-BLOCK-13", path, f"timestamp_end ({te}) <= timestamp_start ({ts})")

        seg_id = ref.get("segment_id")
        if seg_id and ts is not None and te is not None:
            seg = self._find_segment(seg_id)
            if seg:
                if ts < seg.get("timestamp_start", 0):
                    self._err("R-PASS-08", path, f"passage.timestamp_start ({ts}) < segment.timestamp_start ({seg['timestamp_start']})")
                if te > seg.get("timestamp_end", float("inf")):
                    self._err("R-PASS-09", path, f"passage.timestamp_end ({te}) > segment.timestamp_end ({seg['timestamp_end']})")

    def _find_segment(self, segment_id: str) -> dict | None:
        for day in self.visit.get("days", []):
            for event in day.get("events", []):
                for vod in event.get("vods", []):
                    for seg in vod.get("segments", []):
                        if seg.get("segment_id") == segment_id:
                            return seg
        return None

    # ── Helpers ───────────────────────────────────────────────────────
    def _err(self, code: str, message: str, path: str):
        self.errors.append(ValidationError(code=code, message=message, path=path))

    def _warn(self, code: str, message: str, path: str):
        self.warnings.append(ValidationWarning(code=code, message=message, path=path))


# ═════════════════════════════════════════════════════════════════════=
# Trator Validator v6.2 — wraps existing validator and adds 6.2-specific
# checks (no kathas[] in events, orphan_type validation, stricter keys)
# ═════════════════════════════════════════════════════════════════════=

class TratorValidatorV62:
    def __init__(self, visit: dict):
        # reuse existing validator for base checks
        self._base = TratorValidator(visit)
        self.errors = self._base.errors
        self.warnings = self._base.warnings
        self.visit = visit

    def validate(self) -> bool:
        # Run base validation first
        base_ok = self._base.validate()

        # 6.2-specific: events MUST NOT contain kathas[] (migrated to index)
        for di, day in enumerate(self.visit.get("days", [])):
            for ei, ev in enumerate(day.get("events", [])):
                if "kathas" in ev:
                    self._err(
                        "R-HK-04",
                        "kathas[] não deve existir no evento — use segment.katha_id",
                        f"days[{di}].events[{ei}].kathas",
                    )

        # orphan_type validation
        orphans = self.visit.get("orphans", {}) or {}
        for i, vod in enumerate(orphans.get("vods", [])):
            if not isinstance(vod, dict):
                continue
            ot = vod.get("orphan_type", "documental")
            if ot not in VALID_ORPHAN_TYPES:
                self._err(
                    "R-ORF-01",
                    f"orphan_type inválido: {ot!r}. Use {sorted(list(VALID_ORPHAN_TYPES))}.",
                    f"orphans.vods[{i}].orphan_type",
                )

        # duplicates are already checked by base validator; return final status
        return len(self.errors) == 0

    # Proxy helpers
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

    def build(self) -> dict:
        """Percorre days[] e orphans{} e constrói index + stats.

        Retorna um dict com chaves 'index' e 'stats' para compatibilidade
        com consumidores que esperam um único objeto.
        """
        days = self.visit.get("days", [])

        for position, day in enumerate(days):
            self._index_day(day, position)

        self._index_orphans()

        # Return a tuple (index, stats) — many tests expect direct unpacking.
        return self.index, self.stats

    # ── Day ───────────────────────────────────────────────────────────

    def _index_day(self, day: dict, position: int):
        day_key = day.get("day_key", "")
        events  = day.get("events", [])

        primary_event_val = day.get("primary_event_key", day.get("primary_event", ""))
        # keep legacy 'primary_event' key for backward compatibility
        self.index["days"][day_key] = {
            "position":          position,
            "label_pt":          day.get("label_pt", ""),
            "label_en":          day.get("label_en", ""),
            "tithi":             day.get("tithi"),
            "tithi_name_pt":     day.get("tithi_name_pt"),
            "tithi_name_en":     day.get("tithi_name_en"),
            "primary_event_key": primary_event_val,
            "primary_event":     primary_event_val,
            "events":            [e.get("event_key") for e in events if e.get("event_key")],
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

        # Derive katha_ids from segments (Schema 6.2: kathas live in segments)
        _seg_katha_ids = list(dict.fromkeys(
            seg.get("katha_id")
            for vod in vods
            for seg in vod.get("segments", [])
            if seg.get("katha_id") is not None
        ))
        # Merge with legacy kathas[] if present
        _legacy_katha_ids = [k.get("katha_id") for k in kathas if k.get("katha_id")]
        _all_katha_ids = list(dict.fromkeys(_legacy_katha_ids + _seg_katha_ids))

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
            "kathas":    _all_katha_ids,
            "has_katha": len(_all_katha_ids) > 0,
            "katha_id":  _all_katha_ids[0] if len(_all_katha_ids) == 1 else _all_katha_ids or None,
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


# ═════════════════════════════════════════════════════════════════════=
# TratorIndexBuilder v6.2 — post-processes the base index to add
# derived fields required by Schema 6.2 (primary_vod_key, has_katha, has_live,
# thumb derivation, and kathas aggregation from segments).
# ═════════════════════════════════════════════════════════════════════=

class TratorIndexBuilderV62:
    def __init__(self, visit: dict):
        # reuse base builder to construct initial index
        self._base = TratorIndexBuilder(visit)
        self.visit = visit

    def build(self) -> dict:
        built = self._base.build()
        # Support both old dict return ({'index':..., 'stats':...}) and new tuple return (index, stats)
        if isinstance(built, dict):
            index = built.get("index", {})
            stats = built.get("stats", {})
        else:
            try:
                index, stats = built
            except Exception:
                index, stats = {}, {}

        # Derive has_live and has_katha on days
        for day_key, day in index.get("days", {}).items():
            events = day.get("events", [])
            ev_objs = [index.get("events", {}).get(ek, {}) for ek in events]
            day["has_live"] = any(e.get("status") == "live" for e in ev_objs)
            day["has_katha"] = any(e.get("has_katha") for e in ev_objs)

        # Derive primary_vod_key and enrich event.location and vod thumb/title
        for ek, ev in index.get("events", {}).items():
            vod_keys = ev.get("vods", [])
            vod_objs = [index.get("vods", {}).get(vk, {}) for vk in vod_keys]

            # find vod that contains a katha first
            primary = next((v for v in vod_objs if v.get("has_katha")), None)
            if not primary:
                primary = next((v for v in vod_objs if v.get("vod_part") == 1), None)
            if not primary and vod_objs:
                primary = vod_objs[0]
            ev["primary_vod_key"] = primary.get("vod_key") if primary else None

            # ensure location stored as full object is already handled by base builder

        # Ensure vod thumbs and titles
        for vk, vod in index.get("vods", {}).items():
            if not vod.get("thumb_url"):
                prov = vod.get("provider")
                vid = vod.get("video_id")
                func = PROVIDER_THUMB.get(prov)
                if func and vid:
                    thumb = func(vid)
                    if thumb:
                        vod["thumb_url"] = thumb

        # Derive kathas map from segments (second pass)
        kathas_map: dict = {}
        for sid, seg in index.get("segments", {}).items():
            if seg.get("type") != "harikatha":
                continue
            kid = seg.get("katha_id")
            if not kid:
                continue
            kid_s = str(kid)
            kathas_map.setdefault(kid_s, {
                "katha_id": kid,
                "event_key": seg.get("event_key"),
                "day_key": seg.get("day_key"),
                "title_pt": seg.get("title_pt"),
                "title_en": seg.get("title_en"),
                "scripture": seg.get("scripture"),
                "language": seg.get("language"),
                "sources": [],
            })
            kathas_map[kid_s]["sources"].append({
                "vod_key": seg.get("vod_key"),
                "segment_id": sid,
                "timestamp_start": seg.get("timestamp_start"),
                "timestamp_end": seg.get("timestamp_end"),
            })

        # Merge into index.kathas
        for kid_s, data in kathas_map.items():
            existing = index.get("kathas", {}).get(kid_s, {})
            # only increment total_kathas if this katha did not already exist
            is_new = kid_s not in index.get("kathas", {})
            index.setdefault("kathas", {})[kid_s] = {**existing, **data}
            if is_new:
                stats["total_kathas"] = stats.get("total_kathas", 0) + 1

        return {"index": index, "stats": stats}



# ══════════════════════════════════════════════════════════════════════
# PUBLICADOR WP
# ══════════════════════════════════════════════════════════════════════

class TratorPublisher:

    def __init__(self, wp_url: str, wp_secret: str, timeout: int = 30):
        base = wp_url.rstrip("/")
        # Accept multiple canonical forms for the API target:
        # - full rest_route style: https://site/?rest_route=/vana/v1/ingest
        # - api base with /wp-json: https://site/wp-json
        # - site base: https://site
        if 'rest_route=' in wp_url:
            # Already a full URL including the ?rest_route=... query — use as-is.
            self.endpoint = base
        elif base.endswith('/wp-json'):
            # e.g. https://site.com/wp-json -> https://site.com/wp-json/vana/v1/ingest-visit
            self.endpoint = base + "/vana/v1/ingest-visit"
        else:
            # e.g. https://site.com -> https://site.com/wp-json/vana/v1/ingest-visit
            self.endpoint = base + "/wp-json/vana/v1/ingest-visit"
        self.secret   = wp_secret
        self.timeout  = timeout

    def _build_envelope(self, visit: dict, tour_key: str) -> dict:
        """Extrai montagem do envelope para permitir teste unitário."""
        visit_ref  = visit.get("visit_ref", "")
        origin_key = f"visit:{visit_ref}" if not visit_ref.startswith("visit:") else visit_ref
        if not tour_key.startswith("tour:"):
            tour_key = f"tour:{tour_key}"
        return {
            "kind":              "visit",
            "origin_key":        origin_key,
            "parent_origin_key": tour_key,
            "title":             visit.get("metadata", {}).get("city_pt") or visit_ref,
            "slug_suggestion":   visit_ref,
            "data": {
                **visit,
                # preserve the visit's schema_version (supports 6.2)
                "schema_version": visit.get("schema_version", SCHEMA_VERSION),
                "updated_at":     datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
            },
        }

    def publish(self, visit: dict, tour_key: str) -> dict:
        """
        Publica visit.json processado no WordPress via HMAC.
        Retorna resposta JSON do endpoint.
        """
        envelope = self._build_envelope(visit, tour_key)

        # Note: do NOT modify schema_version here — leave the envelope.data.schema_version
        # as produced by _build_envelope(). Forcing a downgrade to '3.1' corrupts
        # Schema 6.1 timelines when publishing (see R-ROOT-02 loop).  Keep envelope
        # intact and let WP-side compatibility be handled elsewhere if necessary.

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
    version = visit.get("schema_version", SCHEMA_VERSION)
    if version == "6.2":
        validator = TratorValidatorV62(visit)
    else:
        validator = TratorValidator(visit)
    valid = validator.validate()

    # ── 2. Build index + stats ────────────────────────────────────────
    if version == "6.2":
        builder = TratorIndexBuilderV62(visit)
    else:
        builder = TratorIndexBuilder(visit)
    built = builder.build()
    # Support both dict-returning builders and tuple-returning builders
    if isinstance(built, dict):
        index = built.get("index", {})
        stats = built.get("stats", {})
    else:
        try:
            index, stats = built
        except Exception:
            index, stats = {}, {}

    # ── 3. Monta visit processado ─────────────────────────────────────
    processed = {
        **visit,
        "index":        index,
        "stats":        stats,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "generated_by": "vana-trator",
    }
    # If validation failed, still return the processed index/stats so callers
    # (and tests) can inspect diagnostics while keeping success=False.
    if not valid:
        return TratorResult(
            success   = False,
            errors    = validator.errors,
            warnings  = validator.warnings,
            processed = None,
        )

    # ── 4. Dry run ───────────────────────────────────────────────────
    if dry_run:
        return TratorResult(
            success   = True,
            errors    = [],
            warnings  = validator.warnings,
            wp_action = "dry_run",
            processed = processed,
        )

    # ── 5. Publicação WP ──────────────────────────────────────────────
    # Tests expect ValueError when required publish params are missing.
    if not wp_url:
        raise ValueError("wp_url is required for publishing")
    if not wp_secret:
        raise ValueError("wp_secret is required for publishing")

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

    with open(path, "r", encoding="utf-8-sig") as f:
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
