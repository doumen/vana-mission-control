# services/autofill.py
# -*- coding: utf-8 -*-
"""
🧠 Vana Autofill — Pré-preenchimento inteligente para o Editor de Visitas.

Fases:
  1. Derivação local (sem API) — labels, keys, títulos, timestamps
  2. YouTube Data API v3       — título, duração, thumb
  3. WordPress REST            — kathās existentes, validação de katha_id

Uso:
  from services.autofill import Autofill
  af = Autofill(visit, yt_api_key=..., wp_katha_fetcher=...)
"""

from __future__ import annotations

import re
from datetime import datetime, timedelta
from typing import Optional, Callable

# ══════════════════════════════════════════════════════════════════════
# CONSTANTES
# ══════════════════════════════════════════════════════════════════════

MESES_PT = {
    1: "jan", 2: "fev", 3: "mar", 4: "abr", 5: "mai", 6: "jun",
    7: "jul", 8: "ago", 9: "set", 10: "out", 11: "nov", 12: "dez",
}

MESES_EN = {
    1: "Jan", 2: "Feb", 3: "Mar", 4: "Apr", 5: "May", 6: "Jun",
    7: "Jul", 8: "Aug", 9: "Sep", 10: "Oct", 11: "Nov", 12: "Dec",
}

EVENT_TITLES = {
    "programa":  {"pt": "Programa",       "en": "Program"},
    "mangala":   {"pt": "Maṅgala-ārati",  "en": "Maṅgala-ārati"},
    "arati":     {"pt": "Ārati",          "en": "Ārati"},
    "darshan":   {"pt": "Darśana",        "en": "Darśana"},
    "other":     {"pt": "Evento",         "en": "Event"},
}

SEGMENT_TITLES = {
    "kirtan":       {"pt": "Kīrtana",       "en": "Kīrtana"},
    "harikatha":    {"pt": "Hari-Kathā",    "en": "Hari-Kathā"},
    "pushpanjali":  {"pt": "Puṣpāñjali",   "en": "Puṣpāñjali"},
    "arati":        {"pt": "Ārati",         "en": "Ārati"},
    "dance":        {"pt": "Dança",         "en": "Dance"},
    "drama":        {"pt": "Teatro",        "en": "Theater"},
    "darshan":      {"pt": "Darśana",       "en": "Darśana"},
    "interval":     {"pt": "Intervalo",     "en": "Interval"},
    "noise":        {"pt": "Ruído",         "en": "Noise"},
    "announcement": {"pt": "Anúncio",       "en": "Announcement"},
}

# Segment types que normalmente iniciam um programa
DEFAULT_PROGRAM_SEGMENTS = ["kirtan", "pushpanjali", "harikatha", "dance"]


# ══════════════════════════════════════════════════════════════════════
# HELPERS INTERNOS
# ══════════════════════════════════════════════════════════════════════

def _parse_date(s: str) -> Optional[datetime]:
    """Tenta parsear YYYY-MM-DD. Retorna None se inválido."""
    if not s:
        return None
    try:
        return datetime.strptime(s.strip(), "%Y-%m-%d")
    except ValueError:
        return None


def _date_part(day_key: str) -> str:
    """'2026-02-21' → '20260221'."""
    return (day_key or "0000-00-00").replace("-", "")


def _format_label_pt(dt: datetime) -> str:
    return f"{dt.day} {MESES_PT[dt.month]}"


def _format_label_en(dt: datetime) -> str:
    return f"{MESES_EN[dt.month]} {dt.day}"


def _count_keys_with_prefix(existing: list[str], prefix: str) -> int:
    """Conta quantas keys existentes começam com o prefixo."""
    return sum(1 for k in existing if k.startswith(prefix))


def _parse_iso8601_duration(dur: str) -> Optional[int]:
    """Parseia duração ISO 8601 (PT1H30M15S) para segundos.
    Fallback leve sem dependência externa."""
    if not dur:
        return None
    m = re.match(
        r"^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$", dur, re.IGNORECASE
    )
    if not m:
        return None
    h = int(m.group(1) or 0)
    mn = int(m.group(2) or 0)
    s = int(m.group(3) or 0)
    return h * 3600 + mn * 60 + s


# ══════════════════════════════════════════════════════════════════════
# CLASSE PRINCIPAL
# ══════════════════════════════════════════════════════════════════════

class Autofill:
    """Pré-preenchimento inteligente para o Editor de Visitas.

    Args:
        visit:            dict do visit.json atual
        yt_api_key:       YouTube Data API v3 key (opcional)
        wp_katha_fetcher: callable(visit_ref) → list[dict] (opcional)
                          Cada dict: {id, title, scripture, language, permalink}
    """

    def __init__(
        self,
        visit: dict,
        yt_api_key: Optional[str] = None,
        wp_katha_fetcher: Optional[Callable] = None,
    ):
        self.visit = visit
        self.meta = visit.get("metadata", {})
        self.days = visit.get("days", [])
        self._yt_api_key = yt_api_key
        self._wp_katha_fetcher = wp_katha_fetcher

        # Cache interno
        self._yt_cache: dict[str, dict | None] = {}
        self._wp_kathas_cache: list[dict] | None = None

    # ══════════════════════════════════════════════════════════════════
    # FASE 1 — DERIVAÇÃO LOCAL (sem API)
    # ══════════════════════════════════════════════════════════════════

    # ── Day ────────────────────────────────────────────────────────────

    def suggest_next_day(self) -> dict:
        """Sugere o próximo dia baseado nos existentes ou date_start.

        Returns:
            {day_key, label_pt, label_en}
        """
        if self.days:
            last_key = self.days[-1].get("day_key", "")
            last_dt = _parse_date(last_key)
            next_dt = (last_dt + timedelta(days=1)) if last_dt else datetime.today()
        elif self.meta.get("date_start"):
            next_dt = _parse_date(self.meta["date_start"]) or datetime.today()
        else:
            next_dt = datetime.today()

        return {
            "day_key":  next_dt.strftime("%Y-%m-%d"),
            "label_pt": _format_label_pt(next_dt),
            "label_en": _format_label_en(next_dt),
        }

    def derive_day_labels(self, day_key: str) -> dict:
        """Gera label_pt e label_en a partir de um day_key.

        Args:
            day_key: "2026-02-21"

        Returns:
            {label_pt: "21 fev", label_en: "Feb 21"}
        """
        dt = _parse_date(day_key)
        if not dt:
            return {"label_pt": "", "label_en": ""}
        return {
            "label_pt": _format_label_pt(dt),
            "label_en": _format_label_en(dt),
        }

    # ── Event ──────────────────────────────────────────────────────────

    def suggest_event(
        self,
        day: dict,
        event_type: str,
        time_str: str = "",
    ) -> dict:
        """Sugere campos para um novo evento.

        Args:
            day:        dict do dia (com day_key, label_pt, events, etc.)
            event_type: "programa", "mangala", etc.
            time_str:   "17:03" ou "" se desconhecido

        Returns:
            {event_key, title_pt, title_en, status, location}
        """
        day_key = day.get("day_key", "0000-00-00")
        label_pt = day.get("label_pt", "")
        label_en = day.get("label_en", "")
        date_part = _date_part(day_key)

        # Time part
        time_clean = time_str.replace(":", "").strip() if time_str else ""
        time_part = time_clean if re.match(r"^\d{4}$", time_clean) else "null"

        # Slug
        slug = event_type or "event"

        # Títulos
        titles = EVENT_TITLES.get(event_type, EVENT_TITLES["other"])
        title_pt = f"{titles['pt']} — {label_pt}" if label_pt else titles["pt"]
        title_en = f"{titles['en']} — {label_en}" if label_en else titles["en"]

        # Status herdado da visita
        visit_status = self.meta.get("status", "upcoming")
        status_map = {
            "completed": "past",
            "active":    "active",
            "live":      "live",
        }
        event_status = status_map.get(visit_status, "future")

        # Location — herdar do último evento do dia
        events = day.get("events", [])
        location = {}
        if events:
            last_loc = events[-1].get("location")
            if isinstance(last_loc, dict):
                location = {**last_loc}
            elif isinstance(last_loc, str):
                location = {"name": last_loc}

        return {
            "event_key": f"{date_part}-{time_part}-{slug}",
            "title_pt":  title_pt,
            "title_en":  title_en,
            "status":    event_status,
            "location":  location,
        }

    # ── VOD ───────────────────────────────────────────────────────────

    def suggest_vod(
        self,
        day_key: str,
        event: dict,
        video_id: str = "",
        provider: str = "youtube",
    ) -> dict:
        """Sugere campos para um novo VOD.

        Args:
            day_key:  "2026-02-21"
            event:    dict do evento pai
            video_id: YouTube ID ou outro
            provider: "youtube", "facebook", "drive"

        Returns:
            {vod_key, title_pt, title_en, duration_s, thumb_url, vod_part}
        """
        # vod_key
        all_vod_keys = self._collect_all_vod_keys()
        date_part = _date_part(day_key)
        n = _count_keys_with_prefix(all_vod_keys, f"vod-{date_part}") + 1
        vod_key = f"vod-{date_part}-{n:03d}"

        # vod_part — posição dentro do evento
        existing_vods = event.get("vods", [])
        vod_part = len(existing_vods) + 1

        # Defaults
        title_pt = ""
        title_en = ""
        duration_s = None
        thumb_url = None

        # Enriquecer com YouTube se disponível
        if provider == "youtube" and video_id:
            thumb_url = f"https://img.youtube.com/vi/{video_id}/maxresdefault.jpg"

            yt = self.fetch_youtube_meta(video_id)
            if yt:
                title_pt = yt.get("title", "")
                title_en = yt.get("title", "")  # YouTube dá 1 título
                duration_s = yt.get("duration_s")

        # Fallback de título
        if not title_pt:
            ev_title = event.get("title_pt", "")
            if vod_part > 1:
                title_pt = f"{ev_title} — Pt.{vod_part}" if ev_title else f"Vídeo {vod_part}"
            else:
                title_pt = ev_title or "Vídeo"

        if not title_en:
            ev_title = event.get("title_en", "")
            if vod_part > 1:
                title_en = f"{ev_title} — Pt.{vod_part}" if ev_title else f"Video {vod_part}"
            else:
                title_en = ev_title or "Video"

        return {
            "vod_key":    vod_key,
            "title_pt":   title_pt,
            "title_en":   title_en,
            "duration_s": duration_s,
            "thumb_url":  thumb_url,
            "vod_part":   vod_part,
        }

    # ── Segment ───────────────────────────────────────────────────────

    def suggest_segment(
        self,
        vod: dict,
        day_key: str,
        seg_type: str,
        event: Optional[dict] = None,
    ) -> dict:
        """Sugere campos para um novo segment.

        Args:
            vod:      dict do VOD pai
            day_key:  "2026-02-21"
            seg_type: "kirtan", "harikatha", etc.
            event:    dict do evento (para herdar katha_id)

        Returns:
            {segment_id, timestamp_start, timestamp_end, title_pt, title_en,
             katha_id_hint}
        """
        segs = vod.get("segments", [])

        # segment_id
        all_seg_ids = self._collect_all_segment_ids()
        date_part = _date_part(day_key)
        n = _count_keys_with_prefix(all_seg_ids, f"seg-{date_part}") + 1
        seg_id = f"seg-{date_part}-{n:03d}"

        # timestamp_start = fim do último segment + 1
        ts_start = 0
        if segs:
            last_end = segs[-1].get("timestamp_end", 0)
            ts_start = last_end + 1 if last_end > 0 else 0

        # timestamp_end = duração do VOD (para o último segment)
        vod_duration = vod.get("duration_s") or 0
        ts_end = vod_duration if vod_duration > ts_start else ts_start

        # Títulos baseados no tipo
        titles = SEGMENT_TITLES.get(seg_type, {"pt": seg_type, "en": seg_type})

        # katha_id — herdar do evento se harikatha
        katha_id_hint = None
        if seg_type == "harikatha" and event:
            katha_id_hint = self._find_event_katha_id(event)

        return {
            "segment_id":      seg_id,
            "timestamp_start": ts_start,
            "timestamp_end":   ts_end,
            "title_pt":        titles["pt"],
            "title_en":        titles["en"],
            "katha_id_hint":   katha_id_hint,
        }

    def suggest_program_segments(self, vod: dict, day_key: str) -> list[dict]:
        """Sugere uma sequência padrão de segments para um programa.

        Sequência típica: kirtan → pushpanjali → harikatha → dance/drama

        Returns:
            Lista de dicts com sugestões para cada segment
        """
        duration = vod.get("duration_s") or 0
        if duration == 0:
            return []

        # Proporções típicas de um programa
        proportions = [
            ("kirtan",      0.20),
            ("pushpanjali", 0.15),
            ("harikatha",   0.45),
            ("dance",       0.20),
        ]

        suggestions = []
        cursor = 0

        all_seg_ids = self._collect_all_segment_ids()
        date_part = _date_part(day_key)

        for seg_type, ratio in proportions:
            seg_duration = int(duration * ratio)
            ts_start = cursor
            ts_end = cursor + seg_duration

            # Último segment vai até o fim do VOD
            if seg_type == proportions[-1][0]:
                ts_end = duration

            n = _count_keys_with_prefix(all_seg_ids, f"seg-{date_part}") + 1
            seg_id = f"seg-{date_part}-{n:03d}"
            all_seg_ids.append(seg_id)

            titles = SEGMENT_TITLES.get(seg_type, {"pt": seg_type, "en": seg_type})

            suggestions.append({
                "segment_id":      seg_id,
                "type":            seg_type,
                "timestamp_start": ts_start,
                "timestamp_end":   ts_end,
                "title_pt":        titles["pt"],
                "title_en":        titles["en"],
                "katha_id":        None,
            })

            cursor = ts_end + 1

        return suggestions

    # ══════════════════════════════════════════════════════════════════
    # FASE 2 — YOUTUBE DATA API v3
    # ══════════════════════════════════════════════════════════════════

    def fetch_youtube_meta(self, video_id: str) -> Optional[dict]:
        """Busca metadados de um vídeo via YouTube Data API v3.

        Retorna None se API key ausente ou vídeo não encontrado.

        Returns:
            {title, description, duration_s, language, thumb_url, published}
        """
        if not self._yt_api_key:
            return None

        if not video_id or len(video_id) != 11:
            return None

        # Cache
        if video_id in self._yt_cache:
            return self._yt_cache[video_id]

        try:
            import requests

            resp = requests.get(
                "https://www.googleapis.com/youtube/v3/videos",
                params={
                    "id":   video_id,
                    "part": "snippet,contentDetails",
                    "key":  self._yt_api_key,
                },
                timeout=10,
            )

            if not resp.ok:
                self._yt_cache[video_id] = None
                return None

            items = resp.json().get("items", [])
            if not items:
                self._yt_cache[video_id] = None
                return None

            item = items[0]
            snippet = item.get("snippet", {})
            content = item.get("contentDetails", {})

            duration_s = _parse_iso8601_duration(content.get("duration", ""))

            result = {
                "title":       snippet.get("title", ""),
                "description": (snippet.get("description", "") or "")[:300],
                "duration_s":  duration_s,
                "language":    snippet.get("defaultAudioLanguage", ""),
                "thumb_url":   f"https://img.youtube.com/vi/{video_id}/maxresdefault.jpg",
                "published":   (snippet.get("publishedAt", "") or "")[:10],
                "channel":     snippet.get("channelTitle", ""),
            }

            self._yt_cache[video_id] = result
            return result

        except Exception:
            self._yt_cache[video_id] = None
            return None

    def validate_youtube_id(self, video_id: str) -> dict:
        """Valida se um YouTube ID existe e retorna status.

        Returns:
            {valid: bool, title: str, reason: str}
        """
        if not video_id or len(video_id) != 11:
            return {
                "valid": False,
                "title": "",
                "reason": f"ID deve ter 11 caracteres (tem {len(video_id or '')})",
            }

        meta = self.fetch_youtube_meta(video_id)
        if meta:
            return {
                "valid": True,
                "title": meta["title"],
                "reason": "OK",
            }

        # Sem API key — validação por thumb (fallback)
        if not self._yt_api_key:
            try:
                import requests
                resp = requests.head(
                    f"https://img.youtube.com/vi/{video_id}/mqdefault.jpg",
                    timeout=5,
                    allow_redirects=True,
                )
                # YouTube retorna 120x90 placeholder se não existe
                if resp.ok and int(resp.headers.get("content-length", 0)) > 2000:
                    return {
                        "valid": True,
                        "title": "(sem API key — thumb existe)",
                        "reason": "Thumb encontrada, mas sem metadados",
                    }
            except Exception:
                pass

        return {
            "valid": False,
            "title": "",
            "reason": "Vídeo não encontrado ou API indisponível",
        }

    # ══════════════════════════════════════════════════════════════════
    # FASE 3 — WORDPRESS REST (kathās)
    # ══════════════════════════════════════════════════════════════════

    def list_kathas(self) -> list[dict]:
        """Lista kathās disponíveis no WordPress para esta visita.

        Returns:
            [{id, title, scripture, language, permalink}, ...]
        """
        if self._wp_kathas_cache is not None:
            return self._wp_kathas_cache

        if not self._wp_katha_fetcher:
            self._wp_kathas_cache = []
            return []

        try:
            visit_ref = self.visit.get("visit_ref", "")
            kathas = self._wp_katha_fetcher(visit_ref)
            self._wp_kathas_cache = kathas if isinstance(kathas, list) else []
        except Exception:
            self._wp_kathas_cache = []

        return self._wp_kathas_cache

    def katha_dropdown_options(self) -> dict[str, int | None]:
        """Gera opções formatadas para um st.selectbox de kathās.

        Returns:
            {"#678 · SB 10.31 — Gopī-gīta (hi)": 678, ...}
            Inclui opções especiais no início e fim.
        """
        options: dict[str, int | None] = {
            "— Nenhum —": None,
        }

        kathas = self.list_kathas()
        for k in kathas:
            kid = k.get("id")
            title = k.get("title", "?")[:45]
            scripture = k.get("scripture", "")
            lang = k.get("language", "")

            label_parts = [f"#{kid}"]
            if scripture:
                label_parts.append(scripture)
            label_parts.append(f"— {title}")
            if lang:
                label_parts.append(f"({lang})")

            label = " ".join(label_parts)
            options[label] = kid

        options["🔢 ID manual..."] = -1

        return options

    def validate_katha_id(self, katha_id: int) -> dict:
        """Valida se um katha_id existe no WordPress.

        Returns:
            {valid: bool, title: str, scripture: str, language: str, reason: str}
        """
        if not katha_id:
            return {
                "valid":     False,
                "title":     "",
                "scripture": "",
                "language":  "",
                "reason":    "katha_id vazio",
            }

        kathas = self.list_kathas()
        for k in kathas:
            if k.get("id") == katha_id:
                return {
                    "valid":     True,
                    "title":     k.get("title", ""),
                    "scripture": k.get("scripture", ""),
                    "language":  k.get("language", ""),
                    "reason":    "OK",
                }

        return {
            "valid":     False,
            "title":     "",
            "scripture": "",
            "language":  "",
            "reason":    f"Kathā #{katha_id} não encontrada no WordPress",
        }

    # ══════════════════════════════════════════════════════════════════
    # COLETORES INTERNOS
    # ══════════════════════════════════════════════════════════════════

    def _collect_all_vod_keys(self) -> list[str]:
        """Coleta todas as vod_keys existentes na visita."""
        keys: list[str] = []
        for day in self.days:
            for ev in day.get("events", []):
                for vod in ev.get("vods", []):
                    vk = vod.get("vod_key")
                    if vk:
                        keys.append(vk)
        for vod in self.visit.get("orphans", {}).get("vods", []):
            vk = vod.get("vod_key")
            if vk:
                keys.append(vk)
        return keys

    def _collect_all_segment_ids(self) -> list[str]:
        """Coleta todos os segment_ids existentes na visita."""
        ids: list[str] = []
        for day in self.days:
            for ev in day.get("events", []):
                for vod in ev.get("vods", []):
                    for seg in vod.get("segments", []):
                        sid = seg.get("segment_id")
                        if sid:
                            ids.append(sid)
        return ids

    def _find_event_katha_id(self, event: dict) -> Optional[int]:
        """Procura katha_id já usado nos segments deste evento."""
        for vod in event.get("vods", []):
            for seg in vod.get("segments", []):
                kid = seg.get("katha_id")
                if kid:
                    return kid
        return None

    # ══════════════════════════════════════════════════════════════════
    # UTILITÁRIOS PÚBLICOS
    # ══════════════════════════════════════════════════════════════════

    def format_duration(self, seconds: int | None) -> str:
        """Formata segundos para HH:MM:SS legível."""
        if not seconds:
            return "—"
        h = seconds // 3600
        m = (seconds % 3600) // 60
        s = seconds % 60
        if h > 0:
            return f"{h}h{m:02d}m{s:02d}s"
        elif m > 0:
            return f"{m}m{s:02d}s"
        else:
            return f"{s}s"

    def format_timestamp_range(self, start: int, end: int) -> str:
        """Formata intervalo de timestamps."""
        return f"{self.format_duration(start)} → {self.format_duration(end)}"

    def summary(self) -> dict:
        """Retorna um resumo da visita para diagnóstico."""
        total_vods = len(self._collect_all_vod_keys())
        total_segs = len(self._collect_all_segment_ids())

        hk_segments = []
        hk_without_id = []
        for day in self.days:
            for ev in day.get("events", []):
                for vod in ev.get("vods", []):
                    for seg in vod.get("segments", []):
                        if seg.get("type") == "harikatha":
                            hk_segments.append(seg.get("segment_id"))
                            if not seg.get("katha_id"):
                                hk_without_id.append(seg.get("segment_id"))

        return {
            "visit_ref":      self.visit.get("visit_ref", ""),
            "schema_version": self.visit.get("schema_version", ""),
            "total_days":     len(self.days),
            "total_events":   sum(len(d.get("events", [])) for d in self.days),
            "total_vods":     total_vods,
            "total_segments": total_segs,
            "total_hk":       len(hk_segments),
            "hk_without_id":  hk_without_id,
            "yt_api_available": self._yt_api_key is not None,
            "wp_api_available": self._wp_katha_fetcher is not None,
        }
