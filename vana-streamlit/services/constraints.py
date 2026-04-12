"""
services/constraints.py
Funções de validação e derivação usadas pelo editor.
"""
from __future__ import annotations

from datetime import datetime, timedelta, timezone
from typing import Optional


def derive_event_key(day_key: str, time: str, event_type: str) -> str:
    date_part = (day_key or "").replace("-", "")
    time_part = (time or "00:00").replace(":", "")
    time_part = time_part.ljust(4, "0")[:4]
    slug = (event_type or "evento").lower().replace(" ", "-")
    return f"{date_part}-{time_part}-{slug}"


def compute_event_status(day_key: str, time: str, event_tz: str = "Asia/Kolkata") -> str:
    try:
        TZ_OFFSETS = {
            "Asia/Kolkata": timedelta(hours=5, minutes=30),
            "America/Sao_Paulo": timedelta(hours=-3),
            "America/New_York": timedelta(hours=-5),
        }
        offset = TZ_OFFSETS.get(event_tz, timedelta(hours=5, minutes=30))
        tz_obj = timezone(offset)

        now = datetime.now(tz_obj)

        event_date = datetime.fromisoformat(day_key).date()
        today = now.date()

        if event_date < today:
            return "past"
        if event_date > today:
            return "future"

        if time:
            h, m = map(int, time.split(":"))
            event_time = now.replace(hour=h, minute=m, second=0, microsecond=0)
            diff = (event_time - now).total_seconds()

            if diff < -7200:
                return "past"
            if diff < 0:
                return "active"
            if diff < 3600:
                return "soon"
            return "future"

        return "future"
    except Exception:
        return "past"


def check_vod_unique(visit: dict, video_id: str, exclude_vod_key: str = "") -> Optional[str]:
    """
    Verifica se `video_id` já existe na visita (em qualquer evento ou órfão).

    Retorna None se único, ou string de erro descrevendo a localização do duplicado.
    """
    if not video_id:
        return None

    for day in visit.get("days", []):
        for ev in day.get("events", []):
            for vod in ev.get("vods", []):
                if vod.get("video_id") == video_id:
                    if vod.get("vod_key") != exclude_vod_key:
                        return (
                            f"❌ Vídeo `{video_id}` já existe: "
                            f"VOD `{vod.get('vod_key')}` no evento `{ev.get('event_key')}`"
                        )
    for vod in visit.get("orphans", {}).get("vods", []):
        if vod.get("video_id") == video_id:
            if vod.get("vod_key") != exclude_vod_key:
                return f"❌ Vídeo `{video_id}` já existe nos órfãos: `{vod.get('vod_key')}`"
    return None


# --- Compatibility aliases and additional helpers requested by melhorias2.md
from datetime import date
from typing import List, Dict, Any


def validate_vod_unique(visit: dict, video_id: str, exclude_vod_key: str = "") -> Optional[str]:
    return check_vod_unique(visit, video_id, exclude_vod_key=exclude_vod_key)


EVENT_TYPES = [
    "programa", "mangala", "arati", "darshan", "other",
]

SEGMENT_TYPES = [
    "kirtan", "harikatha", "pushpanjali", "arati",
    "dance", "drama", "darshan", "interval", "noise", "announcement",
]

CITY_COUNTRY_MAP = {
    "Vrindavan": {"country": "IN", "timezone": "Asia/Kolkata"},
}


def derive_visit_ref(city_pt: str, suffix: str | None = None) -> str:
    base = (city_pt or "visit").lower().replace(" ", "-")
    if suffix:
        return f"{base}-{suffix}"
    return base


def derive_visit_title(city_pt: str, date_start: str, lang: str = "pt") -> str:
    return f"Visita a {city_pt} - {date_start}"


def derive_metadata_from_city(city_pt: str) -> dict:
    info = CITY_COUNTRY_MAP.get(city_pt, {})
    return {
        "city_pt": city_pt,
        "city_en": info.get("city_en", city_pt),
        "country": info.get("country", ""),
        "timezone": info.get("timezone", "Asia/Kolkata"),
    }


def derive_vod_key(day_key: str, seq: int = 1) -> str:
    date_part = (day_key or "").replace("-", "")
    return f"vod-{date_part}-{seq:03d}"


def derive_segment_id(day_key: str, seq: int = 1) -> str:
    date_part = (day_key or "").replace("-", "")
    return f"seg-{date_part}-{seq:03d}"


def derive_vod_title(title: str) -> str:
    return title or ""


def compute_visit_status(date_start: str, date_end: str, tz: str = "Asia/Kolkata") -> str:
    try:
        today = date.today()
        ds = date.fromisoformat(date_start)
        de = date.fromisoformat(date_end)
        if ds <= today <= de:
            return "active"
        if today < ds:
            return "upcoming"
        return "completed"
    except Exception:
        return "upcoming"


def compute_stats(visit: dict) -> dict:
    days = visit.get("days", []) or []
    tot_events = 0
    tot_vods = 0
    tot_segments = 0
    tot_kathas = 0
    tot_passages = 0
    for d in days:
        evs = d.get("events", []) or []
        tot_events += len(evs)
        for ev in evs:
            vds = ev.get("vods", []) or []
            tot_vods += len(vds)
            for v in vds:
                segs = v.get("segments", []) or []
                tot_segments += len(segs)
            kathas = ev.get("kathas", []) or []
            tot_kathas += len(kathas)
            for k in kathas:
                if isinstance(k, dict):
                    ps = k.get("passages", [])
                    tot_passages += len(ps)

    orphans = visit.get("orphans", {}) or {}
    tot_vods += len(orphans.get("vods", []))
    tot_passages += sum(len(k.get("passages", [])) for k in orphans.get("kathas", [])) if orphans.get("kathas") else 0

    return {
        "total_days":     len(days),
        "total_events":   tot_events,
        "total_vods":     tot_vods,
        "total_segments": tot_segments,
        "total_kathas":   tot_kathas,
        "total_passages": tot_passages,
        "total_photos":   len(orphans.get("photos", [])) if orphans else 0,
        "total_sangha":   len(orphans.get("sangha", [])) if orphans else 0,
    }


def suggest_event_title(event_type: str, day_label: str) -> dict:
    return {"title_pt": f"{event_type.title()} - {day_label}", "title_en": f"{event_type.title()} - {day_label}"}


def suggest_event_time(event_type: str) -> str:
    defaults = {"mangala": "05:30", "arati": "18:00", "programa": "19:00"}
    return defaults.get(event_type, "10:00")


def suggest_thumb_url(video_id: str) -> str | None:
    if not video_id:
        return None
    return f"https://img.youtube.com/vi/{video_id}/maxresdefault.jpg"


def collect_known_locations(visit: dict) -> List[dict]:
    locs: List[dict] = []
    seen = set()
    for d in visit.get("days", []) or []:
        for ev in d.get("events", []) or []:
            loc = ev.get("location")
            if isinstance(loc, dict):
                name = loc.get("name")
            else:
                name = loc
            if name and name not in seen:
                seen.add(name)
                locs.append({"name": name})
    return locs


def collect_all_vod_keys(visit: dict) -> List[str]:
    keys: List[str] = []
    for d in visit.get("days", []) or []:
        for ev in d.get("events", []) or []:
            for v in ev.get("vods", []) or []:
                if v.get("vod_key"):
                    keys.append(v.get("vod_key"))
    for v in visit.get("orphans", {}).get("vods", []) or []:
        if v.get("vod_key"):
            keys.append(v.get("vod_key"))
    return keys


def collect_all_segment_ids(visit: dict) -> List[str]:
    ids: List[str] = []
    for d in visit.get("days", []) or []:
        for ev in d.get("events", []) or []:
            for v in ev.get("vods", []) or []:
                for s in v.get("segments", []) or []:
                    if s.get("segment_id"):
                        ids.append(s.get("segment_id"))
    return ids


def validate_date(s: str, label: str = "date") -> Optional[str]:
    try:
        date.fromisoformat(s)
        return None
    except Exception:
        return f"{label} inválida: {s}"


def validate_time(s: str) -> Optional[str]:
    try:
        parts = s.split(":")
        if len(parts) != 2:
            return "formato HH:MM esperado"
        h = int(parts[0]); m = int(parts[1])
        if not (0 <= h <= 23 and 0 <= m <= 59):
            return "hora inválida"
        return None
    except Exception:
        return "hora inválida"


def validate_date_range(start: str, end: str) -> Optional[str]:
    try:
        ds = date.fromisoformat(start)
        de = date.fromisoformat(end)
        if de < ds:
            return "date_end anterior a date_start"
        return None
    except Exception:
        return "intervalo inválido"


def validate_event_key_unique(visit: dict, event_key: str) -> Optional[str]:
    # placeholder: not implementing full check here
    return None


def validate_day_key_unique(visit: dict, day_key: str) -> Optional[str]:
    existing = [d.get("day_key") for d in visit.get("days", []) or []]
    if day_key in existing:
        return f"day_key {day_key} já existe"
    return None


def validate_segment(seg: dict) -> Optional[str]:
    if seg.get("type") == "harikatha" and not seg.get("katha_id"):
        return "katha_id obrigatório para harikatha"
    return None


def validate_harikatha_per_event(event: dict) -> Optional[str]:
    count = 0
    for v in event.get("vods", []) or []:
        for s in v.get("segments", []) or []:
            if s.get("type") == "harikatha":
                count += 1
    if count > 1:
        return "Mais de 1 harikatha neste evento"
    return None
