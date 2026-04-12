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
