# pages/1_Tours.py
# -*- coding: utf-8 -*-
"""
🗺️ Tours — Gerenciador de Tours
"""
import streamlit as st
from api import hmac_client, wp_client

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
                        save = st.form_submit_button("💾 Salvar alterações")
                    if save:
                        try:
                            wp_client.update_tour(tid, title=new_title, origin_key=new_origin)
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
        submitted = st.form_submit_button("🚀 Criar Tour", type="primary")

    if submitted:
        if not title_in or not origin_key:
            st.error("Preencha título e origin_key.")
        elif not origin_key.startswith("tour:"):
            st.error("origin_key deve começar com `tour:`")
        else:
            payload = {
                "kind":       "tour",
                "origin_key": origin_key,
                "data":       {"title": title_in},
            }
            try:
                resp = hmac_client.ingest(payload)
                st.success(f"✅ {resp.get('message', 'Tour criada!')}")
                st.json(resp)
                st.cache_data.clear()
            except Exception as e:
                st.error(f"Erro: {e}")
