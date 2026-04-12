# pages/2_Visits.py
# -*- coding: utf-8 -*-
"""
📅 Editor de Visitas — Schema 6.1
Edita visit.json via GitHub e publica no WP via Trator.
"""

from __future__ import annotations

import json
import re as _re
from datetime import datetime, timezone
from pathlib import Path
import sys
import copy

import streamlit as st

# Ensure both the local `vana-streamlit` package and the repository root
# are on sys.path so `from api...` and `from trator...` imports work.
VANA_ROOT = Path(__file__).resolve().parents[1]
REPO_ROOT = Path(__file__).resolve().parents[2]
for _p in (str(VANA_ROOT), str(REPO_ROOT)):
    if _p not in sys.path:
        sys.path.insert(0, _p)

from api.github_client import GitHubClient
from components.conflict_resolver import render_conflict_resolver
from services.conflict_guard import check_conflict, stamp_revision

from trator.vana_trator import run_trator, TratorResult

# Optional Autofill helper (services/autofill.py)
try:
    from services.autofill import Autofill
except Exception:
    Autofill = None

# ── R2 Service (CDN de fotos) — global helper
_r2 = None
try:
    from services.r2_service import R2Service
    _r2_cfg = st.secrets.get("r2", {})
    if isinstance(_r2_cfg, dict) and _r2_cfg.get("endpoint"):
        _r2 = R2Service(
            endpoint    = _r2_cfg.get("endpoint", ""),
            access_key  = _r2_cfg.get("access_key", ""),
            secret_key  = _r2_cfg.get("secret_key", ""),
            bucket      = _r2_cfg.get("bucket", ""),
            public_base = _r2_cfg.get("public_base", ""),
        )
except Exception:
    _r2 = None


# ── Day Generator + Tithi Fetcher (opcionais)
try:
    from services.day_generator import generate_days, merge_days, MONTHS_PT, MONTHS_EN, WEEKDAYS_PT, WEEKDAYS_EN
except Exception:
    generate_days = None
    merge_days = None
    MONTHS_PT = MONTHS_EN = WEEKDAYS_PT = WEEKDAYS_EN = {}

try:
    from services.tithi_fetcher import fetch_tithis, TZ_TO_DIR
except Exception:
    fetch_tithis = None
    TZ_TO_DIR = {}

# ── Constraints (smart fields + validação estrutural) ────────────────
_has_constraints = False
try:
    from services.constraints import (
        # Derive
        derive_visit_ref,
        derive_visit_title,
        derive_metadata_from_city,
        derive_event_key,
        derive_vod_key,
        derive_segment_id,
        derive_vod_title,
        compute_event_status,
        compute_visit_status,
        compute_stats,
        # Suggest
        suggest_event_title,
        suggest_event_time,
        suggest_thumb_url,
        collect_known_locations,
        collect_all_vod_keys,
        collect_all_segment_ids,
        # Validate
        validate_date,
        validate_time,
        validate_date_range,
        validate_vod_unique,
        validate_event_key_unique,
        validate_day_key_unique,
        validate_segment,
        validate_harikatha_per_event,
        # Constants
        EVENT_TYPES as C_EVENT_TYPES,
        SEGMENT_TYPES as C_SEGMENT_TYPES,
        CITY_COUNTRY_MAP,
    )
    _has_constraints = True
except Exception:
    pass

try:
    from services.yt_discovery import (
        search_videos_for_day,
        build_event_from_video,
        CHANNEL_ID as YT_DEFAULT_CHANNEL,
    )
except Exception:
    search_videos_for_day = None
    build_event_from_video = None
    YT_DEFAULT_CHANNEL = None

try:
    from services.places_lookup import lookup_place
except Exception:
    lookup_place = None

# ══════════════════════════════════════════════════════════════════════
# GUARD
# ══════════════════════════════════════════════════════════════════════
if not st.session_state.get("authenticated"):
    st.warning("🔒 Faça login na página principal.")
    st.stop()


# ══════════════════════════════════════════════════════════════════════
# CLIENTES
# ══════════════════════════════════════════════════════════════════════
@st.cache_resource
def get_gh() -> GitHubClient:
    return GitHubClient(
        token  = st.secrets["github"]["token"],
        repo   = st.secrets["github"]["repo"],
        branch = st.secrets["github"].get("branch", "main"),
    )


# ══════════════════════════════════════════════════════════════════════
# HELPERS
# ══════════════════════════════════════════════════════════════════════
def _save(gh: GitHubClient, visit_ref: str, visit: dict, author: str, action: str) -> bool:
    """Guarded save: verifica conflitos remotos antes de sobrescrever.

    Retorna True se o save foi bem-sucedido, False se há conflito e requer resolução.
    """
    # tenta ler a versão remota para detectar concorrência
    try:
        remote = gh.get_visit(visit_ref)
    except Exception:
        remote = None

    conflicted, details = check_conflict(local_visit=visit, remote_visit=remote)
    if conflicted:
        st.session_state["_conflict"] = {
            "conflict": True,
            "remote_visit": remote,
            "local_visit": visit,
            "remote_rev": details.get("remote_rev"),
            "local_rev": details.get("local_rev"),
            "remote_by": details.get("remote_by"),
            "remote_source": details.get("remote_source"),
            "remote_at": details.get("remote_at"),
        }
        # renderiza o resolvedor imediatamente para que usuário tome ação
        render_conflict_resolver(gh, visit_ref, visit, author)
        return False

    # sem conflito: aplica carimbo de revisão e salva
    visit = stamp_revision(visit, source="streamlit", editor=author)
    ok = gh.save_visit(visit_ref, visit, author, action)
    if ok:
        st.cache_data.clear()
        st.toast("💾 Salvo no GitHub!", icon="✅")
    else:
        st.error("❌ Erro ao salvar no GitHub.")
    return ok


# ── Busca JSON completo do WP ────────────────────────────────────────
@st.cache_data(ttl=60)
def _load_from_wp(wp_id: int) -> dict:
    """Carrega o timeline completo direto do WP via _vana_visit_timeline_json."""
    from api.wp_client import get_visit_timeline
    return get_visit_timeline(wp_id)   # já existe e funciona ✅


# Versions handling: allow current supported schemas to pass unchanged
# and only migrate older, known compatible versions to 6.1.
SUPPORTED_SCHEMAS = {"6.1", "6.2"}
MIGRATABLE_OLDER = {"3.1", "4.0", "5.0", "6.0"}
MIGRATABLE_VERSIONS = SUPPORTED_SCHEMAS.union(MIGRATABLE_OLDER)

@st.cache_data(ttl=60)
def _load(visit_ref: str, wp_id: int | None = None) -> dict:
    gh = get_gh()
    data = gh.get_visit(visit_ref)

    if not data and wp_id:
        data = _load_from_wp(wp_id)

    # ── Migração de schema (SOMENTE modifica os dados; sem UI) -------
    if isinstance(data, dict):
        current = data.get("schema_version", "")
        # Only migrate known older schemas up to 6.1. Leave 6.1/6.2 untouched.
        if current in MIGRATABLE_OLDER:
            data["schema_version"] = "6.1"
            # signal that it was migrated — the UI that called _load() can show a toast
            data["__migrated_from__"] = current

        # If current is a supported modern schema (e.g., 6.1 or 6.2), leave as-is.
        # Do not downgrade 6.2 -> 6.1 automatically.

    return data or {}


def _now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def _day_label(day: dict) -> str:
    return day.get("day_key", "?") + " · " + day.get("label_pt", "")


def _event_label(ev: dict) -> str:
    return ev.get("time", "??:??") + " · " + ev.get("title_pt", ev.get("event_key", "?"))


def _normalize_vod_key(raw: str) -> str:
    """
    Normalize vod_key to format vod-YYYYMMDD-NNN.
    Accepts vod-YYYY-MM-DD-NNN and converts to vod-YYYYMMDD-NNN.
    Leaves already-correct keys untouched.
    """
    if not isinstance(raw, str):
        return raw
    s = raw.strip()
    return _re.sub(r'^vod-(\d{4})-(\d{2})-(\d{2})-(\d+)$', r'vod-\1\2\3-\4', s)


# ── Ingest helpers (used by the 📥 Ingestão tab) ─────────────────────
def _extract_video_id(url_or_id: str) -> tuple[str, str]:
    s = (url_or_id or "").strip()

    # YouTube: full URLs or raw 11-char ID
    yt = _re.search(r'(?:youtu\.be/|youtube\.com/(?:watch\?v=|shorts/|live/))([A-Za-z0-9_-]{11})', s)
    if yt:
        return "youtube", yt.group(1)
    if _re.match(r'^[A-Za-z0-9_-]{11}$', s):
        return "youtube", s

    # Facebook numeric video id
    fb = _re.search(r'facebook\.com/.+/(?:videos?/|watch/?)\??v=?(\d+)', s)
    if fb:
        return "facebook", fb.group(1)

    # Google Drive
    gd = _re.search(r'drive\.google\.com/file/d/([A-Za-z0-9_-]+)', s)
    if gd:
        return "drive", gd.group(1)

    return "unknown", s


def _suggest_vod_key(day_key: str, existing_vods: list[str]) -> str:
    date_part = (day_key or "0000-00-00").replace("-", "")
    n = len(existing_vods) + 1
    return f"vod-{date_part}-{n:03d}"


def _suggest_seg_id(day_key: str, existing_segs: list[str]) -> str:
    date_part = (day_key or "0000-00-00").replace("-", "")
    n = len(existing_segs) + 1
    return f"seg-{date_part}-{n:03d}"


def _count_all_vods(visit: dict) -> list[str]:
    keys: list[str] = []
    for day in visit.get("days", []):
        for ev in day.get("events", []):
            for vod in ev.get("vods", []):
                if vod.get("vod_key"):
                    keys.append(vod["vod_key"])
    for vod in visit.get("orphans", {}).get("vods", []):
        if vod.get("vod_key"):
            keys.append(vod["vod_key"])
    return keys


def _count_all_segments(visit: dict) -> list[str]:
    ids: list[str] = []
    for day in visit.get("days", []):
        for ev in day.get("events", []):
            for vod in ev.get("vods", []):
                for seg in vod.get("segments", []):
                    if seg.get("segment_id"):
                        ids.append(seg.get("segment_id"))
    return ids

# ── helper de resultado do Trator ────────────────────────────────────
def _render_trator_result(result: "TratorResult", dry: bool = False):
    """Renderiza o resultado do run_trator() no Streamlit."""

    if result.errors:
        st.error(f"❌ {len(result.errors)} erro(s) bloqueante(s):")
        for e in result.errors:
            st.code(f"[{e.code}] {e.path}\n  {e.message}", language="text")

    if result.warnings:
        with st.expander(f"⚠️ {len(result.warnings)} aviso(s)"):
            for w in result.warnings:
                st.markdown(f"- `[{w.code}]` **{w.path}** — {w.message}")

    if result.success:
        action_map = {
            "created":  ("✅", "CRIADO no WordPress"),
            "updated":  ("🔄", "ATUALIZADO no WordPress"),
            "noop":     ("💤", "Sem mudanças (noop)"),
            "dry_run":  ("🧪", "Dry run — schema válido"),
        }
        icon, label = action_map.get(result.wp_action or "", ("✅", "OK"))
        st.success(f"{icon} {label}")

        if result.wp_url:
            st.link_button("🌐 Ver no WordPress", result.wp_url)

        if result.processed:
            stats = result.processed.get("stats", {})
            if stats:
                st.markdown("**📊 Stats gerados:**")
                scols = st.columns(4)
                items = list(stats.items())
                for idx, (k, v) in enumerate(items):
                    scols[idx % 4].metric(k.replace("total_", ""), v)

            idx = result.processed.get("index", {})
            if idx:
                st.markdown("**🗂️ Index gerado:**")
                ic = st.columns(4)
                sections = list(idx.items())
                for idx_i, (sec, data) in enumerate(sections):
                    ic[idx_i % 4].metric(sec, len(data))


# ══════════════════════════════════════════════════════════════════════
# SIDEBAR
# ══════════════════════════════════════════════════════════════════════
st.sidebar.title("📅 Editor de Visitas")
st.sidebar.divider()

gh = get_gh()


# ── Busca visitas no WordPress ────────────────────────────────────────
@st.cache_data(ttl=120, show_spinner="Buscando visitas no WP...")
def _list_visits_wp() -> dict[str, list]:
    from api.wp_client import list_visits_wp as _wp_list
    try:
        visits = _wp_list()
    except Exception as e:
        return {"__erro__": [{"visit_ref": str(e), "title_pt": "",
                               "schema_ver": "?", "status": "?",
                               "date_start": "", "wp_status": ""}]}
    groups: dict[str, list] = {}
    for v in visits:
        tour = v.get("tour_ref") or "__sem_tour__"
        groups.setdefault(tour, []).append(v)
    return groups


with st.sidebar:

    col_r, col_n = st.columns(2)
    with col_r:
        if st.button("🔄 Recarregar", use_container_width=True):
            st.cache_data.clear()
            st.rerun()
    with col_n:
        if st.button("➕ Nova visita", use_container_width=True):
            st.session_state["creating_new"] = True

    st.divider()

    visits_by_tour = _list_visits_wp()

    if "__erro__" in visits_by_tour:
        st.error(f"❌ WP API: {visits_by_tour['__erro__'][0]['visit_ref']}")
        st.info("Verifique `api_base`, `wp_user` e `wp_app_password` no secrets.toml")
        st.stop()

    STATUS_ICON = {
        "completed": "✅", "active": "🔴",
        "live": "🔴", "upcoming": "🔜", "?": "⚪",
    }

    # Ordena: tours nomeados primeiro, __sem_tour__ por último
    tour_keys = sorted(
        [t for t in visits_by_tour if t != "__sem_tour__"]
    ) + (["__sem_tour__"] if "__sem_tour__" in visits_by_tour else [])

    real_refs:   list[str]  = []
    real_labels: list[str]  = []
    ref_to_meta: dict       = {}

    for tour_ref in tour_keys:
        visits = visits_by_tour[tour_ref]
        t_icon = "👻" if tour_ref == "__sem_tour__" else "🗺️"
        # Separador visual de grupo (label especial, não selecionável)
        real_refs.append(f"__header__{tour_ref}")
        real_labels.append(f"── {t_icon} {tour_ref}  ({len(visits)}) ──")

        for v in visits:
            vref   = v["visit_ref"]
            s_icon = STATUS_ICON.get(v["status"], "⚪")
            wp_pub = "🌐" if v["wp_status"] == "publish" else "📝"
            label  = f"{s_icon}{wp_pub} {vref}"
            if v["title_pt"]:
                label += f"  ·  {v['title_pt'][:24]}"
            real_refs.append(vref)
            real_labels.append(label)
            ref_to_meta[vref] = v

    # Filtra apenas visitas reais (sem headers)
    sel_refs   = [r for r in real_refs   if not r.startswith("__header__")]
    sel_labels = [real_labels[real_refs.index(r)] for r in sel_refs]

    if not sel_refs:
        st.info("Nenhuma visita encontrada no WordPress.")
        st.stop()

    prev     = st.session_state.get("selected_visit_ref", sel_refs[0])
    prev_idx = sel_refs.index(prev) if prev in sel_refs else 0

    sel_idx = st.selectbox(
        "Visita",
        options=range(len(sel_refs)),
        format_func=lambda i: sel_labels[i],
        index=prev_idx,
        key="visit_selector",
    )
    visit_ref = sel_refs[sel_idx]
    st.session_state["selected_visit_ref"] = visit_ref

    # ── Card info ─────────────────────────────────────────────────────
    meta_wp = ref_to_meta.get(visit_ref)
    if meta_wp:
        s_color = {
            "completed": "#4caf50", "active": "#f44336",
            "live": "#f44336",      "upcoming": "#ff9800",
        }.get(meta_wp["status"], "#9e9e9e")
        wp_badge = (
            "🌐 publicado" if meta_wp["wp_status"] == "publish"
            else f"📝 {meta_wp['wp_status']}"
        )
        st.markdown(
            f"""<div style="background:#1e1e2e;border-radius:8px;
                padding:10px 14px;margin:6px 0;font-size:0.82rem;
                border-left:3px solid {s_color}">
              <b>{meta_wp['title_pt'] or visit_ref}</b><br>
              <span style="color:{s_color}">● {meta_wp['status']}</span>
              &nbsp;·&nbsp;<code>schema {meta_wp['schema_ver']}</code><br>
              <span style="color:#aaa">{meta_wp['date_start'] or '—'}</span>
              &nbsp;·&nbsp;<span style="color:#888">{wp_badge}</span>
            </div>""",
            unsafe_allow_html=True,
        )
        if meta_wp.get("permalink"):
            st.link_button("🌐 Ver no WordPress", meta_wp["permalink"],
                           use_container_width=True)

    st.divider()

    # ── Criar nova visita ─────────────────────────────────────────────
    if st.session_state.get("creating_new"):
        st.markdown("**🆕 Nova visita**")
        new_ref  = st.text_input("visit_ref", placeholder="vrindavan-2026-03",
                                  key="new_visit_ref_input")
        new_tour = st.selectbox(
            "tour_ref",
            options=tour_keys + ["outro..."],
            key="new_visit_tour_sel",
        )
        if new_tour == "outro...":
            new_tour = st.text_input("tour_ref manual", placeholder="india-2026",
                                      key="new_visit_tour_manual")
        c1, c2 = st.columns(2)
        if c1.button("✅ Criar", key="confirm_new_visit", type="primary"):
            if new_ref.strip():
                st.session_state["selected_visit_ref"] = new_ref.strip()
                st.session_state["creating_new"]       = False
                st.session_state["prefill_tour"]       = new_tour
                st.cache_data.clear()
                st.rerun()
            else:
                st.warning("Digite o visit_ref.")
        if c2.button("✖ Cancelar", key="cancel_new_visit"):
            st.session_state["creating_new"] = False
            st.rerun()

    st.divider()

    # ── Nome do editor + tour para publicação ─────────────────────────
    editor_name = st.text_input("Seu nome", placeholder="Madhava Dasa",
                                 key="editor_name_input")
    tour_key = st.text_input(
        "Tour pai (publicação)",
        value=(
            st.session_state.get("prefill_tour")
            or ref_to_meta.get(visit_ref, {}).get("tour_ref", "")
            or st.secrets.get("vana", {}).get("tour_key", "tour:india-2026")
        ),
        key="tour_key_input",
    )

if not visit_ref:
    st.info("Selecione ou digite um visit_ref na sidebar.")
    st.stop()

# ══════════════════════════════════════════════════════════════════════
# CARREGA VISITA
# ══════════════════════════════════════════════════════════════════════
wp_id = ref_to_meta.get(visit_ref, {}).get("wp_id")
visit = _load(visit_ref, wp_id=wp_id)

# Desacopla do cache (evita mutação do objeto cacheado)
visit = copy.deepcopy(visit)

# Feedback de migração de schema (FORA do cache)
migrated_from = None
if isinstance(visit, dict):
    migrated_from = visit.pop("__migrated_from__", None)
if migrated_from:
    st.toast(f"⬆️ Schema migrado: {migrated_from} → 6.1", icon="ℹ️")
    st.info("O schema foi migrado em memória. Se a sidebar mostrar dados antigos, limpe o cache.")
    if st.button("🔄 Limpar cache e recarregar agora"):
        st.cache_data.clear()
        st.rerun()

# Instantiate Autofill helper if available
af = None
if Autofill is not None:
    wp_fetcher = None
    try:
        from api.wp_client import list_kathas_for_visit
        wp_fetcher = lambda vref: list_kathas_for_visit(vref)
    except Exception:
        wp_fetcher = None

    yt_key = st.secrets.get("youtube", {}).get("api_key") if isinstance(st.secrets.get("youtube", {}), dict) else None
    try:
        af = Autofill(visit, yt_api_key=yt_key, wp_katha_fetcher=wp_fetcher)
    except Exception:
        af = None

schema_atual = visit.get("schema_version", "") if isinstance(visit, dict) else ""
if schema_atual and schema_atual not in SUPPORTED_SCHEMAS and schema_atual not in MIGRATABLE_OLDER:
    st.warning(
        f"⚠️ schema_version desconhecido: `{schema_atual}`. "
        f"Verifique antes de publicar."
    )

# Novo visit vazio
if not visit:
    st.warning(f"visit.json não encontrado para `{visit_ref}`. Criando novo...")
    visit = {
        "$schema":        "https://vanamadhuryam.com/schemas/timeline-6.1.json",
        "schema_version": "6.1",
        "visit_ref":      visit_ref,
        "tour_ref":       tour_key.replace("tour:", ""),
        "title_pt":       "",
        "title_en":       "",
        "metadata":       {
            "city_pt": "", "city_en": "", "country": "IN",
            "date_start": "", "date_end": "",
            "timezone": "Asia/Kolkata", "status": "upcoming",
        },
        "days":    [],
        "orphans": {"vods": [], "photos": [], "sangha": []},
        "stats":   {},
        "index":   {},
    }

# ── Título da página ──────────────────────────────────────────────────
st.title(f"📅 {visit.get('title_pt') or visit_ref}")
st.caption(
    f"`{visit_ref}` · Schema {visit.get('schema_version', '6.1')} · "
    f"{len(visit.get('days', []))} dia(s)"
)

# ══════════════════════════════════════════════════════════════════════
# TABS
# ══════════════════════════════════════════════════════════════════════
tab_meta, tab_ingest, tab_vods, tab_kathas, tab_galeria, tab_orfaos, tab_publicar = st.tabs([
    "📋 Visita",
    "📥 Ingestão",
    "🎬 VODs",
    "🙏 Kathas",
    "🖼️ Galeria",
    "👻 Órfãos",
    "🚀 Publicar",
])


# ══════════════════════════════════════════════════════════════════════
# TAB INGESTÃO — recebe mídia bruta e estrutura
# ══════════════════════════════════════════════════════════════════════
with tab_ingest:
    st.markdown("### 📥 Ingestão de Mídia")
    st.caption(
        "Cole uma URL ou ID de vídeo. O editor sugere a associação "
        "ao dia/evento e cria o VOD com segments."
    )

    days = visit.get("days", [])
    if not days:
        st.warning("⚠️ Adicione ao menos um dia na aba 📋 Visita antes de ingerir mídia.")
        st.stop()

    # ── Passo 1 — URL / ID ────────────────────────────────────────────
    st.markdown("#### 1️⃣ Mídia")

    raw_input = st.text_area(
        "Cole URLs ou IDs (uma por linha)",
        height=100,
        placeholder="dQw4w9WgXcQ\nhttps://youtu.be/ABC123\nhttps://www.facebook.com/watch/?v=777",
        key="ingest_raw",
    )

    parsed_medias: list[dict] = []
    if raw_input.strip():
        for line in raw_input.strip().splitlines():
            line = line.strip()
            if not line:
                continue
            provider, video_id = _extract_video_id(line)
            thumb = (
                f"https://img.youtube.com/vi/{video_id}/mqdefault.jpg"
                if provider == "youtube" else None
            )
            parsed_medias.append({
                "raw":      line,
                "provider": provider,
                "video_id": video_id,
                "thumb":    thumb,
            })

        # Preview das mídias detectadas
        if parsed_medias:
            st.success(f"✅ {len(parsed_medias)} mídia(s) detectada(s)")
            prev_cols = st.columns(min(len(parsed_medias), 4))
            for i, m in enumerate(parsed_medias):
                with prev_cols[i % 4]:
                    if m["thumb"]:
                        st.image(m["thumb"], use_container_width=True)
                    st.caption(f"`{m['provider']}` · `{m['video_id']}`")

    # ── Passo 2 — Associação Dia / Evento ─────────────────────────────
    if parsed_medias:
        st.divider()
        st.markdown("#### 2️⃣ Associação")

        # Destino: dia + evento OU órfão
        dest_mode = st.radio(
            "Destino",
            ["Associar a um evento", "Adicionar como órfão"],
            horizontal=True,
            key="ingest_dest_mode",
        )

        sel_day_key   = None
        sel_event_key = None
        sel_day       = None
        sel_event     = None

        if dest_mode == "Associar a um evento":
            day_options = {_day_label(d): d for d in days}
            sel_day_label = st.selectbox(
                "Dia", list(day_options.keys()), key="ingest_day"
            )
            sel_day     = day_options[sel_day_label]
            sel_day_key = sel_day.get("day_key", "")

            events      = sel_day.get("events", [])
            if not events:
                st.warning("Este dia não tem eventos. Adicione um evento na aba 📋 Visita.")
            else:
                ev_opts = {_event_label(e): e for e in events}

                # Opção de criar novo evento inline
                ev_opts["➕ Criar novo evento..."] = None

                sel_ev_label = st.selectbox(
                    "Evento", list(ev_opts.keys()), key="ingest_event"
                )

                if ev_opts[sel_ev_label] is None:
                    # Criação inline de evento
                    with st.container(border=True):
                        st.markdown("**Novo evento:**")
                        ni1, ni2, ni3 = st.columns(3)
                        new_ek  = ni1.text_input(
                            "event_key (YYYYMMDD-HHMM-slug)",
                            # suggest a key with '-null-' time part to be compatible with v6.2
                            value=f"{sel_day_key.replace('-','')}-null-new-event",
                            key="ingest_new_ek",
                        )
                        new_et  = ni2.selectbox(
                            "type",
                            ["programa", "mangala", "arati", "darshan", "other"],
                            key="ingest_new_etype",
                        )
                        new_etm = ni3.text_input("time (HH:MM)", key="ingest_new_etime")
                        new_etp = ni1.text_input("title_pt", key="ingest_new_etp")
                        new_ete = ni2.text_input("title_en", key="ingest_new_ete")
                        new_loc = ni3.text_input("local (nome)", key="ingest_new_eloc")

                        if st.button("✅ Criar evento e continuar", key="ingest_create_ev"):
                            if new_ek:
                                new_event = {
                                    "event_key": new_ek,
                                    "type":      new_et,
                                    "title_pt":  new_etp,
                                    "title_en":  new_ete,
                                    "time":      new_etm,
                                    "status":    "past",
                                    "location":  {"name": new_loc} if new_loc else {},
                                    "vods":      [],
                                    "photos":    [],
                                    "sangha":    [],
                                }
                                sel_day.setdefault("events", []).append(new_event)
                                _save(gh, visit_ref, visit, editor_name or "anon",
                                      f"event {new_ek}: criado via ingestão")
                                st.rerun()
                else:
                    sel_event     = ev_opts[sel_ev_label]
                    sel_event_key = sel_event.get("event_key", "")

        # ── Passo 3 — Configuração dos VODs ───────────────────────────
        st.divider()
        st.markdown("#### 3️⃣ Configuração dos VODs")

        all_vod_keys  = _count_all_vods(visit)
        all_seg_ids   = _count_all_segments(visit)
        vod_configs   = []

        for mi, media in enumerate(parsed_medias):
            with st.container(border=True):
                mc1, mc2 = st.columns([1, 3])

                with mc1:
                    if media["thumb"]:
                        st.image(media["thumb"], use_container_width=True)
                    st.caption(f"`{media['provider']}`")

                with mc2:
                    suggested_key = _suggest_vod_key(
                        sel_day_key or "00000000", all_vod_keys
                    )
                    vod_key = st.text_input(
                        "vod_key",
                        value=suggested_key,
                        key=f"ing_vk_{mi}",
                    )
                    vod_part = st.number_input(
                        "Parte", min_value=1, value=mi + 1, key=f"ing_vp_{mi}"
                    )
                    vod_title_pt = st.text_input(
                        "Título PT",
                        value=(
                            f"{sel_event.get('title_pt', '')} — Pt.{mi+1}"
                            if sel_event else f"Vídeo {mi+1}"
                        ),
                        key=f"ing_vtp_{mi}",
                    )
                    vod_title_en = st.text_input(
                        "Título EN", key=f"ing_vte_{mi}"
                    )
                    duration = st.number_input(
                        "Duração (s)", min_value=0, value=0, key=f"ing_dur_{mi}"
                    )
                    all_vod_keys.append(vod_key)   # reserva para próximos

                vod_configs.append({
                    "vod_key":    vod_key,
                    "provider":   media["provider"],
                    "video_id":   media["video_id"],
                    "title_pt":   vod_title_pt,
                    "title_en":   vod_title_en,
                    "vod_part":   vod_part,
                    "duration_s": duration or None,
                    "thumb_url": (
                        f"https://img.youtube.com/vi/{media['video_id']}/maxresdefault.jpg"
                        if media["provider"] == "youtube" else None
                    ),
                })

        # ── Passo 4 — Segments ────────────────────────────────────────
        st.divider()
        st.markdown("#### 4️⃣ Segments")
        st.caption(
            "Defina os segmentos de cada vídeo. "
            "Para o Hari-Kathā, informe o katha_id (WP post ID)."
        )

        SEGMENT_TYPES = [
            "kirtan", "harikatha", "pushpanjali", "arati",
            "dance", "drama", "darshan", "interval", "noise", "announcement",
        ]

        seg_configs: dict[str, list] = {}   # vod_key → list of segs

        for vc in vod_configs:
            vk = vc["vod_key"]
            seg_configs[vk] = []

            with st.expander(f"🎬 `{vk}` — segments", expanded=True):

                # Adicionar segment
                sa1, sa2, sa3, sa4 = st.columns([2, 2, 1, 1])
                seg_id   = sa1.text_input(
                    "segment_id",
                    value=_suggest_seg_id(sel_day_key or "00000000", all_seg_ids),
                    key=f"ing_sid_{vk}",
                )
                seg_type = sa2.selectbox(
                    "type", SEGMENT_TYPES, key=f"ing_stype_{vk}"
                )
                seg_ts   = sa3.number_input(
                    "start (s)", min_value=0, value=0, key=f"ing_sst_{vk}"
                )
                seg_te   = sa4.number_input(
                    "end (s)", min_value=0, value=vc.get("duration_s") or 0,
                    key=f"ing_set_{vk}",
                )

                seg_tp   = st.text_input("title_pt", key=f"ing_stp_{vk}")
                seg_te_t = st.text_input("title_en", key=f"ing_ste_{vk}")

                katha_id = None
                if seg_type == "harikatha":
                    katha_id = st.number_input(
                        "katha_id (WP post ID)",
                        min_value=0,
                        value=0,
                        key=f"ing_skid_{vk}",
                    ) or None

                if st.button(f"➕ Adicionar segment a `{vk}`", key=f"ing_addseg_{vk}"):
                    if seg_id:
                        seg_configs[vk].append({
                            "segment_id":      seg_id,
                            "type":            seg_type,
                            "title_pt":        seg_tp,
                            "title_en":        seg_te_t,
                            "timestamp_start": int(seg_ts),
                            "timestamp_end":   int(seg_te),
                            "katha_id":        katha_id,
                        })
                        all_seg_ids.append(seg_id)
                        st.success(f"✅ Segment `{seg_id}` adicionado.")

                # Preview segments configurados
                if seg_configs[vk]:
                    st.dataframe(
                        [
                            {
                                "segment_id": s["segment_id"],
                                "type":       s["type"],
                                "start":      s["timestamp_start"],
                                "end":        s["timestamp_end"],
                                "katha_id":   s.get("katha_id") or "—",
                            }
                            for s in seg_configs[vk]
                        ],
                        use_container_width=True,
                    )

        # ── Passo 5 — Confirmar e salvar ──────────────────────────────
        st.divider()
        st.markdown("#### 5️⃣ Confirmar")

        # Resumo
        total_segs = sum(len(v) for v in seg_configs.values())
        hk_count   = sum(
            1 for segs in seg_configs.values()
            for s in segs if s["type"] == "harikatha"
        )

        c1, c2, c3 = st.columns(3)
        c1.metric("VODs",     len(vod_configs))
        c2.metric("Segments", total_segs)
        c3.metric("Hari-Kathā", hk_count)

        if dest_mode == "Associar a um evento" and sel_event:
            st.info(
                f"📌 Destino: **{sel_event.get('title_pt', sel_event_key)}** "
                f"· `{sel_event_key}`"
            )
        else:
            st.info("📌 Destino: **Órfãos** (sem evento)")

        btn_confirm = st.button(
            "💾 Confirmar Ingestão",
            type="primary",
            disabled=not editor_name or not vod_configs,
            key="ingest_confirm",
        )

        if not editor_name:
            st.caption("⚠️ Digite seu nome na sidebar.")

        if btn_confirm:
            # Monta VODs com segments e insere no destino
            new_vods = []
            for vc in vod_configs:
                vk = vc["vod_key"]
                new_vods.append({
                    **vc,
                    "url":      None,
                    "segments": seg_configs.get(vk, []),
                })

            if dest_mode == "Associar a um evento" and sel_event:
                sel_event.setdefault("vods", []).extend(new_vods)
                action_msg = (
                    f"ingestão: {len(new_vods)} vod(s) → evento {sel_event_key}"
                )
            else:
                orphan_type = st.session_state.get("ingest_orphan_type", "documental")
                for v in new_vods:
                    v["orphan_type"] = orphan_type
                    v["day_key"]     = sel_day_key
                visit.setdefault("orphans", {}).setdefault("vods", []).extend(new_vods)
                action_msg = f"ingestão: {len(new_vods)} vod(s) → órfãos"

            ok = _save(gh, visit_ref, visit, editor_name, action_msg)
            if ok:
                st.success(
                    f"✅ {len(new_vods)} VOD(s) e {total_segs} segment(s) salvos!"
                )
                if hk_count:
                    st.info(
                        f"🙏 {hk_count} Hari-Kathā(s) vinculadas. "
                        f"Verifique a aba 🙏 Kathas para confirmar."
                    )
                st.cache_data.clear()
                st.rerun()

# TAB 1 — METADATA + DAYS
# ══════════════════════════════════════════════════════════════════════
with tab_meta:
    st.markdown("### Identificação")

    c1, c2 = st.columns(2)
    with c1:
        visit["title_pt"] = st.text_input(
            "Título PT", value=visit.get("title_pt", ""), key="v_title_pt"
        )
    # ── Migração de schema (botão explícito) ─────────────────────
    if visit.get("schema_version") == "6.1":
        with st.expander("🔁 Migrar 6.1 → 6.2 (opcional)", expanded=False):
            st.write(
                "A migração move kathas para o índice e remove `kathas[]` dos eventos. "
                "Use apenas quando quiser atualizar o payload para 6.2 e salvar no repositório."
            )

            def _migrate_6_1_to_6_2(v: dict) -> dict:
                """Migra visit.json de schema 6.1 para 6.2.

                Mudanças chave do 6.2:
                  - kathas[] removido dos eventos (R-HK-4)
                  - katha_id propagado para segments harikatha
                  - orphan vods recebem orphan_type default
                """
                v = copy.deepcopy(v)
                v["schema_version"] = "6.2"
                v["__migrated_from__"] = "6.1"
                v["$schema"] = "https://vanamadhuryam.com/schemas/timeline-6.2.json"

                for day in v.get("days", []):
                    for ev in day.get("events", []):
                        old_kathas = ev.get("kathas", [])

                        # ── Propagar katha_id dos kathas[] legado para segments ───
                        if old_kathas:
                            # Mapa: vod_key → katha_id (extraído dos sources de cada katha)
                            vod_katha_map: dict[str, int] = {}
                            for katha in old_kathas:
                                kid = katha.get("katha_id") or katha.get("id")
                                if not kid:
                                    continue
                                for src in katha.get("sources", []):
                                    vk = src.get("vod_key")
                                    if vk:
                                        try:
                                            vod_katha_map[str(vk)] = int(kid)
                                        except Exception:
                                            pass

                                # Fallback: se kathas[] não tem sources, usar o primeiro
                                # katha_id para todos os segments harikatha do evento
                                if not katha.get("sources") and "__fallback__" not in vod_katha_map:
                                    try:
                                        vod_katha_map["__fallback__"] = int(kid)
                                    except Exception:
                                        pass

                            # Aplicar nos segments
                            fallback_kid = vod_katha_map.get("__fallback__")
                            for vod in ev.get("vods", []):
                                vk = vod.get("vod_key", "")
                                mapped_kid = vod_katha_map.get(vk, fallback_kid)
                                for seg in vod.get("segments", []):
                                    if seg.get("type") == "harikatha" and not seg.get("katha_id"):
                                        if mapped_kid:
                                            seg["katha_id"] = mapped_kid

                        # Remover kathas[] do evento (R-HK-4)
                        ev.pop("kathas", None)

                # ── Orphans ─────────────────────────────────────────────────
                orphans = v.get("orphans", {})
                if isinstance(orphans, dict):
                    orphans.pop("kathas", None)
                    # Garantir orphan_type em VODs órfãos
                    for vod in orphans.get("vods", []):
                        if "orphan_type" not in vod:
                            vod["orphan_type"] = "documental"

                return v

            if st.button("🔧 Migrar e salvar como 6.2", key="btn_migrate_6_2"):
                migrated = _migrate_6_1_to_6_2(visit)
                ok = _save(gh, visit_ref, migrated, editor_name or "anon", "migrate: 6.1 -> 6.2")
                if ok:
                    st.success("✅ Migrado para 6.2 e salvo no GitHub.")
                    st.cache_data.clear()
                    st.rerun()
                else:
                    st.error("❌ Falha ao salvar a migração.")
        visit["visit_ref"] = st.text_input(
            "visit_ref", value=visit.get("visit_ref", visit_ref), key="v_ref"
        )
    with c2:
        visit["title_en"] = st.text_input(
            "Título EN", value=visit.get("title_en", ""), key="v_title_en"
        )
        visit["tour_ref"] = st.text_input(
            "tour_ref", value=visit.get("tour_ref", ""), key="v_tour_ref"
        )

    st.divider()
    st.markdown("### Metadata")
    meta = visit.setdefault("metadata", {})

    m1, m2, m3 = st.columns(3)
    with m1:
        meta["city_pt"] = st.text_input("Cidade PT", value=meta.get("city_pt", ""), key="m_city_pt")

        # ── Auto-derive de cidade ────────────────────────────────────
        if _has_constraints and meta["city_pt"]:
            _city_info = derive_metadata_from_city(meta["city_pt"])
            if _city_info:
                st.caption(
                    f"💡 {_city_info.get('city_en', '')} · "
                    f"{_city_info.get('country', '')} · "
                    f"{_city_info.get('timezone', '')}"
                )
                if st.button("Aplicar", key="btn_apply_city"):
                    meta["city_en"]  = _city_info.get("city_en", meta.get("city_en", ""))
                    meta["city_pt"]  = _city_info.get("city_pt", meta["city_pt"])
                    meta["country"]  = _city_info.get("country", meta.get("country", ""))
                    meta["timezone"] = _city_info.get("timezone", meta.get("timezone", ""))
                    st.rerun()

        meta["date_start"] = st.text_input("Data início (YYYY-MM-DD)", value=meta.get("date_start", ""), key="m_ds")
        if _has_constraints and meta["date_start"]:
            _err = validate_date(meta["date_start"], "Data início")
            if _err:
                st.error(f"❌ {_err}")

        meta["timezone"] = st.text_input("Timezone", value=meta.get("timezone", "Asia/Kolkata"), key="m_tz")
    with m2:
        meta["city_en"] = st.text_input("Cidade EN", value=meta.get("city_en", ""), key="m_city_en")
        meta["date_end"] = st.text_input("Data fim (YYYY-MM-DD)", value=meta.get("date_end", ""), key="m_de")
        if _has_constraints and meta["date_end"]:
            _err = validate_date(meta["date_end"], "Data fim")
            if _err:
                st.error(f"❌ {_err}")
        if _has_constraints and meta.get("date_start") and meta.get("date_end"):
            _err = validate_date_range(meta["date_start"], meta["date_end"])
            if _err:
                st.warning(f"⚠️ {_err}")

        meta["country"] = st.text_input("País (ISO)", value=meta.get("country", "IN"), key="m_country")
    with m3:
        # ── Status calculado ─────────────────────────────────────────
        if _has_constraints and meta.get("date_start") and meta.get("date_end"):
            _auto_status = compute_visit_status(
                meta["date_start"], meta["date_end"],
                meta.get("timezone", "Asia/Kolkata"),
            )
            st.markdown(f"**Status:** `{_auto_status}` (calculado)")
            meta["status"] = _auto_status
        else:
            meta["status"] = st.selectbox(
                "Status", ["upcoming", "active", "completed"],
                index=["upcoming", "active", "completed"].index(meta.get("status", "upcoming")),
                key="m_status",
            )

        # ── Sugestão de título ────────────────────────────────────────
        if _has_constraints and meta.get("city_pt") and meta.get("date_start"):
            _sug_title = derive_visit_title(meta["city_pt"], meta["date_start"], "pt")
            if _sug_title and not visit.get("title_pt"):
                st.caption(f"💡 Título: *{_sug_title}*")

    if st.button("💾 Salvar Metadata", key="save_meta"):
        visit["metadata"] = meta
        if _has_constraints:
            visit["stats"] = compute_stats(visit)
        _save(gh, visit_ref, visit, editor_name or "anon", "metadata: atualizada")

    # ── DIAS ─────────────────────────────────────────────────────────
    st.divider()
    st.markdown("### Dias")

    days = visit.setdefault("days", [])
    meta = visit.get("metadata", {})

    # ── Gerador de dias em massa ─────────────────────────────────────
    with st.expander("🗓️ Gerar dias automaticamente", expanded=len(days) == 0):

        st.caption(
            "Gera todos os dias entre data início e data fim do metadata. "
            "Preenche labels, dia da semana e tithis (Ekādaśīs, festivais) automaticamente."
        )

        # Pré-popula com metadata da visita
        gen_c1, gen_c2 = st.columns(2)
        with gen_c1:
            gen_start = st.text_input(
                "Data início (YYYY-MM-DD)",
                value=meta.get("date_start", ""),
                key="gen_day_start",
                placeholder="2026-02-18",
            )
        with gen_c2:
            gen_end = st.text_input(
                "Data fim (YYYY-MM-DD)",
                value=meta.get("date_end", ""),
                key="gen_day_end",
                placeholder="2026-02-27",
            )

        # Opções
        gen_opt_c1, gen_opt_c2 = st.columns(2)
        with gen_opt_c1:
            gen_fetch_tithi = st.checkbox(
                "🪷 Buscar tithis (Ekādaśī, festivais)",
                value=True,
                key="gen_fetch_tithi",
            )
        with gen_opt_c2:
            gen_tz = st.selectbox(
                "Timezone do calendário",
                options=list(TZ_TO_DIR.keys()) if 'TZ_TO_DIR' in dir() else [
                    "Asia/Kolkata", "America/Sao_Paulo",
                    "America/New_York", "Europe/London",
                ],
                index=0,
                key="gen_tz",
            )

        # Preview antes de criar
        gen_preview = st.button(
            "👁️ Visualizar dias",
            key="gen_preview_btn",
            disabled=not gen_start or not gen_end,
        )

        if gen_preview and gen_start and gen_end:
            try:
                # Buscar tithis se habilitado
                tithis = {}
                if gen_fetch_tithi and fetch_tithis:
                    try:
                        tithis = fetch_tithis(gen_start, gen_end, gen_tz)
                        if tithis:
                            st.success(f"🪷 {len(tithis)} dia(s) com tithi/festival encontrados")
                        else:
                            st.info("ℹ️ Nenhum tithi/festival encontrado (API pode estar offline)")
                    except Exception as e:
                        st.warning(f"⚠️ Erro ao buscar tithis: {e}")

                # Gerar dias
                if not generate_days:
                    raise RuntimeError("Gerador de dias não disponível")
                preview_days = generate_days(gen_start, gen_end, tithis)

                # Mostrar preview
                st.markdown(f"**{len(preview_days)} dia(s) serão gerados:**")

                # Tabela de preview com checkboxes
                existing_keys = {d.get("day_key") for d in days}

                preview_data = []
                for pd in preview_days:
                    dk = pd["day_key"]
                    exists = dk in existing_keys
                    tithi_display = ""

                    # Montar display do tithi
                    if pd.get("tithi"):
                        t_info = tithis.get(dk, {})
                        tithi_display = t_info.get("display", pd.get("tithi_name_pt", pd["tithi"]))

                    preview_data.append({
                        "": "✅" if not exists else "🔵",
                        "day_key": dk,
                        "label_pt": pd["label_pt"],
                        "label_en": pd["label_en"],
                        "tithi": tithi_display or "—",
                        "status": "já existe" if exists else "novo",
                    })

                st.dataframe(
                    preview_data,
                    use_container_width=True,
                    hide_index=True,
                    column_config={
                        "": st.column_config.TextColumn("", width="small"),
                        "day_key": st.column_config.TextColumn("Data", width="medium"),
                        "label_pt": st.column_config.TextColumn("Label PT", width="medium"),
                        "label_en": st.column_config.TextColumn("Label EN", width="medium"),
                        "tithi": st.column_config.TextColumn("Tithi / Festival", width="large"),
                        "status": st.column_config.TextColumn("Status", width="small"),
                    },
                )

                new_count = sum(1 for p in preview_data if p["status"] == "novo")
                existing_count = sum(1 for p in preview_data if p["status"] == "já existe")

                if existing_count:
                    st.info(
                        f"🔵 {existing_count} dia(s) já existem e serão **preservados** "
                        f"(eventos intactos, tithis preenchidos se vazios)"
                    )

                # Salvar no session_state para o botão de confirmar
                st.session_state["_gen_preview_days"] = preview_days
                st.session_state["_gen_tithis"] = tithis

            except ValueError as e:
                st.error(f"❌ {e}")
            except Exception as e:
                st.error(f"❌ Erro inesperado: {e}")

        # Botão de confirmar (só aparece se há preview)
        if st.session_state.get("_gen_preview_days"):
            preview_days = st.session_state["_gen_preview_days"]
            new_count = sum(
                1 for pd in preview_days
                if pd["day_key"] not in {d.get("day_key") for d in days}
            )

            st.divider()

            confirm_c1, confirm_c2 = st.columns([3, 1])
            with confirm_c1:
                btn_label = (
                    f"💾 Criar {new_count} dia(s) novo(s)"
                    if new_count
                    else "💾 Atualizar tithis dos dias existentes"
                )
                gen_confirm = st.button(
                    btn_label,
                    key="gen_confirm_btn",
                    type="primary",
                    disabled=not editor_name,
                )
            with confirm_c2:
                gen_cancel = st.button("✖ Limpar", key="gen_cancel_btn")

            if not editor_name:
                st.caption("⚠️ Digite seu nome na sidebar para salvar.")

            if gen_cancel:
                st.session_state.pop("_gen_preview_days", None)
                st.session_state.pop("_gen_tithis", None)
                st.rerun()

            if gen_confirm:
                merged = merge_days(days, preview_days) if merge_days else preview_days
                visit["days"] = merged

                # Sincronizar metadata se datas estavam vazias
                if not meta.get("date_start") and preview_days:
                    meta["date_start"] = preview_days[0]["day_key"]
                if not meta.get("date_end") and preview_days:
                    meta["date_end"] = preview_days[-1]["day_key"]
                visit["metadata"] = meta

                action_msg = f"dias: gerados {len(preview_days)}, novos {new_count}"
                ok = _save(gh, visit_ref, visit, editor_name, action_msg)
                if ok:
                    st.session_state.pop("_gen_preview_days", None)
                    st.session_state.pop("_gen_tithis", None)
                    st.cache_data.clear()
                    st.rerun()

    # ── Adicionar dia único (fallback manual) ────────────────────────
    with st.expander("➕ Adicionar dia manualmente", expanded=False):

        st.caption("Para adicionar um dia fora do range, ou com dados personalizados.")

        if af:
            try:
                sug_day = af.suggest_next_day()
            except Exception:
                sug_day = {"day_key": "", "label_pt": "", "label_en": ""}
        else:
            sug_day = {"day_key": "", "label_pt": "", "label_en": ""}

        ndk = st.text_input(
            "day_key (YYYY-MM-DD)",
            value=sug_day.get("day_key", ""),
            key="new_day_key",
        )

        # Validação: dia já existe?
        existing_day_keys = {d.get("day_key") for d in days}
        if ndk and ndk in existing_day_keys:
            st.warning(f"⚠️ Dia `{ndk}` já existe nesta visita.")

        # Auto-derivar labels quando day_key muda
        if af:
            derived = af.derive_day_labels(ndk)
        elif ndk:
            try:
                d = __import__('datetime').date.fromisoformat(ndk)
                derived = {
                    "label_pt": f"{d.day} {MONTHS_PT[d.month]} · {WEEKDAYS_PT[d.weekday()]}",
                    "label_en": f"{WEEKDAYS_EN[d.weekday()]}, {MONTHS_EN[d.month]} {d.day}",
                }
            except Exception:
                derived = {"label_pt": "", "label_en": ""}
        else:
            derived = {"label_pt": "", "label_en": ""}

        man_c1, man_c2 = st.columns(2)
        with man_c1:
            nlp = st.text_input(
                "label_pt",
                value=derived.get("label_pt", ""),
                key="new_day_lp",
            )
        with man_c2:
            nle = st.text_input(
                "label_en",
                value=derived.get("label_en", ""),
                key="new_day_le",
            )

        # Buscar tithi para este dia específico
        man_tithi_data: dict = {}
        if ndk and len(ndk) == 10 and fetch_tithis:
            try:
                single_tithi = fetch_tithis(ndk, ndk, meta.get("timezone", "Asia/Kolkata"))
                if ndk in single_tithi:
                    man_tithi_data = single_tithi[ndk]
                    st.info(f"🪷 {man_tithi_data.get('display', man_tithi_data.get('name_pt', ''))}")
            except Exception:
                pass

        man_t1, man_t2 = st.columns(2)
        with man_t1:
            n_tithi = st.text_input(
                "tithi",
                value=man_tithi_data.get("tithi", ""),
                key="new_day_tithi",
            )
        with man_t2:
            n_tithi_pt = st.text_input(
                "tithi_name_pt",
                value=man_tithi_data.get("name_pt", ""),
                key="new_day_tithi_pt",
            )

        if st.button("Adicionar dia", key="btn_add_day"):
            if not ndk:
                st.warning("day_key é obrigatório.")
            elif ndk in existing_day_keys:
                st.error(f"❌ Dia `{ndk}` já existe. Use o gerador para atualizar tithis.")
            else:
                days.append({
                    "day_key": ndk,
                    "label_pt": nlp,
                    "label_en": nle,
                    "tithi": n_tithi,
                    "tithi_name_pt": n_tithi_pt,
                    "tithi_name_en": man_tithi_data.get("name_en", ""),
                    "primary_event_key": "",
                    "events": [],
                })
                # Manter ordenação cronológica
                days.sort(key=lambda d: d.get("day_key", ""))
                visit["days"] = days
                _save(gh, visit_ref, visit, editor_name or "anon", f"day {ndk}: adicionado")
                st.rerun()

    # ── Listagem de dias existentes ──────────────────────────────────
    if days:
        st.markdown(f"**{len(days)} dia(s) cadastrados:**")

    for di, day in enumerate(days):
        dk = day.get("day_key", f"dia-{di}")
        tithi_badge = ""
        if day.get("tithi"):
            tithi_badge = f" 🪷 {day.get('tithi_name_pt') or day.get('tithi', '')}"

        ev_count = len(day.get("events", []))
        ev_badge = f" · {ev_count} evento(s)" if ev_count else ""

        with st.expander(
            f"📅 {_day_label(day)}{tithi_badge}{ev_badge}",
            expanded=False,
        ):
            # ── Campos do dia ────────────────────────────────────────
            dc1, dc2, dc3 = st.columns(3)
            with dc1:
                day["day_key"] = st.text_input(
                    "day_key", value=day.get("day_key", ""), key=f"dk_{di}", disabled=True
                )
                day["label_pt"] = st.text_input(
                    "label_pt", value=day.get("label_pt", ""), key=f"dlp_{di}",
                )
            with dc2:
                day["label_en"] = st.text_input(
                    "label_en", value=day.get("label_en", ""), key=f"dle_{di}",
                )
                # primary_event_key como selectbox dos eventos existentes
                event_keys = [e.get("event_key", "") for e in day.get("events", [])]
                pek_options = [""] + event_keys
                pv = day.get("primary_event_key", day.get("primary_event", ""))
                pv_idx = pek_options.index(pv) if pv in pek_options else 0
                pv_new = st.selectbox(
                    "primary_event_key",
                    options=pek_options,
                    index=pv_idx,
                    key=f"dpe_{di}",
                    format_func=lambda x: x if x else "(nenhum)",
                )
                day["primary_event_key"] = pv_new
                day["primary_event"] = pv_new  # compat
            with dc3:
                day["tithi"] = st.text_input(
                    "tithi", value=day.get("tithi", ""), key=f"dt_{di}",
                )
                day["tithi_name_pt"] = st.text_input(
                    "tithi_name_pt",
                    value=day.get("tithi_name_pt", ""),
                    key=f"dtnp_{di}",
                )

            # ── Eventos do dia ───────────────────────────────────────
            st.markdown("**Eventos:**")
            events = day.setdefault("events", [])

            # ── YouTube discovery (buscar vídeos do dia) ────────────
            try:
                _has_yt = bool(search_videos_for_day)
            except Exception:
                _has_yt = False

            if _has_yt:
                yt_key = st.secrets.get("youtube", {}).get("api_key", "")
                yt_ch = st.secrets.get("youtube", {}).get("channel_id", YT_DEFAULT_CHANNEL)
                if st.button("🔍 Buscar vídeos no YouTube", key=f"yt_search_{di}"):
                    if not yt_key:
                        st.warning("⚠️ Configure `youtube.api_key` em .streamlit/secrets.toml")
                    else:
                        try:
                            _res = search_videos_for_day(
                                yt_key, dk, channel_id=yt_ch or YT_DEFAULT_CHANNEL,
                                timezone=meta.get("timezone", "Asia/Kolkata"),
                            )
                        except Exception as e:
                            st.error(f"Erro ao buscar YouTube: {e}")
                            _res = []
                        st.session_state[f"yt_results_{dk}"] = _res
                        st.experimental_rerun()

                _yt_results = st.session_state.get(f"yt_results_{dk}", [])
                if _yt_results:
                    with st.expander(f"🔍 Resultados YouTube ({len(_yt_results)})", expanded=False):
                        # render list with selection and optional type override
                        for vi, v in enumerate(_yt_results):
                            cols = st.columns([1, 6, 2])
                            sel = cols[0].checkbox("", key=f"yt_sel_{dk}_{vi}")
                            with cols[1]:
                                st.markdown(f"**{v.get('title','')}**  \n`{v.get('video_id','')}` · {v.get('inferred_time','')} · {v.get('inferred_type','')}")
                                st.caption(f"{v.get('duration_s',0)}s · publicado {v.get('published_at')}")
                            # allow type override
                            _type_opts = C_EVENT_TYPES if _has_constraints else ["programa", "mangala", "arati", "darshan", "other"]
                            cols[2].selectbox("Tipo", [_type_opts[0]] + _type_opts if v.get('inferred_type') not in _type_opts else _type_opts, index=(_type_opts.index(v.get('inferred_type')) if v.get('inferred_type') in _type_opts else 0), key=f"yt_type_{dk}_{vi}")

                        # Bulk actions (with confirmation modal)
                        sel_idxs = [i for i in range(len(_yt_results)) if st.session_state.get(f"yt_sel_{dk}_{i}")]
                        if sel_idxs:
                            # trigger modals via session_state flags
                            if st.button("Criar eventos selecionados", key=f"yt_bulk_create_ev_{dk}"):
                                st.session_state[f"yt_confirm_create_{dk}"] = True

                            if st.session_state.get(f"yt_confirm_create_{dk}"):
                                with st.modal(f"Confirmar criação de eventos — {dk}", key=f"modal_create_{dk}"):
                                    st.markdown(f"Você está prestes a criar **{len(sel_idxs)}** evento(s) para o dia `{dk}`. Confirma?")
                                    if st.button("Confirmar criação", key=f"confirm_create_{dk}"):
                                        created = []
                                        for i in sel_idxs:
                                            v = _yt_results[i]
                                            override_type = st.session_state.get(f"yt_type_{dk}_{i}")
                                            if not build_event_from_video:
                                                st.error("Serviço de build de evento indisponível.")
                                                break
                                            built = build_event_from_video(v, dk)
                                            if not built:
                                                continue
                                            new_event = built.get('event')
                                            if override_type:
                                                new_event['type'] = override_type
                                            # check duplicate vod
                                            if _has_constraints:
                                                dup = validate_vod_unique(visit, v.get('video_id'))
                                                if dup:
                                                    st.error(f"Pulando {v.get('video_id')}: {dup}")
                                                    continue
                                            events.append(new_event)
                                            created.append(new_event.get('event_key'))
                                        if created:
                                            day['events'] = events
                                            _save(gh, visit_ref, visit, editor_name or "anon", f"events created from YouTube: {', '.join(created)}")
                                            st.experimental_rerun()
                                    if st.button("Cancelar", key=f"cancel_create_{dk}"):
                                        st.session_state.pop(f"yt_confirm_create_{dk}", None)
                                        st.experimental_rerun()

                            if st.button("Adicionar órfãos selecionados", key=f"yt_bulk_orphans_{dk}"):
                                st.session_state[f"yt_confirm_orphan_{dk}"] = True

                            if st.session_state.get(f"yt_confirm_orphan_{dk}"):
                                with st.modal(f"Confirmar adicionar órfãos — {dk}", key=f"modal_orphan_{dk}"):
                                    st.markdown(f"Você está prestes a adicionar **{len(sel_idxs)}** VOD(s) como órfãos para o dia `{dk}`. Confirma?")
                                    if st.button("Confirmar adicionar órfãos", key=f"confirm_orphan_{dk}"):
                                        added = []
                                        _orphans = visit.get('orphans', {})
                                        _o_vods = _orphans.get('vods', []) if isinstance(_orphans, dict) else []
                                        for i in sel_idxs:
                                            v = _yt_results[i]
                                            vid = v.get('video_id')
                                            if _has_constraints:
                                                dup = validate_vod_unique(visit, vid)
                                                if dup:
                                                    st.error(f"Pulando órfão {vid}: {dup}")
                                                    continue
                                            # suggest key
                                            date_part = dk.replace('-', '')
                                            n = len(_o_vods) + 1
                                            vod_key = f"vod-{date_part}-orphan-{n:03d}"
                                            _o_vods.append({
                                                'vod_key': vod_key,
                                                'provider': 'youtube',
                                                'video_id': vid,
                                                'url': None,
                                                'thumb_url': v.get('thumbnail'),
                                                'duration_s': v.get('duration_s'),
                                                'title_pt': v.get('title'),
                                                'title_en': v.get('title'),
                                                'vod_part': 1,
                                                'segments': [],
                                            })
                                            added.append(v.get('video_id'))
                                        if added:
                                            visit['orphans'] = {'vods': _o_vods}
                                            _save(gh, visit_ref, visit, editor_name or "anon", f"added orphan vods from YouTube: {', '.join(added)}")
                                            st.experimental_rerun()
                                    if st.button("Cancelar", key=f"cancel_orphan_{dk}"):
                                        st.session_state.pop(f"yt_confirm_orphan_{dk}", None)
                                        st.experimental_rerun()
            else:
                # service not available
                pass

            # Adicionar evento (com campos inteligentes)
            with st.expander("➕ Adicionar evento", expanded=False):
                ne1, ne2, ne3 = st.columns(3)

                # ── Type (enum) ──────────────────────────────────────
                _ev_types = C_EVENT_TYPES if _has_constraints else [
                    "programa", "mangala", "arati", "darshan", "other",
                ]
                net = ne1.selectbox("type", _ev_types, key=f"net_{di}")

                # ── Time (com sugestão + validação) ──────────────────
                _default_time = ""
                if _has_constraints:
                    _default_time = suggest_event_time(net)
                netm = ne2.text_input(
                    "time (HH:MM)", value=_default_time, key=f"netm_{di}",
                )
                if _has_constraints and netm:
                    _terr = validate_time(netm)
                    if _terr:
                        ne2.error(f"❌ {_terr}")

                # ── event_key (derivado) ─────────────────────────────
                dk = day.get("day_key", "")
                if _has_constraints and dk:
                    _auto_ek = derive_event_key(dk, netm, net)
                    ne1.text_input(
                        "event_key (auto)", value=_auto_ek,
                        key=f"nek_{di}", disabled=True,
                    )
                else:
                    _auto_ek = ""
                    if af:
                        try:
                            sug_ev = af.suggest_event(day, event_type=net, time_str=netm)
                            _auto_ek = sug_ev.get("event_key", "")
                        except Exception:
                            pass
                    _auto_ek = ne1.text_input(
                        "event_key", value=_auto_ek, key=f"nek_{di}",
                    )

                # ── Títulos (sugeridos) ──────────────────────────────
                _day_lbl = day.get("label_pt", "")
                if _has_constraints:
                    _sug_t = suggest_event_title(net, _day_lbl)
                    _def_tp = _sug_t["title_pt"]
                    _def_te = _sug_t["title_en"]
                else:
                    _def_tp = _def_te = ""

                netp = ne2.text_input("title_pt", value=_def_tp, key=f"netp_{di}")
                nete = ne3.text_input("title_en", value=_def_te, key=f"nete_{di}")

                # ── Status (calculado) ───────────────────────────────
                if _has_constraints:
                    _auto_st = compute_event_status(
                        dk, netm, meta.get("timezone", "Asia/Kolkata"),
                    )
                    ne3.markdown(f"**Status:** `{_auto_st}`")
                else:
                    _auto_st = ne3.selectbox(
                        "status",
                        ["past", "active", "future", "live", "soon"],
                        index=2,
                        key=f"nest_{di}",
                    )

                # ── Location (quick-pick) ────────────────────────────
                if _has_constraints:
                    _known = collect_known_locations(visit)
                    _loc_names = [""] + [l["name"] for l in _known]
                    _loc_pick = ne3.selectbox(
                        "Local", _loc_names, key=f"neloc_pick_{di}",
                        format_func=lambda x: x if x else "(digitar novo)",
                    )
                    if _loc_pick:
                        neloc = _loc_pick
                    else:
                        neloc = ne3.text_input("local (novo)", key=f"neloc_{di}")

                    # Buscar coordenadas via Google Places (opcional)
                    try:
                        _has_places = bool(lookup_place)
                    except Exception:
                        _has_places = False

                    if _has_places:
                        places_key = st.secrets.get("google", {}).get("places_api_key", "")
                        if st.button("📍 Buscar coordenadas", key=f"loc_search_{di}"):
                            if not places_key:
                                st.caption("⚠️ Configure `google.places_api_key` no .streamlit/secrets.toml")
                            else:
                                _res = lookup_place(
                                    neloc or "", places_key,
                                    bias_lat=float(visit.get("metadata", {}).get("lat", 27.5815)),
                                    bias_lng=float(visit.get("metadata", {}).get("lng", 77.6997)),
                                )
                                if _res:
                                    st.success(f"📍 {_res.get('formatted')}")
                                    # store for use at save
                                    st.session_state[f"lookup_{di}_{dk}"] = _res
                                    neloc = _res.get("name", neloc)
                                else:
                                    st.warning("Local não encontrado.")
                else:
                    neloc = ne3.text_input("local", key=f"neloc_{di}")

                # ── Salvar ───────────────────────────────────────────
                if st.button("Adicionar evento", key=f"btn_aev_{di}"):
                    _can_save_ev = True

                    if not _auto_ek:
                        st.warning("Preencha tipo e horário para gerar o event_key.")
                        _can_save_ev = False

                    if _has_constraints and _can_save_ev:
                        _dup_ev = validate_event_key_unique(day, _auto_ek)
                        if _dup_ev:
                            st.error(f"❌ {_dup_ev}")
                            _can_save_ev = False

                    if _can_save_ev:
                        # Prefer place lookup result if present
                        _loc_res = st.session_state.get(f"lookup_{di}_{dk}")
                        if _loc_res:
                            _loc_dict = {
                                "name": _loc_res.get("name"),
                                "lat": _loc_res.get("lat"),
                                "lng": _loc_res.get("lng"),
                                "place_id": _loc_res.get("place_id"),
                            }
                        else:
                            _loc_dict = {"name": neloc} if neloc else {}

                        events.append({
                            "event_key": _auto_ek,
                            "type":      net,
                            "title_pt":  netp,
                            "title_en":  nete,
                            "time":      netm,
                            "status":    _auto_st,
                            "location":  _loc_dict,
                            "vods":      [],
                            "photos":    [],
                            "sangha":    [],
                        })
                        day["events"] = events

                        # Auto-set primary se primeiro evento
                        if len(events) == 1:
                            day["primary_event_key"] = _auto_ek
                            day["primary_event"]     = _auto_ek

                        _save(gh, visit_ref, visit, editor_name or "anon",
                              f"event {_auto_ek}: adicionado ao dia {dk}")
                        st.rerun()

            # ── Lista de eventos existentes ───────────────────────────
            for ei, ev in enumerate(events):
                with st.container(border=True):
                    ec1, ec2, ec3 = st.columns([3, 2, 1])
                    with ec1:
                        st.text_input(
                            "event_key", value=ev.get("event_key", ""),
                            key=f"evk_{di}_{ei}", disabled=True,
                        )
                        ev["title_pt"]  = st.text_input("title_pt",  value=ev.get("title_pt", ""),  key=f"evtp_{di}_{ei}")
                        ev["title_en"]  = st.text_input("title_en",  value=ev.get("title_en", ""),  key=f"evte_{di}_{ei}")
                    with ec2:
                        # type como selectbox
                        _ev_types_edit = C_EVENT_TYPES if _has_constraints else [
                            "programa", "mangala", "arati", "darshan", "other",
                        ]
                        _cur_type = ev.get("type", "programa")
                        if _cur_type not in _ev_types_edit:
                            _ev_types_edit = _ev_types_edit + [_cur_type]
                        ev["type"] = st.selectbox(
                            "type", _ev_types_edit,
                            index=_ev_types_edit.index(_cur_type),
                            key=f"evt_{di}_{ei}",
                        )
                        ev["time"]   = st.text_input("time",   value=ev.get("time", ""),   key=f"evtm_{di}_{ei}")
                        if _has_constraints and ev.get("time"):
                            _terr = validate_time(ev["time"])
                            if _terr:
                                st.caption(f"❌ {_terr}")
                        # Status recalculado
                        if _has_constraints:
                            _recalc = compute_event_status(
                                day.get("day_key", ""),
                                ev.get("time", ""),
                                meta.get("timezone", "Asia/Kolkata"),
                            )
                            ev["status"] = _recalc
                            st.markdown(f"**Status:** `{_recalc}`")
                        else:
                            ev["status"] = st.selectbox(
                                "status",
                                ["past", "active", "future", "live", "soon"],
                                index=["past", "active", "future", "live", "soon"].index(
                                    ev.get("status", "past")
                                ),
                                key=f"evst_{di}_{ei}",
                            )
                    with ec3:
                        loc = ev.setdefault("location", {})
                        loc["name"] = st.text_input("local", value=loc.get("name", ""), key=f"evloc_{di}_{ei}")

                        # Buscar coordenadas para evento existente
                        try:
                            _has_places2 = bool(lookup_place)
                        except Exception:
                            _has_places2 = False

                        if _has_places2:
                            places_key = st.secrets.get("google", {}).get("places_api_key", "")
                            if st.button("📍 Buscar coordenadas", key=f"loc_search_exist_{di}_{ei}"):
                                if not places_key:
                                    st.caption("⚠️ Configure `google.places_api_key` no .streamlit/secrets.toml")
                                else:
                                    _res = lookup_place(
                                        loc.get("name", ""), places_key,
                                        bias_lat=float(visit.get("metadata", {}).get("lat", 27.5815)),
                                        bias_lng=float(visit.get("metadata", {}).get("lng", 77.6997)),
                                    )
                                    if _res:
                                        ev["location"] = {
                                            "name": _res.get("name"),
                                            "lat": _res.get("lat"),
                                            "lng": _res.get("lng"),
                                            "place_id": _res.get("place_id"),
                                        }
                                        st.success(f"📍 {_res.get('formatted')}")
                                    else:
                                        st.warning("Local não encontrado.")

                        # ── Contadores de conteúdo ────────────────────
                        _vod_c = len(ev.get("vods", []))
                        _ph_c  = len(ev.get("photos", []))
                        _sg_c  = len(ev.get("sangha", []))
                        if _vod_c or _ph_c or _sg_c:
                            st.caption(f"🎬 {_vod_c} · 📸 {_ph_c} · 💬 {_sg_c}")

                        st.write("")
                        if st.button("🗑️", key=f"del_ev_{di}_{ei}", help="Remover evento"):
                            events.pop(ei)
                            day["events"] = events
                            _save(gh, visit_ref, visit, editor_name or "anon", f"event: removido do dia {dk}")
                            st.rerun()

            # ── Ações do dia ─────────────────────────────────────────
            st.write("")
            col_save, col_del = st.columns([4, 1])
            with col_save:
                if st.button(
                    f"💾 Salvar dia {dk}", key=f"save_day_{di}",
                ):
                    if _has_constraints:
                        visit["stats"] = compute_stats(visit)
                    _save(
                        gh, visit_ref, visit,
                        editor_name or "anon",
                        f"day {dk}: atualizado",
                    )
            with col_del:
                ev_count_check = len(day.get("events", []))
                del_disabled = ev_count_check > 0
                if st.button(
                    "🗑️ Remover dia", key=f"del_day_{di}",
                    type="secondary",
                    disabled=del_disabled,
                    help=(
                        "Remova os eventos primeiro"
                        if del_disabled
                        else "Remover este dia da visita"
                    ),
                ):
                    days.pop(di)
                    visit["days"] = days
                    _save(
                        gh, visit_ref, visit,
                        editor_name or "anon",
                        f"day {dk}: removido",
                    )
                    st.rerun()


# ══════════════════════════════════════════════════════════════════════
# TAB 2 — VODS + SEGMENTS
# ══════════════════════════════════════════════════════════════════════
with tab_vods:
    st.markdown("### 🎬 VODs por Evento")

    SEGMENT_TYPES = [
        "kirtan", "harikatha", "pushpanjali", "arati",
        "dance", "drama", "darshan", "interval", "noise", "announcement",
    ]

    days = visit.get("days", [])
    if not days:
        st.info("Adicione dias na aba 📋 Visita primeiro.")
    else:
        # Seletor de dia
        day_opts = {_day_label(d): i for i, d in enumerate(days)}
        sel_day_label = st.selectbox("Dia", list(day_opts.keys()), key="vod_day_sel")
        sel_day = days[day_opts[sel_day_label]]
        events  = sel_day.get("events", [])

        if not events:
            st.info("Este dia não tem eventos ainda.")
        else:
            # Use an index-based selectbox so labels may be non-unique
            ev_labels = [_event_label(e) for e in events]
            day_index = day_opts[sel_day_label]
            sel_ev_idx = st.selectbox(
                "Evento",
                list(range(len(events))),
                format_func=lambda i, _labels=ev_labels: _labels[i],
                key=f"vod_ev_sel_{day_index}",
            )
            sel_ev = events[sel_ev_idx]
            vods   = sel_ev.setdefault("vods", [])

            st.markdown(
                f"**Evento:** `{sel_ev.get('event_key')}` · "
                f"{len(vods)} VOD(s)"
            )

            # ── YouTube discovery directly from VODs tab (search by day)
            try:
                _has_yt_v = bool(search_videos_for_day)
            except Exception:
                _has_yt_v = False

            if _has_yt_v:
                yt_key = st.secrets.get("youtube", {}).get("api_key", "")
                yt_ch = st.secrets.get("youtube", {}).get("channel_id", YT_DEFAULT_CHANNEL)
                dk = sel_day.get("day_key", "")
                if st.button("🔍 Buscar vídeos no YouTube (dia)", key=f"vod_yt_search_{day_index}"):
                    if not yt_key:
                        st.warning("⚠️ Configure `youtube.api_key` em .streamlit/secrets.toml")
                    else:
                        try:
                            _vres = search_videos_for_day(
                                yt_key, dk, channel_id=yt_ch or YT_DEFAULT_CHANNEL,
                                timezone=meta.get("timezone", "Asia/Kolkata"),
                            )
                        except Exception as e:
                            st.error(f"Erro ao buscar YouTube: {e}")
                            _vres = []
                        st.session_state[f"vod_yt_results_{dk}"] = _vres
                        st.experimental_rerun()

                _yt_v_results = st.session_state.get(f"vod_yt_results_{dk}", [])
                if _yt_v_results:
                    with st.expander(f"🔍 Resultados YouTube (dia) ({len(_yt_v_results)})", expanded=False):
                        for vi, v in enumerate(_yt_v_results):
                            cols = st.columns([1, 6, 2])
                            sel = cols[0].checkbox("", key=f"vod_yt_sel_{dk}_{vi}")
                            with cols[1]:
                                st.markdown(f"**{v.get('title','')}**  \n`{v.get('video_id','')}` · {v.get('inferred_time','')} · {v.get('inferred_type','')}")
                                st.caption(f"{v.get('duration_s',0)}s · publicado {v.get('published_at')}")
                            _type_opts = C_EVENT_TYPES if _has_constraints else ["programa", "mangala", "arati", "darshan", "other"]
                            cols[2].selectbox("Tipo", _type_opts, index=0, key=f"vod_yt_type_{dk}_{vi}")

                        sel_idxs_v = [i for i in range(len(_yt_v_results)) if st.session_state.get(f"vod_yt_sel_{dk}_{i}")]
                        if sel_idxs_v:
                            if st.button("Adicionar VODs selecionados ao evento", key=f"vod_add_selected_{day_index}_{sel_ev_idx}"):
                                st.session_state[f"vod_confirm_add_{day_index}_{sel_ev_idx}"] = True

                            if st.session_state.get(f"vod_confirm_add_{day_index}_{sel_ev_idx}"):
                                with st.modal(f"Confirmar adicionar VODs ao evento `{sel_ev.get('event_key')}`", key=f"modal_vod_add_{day_index}_{sel_ev_idx}"):
                                    st.markdown(f"Você está prestes a adicionar **{len(sel_idxs_v)}** VOD(s) ao evento `{sel_ev.get('event_key')}`. Confirma?")
                                    if st.button("Confirmar adicionar VODs", key=f"confirm_vod_add_{day_index}_{sel_ev_idx}"):
                                        all_vod_keys = _count_all_vods(visit)
                                        added = []
                                        for i in sel_idxs_v:
                                            v = _yt_v_results[i]
                                            vid = v.get('video_id')
                                            if _has_constraints:
                                                dup = validate_vod_unique(visit, vid)
                                                if dup:
                                                    st.error(f"Pulando {vid}: {dup}")
                                                    continue
                                            # suggest a new vod_key
                                            vk = _suggest_vod_key(dk, all_vod_keys)
                                            all_vod_keys.append(vk)
                                            vods.append({
                                                "vod_key": vk,
                                                "provider": "youtube",
                                                "video_id": vid,
                                                "url": None,
                                                "thumb_url": v.get('thumbnail'),
                                                "duration_s": v.get('duration_s'),
                                                "title_pt": v.get('title'),
                                                "title_en": v.get('title'),
                                                "vod_part": 1,
                                                "segments": [],
                                            })
                                            added.append(vid)
                                        if added:
                                            sel_ev['vods'] = vods
                                            _save(gh, visit_ref, visit, editor_name or "anon", f"added VODs to event {sel_ev.get('event_key')}: {', '.join(added)}")
                                            st.experimental_rerun()
                                    if st.button("Cancelar", key=f"cancel_vod_add_{day_index}_{sel_ev_idx}"):
                                        st.session_state.pop(f"vod_confirm_add_{day_index}_{sel_ev_idx}", None)
                                        st.experimental_rerun()

            # ── Adicionar VOD ─────────────────────────────────────────
            with st.expander("➕ Adicionar VOD", expanded=len(vods) == 0):
                nv1, nv2 = st.columns(2)
                with nv1:
                    nvk  = st.text_input("vod_key (vod-YYYYMMDD-NNN)", key="nvk")
                    nvid = st.text_input("video_id (YouTube ID)", key="nvid")
                    nvp  = st.selectbox("provider", ["youtube", "facebook", "drive"], key="nvprov")
                with nv2:
                    nvtp = st.text_input("title_pt", key="nvtp")
                    nvte = st.text_input("title_en", key="nvte")
                    nvpart = st.number_input("vod_part", min_value=1, value=1, key="nvpart")

                # Autofill suggestions for VOD (when available)
                if af:
                    try:
                        sug = af.suggest_vod(day.get("day_key"), sel_ev, video_id=st.session_state.get("nvid", ""), provider=st.session_state.get("nvprov", "youtube"))
                    except Exception:
                        sug = None

                    if sug:
                        with st.expander("💡 Sugestão Autofill para VOD", expanded=False):
                            st.markdown(f"**vod_key:** `{sug.get('vod_key','')}`")
                            st.markdown(f"**title_pt:** {sug.get('title_pt','')}  ")
                            st.markdown(f"**title_en:** {sug.get('title_en','')}  ")
                            st.markdown(f"**vod_part:** {sug.get('vod_part',1)}  ")
                            if st.button("Aplicar sugestão VOD", key="apply_vod_sug"):
                                st.session_state['nvk'] = sug.get('vod_key','') or ''
                                st.session_state['nvid'] = st.session_state.get('nvid','') or st.session_state.get('nvid','')
                                st.session_state['nvtp'] = sug.get('title_pt','') or st.session_state.get('nvtp','')
                                st.session_state['nvte'] = sug.get('title_en','') or st.session_state.get('nvte','')
                                st.session_state['nvpart'] = sug.get('vod_part', 1)
                                st.rerun()

                if st.button("Adicionar VOD", key="btn_add_vod"):
                    if nvk and nvid:
                        # ── Guard: unicidade do video_id ──────
                        _can_save_vod = True
                        if _has_constraints:
                            dup = validate_vod_unique(visit, nvid)
                            if dup:
                                st.error(f"❌ {dup}")
                                st.warning("Corrija o duplicado antes de adicionar o VOD.")
                                _can_save_vod = False

                        if not _can_save_vod:
                            pass
                        else:
                            nvk_clean = _normalize_vod_key(nvk)
                            if nvk_clean != nvk:
                                st.info(f"🔧 vod_key normalizado: `{nvk}` → `{nvk_clean}`")
                            vods.append({
                                "vod_key":    nvk_clean,
                                "provider":   nvp,
                                "video_id":   nvid,
                                "url":        None,
                                "thumb_url":  f"https://img.youtube.com/vi/{nvid}/maxresdefault.jpg" if nvp == "youtube" else "",
                                "duration_s": None,
                                "title_pt":   nvtp,
                                "title_en":   nvte,
                                "vod_part":   int(nvpart),
                                "segments":   [],
                            })
                            sel_ev["vods"] = vods
                            _save(gh, visit_ref, visit, editor_name or "anon", f"vod {nvk_clean}: adicionado")
                            st.rerun()
                    else:
                        st.warning("vod_key e video_id são obrigatórios.")

            # ── Lista VODs ────────────────────────────────────────────
            for vi, vod in enumerate(vods):
                with st.expander(
                    f"🎬 [{vod.get('vod_part','')}] {vod.get('vod_key','?')} · "
                    f"{vod.get('provider','')} · `{vod.get('video_id','')}`",
                    expanded=False,
                ):
                    vc1, vc2 = st.columns(2)
                    with vc1:
                        vod["vod_key"]   = st.text_input("vod_key",  value=vod.get("vod_key", ""),  key=f"vk_{vi}")
                        vod["video_id"]  = st.text_input("video_id", value=vod.get("video_id", ""), key=f"vid_{vi}")
                        vod["provider"]  = st.selectbox(
                            "provider", ["youtube", "facebook", "drive"],
                            index=["youtube", "facebook", "drive"].index(vod.get("provider", "youtube")),
                            key=f"vprov_{vi}",
                        )
                        vod["vod_part"]  = st.number_input("vod_part", min_value=1, value=vod.get("vod_part") or 1, key=f"vpart_{vi}")
                    with vc2:
                        vod["title_pt"]  = st.text_input("title_pt",  value=vod.get("title_pt", ""),  key=f"vtp_{vi}")
                        vod["title_en"]  = st.text_input("title_en",  value=vod.get("title_en", ""),  key=f"vte_{vi}")
                        vod["duration_s"] = st.number_input(
                            "duration_s", min_value=0,
                            value=vod.get("duration_s") or 0,
                            key=f"vdur_{vi}",
                        )
                        # Preview thumb
                        thumb = f"https://img.youtube.com/vi/{vod.get('video_id', '')}/mqdefault.jpg"
                        if vod.get("provider") == "youtube" and vod.get("video_id"):
                            st.image(thumb, width=160)
                            vod["thumb_url"] = f"https://img.youtube.com/vi/{vod['video_id']}/maxresdefault.jpg"

                    # ── Segments ──────────────────────────────────────
                    st.markdown("**Segments:**")
                    segs = vod.setdefault("segments", [])

                    with st.expander("➕ Adicionar segment", expanded=len(segs) == 0):
                        sc1, sc2, sc3 = st.columns(3)
                        with sc1:
                            nsk  = st.text_input("segment_id (seg-YYYYMMDD-NNN)", key=f"nsk_{vi}")
                            nstp = st.text_input("title_pt", key=f"nstp_{vi}")
                        with sc2:
                            nst  = st.selectbox("type", SEGMENT_TYPES, key=f"nst_{vi}")
                            nste = st.text_input("title_en", key=f"nste_{vi}")
                        with sc3:
                            nsst = st.number_input("timestamp_start (s)", min_value=0, value=0, key=f"nsst_{vi}")
                            nset = st.number_input("timestamp_end (s)",   min_value=0, value=0, key=f"nset_{vi}")

                        # Autofill suggestions for Segment
                        if af:
                            try:
                                seg_sug = af.suggest_segment(vod, day.get("day_key"), seg_type=st.session_state.get(f"nst_{vi}", "kirtan"), event=sel_ev)
                            except Exception:
                                seg_sug = None

                            if seg_sug:
                                with st.expander("💡 Sugestão Autofill para Segment", expanded=False):
                                    st.markdown(f"**segment_id:** `{seg_sug.get('segment_id','')}`")
                                    st.markdown(f"**title_pt:** {seg_sug.get('title_pt','')}")
                                    st.markdown(f"**timestamp_start:** {seg_sug.get('timestamp_start',0)}")
                                    st.markdown(f"**timestamp_end:** {seg_sug.get('timestamp_end',0)}")
                                    if st.button("Aplicar sugestão Segment", key=f"apply_seg_sug_{vi}"):
                                        st.session_state[f"nsk_{vi}"] = seg_sug.get('segment_id','')
                                        st.session_state[f"nstp_{vi}"] = seg_sug.get('title_pt','')
                                        st.session_state[f"nste_{vi}"] = seg_sug.get('title_en','')
                                        st.session_state[f"nsst_{vi}"] = int(seg_sug.get('timestamp_start',0) or 0)
                                        st.session_state[f"nset_{vi}"] = int(seg_sug.get('timestamp_end',0) or 0)
                                        st.rerun()

                        if st.button("Adicionar segment", key=f"btn_aseg_{vi}"):
                            if nsk:
                                segs.append({
                                    "segment_id":      nsk,
                                    "type":            nst,
                                    "title_pt":        nstp,
                                    "title_en":        nste,
                                    "timestamp_start": int(nsst),
                                    "timestamp_end":   int(nset),
                                })
                                vod["segments"] = segs
                                _save(gh, visit_ref, visit, editor_name or "anon", f"segment {nsk}: adicionado")
                                st.rerun()

                    # Lista segments
                    if segs:
                        seg_cols = st.columns([2, 2, 1, 1, 1, 1])
                        seg_cols[0].caption("segment_id")
                        seg_cols[1].caption("type")
                        seg_cols[2].caption("start")
                        seg_cols[3].caption("end")
                        seg_cols[4].caption("katha_id")
                        seg_cols[5].caption("ação")

                        for si, seg in enumerate(segs):
                            sc = st.columns([2, 2, 1, 1, 1, 1])
                            seg["segment_id"] = sc[0].text_input(
                                "", value=seg.get("segment_id", ""),
                                key=f"sid_{vi}_{si}", label_visibility="collapsed"
                            )
                            seg["type"] = sc[1].selectbox(
                                "", SEGMENT_TYPES,
                                index=SEGMENT_TYPES.index(seg.get("type", "kirtan")) if seg.get("type") in SEGMENT_TYPES else 0,
                                key=f"stype_{vi}_{si}", label_visibility="collapsed"
                            )
                            seg["timestamp_start"] = sc[2].number_input(
                                "", min_value=0, value=seg.get("timestamp_start") or 0,
                                key=f"sst_{vi}_{si}", label_visibility="collapsed"
                            )
                            seg["timestamp_end"] = sc[3].number_input(
                                "", min_value=0, value=seg.get("timestamp_end") or 0,
                                key=f"set_{vi}_{si}", label_visibility="collapsed"
                            )
                            # katha_id apenas para harikatha
                            if seg.get("type") == "harikatha":
                                katha_id_val = sc[4].number_input(
                                    "", min_value=0, value=seg.get("katha_id") or 0,
                                    key=f"skid_{vi}_{si}", label_visibility="collapsed"
                                ) or None
                                seg["katha_id"] = katha_id_val
                                if katha_id_val:
                                    sc[4].caption(f"🙏 #{katha_id_val}")
                            else:
                                seg["katha_id"] = None
                                sc[4].caption("—")

                            if sc[5].button("🗑️", key=f"del_seg_{vi}_{si}"):
                                segs.pop(si)
                                vod["segments"] = segs
                                _save(gh, visit_ref, visit, editor_name or "anon", "segment: removido")
                                st.rerun()

                    st.write("")
                    col_sv, col_dv = st.columns([4, 1])
                    with col_sv:
                        if st.button(f"💾 Salvar VOD `{vod.get('vod_key', vi)}`", key=f"save_vod_{vi}"):
                            # Normalize vod_key before saving
                            vk = vod.get('vod_key')
                            vk_clean = _normalize_vod_key(vk) if vk else vk
                            if vk_clean != vk:
                                vod['vod_key'] = vk_clean
                                st.info(f"🔧 vod_key normalizado: `{vk}` → `{vk_clean}`")
                            _save(gh, visit_ref, visit, editor_name or "anon", f"vod {vod.get('vod_key')}: atualizado")
                    with col_dv:
                        if st.button("🗑️ Remover", key=f"del_vod_{vi}", type="secondary"):
                            vods.pop(vi)
                            sel_ev["vods"] = vods
                            _save(gh, visit_ref, visit, editor_name or "anon", f"vod: removido")
                            st.rerun()


# ══════════════════════════════════════════════════════════════════════
# TAB 3 — KATHAS + PASSAGES
# ══════════════════════════════════════════════════════════════════════
with tab_kathas:
    st.markdown("### 🙏 Hari-Kathās Vinculadas")
    st.caption(
        "Visão consolidada de todas as Hari-Kathās referenciadas nos "
        "segments desta visita. O `katha_id` é a FK para o CPT `vana_katha` no WordPress."
    )

    # ── Coletar katha_ids dos segments ────────────────────────────────
    katha_map: dict[int, list[dict]] = {}   # katha_id → list of sources
    hk_without_id: list[str] = []           # segment_ids sem katha_id

    for day in visit.get("days", []):
        day_key = day.get("day_key", "")
        for ev in day.get("events", []):
            ev_key   = ev.get("event_key", "")
            ev_title = ev.get("title_pt", ev_key)
            for vod in ev.get("vods", []):
                vod_key  = vod.get("vod_key", "")
                video_id = vod.get("video_id", "")
                provider = vod.get("provider", "")
                for seg in vod.get("segments", []):
                    if seg.get("type") != "harikatha":
                        continue
                    kid = seg.get("katha_id")
                    seg_id = seg.get("segment_id", "?")
                    source_info = {
                        "segment_id":      seg_id,
                        "event_key":       ev_key,
                        "event_title":     ev_title,
                        "day_key":         day_key,
                        "vod_key":         vod_key,
                        "video_id":        video_id,
                        "provider":        provider,
                        "timestamp_start": seg.get("timestamp_start", 0),
                        "timestamp_end":   seg.get("timestamp_end", 0),
                    }
                    if kid:
                        katha_map.setdefault(kid, []).append(source_info)
                    else:
                        hk_without_id.append(seg_id)

    # ── Métricas ──────────────────────────────────────────────────────
    km1, km2, km3 = st.columns(3)
    km1.metric("Kathās vinculadas", len(katha_map))
    km2.metric("Segments harikatha", len(hk_without_id) + sum(len(v) for v in katha_map.values()))
    km3.metric("⚠️ Sem katha_id", len(hk_without_id))

    # ── Alerta de segments órfãos ─────────────────────────────────────
    if hk_without_id:
        st.error(
            f"❌ **{len(hk_without_id)} segment(s) `harikatha` sem `katha_id`:**\n\n"
            f"`{'`, `'.join(hk_without_id)}`\n\n"
            "Vá na aba 🎬 VODs e preencha o `katha_id` (WP post ID) para cada um."
        )

    if not katha_map:
        st.info(
            "Nenhuma Hari-Kathā vinculada a esta visita. "
            "Adicione segments com `type: harikatha` e preencha `katha_id` na aba 🎬 VODs."
        )
    else:
        # ── Tentar buscar metadados do WP ─────────────────────────────
        @st.cache_data(ttl=120, show_spinner="Buscando kathās no WP...")
        def _fetch_katha_meta_batch(katha_ids: tuple) -> dict[int, dict]:
            """Busca metadados de múltiplas kathās do WP."""
            results = {}
            try:
                from api.wp_client import get_katha_meta
                for kid in katha_ids:
                    try:
                        meta = get_katha_meta(kid)
                        if meta:
                            results[kid] = meta
                    except Exception:
                        pass
            except ImportError:
                pass
            return results

        katha_ids_tuple = tuple(sorted(katha_map.keys()))
        wp_metas = _fetch_katha_meta_batch(katha_ids_tuple)

        # ── Listar cada kathā ─────────────────────────────────────────
        for kid in sorted(katha_map.keys()):
            sources = katha_map[kid]
            wp_meta = wp_metas.get(kid)

            # Header
            if wp_meta:
                title_display = wp_meta.get("title", f"Kathā #{kid}")
                scripture     = wp_meta.get("scripture", "")
                language      = wp_meta.get("language", "")
                header = f"🙏 #{kid} · {title_display}"
                if scripture:
                    header += f" · 📖 {scripture}"
                if language:
                    header += f" ({language})"
            else:
                header = f"🙏 #{kid} · {len(sources)} source(s)"

            with st.expander(header, expanded=True):

                # ── Card de metadados WP ──────────────────────────────
                if wp_meta:
                    mc1, mc2, mc3, mc4 = st.columns(4)
                    mc1.metric("Título", wp_meta.get("title", "—")[:30])
                    mc2.metric("Escritura", wp_meta.get("scripture", "—"))
                    mc3.metric("Idioma", wp_meta.get("language", "—"))
                    mc4.metric("Passages", wp_meta.get("total_passages", "?"))

                    permalink = wp_meta.get("permalink")
                    if permalink:
                        st.link_button("🌐 Ver no WordPress", permalink)
                else:
                    st.warning(
                        f"⚠️ Kathā **#{kid}** não encontrada no WordPress. "
                        "Verifique se o post existe e está publicado, ou se "
                        "a função `get_katha_meta` está implementada no `wp_client`."
                    )

                # ── Tabela de sources ─────────────────────────────────
                st.markdown("**📍 Sources (segments):**")

                for si, src in enumerate(sources):
                    sc1, sc2, sc3, sc4 = st.columns([2, 2, 2, 1])
                    sc1.markdown(f"**`{src['segment_id']}`**")
                    sc2.markdown(
                        f"📅 `{src['day_key']}` · "
                        f"`{src['event_key']}`"
                    )

                    # Timestamp como link para YouTube
                    ts = src["timestamp_start"]
                    te = src["timestamp_end"]
                    ts_fmt = f"{ts // 3600}:{(ts % 3600) // 60:02d}:{ts % 60:02d}" if ts else "0:00:00"
                    te_fmt = f"{te // 3600}:{(te % 3600) // 60:02d}:{te % 60:02d}" if te else "0:00:00"

                    if src["provider"] == "youtube" and src["video_id"]:
                        yt_link = f"https://www.youtube.com/watch?v={src['video_id']}&t={ts}s"
                        sc3.markdown(f"⏱️ [{ts_fmt} → {te_fmt}]({yt_link})")
                    else:
                        sc3.markdown(f"⏱️ {ts_fmt} → {te_fmt}")

                    sc4.markdown(f"`{src['vod_key']}`")

                # ── Verificação de integridade ────────────────────────
                events_with_this_katha = set(s["event_key"] for s in sources)
                if len(events_with_this_katha) > 1:
                    st.info(
                        f"ℹ️ Esta kathā aparece em **{len(events_with_this_katha)} evento(s)**: "
                        f"`{'`, `'.join(events_with_this_katha)}`. "
                        "Isso é válido para HK fragmentado (R-HK-6)."
                    )

                for ev_key in events_with_this_katha:
                    other_kathas_in_event = set()
                    for other_kid, other_sources in katha_map.items():
                        if other_kid == kid:
                            continue
                        for os_ in other_sources:
                            if os_["event_key"] == ev_key:
                                other_kathas_in_event.add(other_kid)
                    if other_kathas_in_event:
                        st.warning(
                            f"⚠️ Evento `{ev_key}` tem **múltiplas kathās**: "
                            f"#{kid} e #{', #'.join(str(k) for k in other_kathas_in_event)}. "
                            "Verifique se deveria ser eventos separados (R-HK-3)."
                        )

    # ── Resumo para publicação ────────────────────────────────────────
    st.divider()
    st.markdown("### 📋 Checklist Kathā")

    checks = []

    # 1. Todos os harikatha têm katha_id?
    if hk_without_id:
        checks.append(f"❌ {len(hk_without_id)} segment(s) harikatha sem `katha_id`")
    else:
        total_hk = sum(len(v) for v in katha_map.values())
        checks.append(f"✅ Todos os {total_hk} segments harikatha têm `katha_id`")

    # 2. Kathās existem no WP?
    missing_wp = [kid for kid in katha_map if kid not in wp_metas] if katha_map else []
    if missing_wp:
        checks.append(
            f"⚠️ {len(missing_wp)} kathā(s) não encontrada(s) no WP: "
            f"#{', #'.join(str(k) for k in missing_wp)}"
        )
    elif katha_map:
        checks.append(f"✅ Todas as {len(katha_map)} kathā(s) verificadas no WP")

    # 3. Schema 6.2 compliance
    schema_v = visit.get("schema_version", "")
    has_legacy_kathas = False
    for day in visit.get("days", []):
        for ev in day.get("events", []):
            if ev.get("kathas"):
                has_legacy_kathas = True
                break

    if schema_v == "6.2" and has_legacy_kathas:
        checks.append("❌ Schema 6.2 mas evento ainda contém `kathas[]` — migração incompleta")
    elif schema_v == "6.2":
        checks.append("✅ Schema 6.2 — `kathas[]` removido dos eventos (R-HK-4)")
    elif schema_v == "6.1" and katha_map:
        checks.append("ℹ️ Schema 6.1 — considere migrar para 6.2 na aba 📋 Visita")

    for c in checks:
        st.markdown(c)


# ══════════════════════════════════════════════════════════════════════
# TAB 4 — GALERIA (photos + sangha)
# ══════════════════════════════════════════════════════════════════════
with tab_galeria:
    st.markdown("### 🖼️ Fotos e Sangha")

    # Use global `_r2` initialized at module top
    r2 = _r2

    days = visit.get("days", [])
    if not days:
        st.info("Adicione dias primeiro.")
    else:
        gday_opts = {_day_label(d): i for i, d in enumerate(days)}
        gsel_day_label = st.selectbox("Dia", list(gday_opts.keys()), key="g_day")
        gsel_day = days[gday_opts[gsel_day_label]]
        gevents  = gsel_day.get("events", [])

        if not gevents:
            st.info("Sem eventos.")
        else:
            gev_opts = {_event_label(e): i for i, e in enumerate(gevents)}
            gsel_ev_label = st.selectbox("Evento", list(gev_opts.keys()), key="g_ev")
            gsel_ev = gevents[gev_opts[gsel_ev_label]]

            # ── PHOTOS ────────────────────────────────────────────────
            st.markdown("#### 📸 Photos")
            photos = gsel_ev.setdefault("photos", [])

            with st.expander("➕ Adicionar foto"):
                upload_mode = st.radio(
                    "Origem da foto",
                    ["📎 Upload do computador", "🔗 Importar de URL", "🔗 URL manual (sem CDN)"],
                    key=f"upload_mode_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}",
                    horizontal=True,
                )

                new_photo_cap = st.text_input("Legenda (PT)", key=f"new_photo_cap_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}")
                np_c1, np_c2 = st.columns(2)
                new_photo_wg    = np_c1.checkbox("Com Gurudeva", key=f"new_photo_wg_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}")
                new_photo_cover = np_c2.checkbox("Candidata a capa", key=f"new_photo_cover_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}")

                # MODE 1: Upload from client
                if upload_mode == "📎 Upload do computador":
                    uploaded_file = st.file_uploader(
                        "Selecione a foto",
                        type=["jpg", "jpeg", "png", "webp", "avif"],
                        key=f"file_upload_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}",
                    )

                    if uploaded_file:
                        st.image(uploaded_file, width=300, caption=uploaded_file.name)
                        st.caption(f"📐 {uploaded_file.size / 1024:.0f} KB · `{uploaded_file.type}`")
                        convert_webp = st.checkbox("Converter para WebP (recomendado)", value=True, key=f"convert_webp_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}")

                        if st.button("☁️ Enviar para CDN", key=f"btn_upload_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}"):
                            if not r2:
                                st.error("❌ R2 não configurado. Verifique `[r2]` em secrets.toml")
                            else:
                                with st.spinner("Enviando para CDN..."):
                                    try:
                                        img_bytes = uploaded_file.getvalue()
                                        ct = uploaded_file.type or "image/jpeg"
                                        if convert_webp and ct != "image/webp":
                                            from services.r2_service import R2Service
                                            img_bytes, ct = R2Service._convert_to_webp(img_bytes)

                                        result = _r2.upload_photo(
                                            visit_ref=visit_ref,
                                            day_key=gsel_day.get("day_key", ""),
                                            img_bytes=img_bytes,
                                            content_type=ct,
                                            filename_hint=uploaded_file.name,
                                        )

                                        photos.append({
                                            "url":             result["url"],
                                            "r2_key":          result["r2_key"],
                                            "caption_pt":      new_photo_cap,
                                            "caption_en":      "",
                                            "with_gurudeva":   new_photo_wg,
                                            "cover_candidate": new_photo_cover,
                                            "credit":          "",
                                            "provider":        "r2",
                                            "size_bytes":      result["size"],
                                            "uploaded_at":     result["uploaded_at"],
                                            "source":          "upload",
                                        })
                                        gsel_ev["photos"] = photos
                                        _save(gh, visit_ref, visit, editor_name or "anon", f"photo: uploaded to CDN ({result['hash']})")
                                        st.success(f"✅ Foto enviada! ({result['size'] / 1024:.0f} KB)")
                                        st.rerun()

                                    except Exception as e:
                                        st.error(f"❌ Erro no upload: {e}")

                # MODE 2: Import from URL
                elif upload_mode == "🔗 Importar de URL":
                    source_url = st.text_input("URL da foto original", key=f"import_url_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}", placeholder="https://example.com/photo.jpg")

                    if source_url:
                        try:
                            st.image(source_url, width=300, caption="Preview da origem")
                        except Exception:
                            st.warning("⚠️ Não foi possível carregar preview.")

                        import_c1, import_c2 = st.columns(2)
                        convert_webp_url = import_c1.checkbox("Converter para WebP", value=True, key=f"convert_webp_url_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}")
                        max_mb = import_c2.number_input("Tamanho máx (MB)", value=10.0, min_value=1.0, max_value=50.0, key=f"max_mb_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}")

                        if st.button("☁️ Importar para CDN", key=f"btn_import_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}"):
                            if not _r2:
                                st.error("❌ R2 não configurado.")
                            else:
                                with st.spinner("Baixando e enviando para CDN..."):
                                    try:
                                        result = _r2.upload_photo_from_url(
                                            source_url=source_url,
                                            visit_ref=visit_ref,
                                            day_key=gsel_day.get("day_key", ""),
                                            convert_webp=convert_webp_url,
                                            max_size_mb=max_mb,
                                        )

                                        photos.append({
                                            "url":             result["url"],
                                            "r2_key":          result["r2_key"],
                                            "source_url":      source_url,
                                            "caption_pt":      new_photo_cap,
                                            "caption_en":      "",
                                            "with_gurudeva":   new_photo_wg,
                                            "cover_candidate": new_photo_cover,
                                            "credit":          "",
                                            "provider":        "r2",
                                            "size_bytes":      result["size"],
                                            "uploaded_at":     result["uploaded_at"],
                                            "source":          "import_url",
                                        })
                                        gsel_ev["photos"] = photos
                                        _save(gh, visit_ref, visit, editor_name or "anon", f"photo: imported from URL to CDN")
                                        st.success("✅ Importada para CDN")
                                        st.rerun()

                                    except ValueError as e:
                                        st.error(f"❌ {e}")
                                    except Exception as e:
                                        st.error(f"❌ Erro na importação: {e}")

                # MODE 3: Manual URL (no CDN)
                else:
                    new_photo_url = st.text_input("URL da foto", key=f"manual_url_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}", placeholder="https://...")

                    if st.button("Adicionar referência", key=f"btn_add_photo_{gday_opts[gsel_day_label]}_{gev_opts[gsel_ev_label]}"):
                        if new_photo_url:
                            photos.append({
                                "url":             new_photo_url,
                                "caption_pt":      new_photo_cap,
                                "caption_en":      "",
                                "with_gurudeva":   new_photo_wg,
                                "cover_candidate": new_photo_cover,
                                "credit":          "",
                                "provider":        "external",
                                "source":          "manual_url",
                            })
                            gsel_ev["photos"] = photos
                            _save(gh, visit_ref, visit, editor_name or "anon", "photo: added external URL")
                            st.rerun()
                        else:
                            st.warning("URL é obrigatória.")

            # Grid de fotos
            if photos:
                ph_cols = st.columns(3)
                for phi, ph in enumerate(photos):
                    with ph_cols[phi % 3]:
                        if ph.get("thumb_url"):
                            st.image(ph["thumb_url"], use_container_width=True)
                        st.caption(ph.get("caption_pt", ph.get("photo_key", "")))
                        with st.expander("✏️ Editar"):
                            ph["photo_key"]  = st.text_input("photo_key",  value=ph.get("photo_key", ""),  key=f"phk_{phi}")
                            ph["full_url"]   = st.text_input("full_url",   value=ph.get("full_url", ""),   key=f"phfu_{phi}")
                            ph["thumb_url"]  = st.text_input("thumb_url",  value=ph.get("thumb_url", ""),  key=f"phth_{phi}")
                            ph["caption_pt"] = st.text_input("caption_pt", value=ph.get("caption_pt", ""), key=f"phcp_{phi}")
                            ph["caption_en"] = st.text_input("caption_en", value=ph.get("caption_en", ""), key=f"phce_{phi}")
                            ph["author"]     = st.text_input("author",     value=ph.get("author", ""),     key=f"pha_{phi}")
                            if st.button("🗑️ Remover", key=f"del_ph_{phi}"):
                                removed = photos.pop(phi)
                                # If photo was stored in R2, try to delete from bucket
                                r2_key = removed.get("r2_key", "")
                                if r2_key and _r2:
                                    try:
                                        _r2.delete_photo(r2_key)
                                        st.toast(f"🗑️ Deletada do CDN: `{r2_key}`")
                                    except Exception as e:
                                        st.warning(f"⚠️ Foto removida do visit.json mas erro ao deletar do CDN: {e}")

                                gsel_ev["photos"] = photos
                                _save(gh, visit_ref, visit, editor_name or "anon", "photo: removida")
                                st.rerun()

            if st.button("💾 Salvar Photos", key="save_photos"):
                _save(gh, visit_ref, visit, editor_name or "anon", "photos: atualizadas")

            # ── SANGHA ────────────────────────────────────────────────
            st.divider()
            st.markdown("#### 🌸 Sangha Moments")
            sangha = gsel_ev.setdefault("sangha", [])

            with st.expander("➕ Adicionar Sangha"):
                sg1, sg2 = st.columns(2)
                with sg1:
                    nsgk  = st.text_input("sangha_key (sg-YYYYMMDD-NNN)", key="nsgk")
                    nsgtp = st.text_input("type (message/post/reel)", value="message", key="nsgtp")
                    nsgpr = st.selectbox("provider", ["direct", "instagram", "facebook", "whatsapp"], key="nsgpr")
                with sg2:
                    nsgcp = st.text_area("caption_pt", key="nsgcp", height=80)
                    nsgce = st.text_area("caption_en", key="nsgce", height=80)
                    nsga  = st.text_input("author", key="nsga")
                    nsgu  = st.text_input("url", key="nsgu")

                if st.button("Adicionar Sangha", key="btn_addsg"):
                    if nsgk:
                        sangha.append({
                            "sangha_key": nsgk,
                            "type":       nsgtp,
                            "provider":   nsgpr,
                            "url":        nsgu or None,
                            "thumb_url":  None,
                            "caption_pt": nsgcp,
                            "caption_en": nsgce,
                            "author":     nsga,
                        })
                        gsel_ev["sangha"] = sangha
                        _save(gh, visit_ref, visit, editor_name or "anon", f"sangha {nsgk}: adicionado")
                        st.rerun()

            for sgi, sg in enumerate(sangha):
                with st.container(border=True):
                    sc1, sc2, sc3 = st.columns([3, 4, 1])
                    sc1.markdown(f"**{sg.get('author', '—')}** · `{sg.get('provider', '')}`")
                    sc2.markdown(sg.get("caption_pt", ""))
                    if sc3.button("🗑️", key=f"del_sg_{sgi}"):
                        sangha.pop(sgi)
                        gsel_ev["sangha"] = sangha
                        _save(gh, visit_ref, visit, editor_name or "anon", "sangha: removido")
                        st.rerun()
                    if sg.get("url"):
                        st.link_button("🔗 Ver post", sg["url"])

            if st.button("💾 Salvar Sangha", key="save_sangha"):
                _save(gh, visit_ref, visit, editor_name or "anon", "sangha: atualizado")


# ══════════════════════════════════════════════════════════════════════
# TAB 5 — ÓRFÃOS
# ══════════════════════════════════════════════════════════════════════
with tab_orfaos:
    st.markdown("### 📦 Itens Órfãos")
    st.caption(
        "VODs e fotos que não estão vinculados a nenhum evento. "
        "Mova para um evento existente ou remova."
    )

    _orphans = visit.get("orphans", {})
    if not isinstance(_orphans, dict):
        _orphans = {}
        visit["orphans"] = _orphans

    _o_vods   = _orphans.get("vods", [])
    _o_photos = _orphans.get("photos", [])

    # ── Métricas ──────────────────────────────────────────────────
    _om1, _om2, _om3 = st.columns(3)
    _om1.metric("📹 VODs órfãos", len(_o_vods))
    _om2.metric("📸 Fotos órfãs", len(_o_photos))
    _o_health = "✅ Limpo" if (len(_o_vods) + len(_o_photos) == 0) else "⚠️ Pendente"
    _om3.metric("Status", _o_health)

    # ── Helper: montar lista de eventos destino ───────────────────
    def _build_event_targets() -> dict:
        """Retorna dict {'day_label · event_title': (day_dict, event_dict)}"""
        targets = {}
        for _td in visit.get("days", []):
            _tdk = _td.get("day_key", "")
            _tdl = _td.get("label_pt", _tdk)
            for _te in _td.get("events", []):
                _tek = _te.get("event_key", "")
                _tet = _te.get("title_pt", _tek)
                label = f"{_tdl} · {_tet}"
                targets[label] = (_td, _te)
        return targets

    # ══════════════════════════════════════════════════════════════
    # VODs ÓRFÃOS
    # ══════════════════════════════════════════════════════════════
    st.markdown("---")
    st.markdown("#### 📹 VODs Órfãos")

    if not _o_vods:
        st.success("Nenhum VOD órfão. 🎉")
    else:
        for _ovi, _ovod in enumerate(_o_vods):
            _ov_key   = _ovod.get("vod_key", f"orphan-vod-{_ovi}")
            _ov_vid   = _ovod.get("video_id", "")
            _ov_prov  = _ovod.get("provider", "youtube")
            _ov_title = _ovod.get("title_pt", "")
            _ov_dur   = _ovod.get("duration_s")
            _ov_type  = _ovod.get("orphan_type", "documental")

            _type_opts = ["documental", "behind_scenes", "extra", "test", "unknown"]

            with st.expander(
                f"📹 `{_ov_key}` · {_ov_title[:40] or _ov_vid or '(sem título)'}",
                expanded=True,
            ):
                _ovc1, _ovc2 = st.columns(2)

                with _ovc1:
                    _new_ov_title = st.text_input(
                        "title_pt", value=_ov_title,
                        key=f"ov_title_{_ovi}")
                    _new_ov_vid = st.text_input(
                        "video_id", value=_ov_vid,
                        key=f"ov_vid_{_ovi}")
                    _new_ov_type = st.selectbox(
                        "orphan_type", _type_opts,
                        index=_type_opts.index(_ov_type) if _ov_type in _type_opts else 4,
                        key=f"ov_type_{_ovi}",
                    )

                with _ovc2:
                    _prov_opts = ["youtube", "facebook", "drive"]
                    _new_ov_prov = st.selectbox(
                        "provider", _prov_opts,
                        index=_prov_opts.index(_ov_prov) if _ov_prov in _prov_opts else 0,
                        key=f"ov_prov_{_ovi}",
                    )
                    if _ov_dur:
                        _dur_h = _ov_dur // 3600
                        _dur_m = (_ov_dur % 3600) // 60
                        _dur_s = _ov_dur % 60
                        st.metric("Duração", f"{_dur_h}:{_dur_m:02d}:{_dur_s:02d}")
                    if _ov_vid and _ov_prov == "youtube":
                        try:
                            st.image(
                                f"https://img.youtube.com/vi/{_ov_vid}/mqdefault.jpg",
                                width=200,
                            )
                        except Exception:
                            pass

                # ── Ações ─────────────────────────────────────────
                _oac1, _oac2, _oac3 = st.columns(3)

                # Salvar edições
                _ov_changed = (
                    _new_ov_title != _ov_title
                    or _new_ov_vid != _ov_vid
                    or _new_ov_type != _ov_type
                    or _new_ov_prov != _ov_prov
                )
                if _ov_changed:
                    if _oac1.button("💾 Salvar", key=f"ov_save_{_ovi}"):
                        _ovod["title_pt"]    = _new_ov_title
                        _ovod["video_id"]    = _new_ov_vid
                        _ovod["orphan_type"] = _new_ov_type
                        _ovod["provider"]    = _new_ov_prov
                        _save(gh, visit_ref, visit, editor_name or "anon",
                              f"orphan vod {_ov_key}: edited")
                        st.rerun()

                # Mover para evento
                if _oac2.button("📤 Mover para evento", key=f"ov_move_{_ovi}"):
                    st.session_state[f"_mv_ov_{_ovi}"] = True

                if st.session_state.get(f"_mv_ov_{_ovi}"):
                    _ev_targets = _build_event_targets()
                    if not _ev_targets:
                        st.warning("Nenhum evento disponível. Crie um evento primeiro na aba 📋 Visita.")
                    else:
                        _sel_tgt = st.selectbox(
                            "Evento destino",
                            list(_ev_targets.keys()),
                            key=f"ov_target_{_ovi}",
                        )
                        if st.button("✅ Confirmar movimentação",
                                      key=f"ov_confirm_{_ovi}"):
                            _tgt_day, _tgt_ev = _ev_targets[_sel_tgt]
                            # Copiar VOD sem orphan_type
                            _vod_to_move = {
                                k: v for k, v in _ovod.items()
                                if k != "orphan_type"
                            }
                            _tgt_ev.setdefault("vods", []).append(_vod_to_move)
                            _o_vods.pop(_ovi)
                            _orphans["vods"] = _o_vods
                            st.session_state.pop(f"_mv_ov_{_ovi}", None)
                            _save(gh, visit_ref, visit, editor_name or "anon",
                                  f"orphan vod {_ov_key}: moved to {_sel_tgt}")
                            st.rerun()

                        if st.button("❌ Cancelar", key=f"ov_cancel_{_ovi}"):
                            st.session_state.pop(f"_mv_ov_{_ovi}", None)
                            st.rerun()

                # Remover
                if _oac3.button("🗑️ Remover", key=f"ov_del_{_ovi}"):
                    _o_vods.pop(_ovi)
                    _orphans["vods"] = _o_vods
                    _save(gh, visit_ref, visit, editor_name or "anon",
                          f"orphan vod {_ov_key}: removed")
                    st.rerun()

    # ── Adicionar VOD órfão ───────────────────────────────────────
    with st.expander("➕ Adicionar VOD órfão", expanded=False):
        _nav1, _nav2 = st.columns(2)
        with _nav1:
            _new_ov_vid_add  = st.text_input(
                "video_id", key="new_ov_vid_add",
                placeholder="YouTube ID ou outro")
            _prov_opts_add = ["youtube", "facebook", "drive"]
            _new_ov_prov_add = st.selectbox(
                "provider", _prov_opts_add, key="new_ov_prov_add")
            _type_opts_add = ["documental", "behind_scenes", "extra", "test", "unknown"]
            _new_ov_type_add = st.selectbox(
                "orphan_type", _type_opts_add, key="new_ov_type_add")
        with _nav2:
            _new_ov_title_add = st.text_input("title_pt", key="new_ov_title_add")
            _new_ov_dur_add   = st.number_input(
                "duration_s", min_value=0, value=0, key="new_ov_dur_add")

        # Sugerir vod_key
        _date_start = (
            visit.get("metadata", {}).get("date_start", "").replace("-", "")
            or "00000000"
        )
        _orphan_n = len(_o_vods) + 1
        _sug_ov_key = f"vod-{_date_start}-orphan-{_orphan_n:03d}"
        _new_ov_key_add = st.text_input(
            "vod_key", value=_sug_ov_key, key="new_ov_key_add")

        if st.button("Adicionar VOD órfão", key="btn_add_ov"):
            if _new_ov_vid_add and _new_ov_key_add:
                _can_save_ov = True
                if _has_constraints:
                    dup = validate_vod_unique(visit, _new_ov_vid_add)
                    if dup:
                        st.error(f"❌ {dup}")
                        st.warning("Corrija o duplicado antes de adicionar o VOD órfão.")
                        _can_save_ov = False

                if not _can_save_ov:
                    pass
                else:
                    _o_vods.append({
                        "vod_key":     _new_ov_key_add,
                        "provider":    _new_ov_prov_add,
                        "video_id":    _new_ov_vid_add,
                        "url":         None,
                        "thumb_url":   (
                            f"https://img.youtube.com/vi/{_new_ov_vid_add}/maxresdefault.jpg"
                            if _new_ov_prov_add == "youtube" else ""
                        ),
                        "duration_s":  _new_ov_dur_add or None,
                        "title_pt":    _new_ov_title_add,
                        "title_en":    "",
                        "orphan_type": _new_ov_type_add,
                        "segments":    [],
                    })
                    _orphans["vods"] = _o_vods
                    visit["orphans"] = _orphans
                    _save(gh, visit_ref, visit, editor_name or "anon",
                          f"orphan vod {_new_ov_key_add}: added")
                    st.rerun()
            else:
                st.warning("video_id e vod_key são obrigatórios.")

    # ══════════════════════════════════════════════════════════════
    # FOTOS ÓRFÃS
    # ══════════════════════════════════════════════════════════════
    st.markdown("---")
    st.markdown("#### 📸 Fotos Órfãs")

    if not _o_photos:
        st.success("Nenhuma foto órfã. 🎉")
    else:
        for _opi, _ophoto in enumerate(_o_photos):
            _op_url  = _ophoto.get("url", _ophoto.get("ref", ""))
            _op_cap  = _ophoto.get("caption_pt", "")
            _op_prov = _ophoto.get("provider", "external")

            _opp1, _opp2 = st.columns([1, 3])

            with _opp1:
                if _op_url and _op_url.startswith("http"):
                    try:
                        st.image(_op_url, width=150)
                    except Exception:
                        st.caption("❌ Preview indisponível")
                else:
                    st.caption(f"🔗 `{_op_url[:60]}`")

                # Badge
                if _op_prov == "r2":
                    st.caption("☁️ CDN")
                else:
                    st.caption("🔗 Externa")

            with _opp2:
                _oppc1, _oppc2, _oppc3 = st.columns([3, 1, 1])
                _oppc1.text_input(
                    "url", value=_op_url,
                    key=f"op_url_{_opi}",
                    label_visibility="collapsed",
                    disabled=True,
                )
                _oppc2.caption(
                    f"📝 {_op_cap[:30]}" if _op_cap else "📝 (sem legenda)"
                )

                # Mover
                if _oppc2.button("📤", key=f"op_move_{_opi}",
                                  help="Mover para evento"):
                    st.session_state[f"_mv_op_{_opi}"] = True

                # Remover
                if _oppc3.button("🗑️", key=f"op_del_{_opi}",
                                  help="Remover"):
                    _op_r2key = _ophoto.get("r2_key", "")
                    if _op_r2key and _r2:
                        try:
                            _r2.delete_photo(_op_r2key)
                        except Exception:
                            pass
                    _o_photos.pop(_opi)
                    _orphans["photos"] = _o_photos
                    _save(gh, visit_ref, visit, editor_name or "anon",
                          "orphan photo: removed")
                    st.rerun()

            # Panel de movimentação
            if st.session_state.get(f"_mv_op_{_opi}"):
                _ev_targets_p = _build_event_targets()
                if _ev_targets_p:
                    _sel_p = st.selectbox(
                        "Evento destino",
                        list(_ev_targets_p.keys()),
                        key=f"op_target_{_opi}",
                    )
                    _mvc1, _mvc2 = st.columns(2)
                    if _mvc1.button("✅ Confirmar", key=f"op_confirm_{_opi}"):
                        _, _tgt_ev_p = _ev_targets_p[_sel_p]
                        _tgt_ev_p.setdefault("photos", []).append(_ophoto)
                        _o_photos.pop(_opi)
                        _orphans["photos"] = _o_photos
                        st.session_state.pop(f"_mv_op_{_opi}", None)
                        _save(gh, visit_ref, visit, editor_name or "anon",
                              f"orphan photo: moved to {_sel_p}")
                        st.rerun()
                    if _mvc2.button("❌ Cancelar", key=f"op_cancel_{_opi}"):
                        st.session_state.pop(f"_mv_op_{_opi}", None)
                        st.rerun()
                else:
                    st.warning("Nenhum evento disponível.")

            st.divider()

    # ── Adicionar foto órfã ───────────────────────────────────────
    with st.expander("➕ Adicionar foto órfã", expanded=False):
        _oph_modes = ["📎 Upload CDN", "🔗 URL manual"]
        if not _r2:
            _oph_modes = ["🔗 URL manual"]

        _oph_mode = st.radio(
            "Origem", _oph_modes, key="oph_mode", horizontal=True)

        _oph_cap = st.text_input("Legenda", key="oph_cap")

        if _oph_mode == "📎 Upload CDN" and _r2:
            _oph_file = st.file_uploader(
                "Foto", type=["jpg", "jpeg", "png", "webp"],
                key="oph_file",
            )
            if _oph_file and st.button("☁️ Enviar", key="btn_oph_upload",
                                        type="primary"):
                with st.spinner("Enviando..."):
                    try:
                        _oph_bytes = _oph_file.getvalue()
                        _oph_ct = _oph_file.type or "image/jpeg"
                        if _oph_ct != "image/webp":
                            from services.r2_service import R2Service
                            _oph_bytes, _oph_ct = R2Service._convert_to_webp(
                                _oph_bytes)

                        _oph_dk = (
                            visit.get("days", [{}])[0].get("day_key", "")
                            if visit.get("days") else "orphans"
                        )
                        _oph_result = _r2.upload_photo(
                            visit_ref=visit_ref,
                            day_key=_oph_dk,
                            img_bytes=_oph_bytes,
                            content_type=_oph_ct,
                        )
                        _o_photos.append({
                            "url":         _oph_result["url"],
                            "r2_key":      _oph_result["r2_key"],
                            "caption_pt":  _oph_cap,
                            "caption_en":  "",
                            "provider":    "r2",
                            "size_bytes":  _oph_result["size"],
                            "uploaded_at": _oph_result["uploaded_at"],
                            "source":      "upload",
                        })
                        _orphans["photos"] = _o_photos
                        visit["orphans"] = _orphans
                        _save(gh, visit_ref, visit, editor_name or "anon",
                              "orphan photo: uploaded to CDN")
                        st.rerun()
                    except Exception as _ophe:
                        st.error(f"❌ {_ophe}")

        else:  # URL manual
            _oph_url = st.text_input("URL", key="oph_url")
            if st.button("Adicionar", key="btn_oph_manual"):
                if _oph_url:
                    _o_photos.append({
                        "url":        _oph_url,
                        "caption_pt": _oph_cap,
                        "caption_en": "",
                        "provider":   "external",
                        "source":     "manual_url",
                    })
                    _orphans["photos"] = _o_photos
                    visit["orphans"] = _orphans
                    _save(gh, visit_ref, visit, editor_name or "anon",
                          "orphan photo: external URL added")
                    st.rerun()
                else:
                    st.warning("URL é obrigatória.")

with tab_publicar:
    st.markdown("### 🚀 Publicar Visita")
    st.caption(
        "Checklist de validação + publicação via Trator ou direta ao WordPress. "
        "Todos os ❌ devem ser resolvidos para habilitar."
    )

    # ══════════════════════════════════════════════════════════════
    # VALIDAÇÕES (13 checks)
    # ══════════════════════════════════════════════════════════════
    _checks: list[dict] = []

    _pub_schema = visit.get("schema_version", "")
    _pub_meta   = visit.get("metadata", {})
    if not isinstance(_pub_meta, dict):
        _pub_meta = {}
    _pub_days   = visit.get("days", [])
    _pub_orph   = visit.get("orphans", {})
    if not isinstance(_pub_orph, dict):
        _pub_orph = {}

    # 1. Schema
    if _pub_schema in SUPPORTED_SCHEMAS:
        _checks.append({
            "ok": True,
            "label": f"Schema `{_pub_schema}` suportado",
            "sev": "info",
        })
    elif _pub_schema in MIGRATABLE_OLDER:
        _checks.append({
            "ok": False,
            "label": f"Schema `{_pub_schema}` precisa migração → aba 📋 Visita",
            "sev": "error",
        })
    else:
        _checks.append({
            "ok": False,
            "label": f"Schema `{_pub_schema or '(vazio)'}` desconhecido",
            "sev": "warning",
        })

    # 2. Metadata obrigatórios
    for _mfield in ["date_start", "date_end", "title_pt", "title_en"]:
        _mval = _pub_meta.get(_mfield)
        if _mval:
            _checks.append({
                "ok": True,
                "label": f"`metadata.{_mfield}` = `{str(_mval)[:40]}`",
                "sev": "info",
            })
        else:
            _checks.append({
                "ok": False,
                "label": f"`metadata.{_mfield}` vazio",
                "sev": "error",
            })

    # 3. Tour ref
    _pub_tour = visit.get("tour_ref", "")
    if _pub_tour:
        _checks.append({
            "ok": True,
            "label": f"`tour_ref` = `{_pub_tour}`",
            "sev": "info",
        })
    else:
        _checks.append({
            "ok": False,
            "label": "`tour_ref` ausente",
            "sev": "warning",
        })

    # 4. Dias
    if _pub_days:
        _checks.append({
            "ok": True,
            "label": f"{len(_pub_days)} dia(s) cadastrado(s)",
            "sev": "info",
        })
    else:
        _checks.append({
            "ok": False,
            "label": "Nenhum dia cadastrado",
            "sev": "error",
        })

    # 5. Eventos
    _pub_ev_total = sum(len(d.get("events", [])) for d in _pub_days)
    if _pub_ev_total > 0:
        _checks.append({
            "ok": True,
            "label": f"{_pub_ev_total} evento(s)",
            "sev": "info",
        })
    else:
        _checks.append({
            "ok": False,
            "label": "Nenhum evento",
            "sev": "error",
        })

    # 6. VODs
    _pub_vod_total = 0
    _pub_vod_novid = []
    for _pd in _pub_days:
        for _pe in _pd.get("events", []):
            for _pv in _pe.get("vods", []):
                _pub_vod_total += 1
                if not _pv.get("video_id"):
                    _pub_vod_novid.append(_pv.get("vod_key", "?"))

    if _pub_vod_total > 0:
        _checks.append({
            "ok": True,
            "label": f"{_pub_vod_total} VOD(s)",
            "sev": "info",
        })
    else:
        _checks.append({
            "ok": False,
            "label": "Nenhum VOD cadastrado",
            "sev": "warning",
        })

    if _pub_vod_novid:
        _checks.append({
            "ok": False,
            "label": {
                "ok": False,
                "label": (
                    f"{len(_pub_vod_novid)} VOD(s) sem `video_id`: "
                    f"`{'`, `'.join(_pub_vod_novid[:5])}`"
                ),
                "sev": "error",
            },
        })

    # 7. Segments
    _pub_seg_total = 0
    _pub_seg_noend = []
    for _pd in _pub_days:
        for _pe in _pd.get("events", []):
            for _pv in _pe.get("vods", []):
                for _ps in _pv.get("segments", []):
                    _pub_seg_total += 1
                    if not _ps.get("timestamp_end"):
                        _pub_seg_noend.append(
                            _ps.get("segment_id", "?"))

    if _pub_seg_total > 0:
        _checks.append({
            "ok": True,
            "label": f"{_pub_seg_total} segment(s)",
            "sev": "info",
        })
        if _pub_seg_noend:
            _checks.append({
                "ok": False,
                "label": (
                    f"{len(_pub_seg_noend)} segment(s) sem "
                    f"`timestamp_end`"
                ),
                "sev": "warning",
            })
    else:
        _checks.append({
            "ok": False,
            "label": "Nenhum segment",
            "sev": "warning",
        })

    # 8. Harikatha sem katha_id
    _pub_hk_total = 0
    _pub_hk_missing = []
    for _pd in _pub_days:
        for _pe in _pd.get("events", []):
            for _pv in _pe.get("vods", []):
                for _ps in _pv.get("segments", []):
                    if _ps.get("type") == "harikatha":
                        _pub_hk_total += 1
                        if not _ps.get("katha_id"):
                            _pub_hk_missing.append(
                                _ps.get("segment_id", "?"))

    if _pub_hk_total > 0 and not _pub_hk_missing:
        _checks.append({
            "ok": True,
            "label": f"{_pub_hk_total} harikatha(s) com `katha_id` ✓",
            "sev": "info",
        })
    elif _pub_hk_missing:
        _checks.append({
            "ok": False,
            "label": (
                f"{len(_pub_hk_missing)} harikatha(s) sem `katha_id`: "
                f"`{'`, `'.join(_pub_hk_missing[:5])}`"
            ),
            "sev": "error",
        })

    # 9. Schema 6.2 — legacy kathas[]
    if _pub_schema == "6.2":
        _has_legacy = any(
            ev.get("kathas")
            for d in _pub_days
            for ev in d.get("events", [])
        )
        if _has_legacy:
            _checks.append({
                "ok": False,
                "label": "Schema 6.2 mas `kathas[]` residual em eventos (R-HK-4)",
                "sev": "error",
            })
        else:
            _checks.append({
                "ok": True,
                "label": "`kathas[]` removido (R-HK-4 ✓)",
                "sev": "info",
            })

    # 10. Órfãos
    _n_o_v = len(_pub_orph.get("vods", []))
    _n_o_p = len(_pub_orph.get("photos", []))
    if _n_o_v + _n_o_p == 0:
        _checks.append({
            "ok": True,
            "label": "Nenhum item órfão",
            "sev": "info",
        })
    else:
        _checks.append({
            "ok": False,
            "label": f"{_n_o_v} VOD(s) + {_n_o_p} foto(s) órfã(s) → aba 📦 Órfãos",
            "sev": "warning",
        })

    # 11. primary_event_key
    _no_primary = []
    for _pd in _pub_days:
        if not _pd.get("primary_event_key") and _pd.get("events"):
            _no_primary.append(_pd.get("day_key", "?"))
    if _no_primary:
        _checks.append({
            "ok": False,
            "label": f"{len(_no_primary)} dia(s) sem `primary_event_key`",
            "sev": "warning",
        })
    elif _pub_days:
        _checks.append({
            "ok": True,
            "label": "Todos os dias têm `primary_event_key`",
            "sev": "info",
        })

    # 12. Fotos
    _pub_photo_total = sum(
        len(e.get("photos", []))
        for d in _pub_days
        for e in d.get("events", [])
    )
    if _pub_photo_total > 0:
        _checks.append({
            "ok": True,
            "label": f"{_pub_photo_total} foto(s)",
            "sev": "info",
        })
    else:
        _checks.append({
            "ok": False,
            "label": "Nenhuma foto cadastrada",
            "sev": "warning",
        })

    # 13. Fotos com Gurudeva
    _pub_gurudeva = sum(
        1 for d in _pub_days
        for e in d.get("events", [])
        for p in e.get("photos", [])
        if p.get("with_gurudeva")
    )
    if _pub_gurudeva > 0:
        _checks.append({
            "ok": True,
            "label": f"{_pub_gurudeva} foto(s) marcada(s) `com Gurudeva`",
            "sev": "info",
        })
    else:
        _checks.append({
            "ok": False,
            "label": "Nenhuma foto marcada `com Gurudeva`",
            "sev": "warning",
        })

    # ══════════════════════════════════════════════════════════════
    # RENDER CHECKLIST
    # ══════════════════════════════════════════════════════════════
    _errors   = [c for c in _checks if not c["ok"] and c["sev"] == "error"]
    _warnings = [c for c in _checks if not c["ok"] and c["sev"] == "warning"]
    _passed   = [c for c in _checks if c["ok"]]

    _total_ck = len(_checks)
    _passed_n = len(_passed)
    _score    = int((_passed_n / _total_ck) * 100) if _total_ck else 0

    _sc1, _sc2, _sc3, _sc4 = st.columns(4)
    _sc1.metric("Score", f"{_score}%")
    _sc2.metric("✅ OK", _passed_n)
    _sc3.metric("❌ Erros", len(_errors))
    _sc4.metric("⚠️ Avisos", len(_warnings))

    st.progress(_score / 100, text=f"Validação: {_passed_n}/{_total_ck}")

    if _errors:
        st.markdown("#### ❌ Erros (bloqueantes)")
        for _c in _errors:
            st.error(_c["label"])

    if _warnings:
        st.markdown("#### ⚠️ Avisos")
        for _c in _warnings:
            st.warning(_c["label"])

    if _passed:
        with st.expander(f"✅ {len(_passed)} item(s) OK", expanded=False):
            for _c in _passed:
                st.success(_c["label"])

    # ══════════════════════════════════════════════════════════════
    # AÇÕES
    # ══════════════════════════════════════════════════════════════
    st.markdown("---")
    st.markdown("### 🎯 Ações")

    _can_publish = len(_errors) == 0
    _wp_id = visit.get("wp_id") or _pub_meta.get("wp_id")

    _pa1, _pa2, _pa3 = st.columns(3)

    # ── 1. GitHub ─────────────────────────────────────────────────
    with _pa1:
        st.markdown("**💾 GitHub**")
        if st.button("Salvar no GitHub", key="btn_save_gh_pub",
                      use_container_width=True):
            try:
                _save(gh, visit_ref, visit, editor_name or "anon",
                      f"publicar: save manual (score={_score}%)")
                st.success("✅ Salvo no GitHub!")
            except Exception as _ghe:
                st.error(f"❌ {_ghe}")

    # ── 2. Trator (mantido) ───────────────────────────────────────
    with _pa2:
        st.markdown("**🚜 Trator**")

        _trator_mode = st.radio(
            "Modo",
            ["Dry-run (preview)", "Publicar (real)"],
            key="trator_mode_pub",
            horizontal=True,
        )
        _is_dry = _trator_mode.startswith("Dry")

        if st.button(
            "🚜 Dry-run" if _is_dry else "🚜 Publicar via Trator",
            key="btn_trator_pub",
            disabled=not _can_publish and not _is_dry,
            type="primary" if _can_publish and not _is_dry else "secondary",
            use_container_width=True,
        ):
            if not _can_publish and not _is_dry:
                st.error("Resolva os erros bloqueantes antes de publicar.")
            else:
                with st.spinner(
                    "Executando Trator (dry-run)..." if _is_dry
                    else "Publicando via Trator..."
                ):
                    try:
                        result: TratorResult = run_trator(
                            visit,
                            dry_run=_is_dry,
                        )

                        if result.success:
                            st.success(
                                f"✅ {'Dry-run OK' if _is_dry else 'Publicado!'} "
                                f"— {result.summary}"
                            )
                            if not _is_dry:
                                # Log de publicação
                                from datetime import datetime, timezone
                                _pub_meta.setdefault("publish_log", []).append({
                                    "at":     datetime.now(timezone.utc).isoformat(),
                                    "action": "trator_publish",
                                    "by":     editor_name or "anon",
                                    "score":  _score,
                                    "mode":   "trator",
                                })
                                visit["metadata"] = _pub_meta
                                _save(gh, visit_ref, visit,
                                      editor_name or "anon",
                                      f"publicar: trator publish "
                                      f"(score={_score}%)")
                        else:
                            st.error(f"❌ Trator falhou: {result.error}")

                        # Detalhes
                        if hasattr(result, 'details') and result.details:
                            with st.expander("📋 Detalhes do Trator"):
                                st.json(result.details)

                    except Exception as _te:
                        st.error(f"❌ Erro no Trator: {_te}")

    # ── 3. Exportar ───────────────────────────────────────────────
    with _pa3:
        st.markdown("**📥 Exportar**")
        _json_str = json.dumps(visit, ensure_ascii=False, indent=2)
        st.download_button(
            "Baixar visit.json",
            data=_json_str,
            file_name=f"{visit_ref}_visit.json",
            mime="application/json",
            key="btn_export_json",
            use_container_width=True,
        )
        st.caption(f"{len(_json_str):,} bytes · schema {_pub_schema}")

    # ── 4. WP direto (opcional) ───────────────────────────────────
    st.markdown("---")
    with st.expander("🌐 Publicar diretamente no WordPress (avançado)", expanded=False):
        if _wp_id:
            st.caption(f"WP ID: `{_wp_id}`")
            st.warning(
                "⚠️ Isso envia o timeline diretamente ao WordPress, "
                "**sem** passar pelo Trator (sem indexação/estatísticas). "
                "Use apenas se souber o que está fazendo."
            )
            if st.button(
                "🌐 Patch WP Timeline",
                key="btn_pub_wp_direct",
                disabled=not _can_publish,
                type="secondary",
            ):
                try:
                    from api.wp_client import patch_visit_timeline
                    patch_visit_timeline(int(_wp_id), visit)
                    st.success("✅ Timeline atualizado no WordPress!")

                    from datetime import datetime, timezone
                    _pub_meta.setdefault("publish_log", []).append({
                        "at":     datetime.now(timezone.utc).isoformat(),
                        "action": "wp_patch_direct",
                        "by":     editor_name or "anon",
                        "wp_id":  int(_wp_id),
                        "score":  _score,
                    })
                    visit["metadata"] = _pub_meta
                    _save(gh, visit_ref, visit, editor_name or "anon",
                          f"publicar: WP patch direct "
                          f"(wp_id={_wp_id}, score={_score}%)")

                except ImportError:
                    st.error(
                        "❌ `wp_client.patch_visit_timeline` não disponível. "
                        "Implemente em `api/wp_client.py`."
                    )
                except Exception as _wpe:
                    st.error(f"❌ Erro WP: {_wpe}")
        else:
            st.info(
                "ℹ️ `wp_id` não configurado. Preencha na aba 📋 Visita → metadata "
                "para habilitar publicação direta."
            )

    # ── Resumo ────────────────────────────────────────────────────
    st.markdown("---")
    with st.expander("📝 Resumo da visita", expanded=False):
        _summary = {
            "visit_ref":     visit_ref,
            "schema":        _pub_schema,
            "tour_ref":      visit.get("tour_ref", ""),
            "title_pt":      _pub_meta.get("title_pt", ""),
            "title_en":      _pub_meta.get("title_en", ""),
            "date_start":    _pub_meta.get("date_start", ""),
            "date_end":      _pub_meta.get("date_end", ""),
            "days":          len(_pub_days),
            "events":        _pub_ev_total,
            "vods":          _pub_vod_total,
            "segments":      _pub_seg_total,
            "harikatha":     _pub_hk_total,
            "photos":        _pub_photo_total,
            "orphan_vods":   _n_o_v,
            "orphan_photos": _n_o_p,
            "score":         f"{_score}%",
            "errors":        len(_errors),
            "warnings":      len(_warnings),
        }
        st.json(_summary)

    # ── Histórico de publicação ─────────────────────────────────
    _pub_log = _pub_meta.get("publish_log", [])
    if _pub_log:
        with st.expander(
            f"📋 Histórico de publicação ({len(_pub_log)} entries)",
            expanded=False,
        ):
            for _entry in reversed(_pub_log[-20:]):
                _icon = "🚜" if "trator" in _entry.get("action", "") else "🌐"
                st.caption(
                    f"{_icon} **{_entry.get('at', '?')[:19]}** — "
                    f"`{_entry.get('action', '?')}` "
                    f"por **{_entry.get('by', '?')}** "
                    f"(score: {_entry.get('score', '?')}%)"
                )

