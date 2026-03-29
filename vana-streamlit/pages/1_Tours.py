# pages/1_Tours.py
# -*- coding: utf-8 -*-
"""
🗺️ Tours — Gerenciador de Tours
"""
import streamlit as st
from api import hmac_client, wp_client

# Enums (match plugin contract)
REGION_CODES = ["", "AME", "EUR", "IND", "ASI", "AFR"]
REGION_LABELS = {
    "": "— selecionar —",
    "AME": "AME — Americas",
    "EUR": "EUR — Europe",
    "IND": "IND — India",
    "ASI": "ASI — Asia",
    "AFR": "AFR — Africa",
}

SEASON_CODES = ["", "WIN", "SUM", "SPR", "AUT", "KAR", "GAU"]
SEASON_LABELS = {
    "": "— selecionar —",
    "WIN": "WIN — Winter",
    "SUM": "SUM — Summer",
    "SPR": "SPR — Spring",
    "AUT": "AUT — Autumn",
    "KAR": "KAR — Kartik",
    "GAU": "GAU — Gaura Purnima",
}


def _select_index(options, value):
    try:
        return options.index(value if value is not None else "")
    except ValueError:
        return 0

if not st.session_state.get("authenticated"):
    st.warning("🔒 Faça login na página principal.")
    st.stop()

st.title("🗺️ Tours")

tab_list, tab_create = st.tabs(["📋 Listar", "➕ Criar"])

# ══════════════════════════════════════════════════════════════
# TAB 1 — LISTAR
# ══════════════════════════════════════════════════════════════
with tab_list:
    st.subheader("Tours cadastradas")

    if st.button("🔄 Atualizar lista"):
        st.cache_data.clear()
        st.rerun()

    try:
        tours = wp_client.list_tours()
    except Exception as e:
        st.error(f"Erro ao carregar tours: {e}")
        tours = []

    if not tours:
        st.info("Nenhuma tour encontrada.")
    else:
        st.success(f"**{len(tours)}** tour(s) encontrada(s)")

        for t in tours:
            tid    = t["id"]
            title  = t["title"]          # ← já string (normalizado no wp_client)
            ok     = t["origin_key"]
            status = t["status"]
            slug   = t["slug"]
            link   = t["permalink"]
            icon   = "🟢" if status == "publish" else "🔴"

            with st.expander(f"{icon} [{tid}]  {title}"):
                c1, c2, c3 = st.columns([2, 2, 1])
                c1.markdown(f"**Status:** `{status}`")
                c1.markdown(f"**Slug:** `{slug}`")
                c2.markdown(f"**origin_key:** `{ok}`")
                if link:
                    c3.link_button("🌐 Ver online", link)

                st.divider()

                # Editar tour — abre formulário inline
                # ensure session key exists before creating widgets that may reference it
                if f"edit_tour_{tid}" not in st.session_state:
                    st.session_state[f"edit_tour_{tid}"] = False

                if st.button("✏️ Editar tour", key=f"edit_btn_{tid}"):
                    st.session_state[f"edit_tour_{tid}"] = True

                if st.session_state.get(f"edit_tour_{tid}"):
                    with st.form(f"form_edit_tour_{tid}"):
                        new_title = st.text_input("Título (visível)", value=title)
                        new_origin = st.text_input("origin_key", value=ok)
                        # Additional editable fields (if available from API)
                        new_title_pt = st.text_input("Título (PT)", value=t.get("title_pt", ""))
                        new_title_en = st.text_input("Título (EN)", value=t.get("title_en", ""))
                        # Region / Season as selectboxes using enums
                        new_region = st.selectbox(
                            "Region Code",
                            options=REGION_CODES,
                            index=_select_index(REGION_CODES, t.get("region_code", "")),
                            format_func=lambda c: REGION_LABELS.get(c, c),
                        )
                        new_season = st.selectbox(
                            "Season Code",
                            options=SEASON_CODES,
                            index=_select_index(SEASON_CODES, t.get("season_code", "")),
                            format_func=lambda c: SEASON_LABELS.get(c, c),
                        )
                        new_year_start = st.text_input("Year Start", value=str(t.get("year_start", "")))
                        new_year_end = st.text_input("Year End", value=str(t.get("year_end", "")))
                        save = st.form_submit_button("💾 Salvar alterações")
                    if save:
                        try:
                            # Build kwargs only for fields provided
                            kwargs = {"title": new_title, "origin_key": new_origin}
                            if new_title_pt:
                                kwargs["title_pt"] = new_title_pt
                            if new_title_en:
                                kwargs["title_en"] = new_title_en
                            if new_region:
                                kwargs["region_code"] = new_region
                            if new_season:
                                kwargs["season_code"] = new_season
                            try:
                                if new_year_start and new_year_start.strip() != "":
                                    kwargs["year_start"] = int(new_year_start)
                            except ValueError:
                                st.error("Year Start deve ser um número inteiro.")
                            try:
                                if new_year_end and new_year_end.strip() != "":
                                    kwargs["year_end"] = int(new_year_end)
                            except ValueError:
                                st.error("Year End deve ser um número inteiro.")

                            wp_client.update_tour(tid, **kwargs)
                            st.success("✅ Tour atualizada com sucesso.")
                            st.session_state.pop(f"edit_tour_{tid}", None)
                            st.cache_data.clear()
                            st.rerun()
                        except Exception as e:
                            st.error(f"Erro ao atualizar tour: {e}")

                if st.button("🗑️ Mover para lixeira",
                             key=f"trash_tour_{tid}", type="secondary"):
                    try:
                        wp_client.trash_tour(tid)
                        st.success(f"Tour #{tid} enviada para lixeira.")
                        st.cache_data.clear()
                        st.rerun()
                    except Exception as e:
                        st.error(f"Erro: {e}")

# ══════════════════════════════════════════════════════════════
# TAB 2 — CRIAR
# ══════════════════════════════════════════════════════════════
with tab_create:
    st.subheader("Nova Tour")
    st.info("Tours são criadas como placeholder. O Trator Python completa os dados.")

    with st.form("form_create_tour"):
        title_in   = st.text_input("Título da Tour *")
        origin_key = st.text_input(
            "origin_key *",
            placeholder="tour:india-2026",
        )
        # Additional metadata fields
        title_pt_in = st.text_input("Título (PT)")
        title_en_in = st.text_input("Título (EN)")
        region_in = st.selectbox(
            "Region Code",
            options=REGION_CODES,
            index=_select_index(REGION_CODES, ""),
            format_func=lambda c: REGION_LABELS.get(c, c),
        )
        season_in = st.selectbox(
            "Season Code",
            options=SEASON_CODES,
            index=_select_index(SEASON_CODES, ""),
            format_func=lambda c: SEASON_LABELS.get(c, c),
        )
        year_start_in = st.text_input("Year Start", placeholder="e.g. 2026")
        year_end_in = st.text_input("Year End", placeholder="e.g. 2026")

        submitted = st.form_submit_button("🚀 Criar Tour", type="primary")

    if submitted:
        if not title_in or not origin_key:
            st.error("Preencha título e origin_key.")
        elif not origin_key.startswith("tour:"):
            st.error("origin_key deve começar com `tour:`")
        else:
            # Build data payload with optional fields
            data = {"title": title_in}
            if title_pt_in:
                data["title_pt"] = title_pt_in
            if title_en_in:
                data["title_en"] = title_en_in
            if region_in:
                data["region_code"] = region_in
            if season_in:
                data["season_code"] = season_in
            try:
                if year_start_in and year_start_in.strip() != "":
                    data["year_start"] = int(year_start_in)
            except ValueError:
                st.error("Year Start deve ser um número inteiro.")
                data.pop("year_start", None)
            try:
                if year_end_in and year_end_in.strip() != "":
                    data["year_end"] = int(year_end_in)
            except ValueError:
                st.error("Year End deve ser um número inteiro.")
                data.pop("year_end", None)

            payload = {
                "kind":       "tour",
                "origin_key": origin_key,
                "data":       data,
            }
            try:
                resp = hmac_client.ingest(payload)
                st.success(f"✅ {resp.get('message', 'Tour criada!')}")
                st.json(resp)
                st.cache_data.clear()
            except Exception as e:
                st.error(f"Erro: {e}")
