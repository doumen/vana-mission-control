# components/days_editor.py
# -*- coding: utf-8 -*-
"""
Componente reutilizável de edição de dias do timeline.
Plugar em qualquer page via: render_days_editor(visit_id)
"""
import streamlit as st
from api.wp_client import (
    get_visit_days,
    update_schedule_status,
    add_vod_to_day,
    patch_visit_timeline,
    get_visit_timeline,
)

STATUS_ICON   = {"done": "✅", "live": "🔴", "upcoming": "🕐"}
STATUS_OPTS   = ["upcoming", "live", "done"]
MEDIA_SOURCES = ["youtube_url", "facebook_url", "instagram_url", "drive_url"]


def render_days_editor(visit_id: int):
    """
    Renderiza tabs de dias com schedule (alterar status)
    e VODs (visualizar + adicionar novos).
    """
    st.caption(f"🔗 Lendo via REST · visit_id WP = `{visit_id}`")

    with st.spinner("Carregando timeline..."):
        days = get_visit_days(visit_id)

    if not days:
        st.warning("Nenhum dia encontrado para esta visita.")
        return

    tabs = st.tabs([d.get("label_pt", d["date_local"]) for d in days])

    for tab, day in zip(tabs, days):
        with tab:
            date = day["date_local"]

            # ── HERO ──────────────────────────────────────────
            hero = day.get("hero", {})
            if hero:
                st.markdown(f"**Hero:** {hero.get('title_pt','—')}")
                for src in MEDIA_SOURCES:
                    if hero.get(src):
                        st.caption(f"🎬 `{src}`: {hero[src]}")

            st.divider()

            # ── SCHEDULE ──────────────────────────────────────
            st.subheader("📋 Programação")
            schedule = day.get("schedule", [])

            if not schedule:
                st.info("Sem itens de programação neste dia.")
            else:
                for item in schedule:
                    col1, col2, col3 = st.columns([1, 4, 2])
                    col1.write(f"`{item.get('time_local','?')}`")
                    col2.write(
                        f"{STATUS_ICON.get(item.get('status',''),'•')} "
                        f"{item.get('title_pt','—')}"
                    )

                    current = item.get("status", "upcoming")
                    novo = col3.selectbox(
                        "Status",
                        STATUS_OPTS,
                        index=STATUS_OPTS.index(current) if current in STATUS_OPTS else 0,
                        key=f"st_{visit_id}_{date}_{item.get('time_local','')}",
                        label_visibility="collapsed",
                    )

                    if novo != current:
                        with st.spinner("Salvando..."):
                            ok = update_schedule_status(
                                visit_id, date, item["time_local"], novo
                            )
                        if ok:
                            st.success(f"✅ Status atualizado → `{novo}`")
                            st.rerun()
                        else:
                            st.error("❌ Falha ao atualizar.")

            st.divider()

            # ── VODs ───────────────────────────────────────────
            st.subheader("🎬 VODs")
            vods = day.get("vods", [])

            if not vods:
                st.info("Sem VODs neste dia.")
            else:
                for vod in vods:
                    src_icon = (
                        "▶️" if vod.get("youtube_url")
                        else "📘" if vod.get("facebook_url")
                        else "📸" if vod.get("instagram_url")
                        else "📁"
                    )
                    st.write(
                        f"{src_icon} **{vod.get('title_pt','—')}** "
                        f"`{vod.get('duration','')}`"
                    )
                    segs = vod.get("segments", [])
                    if segs:
                        with st.expander("Segmentos"):
                            for s in segs:
                                st.write(f"`{s.get('t','')}` — {s.get('title_pt','')}")

            # Formulário — adicionar VOD
            with st.expander("➕ Adicionar VOD"):
                with st.form(key=f"vod_{visit_id}_{date}"):
                    title_pt = st.text_input("Título PT *")
                    title_en = st.text_input("Título EN")
                    yt_url   = st.text_input("YouTube URL *")
                    duration = st.text_input("Duração (ex: 42:18)")

                    submitted = st.form_submit_button("💾 Salvar VOD")
                    if submitted:
                        if not title_pt or not yt_url:
                            st.error("Título PT e YouTube URL são obrigatórios.")
                        else:
                            vod = {
                                "title_pt":   title_pt,
                                "title_en":   title_en,
                                "youtube_url": yt_url,
                                "duration":   duration,
                                "segments":   [],
                            }
                            with st.spinner("Salvando..."):
                                ok = add_vod_to_day(visit_id, date, vod)
                            if ok:
                                st.success(f"✅ VOD '{title_pt}' adicionado!")
                                st.rerun()
                            else:
                                st.error("❌ Dia não encontrado.")