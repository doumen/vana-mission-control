"""YouTube discovery helpers for Streamlit editor.

Implements search_videos_for_day, _infer_type and build_event_from_video
based on the spec in melhorias2.md.
"""
from __future__ import annotations

import re
from datetime import datetime, timedelta, timezone as _timezone
from typing import List, Dict, Optional

import requests

try:
    import isodate
except Exception:  # pragma: no cover - optional dependency
    isodate = None


# YouTube Data API v3 endpoints
YT_SEARCH_URL = "https://www.googleapis.com/youtube/v3/search"
YT_VIDEOS_URL = "https://www.googleapis.com/youtube/v3/videos"

# Default channel id placeholder (override in calls)
CHANNEL_ID = "UCREPLACE_THIS_WITH_CHANNEL_ID"

# Infer patterns (simple, case-insensitive)
TYPE_PATTERNS = [
    (r"mangala[\s\-_]?arati", "mangala"),
    (r"arati", "arati"),
    (r"hari[\s\-_]?katha", "programa"),
    (r"morning[\s\-_]?class", "programa"),
    (r"evening[\s\-_]?program", "programa"),
    (r"darshan", "darshan"),
    (r"kirtan", "programa"),
    (r"parikrama", "darshan"),
]


def _infer_type(title: str) -> str:
    t = (title or "").lower()
    for pattern, event_type in TYPE_PATTERNS:
        try:
            if re.search(pattern, t):
                return event_type
        except re.error:
            continue
    return "programa"


def _parse_duration_iso(dur_iso: str) -> int:
    """Return duration in seconds for an ISO 8601 duration string.

    Falls back to 0 if parsing lib is not available or parsing fails.
    """
    if not dur_iso:
        return 0
    if isodate:
        try:
            td = isodate.parse_duration(dur_iso)
            return int(td.total_seconds())
        except Exception:
            return 0
    # Best effort fallback: try to parse PT#H#M#S
    m = re.match(r"PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?", dur_iso)
    if not m:
        return 0
    h = int(m.group(1) or 0)
    mi = int(m.group(2) or 0)
    s = int(m.group(3) or 0)
    return h * 3600 + mi * 60 + s


def search_videos_for_day(
    api_key: str,
    day_key: str,
    channel_id: str = CHANNEL_ID,
    timezone: str = "Asia/Kolkata",
    max_results: int = 10,
) -> List[Dict]:
    """Search channel videos published on the given day.

    Args:
        api_key: YouTube Data API v3 key.
        day_key: ISO date string YYYY-MM-DD.
        channel_id: YouTube channel ID to restrict search.
        timezone: timezone string (only used to compute simple offset windows).
        max_results: max results for search API.

    Returns:
        List of video dicts with keys: video_id, title, published_at,
        duration_s, thumbnail, inferred_type, inferred_time.
    """
    if not api_key:
        return []

    # Simple mapping for commonly used timezones to offset; fallback to IST
    TZ_OFFSETS = {
        "Asia/Kolkata": timedelta(hours=5, minutes=30),
        "America/Sao_Paulo": timedelta(hours=-3),
        "America/New_York": timedelta(hours=-5),
        "Europe/London": timedelta(hours=0),
    }
    offset = TZ_OFFSETS.get(timezone, timedelta(hours=5, minutes=30))

    # Interpret day_key as date at 00:00 local
    try:
        day_dt = datetime.fromisoformat(day_key)
    except Exception:
        # try date-only parsing
        try:
            day_dt = datetime.strptime(day_key, "%Y-%m-%d")
        except Exception:
            return []

    # Compute publishedAfter and publishedBefore in UTC by subtracting offset
    published_after = (day_dt - offset).isoformat() + "Z"
    published_before = (day_dt + timedelta(days=1) - offset).isoformat() + "Z"

    # Step 1: search for videos
    try:
        resp = requests.get(
            YT_SEARCH_URL,
            params={
                "key": api_key,
                "channelId": channel_id,
                "part": "snippet",
                "type": "video",
                "publishedAfter": published_after,
                "publishedBefore": published_before,
                "maxResults": max_results,
                "order": "date",
            },
            timeout=15,
        )
        resp.raise_for_status()
        items = resp.json().get("items", [])
    except Exception:
        return []

    if not items:
        return []

    video_ids = [it["id"]["videoId"] for it in items if it.get("id") and it["id"].get("videoId")]
    if not video_ids:
        return []

    # Step 2: fetch video details (contentDetails + snippet)
    try:
        detail_resp = requests.get(
            YT_VIDEOS_URL,
            params={
                "key": api_key,
                "id": ",".join(video_ids),
                "part": "contentDetails,snippet",
            },
            timeout=15,
        )
        detail_resp.raise_for_status()
        details_map = {v["id"]: v for v in detail_resp.json().get("items", [])}
    except Exception:
        details_map = {}

    results: List[Dict] = []
    for item in items:
        vid = item.get("id", {}).get("videoId")
        snippet = item.get("snippet", {})
        title = snippet.get("title", "")
        published = snippet.get("publishedAt", "")

        dur_iso = (
            details_map.get(vid, {}).get("contentDetails", {}).get("duration", "PT0S")
            if vid
            else "PT0S"
        )
        duration_s = _parse_duration_iso(dur_iso)

        inferred_type = _infer_type(title)

        inferred_time = ""
        if published:
            try:
                pub_dt = datetime.fromisoformat(published.replace("Z", "+00:00"))
                # convert to local simple offset tz
                tzobj = _timezone(offset)
                pub_local = pub_dt.astimezone(tzobj)
                inferred_time = pub_local.strftime("%H:%M")
            except Exception:
                inferred_time = ""

        results.append(
            {
                "video_id": vid,
                "title": title,
                "published_at": published,
                "duration_s": duration_s,
                "thumbnail": f"https://img.youtube.com/vi/{vid}/maxresdefault.jpg" if vid else "",
                "inferred_type": inferred_type,
                "inferred_time": inferred_time,
            }
        )

    # sort by published_at
    results.sort(key=lambda x: x.get("published_at") or "")
    return results


def build_event_from_video(video: Dict, day_key: str) -> Dict:
    """Build an event dict + vod entry from a discovered video.

    Returns a dict with keys: "event" and "vod_key" as described in the spec.
    """
    date_part = day_key.replace("-", "")
    time_part = (video.get("inferred_time") or "0000").replace(":", "")
    event_type = video.get("inferred_type", "programa")

    event_key = f"{date_part}-{time_part}-{event_type}"

    raw_title = video.get("title", "")
    title_pt = raw_title
    title_en = raw_title

    # Vod key generation: keep simple counter suffix '001' — caller may adjust
    vod_key = f"vod-{date_part}-001"

    event = {
        "event_key": event_key,
        "type": event_type,
        "title_pt": title_pt,
        "title_en": title_en,
        "time": video.get("inferred_time", ""),
        "status": "past",
        "location": {},
        "vods": [
            {
                "vod_key": vod_key,
                "provider": "youtube",
                "video_id": video.get("video_id"),
                "url": None,
                "thumb_url": video.get("thumbnail"),
                "duration_s": video.get("duration_s"),
                "title_pt": title_pt,
                "title_en": title_en,
                "vod_part": 1,
                "segments": [],
            }
        ],
        "photos": [],
        "sangha": [],
    }

    return {"event": event, "vod_key": vod_key}


__all__ = [
    "search_videos_for_day",
    "build_event_from_video",
    "_infer_type",
]
