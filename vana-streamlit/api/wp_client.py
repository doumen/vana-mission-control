# api/wp_client.py

import json
import requests
from typing import Optional

WP_BASE_URL = "https://beta.vanamadhuryamdaily.com/wp-json/wp/v2"
WP_AUTH = ("vana-streamlit", "KZzqSMQFYAlY7Xwj6nLko9Eu")


def normalize_status(s: str | None, target: str = "event") -> str:
    """
    Normaliza valores de status vindos do WP/timeline para um conjunto canônico.

    target = "event" -> retorna um de: 'past', 'live', 'upcoming'
    target = "visit" -> retorna um de: 'completed', 'active', 'draft'
    """
    if not s:
        return "?"
    v = str(s).lower()
    # event-level normalization
    if target == "event":
        if v in ("past", "done", "completed"):
            return "past"
        if v in ("live", "active", "running"):
            return "live"
        if v in ("upcoming", "future", "scheduled"):
            return "upcoming"
        return v

    # visit-level normalization
    if target == "visit":
        if v in ("completed", "done"):
            return "completed"
        if v in ("active", "publish", "published"):
            return "active"
        if v in ("draft", "pending"):
            return "draft"
        return v



def get_visit_timeline(visit_id: int) -> dict:
    """
    Retorna o timeline completo da visita (dict com days[], schema_version, etc.)
    Lê de _vana_visit_timeline_json via REST.
    """
    url = f"{WP_BASE_URL}/vana_visit/{visit_id}?_fields=id,meta"
    resp = requests.get(url, auth=WP_AUTH, timeout=15)
    resp.raise_for_status()

    raw = resp.json().get("meta", {}).get("_vana_visit_timeline_json", "")

    if not raw:
        return {}

    data = json.loads(raw) if isinstance(raw, str) else raw

    # Normalize timeline values if we got a dict
    if isinstance(data, dict):
        meta_md = data.get("metadata", {})
        if isinstance(meta_md, dict) and "status" in meta_md:
            meta_md["status"] = normalize_status(meta_md.get("status"), target="visit")

        for day in data.get("days", []) or []:
            for sched in (day.get("schedule") or []):
                if "status" in sched:
                    sched["status"] = normalize_status(sched.get("status"), target="event")
            for ev in (day.get("events") or []):
                if "status" in ev:
                    ev["status"] = normalize_status(ev.get("status"), target="event")

    return data


def get_visit_days(visit_id: int) -> list[dict]:
    """
    Retorna apenas o array days[] do timeline da visita.
    """
    return get_visit_timeline(visit_id).get("days", [])


def get_day(visit_id: int, date_local: str) -> Optional[dict]:
    """
    Retorna um dia específico pelo date_local (ex: '2026-02-21').
    """
    days = get_visit_days(visit_id)
    return next((d for d in days if d["date_local"] == date_local), None)


def get_day_vods(visit_id: int, date_local: str) -> list[dict]:
    """Retorna os VODs de um dia específico."""
    day = get_day(visit_id, date_local)
    return day.get("vods", []) if day else []


def get_day_schedule(visit_id: int, date_local: str) -> list[dict]:
    """Retorna a programação de um dia específico."""
    day = get_day(visit_id, date_local)
    return day.get("schedule", []) if day else []


def get_day_sangha_moments(visit_id: int, date_local: str) -> list[dict]:
    """Retorna os momentos da sangha de um dia."""
    day = get_day(visit_id, date_local)
    return day.get("sangha_moments", []) if day else []

# ADICIONAR AO FINAL de api/wp_client.py

def patch_visit_timeline(visit_id: int, timeline: dict) -> dict:
    """
    Salva o timeline completo de volta no WP via REST.
    Uso: patch_visit_timeline(359, timeline_modificado)
    """
    url = f"{WP_BASE_URL}/vana_visit/{visit_id}"
    payload = {
        "meta": {
            "_vana_visit_timeline_json": json.dumps(timeline, ensure_ascii=False)
        }
    }
    resp = requests.post(url, auth=WP_AUTH, json=payload, timeout=15)
    resp.raise_for_status()
    return resp.json()


def update_schedule_status(visit_id: int, date_local: str,
                           time_local: str, status: str) -> bool:
    """
    Muda o status de um item do schedule.
    status: 'upcoming' | 'live' | 'done'
    """
    timeline = get_visit_timeline(visit_id)
    for day in timeline.get("days", []):
        if day["date_local"] == date_local:
            for item in day.get("schedule", []):
                if item["time_local"] == time_local:
                    item["status"] = status
                    patch_visit_timeline(visit_id, timeline)
                    return True
    return False


def add_vod_to_day(visit_id: int, date_local: str, vod: dict) -> bool:
    """
    Adiciona um VOD ao array vods[] de um dia.
    """
    timeline = get_visit_timeline(visit_id)
    for day in timeline.get("days", []):
        if day["date_local"] == date_local:
            day.setdefault("vods", []).append(vod)
            patch_visit_timeline(visit_id, timeline)
            return True
    return False


def update_day_field(visit_id: int, date_local: str,
                     field: str, value) -> bool:
    """
    Atualiza qualquer campo de um dia específico.
    Ex: update_day_field(359, '2026-02-21', 'hero', {...})
    """
    timeline = get_visit_timeline(visit_id)
    for day in timeline.get("days", []):
        if day["date_local"] == date_local:
            day[field] = value
            patch_visit_timeline(visit_id, timeline)
            return True
    return False

def update_visit_tour(visit_id: int, tour_origin_key: str) -> bool:
    """
    Atualiza o tour pai de uma visita diretamente via REST.
    Garante que _vana_parent_tour_origin_key seja gravado.
    """
    if not tour_origin_key.startswith("tour:"):
        tour_origin_key = f"tour:{tour_origin_key}"

    url = f"{WP_BASE_URL}/vana_visit/{visit_id}"
    payload = {
        "meta": {
            "_vana_parent_tour_origin_key": tour_origin_key,
            "_tour_parent_key":             tour_origin_key,  # fallback
        }
    }
    resp = requests.post(url, auth=WP_AUTH, json=payload, timeout=15)
    resp.raise_for_status()
    return True

# Adicionar ao final de api/wp_client.py

def list_visits_rest(per_page: int = 100) -> list[dict]:
    """
    Lista visitas diretamente via WP REST API.
    Mais confiável que hmac_client para campos de meta como tour_key.
    """
    url = (
        f"{WP_BASE_URL}/vana_visit"
        f"?per_page={per_page}"
        f"&_fields=id,title,status,slug,meta"
        f"&orderby=date&order=desc"
    )
    resp = requests.get(url, auth=WP_AUTH, timeout=15)
    resp.raise_for_status()

    items = []
    for v in resp.json():
        meta = v.get("meta", {})
        tl_raw = meta.get("_vana_visit_timeline_json", "{}")
        try:
            tl = json.loads(tl_raw) if isinstance(tl_raw, str) else tl_raw
        except Exception:
            tl = {}
        # Normalize timeline values if present
        if isinstance(tl, dict):
            md = tl.get("metadata", {})
            if isinstance(md, dict) and "status" in md:
                md["status"] = normalize_status(md.get("status"), target="visit")
            for day in tl.get("days", []) or []:
                for sched in (day.get("schedule") or []):
                    if "status" in sched:
                        sched["status"] = normalize_status(sched.get("status"), target="event")
                for ev in (day.get("events") or []):
                    if "status" in ev:
                        ev["status"] = normalize_status(ev.get("status"), target="event")

        items.append({
            "id":         v.get("id"),
            "title":      v.get("title", {}).get("rendered", f"ID {v.get('id')}"),
            # prefer timeline metadata status if available
            "status":     tl.get("metadata", {}).get("status", v.get("status", "—")),
            "slug":       v.get("slug", "—"),
            "origin_key": meta.get("_vana_origin_key", "—"),
            "tour_key":   meta.get("_vana_parent_tour_origin_key", "—"),  # ← fonte correta
            "start_date": tl.get("start_date", "—"),
            "schema_ver": tl.get("schema_version", "—"),
            "permalink":  f"https://beta.vanamadhuryamdaily.com/visit/{v.get('slug', '')}/"
        })
    return items

# Adicionar ao final de api/wp_client.py

def list_tours(per_page: int = 100) -> list[dict]:
    """
    Lista tours diretamente via WP REST API.
    """
    url = (
        f"{WP_BASE_URL}/vana_tour"
        f"?per_page={per_page}"
        f"&_fields=id,title,status,slug,meta"
        f"&orderby=date&order=desc"
    )
    resp = requests.get(url, auth=WP_AUTH, timeout=15)
    resp.raise_for_status()

    items = []
    for t in resp.json():
        meta = t.get("meta", {})
        items.append({
            "id":         t.get("id"),
            "title":      t.get("title", {}).get("rendered", f"ID {t.get('id')}"),
            "status":     t.get("status", "—"),
            "slug":       t.get("slug", "—"),
            "origin_key": meta.get("_vana_origin_key", "—"),
            "permalink":  f"https://beta.vanamadhuryamdaily.com/tour/{t.get('slug', '')}/",
        })
    return items


# Adicionar ao final de api/wp_client.py

def trash_tour(tour_id: int) -> bool:
    """Move uma tour para a lixeira via REST."""
    url = f"{WP_BASE_URL}/vana_tour/{tour_id}"
    resp = requests.delete(url, auth=WP_AUTH, timeout=15)
    resp.raise_for_status()
    return True


def update_tour(
    tour_id: int,
    title: str | None = None,
    origin_key: str | None = None,
    title_pt: str | None = None,
    title_en: str | None = None,
    region_code: str | None = None,
    season_code: str | None = None,
    year_start: int | None = None,
    year_end: int | None = None,
) -> bool:
    """
    Atualiza campos de uma tour via REST API.
    Aceita títulos PT/EN e metadados usados pelo plugin (mapeia para meta keys).
    Ex: update_tour(390, title='Novo Título', title_pt='Título PT')
    """
    url = f"{WP_BASE_URL}/vana_tour/{tour_id}"
    payload = {}

    if title is not None:
        payload["title"] = title

    # Meta fields used by the plugin
    meta = payload.get("meta") if isinstance(payload.get("meta"), dict) else {}
    if origin_key is not None:
        meta["_vana_origin_key"] = origin_key
        meta["_tour_origin_key"] = origin_key
    if title_pt is not None:
        meta["_vana_title_pt"] = title_pt
    if title_en is not None:
        meta["_vana_title_en"] = title_en
    if region_code is not None:
        meta["_vana_region_code"] = region_code
    if season_code is not None:
        meta["_vana_season_code"] = season_code
    if year_start is not None:
        meta["_vana_year_start"] = int(year_start)
    if year_end is not None:
        meta["_vana_year_end"] = int(year_end)

    if meta:
        payload["meta"] = meta

    if not payload:
        return False

    resp = requests.post(url, auth=WP_AUTH, json=payload, timeout=15)
    resp.raise_for_status()
    return True

def list_visits_wp(per_page: int = 100) -> list[dict]:
    """Lista vana_visit do WP REST API, retorna metadados leves."""
    import json as _json
    import streamlit as st

    base   = st.secrets["vana"]["api_base"].rstrip("/")
    user   = st.secrets["vana"]["wp_user"]
    passwd = st.secrets["vana"]["wp_app_password"]

    url = f"{base}/wp/v2/vana_visit"
    params = {
        "per_page": per_page,
        "status":   "any",
        "_fields":  "id,slug,status,link,modified,meta",
    }

    def normalize_status(s: str | None, target: str = "event") -> str:
        """
        Normaliza valores de status vindos do WP/timeline para um conjunto canônico.

        target = "event" -> retorna um de: 'past', 'live', 'upcoming'
        target = "visit" -> retorna um de: 'completed', 'active', 'draft'
        """
        if not s:
            return "?"
        v = str(s).lower()
        # event-level normalization
        if target == "event":
            if v in ("past", "done", "completed"):
                return "past"
            if v in ("live", "active", "running"):
                return "live"
            if v in ("upcoming", "future", "scheduled"):
                return "upcoming"
            return v

        # visit-level normalization
        if target == "visit":
            if v in ("completed", "done"):
                return "completed"
            if v in ("active", "publish", "published"):
                return "active"
            if v in ("draft", "pending"):
                return "draft"
            return v

    try:
        r = requests.get(url, params=params, auth=(user, passwd), timeout=20)
        r.raise_for_status()
    except requests.HTTPError as e:
        # Some app-password users are not permitted to query `status=any`.
        # If WP returns 400 with `rest_forbidden_status`, retry without the status param.
        resp = getattr(e, 'response', None) or (locals().get('r') if 'r' in locals() else None)
        if resp is not None and resp.status_code == 400 and resp.text and 'rest_forbidden_status' in resp.text:
            params.pop('status', None)
            r = requests.get(url, params=params, auth=(user, passwd), timeout=20)
            r.raise_for_status()
        else:
            raise

    results = []
    for item in r.json():
        meta = item.get("meta", {})

        # ── Lê o campo correto ─────────────────────────────────────
        raw = (
            meta.get("_vana_visit_timeline_json")   # ← campo real no WP
            or meta.get("_vana_data")               # fallback legado
            or {}
        )
        if isinstance(raw, str):
            try:
                vana_data = _json.loads(raw)
            except Exception:
                vana_data = {}
        else:
            vana_data = raw or {}
        # Normalize timeline event/status values for downstream consumers
        if isinstance(vana_data, dict):
            meta_md = vana_data.get("metadata", {})
            # normalize visit-level metadata.status
            if isinstance(meta_md, dict) and "status" in meta_md:
                meta_md["status"] = normalize_status(meta_md.get("status"), target="visit")

            for day in vana_data.get("days", []) or []:
                # normalize schedule items
                for sched in (day.get("schedule") or []):
                    if "status" in sched:
                        sched["status"] = normalize_status(sched.get("status"), target="event")
                # normalize event-level statuses
                for ev in (day.get("events") or []):
                    if "status" in ev:
                        ev["status"] = normalize_status(ev.get("status"), target="event")
        # ──────────────────────────────────────────────────────────

        results.append({
            "id":        item.get("id"),
            "wp_id":     item.get("id"),             # ← expõe o id para _load()
            "visit_ref": (
                vana_data.get("visit_ref")
                or meta.get("_vana_origin_key", "").replace("visit:", "")
                or item.get("slug", "")
            ),
            "tour_ref": (
                vana_data.get("tour_ref")
                or meta.get("_vana_parent_origin_key", "").replace("tour:", "")
                or "__sem_tour__"
            ),
            "title_pt":   vana_data.get("title_pt", ""),
            "schema_ver": vana_data.get("schema_version", "?"),
            "status":     vana_data.get("metadata", {}).get("status", "?"),
            "date_start": vana_data.get("metadata", {}).get("date_start", ""),
            "wp_status":  item.get("status", ""),
            "permalink":  item.get("link", ""),
            "updated_at": item.get("modified", ""),
        })

    results.sort(key=lambda x: x["date_start"], reverse=True)
    return results


# ══════════════════════════════════════════════════════════════════════
# KATHAS — leitura do CPT vana_katha via WP REST
# ══════════════════════════════════════════════════════════════════════

# Meta keys usadas pelo plugin Vana para o CPT vana_katha.
# Ajuste se o plugin usar nomes diferentes.
_KATHA_META_KEYS = {
    "scripture":       "_vana_scripture",        # ex: "SB 10.31"
    "language":        "_vana_language",          # ex: "hi", "en", "pt"
    "katha_ref":       "_vana_katha_ref",         # ex: "katha:sb-10-31-gopi-gita-navadvipa-2026"
    "visit_ref":       "_vana_visit_ref",         # ex: "navadvipa-2026-feb"
    "total_passages":  "_vana_total_passages",    # ex: 42
    "date_recorded":   "_vana_date_recorded",     # ex: "2026-02-22"
}


def get_katha_meta(katha_id: int) -> Optional[dict]:
    """
    Busca metadados de uma única kathā pelo WP post ID.

    Returns:
        {id, title, scripture, language, katha_ref, visit_ref,
         total_passages, date_recorded, permalink, wp_status}
        ou None se não encontrada.
    """
    url = f"{WP_BASE_URL}/vana_katha/{katha_id}"
    params = {"_fields": "id,title,status,slug,link,meta"}

    try:
        resp = requests.get(url, auth=WP_AUTH, params=params, timeout=15)
        if resp.status_code == 404:
            return None
        resp.raise_for_status()
    except requests.RequestException:
        return None

    item = resp.json()
    meta = item.get("meta", {})

    return {
        "id":              item.get("id"),
        "title":           item.get("title", {}).get("rendered", f"Kathā #{katha_id}"),
        "scripture":       meta.get(_KATHA_META_KEYS["scripture"], ""),
        "language":        meta.get(_KATHA_META_KEYS["language"], ""),
        "katha_ref":       meta.get(_KATHA_META_KEYS["katha_ref"], ""),
        "visit_ref":       meta.get(_KATHA_META_KEYS["visit_ref"], ""),
        "total_passages":  meta.get(_KATHA_META_KEYS["total_passages"], None),
        "date_recorded":   meta.get(_KATHA_META_KEYS["date_recorded"], ""),
        "permalink":       item.get("link", ""),
        "wp_status":       item.get("status", ""),
        "slug":            item.get("slug", ""),
    }


def get_katha_meta_batch(katha_ids: list[int]) -> dict[int, dict]:
    """
    Busca metadados de múltiplas kathās em uma única request (via include[]).

    Limitação WP REST: máx ~100 IDs por request. Para listas maiores,
    fazer paginação manual.
    """
    if not katha_ids:
        return {}

    # Dedupe e limita
    unique_ids = list(set(katha_ids))[:100]

    url = f"{WP_BASE_URL}/vana_katha"
    params = {
        "include":  ",".join(str(i) for i in unique_ids),
        "per_page": len(unique_ids),
        "_fields":  "id,title,status,slug,link,meta",
    }

    try:
        resp = requests.get(url, auth=WP_AUTH, params=params, timeout=20)
        resp.raise_for_status()
    except requests.RequestException:
        # Fallback: buscar uma a uma
        results = {}
        for kid in unique_ids:
            meta = get_katha_meta(kid)
            if meta:
                results[kid] = meta
        return results

    results = {}
    for item in resp.json():
        meta = item.get("meta", {})
        kid = item.get("id")
        if kid is None:
            continue

        results[kid] = {
            "id":              kid,
            "title":           item.get("title", {}).get("rendered", f"Kathā #{kid}"),
            "scripture":       meta.get(_KATHA_META_KEYS["scripture"], ""),
            "language":        meta.get(_KATHA_META_KEYS["language"], ""),
            "katha_ref":       meta.get(_KATHA_META_KEYS["katha_ref"], ""),
            "visit_ref":       meta.get(_KATHA_META_KEYS["visit_ref"], ""),
            "total_passages":  meta.get(_KATHA_META_KEYS["total_passages"], None),
            "date_recorded":   meta.get(_KATHA_META_KEYS["date_recorded"], ""),
            "permalink":       item.get("link", ""),
            "wp_status":       item.get("status", ""),
            "slug":            item.get("slug", ""),
        }

    return results


def list_kathas_for_visit(visit_ref: str) -> list[dict]:
    """
    Lista todas as kathās vinculadas a uma visita específica.

    Busca via meta query: _vana_visit_ref = visit_ref.
    Se o plugin não suportar meta query, faz fallback listando todas
    e filtrando client-side.
    """
    # Estratégia 1: meta_query (requer que o plugin exposa o meta como filterable)
    url = f"{WP_BASE_URL}/vana_katha"
    params = {
        "per_page":  100,
        "_fields":   "id,title,status,slug,link,meta",
        "orderby":   "date",
        "order":     "asc",
    }

    # Tentar meta_query via query param (depende do plugin registrar o meta como queryable)
    # Formato: ?meta_key=_vana_visit_ref&meta_value=navadvipa-2026-feb
    if visit_ref:
        params["meta_key"]   = _KATHA_META_KEYS["visit_ref"]
        params["meta_value"] = visit_ref

    try:
        resp = requests.get(url, auth=WP_AUTH, params=params, timeout=20)

        # Se WP retorna 400 (meta_query não suportada), fallback
        if resp.status_code == 400:
            return _list_kathas_fallback(visit_ref)

        resp.raise_for_status()
        items = resp.json()

    except requests.RequestException:
        return _list_kathas_fallback(visit_ref)

    return _parse_katha_list(items)


def _list_kathas_fallback(visit_ref: str) -> list[dict]:
    """
    Fallback: lista TODAS as kathās e filtra client-side por visit_ref.
    Menos eficiente, mas funciona sem meta_query no REST.
    """
    url = f"{WP_BASE_URL}/vana_katha"
    params = {
        "per_page": 100,
        "_fields":  "id,title,status,slug,link,meta",
        "orderby":  "date",
        "order":    "asc",
    }

    try:
        resp = requests.get(url, auth=WP_AUTH, params=params, timeout=20)
        resp.raise_for_status()
        all_items = resp.json()
    except requests.RequestException:
        return []

    # Filtrar client-side
    filtered = []
    for item in all_items:
        meta = item.get("meta", {})
        item_visit_ref = meta.get(_KATHA_META_KEYS["visit_ref"], "")
        if item_visit_ref == visit_ref:
            filtered.append(item)

    return _parse_katha_list(filtered)


def _parse_katha_list(items: list) -> list[dict]:
    """Converte items crus do WP REST para o formato padronizado."""
    results = []
    for item in items:
        meta = item.get("meta", {})
        kid = item.get("id")
        if kid is None:
            continue

        results.append({
            "id":              kid,
            "title":           item.get("title", {}).get("rendered", f"Kathā #{kid}"),
            "scripture":       meta.get(_KATHA_META_KEYS["scripture"], ""),
            "language":        meta.get(_KATHA_META_KEYS["language"], ""),
            "katha_ref":       meta.get(_KATHA_META_KEYS["katha_ref"], ""),
            "visit_ref":       meta.get(_KATHA_META_KEYS["visit_ref"], ""),
            "total_passages":  meta.get(_KATHA_META_KEYS["total_passages"], None),
            "date_recorded":   meta.get(_KATHA_META_KEYS["date_recorded"], ""),
            "permalink":       item.get("link", ""),
            "wp_status":       item.get("status", ""),
            "slug":            item.get("slug", ""),
        })

    # Ordenar por date_recorded
    results.sort(key=lambda x: x.get("date_recorded") or "")
    return results


def list_kathas_all(per_page: int = 100) -> list[dict]:
    """
    Lista TODAS as kathās publicadas no WP.
    Útil para diagnóstico e para popular dropdowns sem filtro de visit.

    Returns:
        [{id, title, scripture, language, ...}, ...]
    """
    url = f"{WP_BASE_URL}/vana_katha"
    params = {
        "per_page": per_page,
        "_fields":  "id,title,status,slug,link,meta",
        "orderby":  "date",
        "order":    "desc",
    }

    try:
        resp = requests.get(url, auth=WP_AUTH, params=params, timeout=20)
        resp.raise_for_status()
    except requests.RequestException:
        return []

    return _parse_katha_list(resp.json())
