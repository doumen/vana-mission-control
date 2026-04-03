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

from api.github_client import GitHubClient
# Ensure repository root is on sys.path so we can import the `trator` package
REPO_ROOT = Path(__file__).resolve().parents[2]
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

from trator.vana_trator import run_trator, TratorResult

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
def _save(gh: GitHubClient, visit_ref: str, visit: dict, author: str, action: str):
    """Salva no GitHub e limpa cache."""
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


# Versões que são compatíveis e podem ser migradas para 6.1
MIGRATABLE_VERSIONS = {"3.1", "4.0", "5.0", "6.0"}

@st.cache_data(ttl=60)
def _load(visit_ref: str, wp_id: int | None = None) -> dict:
    gh = get_gh()
    data = gh.get_visit(visit_ref)

    if not data and wp_id:
        data = _load_from_wp(wp_id)

    # ── Migração de schema (SOMENTE modifica os dados; sem UI) -------
    if isinstance(data, dict):
        current = data.get("schema_version", "")
        if current != "6.1" and current in MIGRATABLE_VERSIONS:
            data["schema_version"] = "6.1"
            # sinaliza que foi migrado — a UI que chamar _load() fará o toast
            data["__migrated_from__"] = current

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

schema_atual = visit.get("schema_version", "") if isinstance(visit, dict) else ""
if schema_atual and schema_atual != "6.1" and schema_atual not in MIGRATABLE_VERSIONS:
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
        "orphans": {"vods": [], "photos": [], "sangha": [], "kathas": []},
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
tab_meta, tab_vods, tab_kathas, tab_galeria, tab_orfaos, tab_publicar = st.tabs([
    "📋 Visita",
    "🎬 VODs",
    "🙏 Kathas",
    "🖼️ Galeria",
    "👻 Órfãos",
    "🚀 Publicar",
])


# ══════════════════════════════════════════════════════════════════════
# TAB 1 — METADATA + DAYS
# ══════════════════════════════════════════════════════════════════════
with tab_meta:
    st.markdown("### Identificação")

    c1, c2 = st.columns(2)
    with c1:
        visit["title_pt"] = st.text_input(
            "Título PT", value=visit.get("title_pt", ""), key="v_title_pt"
        )
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
        meta["city_pt"]    = st.text_input("Cidade PT", value=meta.get("city_pt", ""), key="m_city_pt")
        meta["date_start"] = st.text_input("Data início (YYYY-MM-DD)", value=meta.get("date_start", ""), key="m_ds")
        meta["timezone"]   = st.text_input("Timezone", value=meta.get("timezone", "Asia/Kolkata"), key="m_tz")
    with m2:
        meta["city_en"]  = st.text_input("Cidade EN", value=meta.get("city_en", ""), key="m_city_en")
        meta["date_end"] = st.text_input("Data fim (YYYY-MM-DD)", value=meta.get("date_end", ""), key="m_de")
        meta["country"]  = st.text_input("País (ISO)", value=meta.get("country", "IN"), key="m_country")
    with m3:
        meta["status"] = st.selectbox(
            "Status", ["upcoming", "active", "completed"],
            index=["upcoming", "active", "completed"].index(meta.get("status", "upcoming")),
            key="m_status",
        )

    if st.button("💾 Salvar Metadata", key="save_meta"):
        visit["metadata"] = meta
        _save(gh, visit_ref, visit, editor_name or "anon", "metadata: atualizada")

    # ── DIAS ─────────────────────────────────────────────────────────
    st.divider()
    st.markdown("### Dias")

    days = visit.setdefault("days", [])

    # Adicionar dia
    with st.expander("➕ Adicionar dia"):
        ndk = st.text_input("day_key (YYYY-MM-DD)", key="new_day_key")
        nlp = st.text_input("label_pt (ex: 21 fev)", key="new_day_lp")
        nle = st.text_input("label_en (ex: Feb 21)", key="new_day_le")
        if st.button("Adicionar dia", key="btn_add_day"):
            if ndk:
                days.append({
                    "day_key":       ndk,
                    "label_pt":      nlp,
                    "label_en":      nle,
                    "primary_event": "",
                    "events":        [],
                })
                visit["days"] = days
                _save(gh, visit_ref, visit, editor_name or "anon", f"day {ndk}: adicionado")
                st.rerun()
            else:
                st.warning("day_key é obrigatório.")

    for di, day in enumerate(days):
        with st.expander(f"📅 {_day_label(day)}", expanded=False):

            dc1, dc2, dc3 = st.columns(3)
            with dc1:
                day["day_key"]  = st.text_input("day_key", value=day.get("day_key", ""), key=f"dk_{di}")
                day["label_pt"] = st.text_input("label_pt", value=day.get("label_pt", ""), key=f"dlp_{di}")
            with dc2:
                day["label_en"]      = st.text_input("label_en", value=day.get("label_en", ""), key=f"dle_{di}")
                day["primary_event"] = st.text_input("primary_event", value=day.get("primary_event", ""), key=f"dpe_{di}")
            with dc3:
                day["tithi"]         = st.text_input("tithi", value=day.get("tithi", ""), key=f"dt_{di}")
                day["tithi_name_pt"] = st.text_input("tithi_name_pt", value=day.get("tithi_name_pt", ""), key=f"dtnp_{di}")

            # ── Eventos do dia ──────────────────────────────────────
            st.markdown("**Eventos:**")
            events = day.setdefault("events", [])

            with st.expander("➕ Adicionar evento", expanded=False):
                nek  = st.text_input("event_key (YYYYMMDD-HHMM-slug)", key=f"nek_{di}")
                net  = st.selectbox(
                    "type", ["programa", "mangala", "arati", "darshan", "other"],
                    key=f"net_{di}"
                )
                netm = st.text_input("time (HH:MM)", key=f"netm_{di}")
                netp = st.text_input("title_pt", key=f"netp_{di}")
                nete = st.text_input("title_en", key=f"nete_{di}")
                if st.button("Adicionar evento", key=f"btn_aev_{di}"):
                    if nek:
                        events.append({
                            "event_key": nek,
                            "type":      net,
                            "title_pt":  netp,
                            "title_en":  nete,
                            "time":      netm,
                            "status":    "upcoming",
                            "location":  {},
                            "vods":      [],
                            "kathas":    [],
                            "photos":    [],
                            "sangha":    [],
                        })
                        day["events"] = events
                        _save(gh, visit_ref, visit, editor_name or "anon", f"event {nek}: adicionado")
                        st.rerun()
                    else:
                        st.warning("event_key é obrigatório.")

            for ei, ev in enumerate(events):
                with st.container(border=True):
                    ec1, ec2, ec3 = st.columns([3, 2, 1])
                    with ec1:
                        ev["event_key"] = st.text_input("event_key", value=ev.get("event_key", ""), key=f"evk_{di}_{ei}")
                        ev["title_pt"]  = st.text_input("title_pt",  value=ev.get("title_pt", ""),  key=f"evtp_{di}_{ei}")
                        ev["title_en"]  = st.text_input("title_en",  value=ev.get("title_en", ""),  key=f"evte_{di}_{ei}")
                    with ec2:
                        ev["type"]   = st.text_input("type",   value=ev.get("type", ""),   key=f"evt_{di}_{ei}")
                        ev["time"]   = st.text_input("time",   value=ev.get("time", ""),   key=f"evtm_{di}_{ei}")
                        ev["status"] = st.selectbox(
                            "status",
                            ["past", "active", "future", "live"],
                            index=["past", "active", "future", "live"].index(ev.get("status", "past")),
                            key=f"evst_{di}_{ei}",
                        )
                    with ec3:
                        # Localização rápida
                        loc = ev.setdefault("location", {})
                        loc["name"] = st.text_input("local", value=loc.get("name", ""), key=f"evloc_{di}_{ei}")
                        st.write("")
                        if st.button("🗑️", key=f"del_ev_{di}_{ei}", help="Remover evento"):
                            events.pop(ei)
                            day["events"] = events
                            _save(gh, visit_ref, visit, editor_name or "anon", f"event: removido")
                            st.rerun()

            st.write("")
            col_save, col_del = st.columns([4, 1])
            with col_save:
                if st.button(f"💾 Salvar dia {day.get('day_key', di)}", key=f"save_day_{di}"):
                    _save(gh, visit_ref, visit, editor_name or "anon", f"day {day.get('day_key')}: atualizado")
            with col_del:
                if st.button("🗑️ Remover dia", key=f"del_day_{di}", type="secondary"):
                    days.pop(di)
                    visit["days"] = days
                    _save(gh, visit_ref, visit, editor_name or "anon", f"day {day.get('day_key')}: removido")
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
            ev_opts = {_event_label(e): i for i, e in enumerate(events)}
            sel_ev_label = st.selectbox("Evento", list(ev_opts.keys()), key="vod_ev_sel")
            sel_ev = events[ev_opts[sel_ev_label]]
            vods   = sel_ev.setdefault("vods", [])

            st.markdown(
                f"**Evento:** `{sel_ev.get('event_key')}` · "
                f"{len(vods)} VOD(s)"
            )

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

                if st.button("Adicionar VOD", key="btn_add_vod"):
                    if nvk and nvid:
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
                                seg["katha_id"] = sc[4].number_input(
                                    "", min_value=0, value=seg.get("katha_id") or 0,
                                    key=f"skid_{vi}_{si}", label_visibility="collapsed"
                                ) or None
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
    st.markdown("### 🙏 Kathas por Evento")

    days = visit.get("days", [])
    if not days:
        st.info("Adicione dias na aba 📋 Visita primeiro.")
    else:
        kday_opts = {_day_label(d): i for i, d in enumerate(days)}
        ksel_day_label = st.selectbox("Dia", list(kday_opts.keys()), key="k_day_sel")
        ksel_day = days[kday_opts[ksel_day_label]]
        kevents  = ksel_day.get("events", [])

        if not kevents:
            st.info("Sem eventos neste dia.")
        else:
            kev_opts = {_event_label(e): i for i, e in enumerate(kevents)}
            ksel_ev_label = st.selectbox("Evento", list(kev_opts.keys()), key="k_ev_sel")
            ksel_ev = kevents[kev_opts[ksel_ev_label]]
            kathas  = ksel_ev.setdefault("kathas", [])

            # ── Adicionar Katha ───────────────────────────────────────
            with st.expander("➕ Adicionar Katha", expanded=len(kathas) == 0):
                kc1, kc2 = st.columns(2)
                with kc1:
                    nkid  = st.number_input("katha_id (int)", min_value=1, value=700, key="nkid")
                    nkkey = st.text_input("katha_key (katha-YYYYMMDD-slug)", key="nkkey")
                    nktp  = st.text_input("title_pt", key="nktp")
                with kc2:
                    nkte  = st.text_input("title_en", key="nkte")
                    nksc  = st.text_input("scripture (ex: SB 10.31)", key="nksc")
                    nklg  = st.selectbox("language", ["hi", "pt", "en", "sa"], key="nklg")

                if st.button("Adicionar Katha", key="btn_add_katha"):
                    if nkkey:
                        kathas.append({
                            "katha_id":  int(nkid),
                            "katha_key": nkkey,
                            "title_pt":  nktp,
                            "title_en":  nkte,
                            "scripture": nksc,
                            "language":  nklg,
                            "sources":   [],
                            "passages":  [],
                        })
                        ksel_ev["kathas"] = kathas
                        _save(gh, visit_ref, visit, editor_name or "anon", f"katha {nkkey}: adicionada")
                        st.rerun()
                    else:
                        st.warning("katha_key é obrigatório.")

            # ── Lista Kathas ──────────────────────────────────────────
            for ki, katha in enumerate(kathas):
                with st.expander(
                    f"🙏 [{katha.get('katha_id')}] {katha.get('title_pt', katha.get('katha_key', '?'))}",
                    expanded=False,
                ):
                    # Campos katha
                    kf1, kf2 = st.columns(2)
                    with kf1:
                        katha["katha_id"]  = st.number_input("katha_id", value=katha.get("katha_id") or 0, key=f"kid_{ki}")
                        katha["katha_key"] = st.text_input("katha_key", value=katha.get("katha_key", ""), key=f"kkey_{ki}")
                        katha["title_pt"]  = st.text_input("title_pt",  value=katha.get("title_pt", ""),  key=f"ktp_{ki}")
                    with kf2:
                        katha["title_en"]  = st.text_input("title_en",  value=katha.get("title_en", ""),  key=f"kte_{ki}")
                        katha["scripture"] = st.text_input("scripture", value=katha.get("scripture", ""), key=f"ksc_{ki}")
                        katha["language"]  = st.selectbox(
                            "language", ["hi", "pt", "en", "sa"],
                            index=["hi", "pt", "en", "sa"].index(katha.get("language", "hi")),
                            key=f"klg_{ki}",
                        )

                    # Sources
                    st.markdown("**Sources (vod → segment):**")
                    sources = katha.setdefault("sources", [])
                    for sri, src in enumerate(sources):
                        sr1, sr2, sr3 = st.columns([3, 3, 1])
                        src["vod_key"]    = sr1.text_input("vod_key",    value=src.get("vod_key", ""),    key=f"svk_{ki}_{sri}")
                        src["segment_id"] = sr2.text_input("segment_id", value=src.get("segment_id", ""), key=f"ssid_{ki}_{sri}")
                        if sr3.button("🗑️", key=f"del_src_{ki}_{sri}"):
                            sources.pop(sri)
                            katha["sources"] = sources
                            _save(gh, visit_ref, visit, editor_name or "anon", "source: removido")
                            st.rerun()

                    if st.button("➕ Source", key=f"add_src_{ki}"):
                        sources.append({"vod_key": "", "segment_id": "", "timestamp_start": 0, "timestamp_end": 0, "vod_part": 1})
                        katha["sources"] = sources
                        st.rerun()

                    # ── Passages ──────────────────────────────────────
                    st.divider()
                    st.markdown("**Passages:**")
                    passages = katha.setdefault("passages", [])

                    with st.expander("➕ Adicionar Passage", expanded=len(passages) == 0):
                        pp1, pp2 = st.columns(2)
                        with pp1:
                            npid  = st.text_input("passage_id (hkp-YYYYMMDD-NNN)", key=f"npid_{ki}")
                            nptp  = st.text_input("title_pt", key=f"nptp_{ki}")
                            npte  = st.text_input("title_en", key=f"npte_{ki}")
                        with pp2:
                            npkq  = st.text_input("key_quote (Sanskrit)", key=f"npkq_{ki}")
                            npvk  = st.text_input("source_ref.vod_key", key=f"npvk_{ki}")
                            npsi  = st.text_input("source_ref.segment_id", key=f"npsi_{ki}")

                        pp3, pp4, pp5 = st.columns(3)
                        npst = pp3.number_input("timestamp_start", min_value=0, value=0, key=f"npst_{ki}")
                        npet = pp4.number_input("timestamp_end",   min_value=0, value=0, key=f"npet_{ki}")
                        pp5.write("")

                        nptchpt = st.text_area("teaching_pt", key=f"nptchpt_{ki}", height=80)
                        nptchpe = st.text_area("teaching_en", key=f"nptchpe_{ki}", height=80)

                        if st.button("Adicionar Passage", key=f"btn_ap_{ki}"):
                            if npid:
                                passages.append({
                                    "passage_id":  npid,
                                    "passage_key": npid,
                                    "title_pt":    nptp,
                                    "title_en":    npte,
                                    "teaching_pt": nptchpt,
                                    "teaching_en": nptchpe,
                                    "key_quote":   npkq,
                                    "source_ref": {
                                        "vod_key":         npvk,
                                        "segment_id":      npsi,
                                        "timestamp_start": int(npst),
                                        "timestamp_end":   int(npet),
                                    },
                                })
                                katha["passages"] = passages
                                _save(gh, visit_ref, visit, editor_name or "anon", f"passage {npid}: adicionada")
                                st.rerun()
                            else:
                                st.warning("passage_id é obrigatório.")

                    # Lista passages
                    for pi, passage in enumerate(passages):
                        with st.container(border=True):
                            pid_label = passage.get("passage_id", f"passage[{pi}]")
                            pcc1, pcc2 = st.columns([5, 1])
                            pcc1.markdown(
                                f"**`{pid_label}`** · "
                                f"_{passage.get('title_pt', '')}_"
                            )
                            if pcc2.button("🗑️", key=f"del_p_{ki}_{pi}"):
                                passages.pop(pi)
                                katha["passages"] = passages
                                _save(gh, visit_ref, visit, editor_name or "anon", f"passage: removida")
                                st.rerun()

                            pf1, pf2 = st.columns(2)
                            with pf1:
                                passage["passage_id"] = st.text_input(
                                    "passage_id", value=passage.get("passage_id", ""), key=f"pid_{ki}_{pi}"
                                )
                                passage["title_pt"] = st.text_input(
                                    "title_pt", value=passage.get("title_pt", ""), key=f"ptp_{ki}_{pi}"
                                )
                                passage["title_en"] = st.text_input(
                                    "title_en", value=passage.get("title_en", ""), key=f"pte_{ki}_{pi}"
                                )
                                passage["key_quote"] = st.text_input(
                                    "key_quote", value=passage.get("key_quote", ""), key=f"pkq_{ki}_{pi}"
                                )
                            with pf2:
                                ref = passage.setdefault("source_ref", {})
                                ref["vod_key"]    = st.text_input(
                                    "vod_key", value=ref.get("vod_key", ""), key=f"pvk_{ki}_{pi}"
                                )
                                ref["segment_id"] = st.text_input(
                                    "segment_id", value=ref.get("segment_id", ""), key=f"psid_{ki}_{pi}"
                                )
                                ts_col, te_col = st.columns(2)
                                ref["timestamp_start"] = ts_col.number_input(
                                    "start(s)", min_value=0, value=ref.get("timestamp_start") or 0, key=f"pst_{ki}_{pi}"
                                )
                                ref["timestamp_end"] = te_col.number_input(
                                    "end(s)", min_value=0, value=ref.get("timestamp_end") or 0, key=f"pet_{ki}_{pi}"
                                )
                                # Link seek rápido
                                if ref.get("vod_key") and ref.get("timestamp_start") is not None:
                                    vod_idx = visit.get("index", {}).get("vods", {}).get(ref["vod_key"], {})
                                    vid_id  = vod_idx.get("video_id")
                                    if vid_id:
                                        ts_seek = ref["timestamp_start"]
                                        yt_url  = f"https://youtu.be/{vid_id}?t={ts_seek}"
                                        st.link_button("▶ Verificar no YouTube", yt_url)

                            passage["teaching_pt"] = st.text_area(
                                "teaching_pt", value=passage.get("teaching_pt", ""),
                                height=80, key=f"ptchpt_{ki}_{pi}"
                            )
                            passage["teaching_en"] = st.text_area(
                                "teaching_en", value=passage.get("teaching_en", ""),
                                height=80, key=f"ptchpe_{ki}_{pi}"
                            )

                    st.write("")
                    col_sk, col_dk = st.columns([4, 1])
                    with col_sk:
                        if st.button(f"💾 Salvar Katha `{katha.get('katha_key', ki)}`", key=f"save_k_{ki}"):
                            _save(gh, visit_ref, visit, editor_name or "anon", f"katha {katha.get('katha_key')}: atualizada")
                    with col_dk:
                        if st.button("🗑️ Remover", key=f"del_k_{ki}", type="secondary"):
                            kathas.pop(ki)
                            ksel_ev["kathas"] = kathas
                            _save(gh, visit_ref, visit, editor_name or "anon", "katha: removida")
                            st.rerun()


# ══════════════════════════════════════════════════════════════════════
# TAB 4 — GALERIA (photos + sangha)
# ══════════════════════════════════════════════════════════════════════
with tab_galeria:
    st.markdown("### 🖼️ Fotos e Sangha")

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

            with st.expander("➕ Adicionar Photo"):
                ph1, ph2 = st.columns(2)
                with ph1:
                    nphk  = st.text_input("photo_key (ph-YYYYMMDD-NNN)", key="nphk")
                    nphfu = st.text_input("full_url", key="nphfu")
                with ph2:
                    nphth = st.text_input("thumb_url", key="nphth")
                    nphcp = st.text_input("caption_pt", key="nphcp")
                    nphce = st.text_input("caption_en", key="nphce")
                    npha  = st.text_input("author", key="npha")

                if st.button("Adicionar Photo", key="btn_addph"):
                    if nphk:
                        photos.append({
                            "photo_key":  nphk,
                            "thumb_url":  nphth,
                            "full_url":   nphfu,
                            "caption_pt": nphcp,
                            "caption_en": nphce,
                            "author":     npha,
                        })
                        gsel_ev["photos"] = photos
                        _save(gh, visit_ref, visit, editor_name or "anon", f"photo {nphk}: adicionada")
                        st.rerun()

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
                                photos.pop(phi)
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
    st.markdown("### 👻 Mídia Órfã (sem event_key)")
    st.caption("Conteúdo sem evento definido. Visível via Modal para qualquer devoto.")

    orphans = visit.setdefault("orphans", {"vods": [], "photos": [], "sangha": [], "kathas": []})

    # VODs Órfãos
    st.markdown("**VODs:**")
    with st.expander("➕ Adicionar VOD órfão"):
        ov1, ov2 = st.columns(2)
        with ov1:
            novk  = st.text_input("vod_key", key="novk")
            novid = st.text_input("video_id", key="novid")
            novp  = st.selectbox("provider", ["youtube", "facebook", "drive"], key="novp")
        with ov2:
            novtp = st.text_input("title_pt", key="novtp")
            novte = st.text_input("title_en", key="novte")

        if st.button("Adicionar VOD órfão", key="btn_add_ov"):
            if novk:
                novk_clean = _normalize_vod_key(novk)
                if novk_clean != novk:
                    st.info(f"🔧 vod_key normalizado: `{novk}` → `{novk_clean}`")
                orphans["vods"].append({
                    "vod_key":    novk_clean,
                    "provider":   novp,
                    "video_id":   novid,
                    "url":        None,
                    "thumb_url":  f"https://img.youtube.com/vi/{novid}/maxresdefault.jpg" if novp == "youtube" else "",
                    "duration_s": None,
                    "title_pt":   novtp,
                    "title_en":   novte,
                    "segments":   [],
                })
                visit["orphans"] = orphans
                _save(gh, visit_ref, visit, editor_name or "anon", f"orphan vod {novk_clean}: adicionado")
                st.rerun()

    for ovi, ov in enumerate(orphans.get("vods", [])):
        with st.container(border=True):
            oc1, oc2 = st.columns([5, 1])
            oc1.markdown(
                f"**`{ov.get('vod_key', '?')}`** · "
                f"{ov.get('provider', '')} · `{ov.get('video_id', '')}`"
            )
            if oc2.button("🗑️", key=f"del_ov_{ovi}"):
                orphans["vods"].pop(ovi)
                visit["orphans"] = orphans
                _save(gh, visit_ref, visit, editor_name or "anon", "orphan vod: removido")
                st.rerun()

    # Fotos Órfãs
    st.divider()
    st.markdown("**Photos:**")
    for opi, op in enumerate(orphans.get("photos", [])):
        with st.container(border=True):
            opo1, opo2 = st.columns([5, 1])
            if op.get("thumb_url"):
                opo1.image(op["thumb_url"], width=100)
            opo1.caption(op.get("caption_pt", op.get("photo_key", "")))
            if opo2.button("🗑️", key=f"del_op_{opi}"):
                orphans["photos"].pop(opi)
                visit["orphans"] = orphans
                _save(gh, visit_ref, visit, editor_name or "anon", "orphan photo: removida")
                st.rerun()

    if st.button("💾 Salvar Órfãos", key="save_orphans"):
        _save(gh, visit_ref, visit, editor_name or "anon", "orphans: atualizados")


# ══════════════════════════════════════════════════════════════════════
# TAB 6 — PUBLICAR (Trator)
# ══════════════════════════════════════════════════════════════════════
with tab_publicar:
    st.markdown("### 🚀 Validar e Publicar")

    col_wp, col_sc = st.columns(2)
    with col_wp:
        wp_url = st.text_input(
            "WP URL",
            value=st.secrets.get("vana", {}).get("api_base", ""),
            key="pub_wp_url",
        )
    with col_sc:
        wp_secret = st.text_input(
            "WP Secret",
            value=st.secrets.get("vana", {}).get("ingest_secret", ""),
            type="password",
            key="pub_secret",
        )

    tour_k = st.text_input(
        "tour_key",
        value=tour_key or "tour:india-2026",
        key="pub_tour_key",
    )

    dry_run = st.toggle("🧪 Dry Run (valida sem publicar)", value=True, key="pub_dry")

    st.divider()
    col_val, col_pub = st.columns(2)

    with col_val:
        btn_validar = st.button("🔍 Validar Schema", type="secondary", use_container_width=True, key="btn_validar")

    with col_pub:
        btn_publicar = st.button(
            "🧪 Dry Run" if dry_run else "🚀 Publicar no WordPress",
            type="primary",
            use_container_width=True,
            key="btn_publicar",
            disabled=not editor_name,
        )

    if not editor_name:
        st.caption("⚠️ Digite seu nome na sidebar para publicar.")

    if btn_validar or btn_publicar:
        is_dry = bool(dry_run) or bool(btn_validar)

        with st.spinner("⚙️ Processando..."):
            result = run_trator(
                visit     = visit,
                wp_url    = wp_url    if (btn_publicar and not dry_run) else None,
                wp_secret = wp_secret if (btn_publicar and not dry_run) else None,
                tour_key  = tour_k,
                dry_run   = is_dry,
            )

        _render_trator_result(result, dry=is_dry)

        # Se publicou com sucesso e havia migração pendente, informar
        if result.success and not is_dry and migrated_from:
            st.info("💾 Schema 6.1 persistido no WordPress via publicação.")

        # Se o Trator produziu processed, salva de volta ao GitHub
        if result.success and not is_dry and result.processed:
            _save(
                gh, visit_ref, result.processed,
                editor_name, "trator: index + stats atualizados"
            )

    # ── Preview JSON ──────────────────────────────────────────────────
    st.divider()
    with st.expander("🔍 Preview JSON atual (sem index)"):
        preview = {
            k: v for k, v in visit.items()
            if k not in ("index", "stats")
        }
        st.json(preview, expanded=False)

    with st.expander("📊 Stats (último index gerado)"):
        st.json(visit.get("stats", {}))
