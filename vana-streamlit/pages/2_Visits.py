# pages/2_Visits.py
# -*- coding: utf-8 -*-
"""
📅 Visits — Gerenciador de Visitas (schema 3.1)
Integra o ingest HMAC via /vana/v1/ingest-visit
"""
import json
from datetime import datetime, timezone

import streamlit as st
from pathlib import Path
import sys

# When running `streamlit run` the current working directory is `vana-streamlit`.
# Ensure the repository root is on sys.path so we can import the `trator` package.
REPO_ROOT = Path(__file__).resolve().parents[2]
if str(REPO_ROOT) not in sys.path:
    sys.path.insert(0, str(REPO_ROOT))

# ── Guard ─────────────────────────────────────────────────────────────────
if not st.session_state.get("authenticated"):
    st.warning("🔒 Faça login na página principal.")
    st.stop()

    # UI language selector (pt / en)
    # Stored in session state and passed to data-fetch helpers so labels
    # use `title_pt` / `title_en` when available.
ui_lang = st.selectbox("Idioma / Language:", ["pt", "en"], index=0, key="ui_lang")

from api.hmac_client import (
    list_visits, get_visit, delete_visit, list_tours,
)
from trator.vana_trator import run_trator
from api.wp_client import get_visit_timeline
from components.days_editor import render_days_editor

# ══════════════════════════════════════════════════════════════════════════
# HELPERS
# ══════════════════════════════════════════════════════════════════════════

def _unwrap(response) -> list:
    if isinstance(response, list):
        return response
    if isinstance(response, dict):
        data = response.get("data", response)
        if isinstance(data, dict):
            return data.get("items", [])
        if isinstance(data, list):
            return data
    return []


def _clean(obj):
    if isinstance(obj, dict):
        return {k: _clean(v) for k, v in obj.items() if not k.startswith("_")}
    if isinstance(obj, list):
        return [_clean(i) for i in obj]
    return obj


def build_envelope(payload: dict, parent_tour_key: str) -> dict:
    visit_id = str(payload.get("visit_id", "")).strip()
    if not visit_id:
        raise ValueError("Campo 'visit_id' ausente no JSON.")
    origin_key = f"visit:{visit_id}" if not visit_id.startswith("visit:") else visit_id
    if not parent_tour_key.startswith("tour:"):
        parent_tour_key = f"tour:{parent_tour_key}"
    data = _clean(payload)
    data["schema_version"] = "3.1"
    data["updated_at"] = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    title = payload.get("title_pt") or f"Visita: {visit_id}"
    slug = visit_id.replace(":", "-")
    return {
        "kind":              "visit",
        "origin_key":        origin_key,
        "parent_origin_key": parent_tour_key,
        "title":             title,
        "slug_suggestion":   slug,
        "data":              data,
    }


VALID_STATUSES = {"done", "live", "upcoming", ""}


def validate_payload(payload: dict) -> list:
    errors = []
    for f in ["visit_id", "title_pt", "timezone", "days"]:
        if not payload.get(f):
            errors.append(f"Campo obrigatório ausente: `{f}`")
    days = payload.get("days", [])
    if not isinstance(days, list) or len(days) == 0:
        errors.append("`days` deve ser uma lista não-vazia.")
        return errors
    if len(days) > 400:
        errors.append(f"`days` excede 400 itens (tem {len(days)}).")
    for i, day in enumerate(days):
        lbl  = day.get("date_local") or f"days[{i}]"
        hero = day.get("hero")
        if not day.get("date_local"):
            errors.append(f"{lbl}: `date_local` ausente.")
        if not day.get("label_pt"):
            errors.append(f"{lbl}: `label_pt` ausente.")
        if not hero:
            errors.append(f"{lbl}: `hero` ausente.")
        else:
            if not hero.get("title_pt"):
                errors.append(f"{lbl} › hero: `title_pt` ausente.")
            if not any(hero.get(k) for k in
                       ["youtube_url", "instagram_url", "facebook_url", "drive_url"]):
                errors.append(f"{lbl} › hero: nenhuma fonte de mídia.")
        for j, s in enumerate(day.get("schedule", [])):
            if not s.get("time_local"):
                errors.append(f"{lbl} › schedule[{j}]: `time_local` ausente.")
            if not s.get("title_pt"):
                errors.append(f"{lbl} › schedule[{j}]: `title_pt` ausente.")
            if s.get("status", "") not in VALID_STATUSES:
                errors.append(f"{lbl} › schedule[{j}]: status inválido `{s.get('status')}`.")
    return errors


@st.cache_data(ttl=60, show_spinner=False)
def _fetch_visits() -> list:
    try:
        from api.wp_client import list_visits_rest
        return list_visits_rest(per_page=100)
    except Exception as e1:
        try:
            from api.hmac_client import list_visits
            raw = list_visits(per_page=100)
            data = raw.get("data", raw)
            return data.get("items", []) if isinstance(data, dict) else data
        except Exception as e2:
            st.error(f"❌ Erro ao carregar visitas: REST={e1} | HMAC={e2}")
            return []


@st.cache_data(ttl=120, show_spinner=False)
def _fetch_tours(lang: str = "pt") -> dict:
    FALLBACK_PT = {
        "Tour Espiritual Índia 2026  [tour:india-2026]":                       "tour:india-2026",
        "South America Summer Tour 2025/2026  [tour:south-america-2025-2026]": "tour:south-america-2025-2026",
        "Holand 2026  [tour:holand-2026]":                                     "tour:holand-2026",
    }
    FALLBACK_EN = {
        "Spiritual Tour India 2026  [tour:india-2026]":                        "tour:india-2026",
        "South America Summer Tour 2025/2026  [tour:south-america-2025-2026]": "tour:south-america-2025-2026",
        "Holland 2026  [tour:holand-2026]":                                   "tour:holand-2026",
    }
    try:
        items = _unwrap(list_tours(per_page=100))
        if not items:
            return FALLBACK_PT if lang == "pt" else FALLBACK_EN
        result = {}
        for t in items:
            ok = t.get("origin_key", "")
            ttl_pt = t.get("title_pt") or t.get("title") or ok or f"Tour {t.get('id','?')}"
            ttl_en = t.get("title_en") or t.get("title") or ok or f"Tour {t.get('id','?')}"
            display = ttl_pt if lang == "pt" else ttl_en
            if ok:
                result[f"{display}  [{ok}]"] = ok
        return result if result else (FALLBACK_PT if lang == "pt" else FALLBACK_EN)
    except Exception:
        return FALLBACK_PT if lang == "pt" else FALLBACK_EN


def _day_rows(days: list) -> list:
    return [
        {
            "Data":     d.get("date_local", "—"),
            "Label":    d.get("label_pt", "—"),
            "Hero":     "✅" if d.get("hero") else "❌",
            "VODs":     len(d.get("vods", [])),
            "Agenda":   len(d.get("schedule", [])),
            "Fotos":    len(d.get("photos", [])),
            "Momentos": len(d.get("sangha_moments", [])),
        }
        for d in days
    ]


# ══════════════════════════════════════════════════════════════════════════
# LAYOUT
# ══════════════════════════════════════════════════════════════════════════
st.title("📅 Visits")
st.caption("Gerencie as visitas de Srila Vana Maharaj — schema 3.1 · HMAC auth")

tab_list, tab_ingest, tab_edit, tab_days = st.tabs([
    "📋 Listar",
    "🚀 Ingerir Visita",
    "✏️ Editar / Baixar JSON",
    "📅 Editar Dias",
])


# ══════════════════════════════════════════════════════════════════════════
# TAB 1 — LISTAR
# ══════════════════════════════════════════════════════════════════════════
with tab_list:

    col_refresh, col_filter = st.columns([1, 3])

    with col_refresh:
        if st.button("🔄 Atualizar", key="refresh_visits"):
            st.cache_data.clear()
            st.rerun()

    visits = _fetch_visits()

    # ── Monta opções de filtro de tour ────────────────────────────────────
    # Coleta todos os tour_keys únicos presentes nas visitas
    tour_keys_found: dict[str, str] = {}   # label → tour_key
    for v in visits:
        tk = v.get("tour_key", "") or ""
        if tk and tk != "—":
            # tenta montar label legível a partir de _fetch_tours
            tours_map_ref = _fetch_tours(ui_lang)
            label = next(
                (lbl for lbl, val in tours_map_ref.items() if val == tk),
                tk,   # fallback: mostra o próprio key
            )
            tour_keys_found[label] = tk

    # Opções do selectbox: "Todas", "Sem tour pai", depois cada tour
    FILTER_ALL      = "🌐 Todas"
    FILTER_ORPHAN   = "🔗 Sem tour pai"
    filter_options  = [FILTER_ALL, FILTER_ORPHAN] + sorted(tour_keys_found.keys())

    with col_filter:
        selected_filter = st.selectbox(
            "Filtrar por tour:",
            filter_options,
            key="visit_filter_tour",
        )

    # ── Aplica filtro ─────────────────────────────────────────────────────
    if selected_filter == FILTER_ALL:
        visits_filtered = visits
    elif selected_filter == FILTER_ORPHAN:
        visits_filtered = [
            v for v in visits
            if not v.get("tour_key") or v.get("tour_key") in ("—", "", None)
        ]
    else:
        target_key = tour_keys_found[selected_filter]
        visits_filtered = [v for v in visits if v.get("tour_key") == target_key]

    # ── Métricas rápidas ──────────────────────────────────────────────────
    if not visits:
        st.info("Nenhuma visita encontrada.")
    else:
        m1, m2, m3 = st.columns(3)
        m1.metric("Total", len(visits))
        m2.metric(
            "Com tour pai",
            sum(1 for v in visits if v.get("tour_key") and v.get("tour_key") not in ("—", "", None))
        )
        m3.metric(
            "Sem tour pai",
            sum(1 for v in visits if not v.get("tour_key") or v.get("tour_key") in ("—", "", None))
        )

        st.caption(f"Exibindo **{len(visits_filtered)}** visita(s) · filtro: *{selected_filter}*")
        st.divider()

        if not visits_filtered:
            st.info("Nenhuma visita para este filtro.")
        else:
            STATUS_ICON = {
                "publish": "🟢", "draft": "🟡",
                "pending": "🟠", "trash": "🔴",
            }

            for v in visits_filtered:
                vid    = v.get("id")
                title  = v.get("title", f"ID {vid}")
                status = v.get("status", "—")
                slug   = v.get("slug", "—")
                ok     = v.get("origin_key", "—")
                pk     = v.get("tour_key", "—")
                link   = v.get("permalink", "")
                sv     = v.get("schema_ver", "—")
                icon   = STATUS_ICON.get(status, "⚪")

                with st.expander(f"{icon} [{vid}]  {title}"):
                    c1, c2, c3 = st.columns([2, 2, 1])
                    c1.markdown(f"**Slug:** `{slug}`")
                    c1.markdown(f"**Status:** `{status}`")
                    c1.markdown(f"**Schema:** `{sv}`")
                    c2.markdown(f"**origin_key:** `{ok}`")
                    c2.markdown(f"**tour_key:** `{pk}`")
                    c2.markdown(f"**start_date:** `{v.get('start_date','—')}`")
                    if link:
                        c3.link_button("🌐 Ver online", link)

                    if st.button("📂 Carregar dias", key=f"load_days_{vid}"):
                        with st.spinner("Carregando..."):
                            try:
                                tl   = get_visit_timeline(int(vid))
                                days = tl.get("days", [])
                                if days:
                                    st.dataframe(_day_rows(days),
                                                 use_container_width=True,
                                                 hide_index=True)
                                    st.caption(
                                        f"schema: {tl.get('schema_version')} "
                                        f"| updated: {tl.get('updated_at')}"
                                    )
                                else:
                                    st.info("Sem dias registrados nesta visita.")
                            except Exception as e:
                                st.warning(f"Não foi possível carregar dias: {e}")

                    st.divider()
                    if st.button("🗑️ Mover para lixeira",
                                 key=f"trash_{vid}", type="secondary"):
                        try:
                            delete_visit(int(vid))
                            st.success("Movido para lixeira.")
                            st.cache_data.clear()
                            st.rerun()
                        except Exception as e:
                            st.error(f"Erro: {e}")


# ══════════════════════════════════════════════════════════════════════════
# TAB 2 — INGERIR VISITA
# ══════════════════════════════════════════════════════════════════════════
with tab_ingest:
    st.subheader("🚀 Ingerir Visita  ·  schema 3.1  ·  HMAC")

    st.markdown("#### 1 · Tour pai")
    tours_map  = _fetch_tours(ui_lang)
    tour_label = st.selectbox("Selecionar tour:", list(tours_map.keys()), key="ingest_tour_sel")
    tour_key   = tours_map[tour_label]
    st.caption(f"→ `parent_origin_key` = **`{tour_key}`**")

    st.markdown("#### 2 · JSON da visita")
    method = st.radio("Método:", ["📋 Colar JSON", "📁 Upload .json"],
                      horizontal=True, key="ingest_method")

    raw_json = None
    if method == "📋 Colar JSON":
        raw_json = st.text_area(
            "Cole o JSON:", height=260,
            placeholder='{"visit_id": "vrindavan-2026-03", "title_pt": "...", "timezone": "Asia/Kolkata", "days": []}',
            key="ingest_json_text",
        )
    else:
        up = st.file_uploader("Selecione .json:", type=["json"], key="ingest_file")
        if up:
            raw_json = up.read().decode("utf-8")
            st.success(f"✅ `{up.name}` carregado")

    if raw_json and raw_json.strip():
        try:
            payload = json.loads(raw_json)
        except json.JSONDecodeError as e:
            st.error(f"❌ JSON inválido: {e}")
            payload = None

        if payload:
            st.markdown("#### 3 · Preview")
            p1, p2 = st.columns(2)
            p1.markdown(f"**visit_id:** `{payload.get('visit_id','—')}`")
            p1.markdown(f"**title_pt:** {payload.get('title_pt','—')}")
            p1.markdown(f"**timezone:** `{payload.get('timezone','—')}`")
            p2.markdown(f"**dias:** `{len(payload.get('days',[]))}`")
            p2.markdown(f"**cover_url:** {'✅' if payload.get('cover_url') else '❌ vazio'}")
            p2.markdown(f"**tour pai:** `{tour_key}`")
            if payload.get("days"):
                st.dataframe(_day_rows(payload["days"]), use_container_width=True, hide_index=True)

            st.markdown("#### 4 · Validação")
            errors = validate_payload(payload)
            if errors:
                st.error(f"**{len(errors)} erro(s):**")
                for e in errors:
                    st.markdown(f"- {e}")
                can_send = st.checkbox("⚠️ Ignorar erros e enviar mesmo assim", key="skip_val")
            else:
                st.success("✅ Schema válido")
                can_send = True

            if st.checkbox("🔍 Ver envelope completo", key="show_env"):
                try:
                    ep = build_envelope(payload, tour_key)
                    ep_show = dict(ep)
                    ep_show["data"] = {
                        k: (f"[{len(v)} dias]" if k == "days" else v)
                        for k, v in ep["data"].items()
                    }
                    st.json(ep_show)
                except Exception as e:
                    st.warning(str(e))

            st.divider()
            st.markdown("#### 5 · Enviar")
            col_btn, col_tip = st.columns([2, 3])
            send = col_btn.button("🚀 Ingerir visita", type="primary",
                                  disabled=not can_send, key="ingest_send")
            col_tip.caption("Idempotente — hash idêntico → `noop` sem writes.")

            if send:
                try:
                    with st.spinner("Processando com Trator..."):
                        result = run_trator(
                            visit      = payload,
                            wp_url     = st.secrets["vana"]["api_base"],
                            wp_secret  = st.secrets["vana"]["ingest_secret"],
                            tour_key   = tour_key,
                            dry_run    = False,
                        )

                    if result.success:
                        action = result.wp_action or "?"
                        LABELS = {
                            "created": ("✅", "success", "Visita criada com sucesso!"),
                            "updated": ("🔄", "success", "Visita atualizada com sucesso!"),
                            "noop":    ("💤", "info",    "Sem mudanças — hash idêntico."),
                        }
                        ico, lvl, msg = LABELS.get(action, ("ℹ️", "info", ""))
                        getattr(st, lvl)(f"{ico} {msg}")

                        if action != "noop":
                            m1, m2, m3 = st.columns(3)
                            m1.metric("visit_id WP", result.wp_id or "—")
                            m2.metric("Ação", action)
                            m3.metric("tour_id", "—")
                            if result.wp_url:
                                st.link_button("🌐 Ver visita online", result.wp_url)
                            st.cache_data.clear()
                    else:
                        for e in result.errors:
                            st.error(f"[{e.code}] {e.path} — {e.message}")
                        if result.processed:
                            with st.expander("🔍 Visit processado"):
                                st.json(result.processed)

                except Exception as e:
                    st.error(f"❌ Erro: {e}")


# ══════════════════════════════════════════════════════════════════════════
# TAB 3 — EDITAR / BAIXAR JSON
# ══════════════════════════════════════════════════════════════════════════
with tab_edit:
    st.subheader("✏️ Editar / Baixar JSON de visita existente")

    visits_edit = _fetch_visits()

    if not visits_edit:
        st.info("Nenhuma visita disponível.")
    else:
        options_edit = {
            f"[{v.get('id')}]  {v.get('title', '—')}  ({v.get('slug','')})": v.get("id")
            for v in visits_edit
        }
        selected_label = st.selectbox("Selecionar visita:", list(options_edit.keys()), key="edit_sel")
        selected_id    = options_edit[selected_label]

        if st.button("📥 Carregar JSON", key="load_btn", type="secondary"):
            with st.spinner("Carregando..."):
                try:
                    tl = get_visit_timeline(int(selected_id))
                    v_data = get_visit(int(selected_id))
                except Exception as e:
                    st.error(f"❌ {e}")
                    tl     = {}
                    v_data = {}

            meta       = v_data.get("meta", {}) if v_data else {}
            origin_key = meta.get("_vana_origin_key", "")
            vid_str    = origin_key.replace("visit:", "") if origin_key else str(selected_id)

            reconstructed = {
                "visit_id":       tl.get("visit_id", vid_str),
                "title_pt":       tl.get("title_pt", ""),
                "title_en":       tl.get("title_en", ""),
                "description_pt": tl.get("description_pt", ""),
                "description_en": tl.get("description_en", ""),
                "timezone":       tl.get("timezone", "Asia/Kolkata"),
                "cover_url":      tl.get("cover_url", ""),
                "days":           tl.get("days", []),
            }
            st.session_state["edit_json"] = json.dumps(reconstructed, ensure_ascii=False, indent=2)
            st.session_state["edit_vid"]  = selected_id
            st.session_state["edit_ok"]   = meta.get("_vana_parent_tour_origin_key",
                                                      meta.get("_tour_parent_key", ""))
            st.success(f"✅ JSON carregado — {len(tl.get('days',[]))} dias — ID {selected_id}")

        if "edit_json" not in st.session_state:
            st.info("⬆️ Selecione uma visita e clique em **Carregar JSON**.")
        else:
            edited_raw = st.text_area(
                "JSON editável:",
                value=st.session_state["edit_json"],
                height=500,
                key="edit_textarea",
            )

            tours_map_edit = _fetch_tours(ui_lang)
            parent_current = st.session_state.get("edit_ok", "")
            default_idx    = next(
                (i for i, k in enumerate(tours_map_edit.values()) if k == parent_current), 0
            )
            tour_label_edit = st.selectbox(
                "Tour pai:", list(tours_map_edit.keys()),
                index=default_idx, key="edit_tour_sel",
            )
            tour_key_edit = tours_map_edit[tour_label_edit]

            col_dl, col_save = st.columns([1, 1])
            col_dl.download_button(
                "⬇️ Baixar JSON", data=edited_raw,
                file_name=f"visit-{selected_id}.json",
                mime="application/json", key="dl_btn",
            )

            if col_save.button("💾 Salvar via Ingest HMAC", type="primary", key="save_btn"):
                try:
                    edited_payload = json.loads(edited_raw)
                except json.JSONDecodeError as e:
                    st.error(f"❌ JSON inválido: {e}")
                    edited_payload = None

                if edited_payload:
                    errs = validate_payload(edited_payload)
                    if errs:
                        st.warning(f"⚠️ {len(errs)} aviso(s) — enviando mesmo assim.")
                    try:
                        with st.spinner("Processando com Trator..."):
                            result = run_trator(
                                visit      = edited_payload,
                                wp_url     = st.secrets["vana"]["api_base"],
                                wp_secret  = st.secrets["vana"]["ingest_secret"],
                                tour_key   = tour_key_edit,
                                dry_run    = False,
                            )

                        if result.success:
                            LABELS = {
                                "updated": "🔄 Atualizada",
                                "noop":    "💤 Sem mudanças",
                                "created": "✅ Criada",
                            }
                            action = result.wp_action or "?"
                            st.success(f"{LABELS.get(action, action)} — ID {result.wp_id or '—'}")

                            if action in ("updated", "created"):
                                try:
                                    from api.wp_client import update_visit_tour
                                    update_visit_tour(int(selected_id), tour_key_edit)
                                    st.caption(f"🔗 Tour pai gravado → `{tour_key_edit}`")
                                except Exception as e_tour:
                                    st.warning(f"⚠️ Tour pai não atualizado: {e_tour}")

                            if result.wp_url:
                                st.link_button("🌐 Ver online", result.wp_url)
                            st.cache_data.clear()

                        else:
                            for e in result.errors:
                                st.error(f"[{e.code}] {e.path} — {e.message}")
                            if result.processed:
                                with st.expander("🔍 Visit processado"):
                                    st.json(result.processed)

                    except Exception as e:
                        st.error(f"❌ {e}")


# ══════════════════════════════════════════════════════════════════════════
# TAB 4 — EDITAR DIAS
# ══════════════════════════════════════════════════════════════════════════
with tab_days:
    st.subheader("📅 Editar Dias — Schedule & VODs")

    visits_days = _fetch_visits()

    if not visits_days:
        st.info("Nenhuma visita disponível.")
    else:
        opts = {
            f"[{v.get('id')}]  {v.get('title','—')}": v.get("id")
            for v in visits_days
        }
        sel = st.selectbox("Selecionar visita:", list(opts.keys()), key="days_sel")
        vid = opts[sel]

        st.divider()

        if st.button("📂 Carregar dias para edição", key="load_days_tab4"):
            with st.spinner("Carregando dias..."):
                try:
                    tl   = get_visit_timeline(int(vid))
                    days = tl.get("days", [])
                    st.session_state["days_tab4"] = days
                    st.success(f"✅ {len(days)} dia(s) carregados")
                except Exception as e:
                    st.error(f"❌ Erro ao carregar: {e}")

        # ── Renderiza editor só depois de carregar ─────────────────────
        if "days_tab4" in st.session_state:
            updated = render_days_editor(st.session_state["days_tab4"])  # ← passa a LISTA
            st.session_state["days_tab4"] = updated
        else:
            st.info("⬆️ Clique em **Carregar dias** para editar.")
