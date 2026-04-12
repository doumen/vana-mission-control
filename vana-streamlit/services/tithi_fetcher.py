"""
services/tithi_fetcher.py
Busca tithi/festivais do repositório gaura.space (mindifest/vaisnava-calendar-data-collection).

Notas:
- Faz schema discovery em runtime e degrada silenciosamente se a API mudar.
- Loga o schema amostrado uma vez para debugging.
"""
from __future__ import annotations

import logging
from datetime import date, timedelta
from typing import Any

import requests

log = logging.getLogger(__name__)

# ── Constantes
GAURA_SPACE_BASE = "https://vaisnava-calendar-data-collection.gaura.space"

TZ_TO_DIR: dict[str, str] = {
    "Asia/Kolkata": "P0530",
    "Asia/Calcutta": "P0530",
    "America/Sao_Paulo": "N0300",
    "America/New_York": "N0500",
    "America/Los_Angeles": "N0800",
    "Europe/London": "P0000",
}

_DATE_FIELDS = ("date", "iso", "day", "gregorian_date", "greg_date", "d")
_TITHI_FIELDS = ("tithi", "tithi_name", "tithi_at_sunrise", "Tithi")
_PAKSHA_FIELDS = ("paksha", "Paksha", "paksa", "pk")
_EVENT_FIELDS = ("event_name", "festivals", "events", "event", "fest", "special")
_FAST_FIELDS = ("fast", "fast_type", "fasting", "ekadashi_fast")

_schema_logged = False


# Tradução básica
_TITHI_PT: dict[str, str] = {
    "Vijaya Ekadashi": "Vijayā Ekādaśī",
    "Amalaki Ekadashi": "Āmalakī Ekādaśī",
    "Gaura Purnima": "Gaura Pūrṇimā",
}


def _translate(name: str) -> str:
    if not name:
        return name
    if name in _TITHI_PT:
        return _TITHI_PT[name]
    name_lower = name.lower()
    for k, v in _TITHI_PT.items():
        if k.lower() == name_lower:
            return v
    return name


def _extract_first(entry: dict, candidates: tuple[str, ...], default: Any = "") -> Any:
    for field in candidates:
        val = entry.get(field)
        if val is not None and val != "":
            return val
    return default


def _extract_date_str(entry: dict) -> str | None:
    raw = _extract_first(entry, _DATE_FIELDS, None)
    if raw is None:
        return None
    s = str(raw).strip()
    if len(s) == 10 and s[4] == "-":
        return s
    if len(s) == 10 and (s[2] == "-" or s[2] == "/"):
        return f"{s[6:10]}-{s[3:5]}-{s[0:2]}"
    return None


def _extract_festivals(entry: dict) -> list[str]:
    raw = _extract_first(entry, _EVENT_FIELDS, None)
    if raw is None:
        return []
    if isinstance(raw, list):
        return [str(x).strip() for x in raw if x]
    if isinstance(raw, str) and raw.strip():
        return [s.strip() for s in raw.replace(";", ",").split(",") if s.strip()]
    return []


def _extract_fast(entry: dict) -> bool:
    raw = _extract_first(entry, _FAST_FIELDS, None)
    if raw is None:
        return False
    if isinstance(raw, bool):
        return raw
    if isinstance(raw, str):
        return raw.lower() in ("true", "yes", "1", "ekadasi", "ekadashi")
    return bool(raw)


def _log_schema_sample(month_data: Any) -> None:
    global _schema_logged
    if _schema_logged:
        return
    _schema_logged = True

    sample = None
    if isinstance(month_data, list) and month_data:
        sample = month_data[0]
    elif isinstance(month_data, dict):
        for k, v in month_data.items():
            if isinstance(v, list) and v:
                sample = v[0]
                break
            if isinstance(v, dict):
                sample = v
                break

    if sample and isinstance(sample, dict):
        log.info("tithi_fetcher: schema discovery — campos encontrados: %s", list(sample.keys()))
    else:
        log.warning("tithi_fetcher: schema discovery — formato inesperado: %s", type(month_data).__name__)


def _normalize_month_data(month_data: Any) -> list[dict]:
    if isinstance(month_data, list):
        return month_data
    if isinstance(month_data, dict):
        if "days" in month_data and isinstance(month_data["days"], list):
            return month_data["days"]
        entries = []
        for key, val in month_data.items():
            if isinstance(val, dict):
                val_copy = dict(val)
                if "date" not in val_copy:
                    val_copy["date"] = key
                entries.append(val_copy)
            elif isinstance(val, list):
                entries.extend(val)
        if entries:
            return entries
    return []


def fetch_tithis(date_start: str, date_end: str, timezone: str = "Asia/Kolkata") -> dict[str, dict]:
    tz_dir = TZ_TO_DIR.get(timezone)
    if not tz_dir:
        log.warning("tithi_fetcher: timezone '%s' não mapeado. Usando P0530.", timezone)
        tz_dir = "P0530"

    try:
        start = date.fromisoformat(date_start)
        end = date.fromisoformat(date_end)
    except ValueError as e:
        log.error("tithi_fetcher: data inválida — %s", e)
        return {}

    if end < start:
        log.warning("tithi_fetcher: date_end < date_start. Invertendo.")
        start, end = end, start

    if (end - start).days > 90:
        log.warning("tithi_fetcher: range > 90 dias. Truncando.")
        end = start + timedelta(days=90)

    months_needed: set[tuple[int, int]] = set()
    current = start
    while current <= end:
        months_needed.add((current.year, current.month))
        current += timedelta(days=1)

    result: dict[str, dict] = {}

    for year, month in sorted(months_needed):
        url = f"{GAURA_SPACE_BASE}/{tz_dir}/{year}/{month:02d}.json"
        try:
            resp = requests.get(url, timeout=12)
            resp.raise_for_status()
            month_data = resp.json()
        except requests.exceptions.Timeout:
            log.warning("tithi_fetcher: timeout ao buscar %s", url)
            continue
        except requests.exceptions.HTTPError as e:
            log.warning("tithi_fetcher: HTTP %s para %s", e.response.status_code, url)
            continue
        except Exception as e:
            log.warning("tithi_fetcher: erro ao buscar %s — %s", url, e)
            continue

        _log_schema_sample(month_data)
        day_entries = _normalize_month_data(month_data)

        for entry in day_entries:
            if not isinstance(entry, dict):
                continue
            d_str = _extract_date_str(entry)
            if not d_str:
                continue
            try:
                d_date = date.fromisoformat(d_str)
            except ValueError:
                continue
            if not (start <= d_date <= end):
                continue

            tithi_name = str(_extract_first(entry, _TITHI_FIELDS, "")).strip()
            paksha = str(_extract_first(entry, _PAKSHA_FIELDS, "")).strip()
            festivals = _extract_festivals(entry)
            is_fast = _extract_fast(entry)

            if not tithi_name and not festivals:
                continue

            name_pt = _translate(tithi_name)
            display_parts: list[str] = []
            if festivals:
                display_parts.append(" · ".join(_translate(f) for f in festivals))
            elif tithi_name:
                display_parts.append(name_pt)
            if is_fast:
                display_parts.append("(jejum)")
            display = "🪷 " + " ".join(display_parts) if display_parts else ""

            result[d_str] = {
                "tithi": tithi_name,
                "name_pt": name_pt,
                "name_en": tithi_name,
                "paksha": paksha,
                "festivals": festivals,
                "fast": is_fast,
                "display": display,
            }

    log.info("tithi_fetcher: %d dia(s) com tithi/festival entre %s e %s", len(result), date_start, date_end)
    return result
# services/tithi_fetcher.py
"""
Busca tithi data do repositório gaura.space (vaisnava-calendar-data-collection).
"""
from datetime import date, timedelta
from typing import Dict
import requests


GAURA_SPACE_BASE = "https://vaisnava-calendar-data-collection.gaura.space"

TZ_TO_DIR = {
    "Asia/Kolkata": "P0530",
    "America/Sao_Paulo": "N0300",
    "America/New_York": "N0500",
    "Europe/London": "P0000",
}


def _translate_tithi(name: str) -> str:
    _TITHI_PT = {
        "Vijaya Ekadashi": "Vijaya Ekādaśī",
        "Amalaki Ekadashi": "Āmalakī Ekādaśī",
        "Gaura Purnima": "Gaura Pūrṇimā",
        "Nityananda Trayodashi": "Nityānanda Trayodaśī",
    }
    return _TITHI_PT.get(name, name)


def fetch_tithis(date_start: str, date_end: str, timezone: str = "Asia/Kolkata") -> Dict[str, Dict]:
    """
    Fetch tithi metadata between date_start and date_end from gaura.space JSON dumps.

    Returns a map: {"2026-02-20": {"tithi": "Ekadashi", "name_pt": ..., ...}}
    """
    tz_dir = TZ_TO_DIR.get(timezone, "P0530")
    start = date.fromisoformat(date_start)
    end = date.fromisoformat(date_end)

    result: Dict[str, Dict] = {}

    months_needed = set()
    current = start
    while current <= end:
        months_needed.add((current.year, current.month))
        current += timedelta(days=1)

    for year, month in sorted(months_needed):
        url = f"{GAURA_SPACE_BASE}/{tz_dir}/{year}/{month:02d}.json"
        try:
            resp = requests.get(url, timeout=10)
            resp.raise_for_status()
            month_data = resp.json()
        except Exception:
            continue

        # month_data may be list or dict depending on source
        day_entries = month_data if isinstance(month_data, list) else month_data.get("days", []) if isinstance(month_data, dict) else []

        for day_entry in day_entries:
            d = day_entry.get("date") or day_entry.get("day") or day_entry.get("iso")
            if not d:
                continue
            try:
                d_date = date.fromisoformat(d)
            except Exception:
                continue
            if not (start <= d_date <= end):
                continue

            tithi_name = day_entry.get("tithi") or day_entry.get("tithi_name") or ""
            festivals = day_entry.get("festivals", [])
            fast = day_entry.get("fast", False)

            if tithi_name or festivals:
                result[d] = {
                    "tithi": tithi_name,
                    "name_pt": _translate_tithi(tithi_name),
                    "name_en": tithi_name,
                    "paksha": day_entry.get("paksha", ""),
                    "festivals": festivals,
                    "fast": fast,
                }

    return result
