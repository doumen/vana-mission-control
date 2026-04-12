"""
services/day_generator.py
Gera listas de dias entre duas datas, prontos para visit["days"].

Responsabilidades:
  - Labels PT/EN com formatação correta (dia + mês abreviado)
  - Integração com tithis do tithi_fetcher (opcional)
  - Merge inteligente com dias já existentes (preserva eventos)
  - Validação de range
"""
from __future__ import annotations

import logging
from datetime import date, timedelta

log = logging.getLogger(__name__)

MONTHS_PT: dict[int, str] = {
    1: "jan", 2: "fev", 3: "mar", 4: "abr",
    5: "mai", 6: "jun", 7: "jul", 8: "ago",
    9: "set", 10: "out", 11: "nov", 12: "dez",
}

MONTHS_EN: dict[int, str] = {
    1: "Jan", 2: "Feb", 3: "Mar", 4: "Apr",
    5: "May", 6: "Jun", 7: "Jul", 8: "Aug",
    9: "Sep", 10: "Oct", 11: "Nov", 12: "Dec",
}

WEEKDAYS_PT = {0: "seg", 1: "ter", 2: "qua", 3: "qui", 4: "sex", 5: "sáb", 6: "dom"}
WEEKDAYS_EN = {0: "Mon", 1: "Tue", 2: "Wed", 3: "Thu", 4: "Fri", 5: "Sat", 6: "Sun"}

MAX_DAYS = 60


def generate_days(date_start: str, date_end: str, tithis: dict[str, dict] | None = None) -> list[dict]:
    try:
        start = date.fromisoformat(date_start)
        end = date.fromisoformat(date_end)
    except ValueError as e:
        raise ValueError(f"Data inválida: {e}") from e

    if end < start:
        raise ValueError(f"date_end ({date_end}) é anterior a date_start ({date_start})")

    span = (end - start).days + 1
    if span > MAX_DAYS:
        raise ValueError(f"Range de {span} dias excede o limite de {MAX_DAYS}.")

    tithis = tithis or {}
    days: list[dict] = []
    current = start

    while current <= end:
        dk = current.isoformat()
        t = tithis.get(dk, {})
        wd = current.weekday()

        days.append({
            "day_key": dk,
            "label_pt": f"{current.day} {MONTHS_PT[current.month]} · {WEEKDAYS_PT[wd]}",
            "label_en": f"{WEEKDAYS_EN[wd]}, {MONTHS_EN[current.month]} {current.day}",
            "tithi": t.get("tithi", ""),
            "tithi_name_pt": t.get("name_pt", ""),
            "tithi_name_en": t.get("name_en", ""),
            "primary_event_key": "",
            "events": [],
        })
        current += timedelta(days=1)

    log.info("day_generator: %d dia(s) gerados (%s → %s)", len(days), date_start, date_end)
    return days


def merge_days(existing_days: list[dict], new_days: list[dict]) -> list[dict]:
    existing_map: dict[str, dict] = {d["day_key"]: d for d in existing_days if d.get("day_key")}
    new_map: dict[str, dict] = {d["day_key"]: d for d in new_days if d.get("day_key")}

    merged_map: dict[str, dict] = {}

    for dk, day in existing_map.items():
        merged_day = dict(day)
        if dk in new_map:
            new_day = new_map[dk]
            if not merged_day.get("tithi") and new_day.get("tithi"):
                merged_day["tithi"] = new_day["tithi"]
                merged_day["tithi_name_pt"] = new_day.get("tithi_name_pt", "")
                merged_day["tithi_name_en"] = new_day.get("tithi_name_en", "")
            if not merged_day.get("label_pt") and new_day.get("label_pt"):
                merged_day["label_pt"] = new_day["label_pt"]
            if not merged_day.get("label_en") and new_day.get("label_en"):
                merged_day["label_en"] = new_day["label_en"]
        merged_map[dk] = merged_day

    for dk, day in new_map.items():
        if dk not in merged_map:
            merged_map[dk] = day

    result = sorted(merged_map.values(), key=lambda d: d.get("day_key", ""))

    added = len(result) - len(existing_days)
    if added > 0:
        log.info("day_generator: merge — %d dia(s) novos adicionados", added)

    return result
# services/day_generator.py
"""
Gera listas de dias entre duas datas com labels e campos de tithi.
"""
from datetime import date, timedelta
from typing import List, Dict


MONTHS_PT = {
    1: "jan", 2: "fev", 3: "mar", 4: "abr",
    5: "mai", 6: "jun", 7: "jul", 8: "ago",
    9: "set", 10: "out", 11: "nov", 12: "dez",
}

MONTHS_EN = {
    1: "Jan", 2: "Feb", 3: "Mar", 4: "Apr",
    5: "May", 6: "Jun", 7: "Jul", 8: "Aug",
    9: "Sep", 10: "Oct", 11: "Nov", 12: "Dec",
}


def generate_days(date_start: str, date_end: str, tithis: Dict[str, Dict] | None = None) -> List[Dict]:
    """
    Gera objetos de dia para inserir em `visit["days"]`.

    Args:
        date_start: ISO date string, e.g. "2026-02-18"
        date_end: ISO date string, e.g. "2026-02-27"
        tithis: optional map of date->tithi metadata

    Returns:
        list de dicts com keys esperadas pelo schema de visita.
    """
    start = date.fromisoformat(date_start)
    end = date.fromisoformat(date_end)
    days: List[Dict] = []
    current = start

    while current <= end:
        dk = current.isoformat()
        t = (tithis or {}).get(dk, {})

        days.append({
            "day_key": dk,
            "label_pt": f"{current.day} {MONTHS_PT[current.month]}",
            "label_en": f"{MONTHS_EN[current.month]} {current.day}",
            "tithi": t.get("tithi", ""),
            "tithi_name_pt": t.get("name_pt", ""),
            "tithi_name_en": t.get("name_en", ""),
            "primary_event_key": "",
            "events": [],
        })

        current = current + timedelta(days=1)

    return days
