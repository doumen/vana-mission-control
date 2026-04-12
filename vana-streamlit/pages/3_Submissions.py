# pages/3_Submissions.py
# -*- coding: utf-8 -*-
"""
🙏 Oferendas dos Devotos — Moderação de Submissions
CPT: vana_submission ✅ confirmado no WP
"""
import streamlit as st
from api import wp_client

# ── Guard ──────────────────────────────────────────────────────────────────
if not st.session_state.get("authenticated"):
    st.warning("🔒 Faça login na página principal.")
    st.stop()

# ══════════════════════════════════════════════════════════════════════════
# LAYOUT
# ══════════════════════════════════════════════════════════════════════════
st.title("🙏 Oferendas dos Devotos")
st.caption("Modere as oferendas enviadas pelos devotos da missão · CPT `vana_submission`")

STATUS_OPTS = {
    "⏳ Pendentes": "pending",
    "✅ Aprovadas":  "publish",
    "📝 Rascunho":  "draft",
    "🗑️ Lixeira":   "trash",
    "🔍 Todas":     "any",
}

col_filter, col_refresh = st.columns([5, 1])
with col_filter:
    status_label = st.radio(
        "Status:", list(STATUS_OPTS.keys()),
        horizontal=True, key="sub_filter",
    )
with col_refresh:
    st.write("")
    if st.button("🔄", key="refresh_subs", help="Atualizar lista"):
        st.cache_data.clear()
        st.rerun()

status_val = STATUS_OPTS[status_label]

# ── Carrega ────────────────────────────────────────────────────────────────
try:
    subs = wp_client.list_submissions(status=status_val, per_page=50)
    # Garante lista (WP pode retornar dict em caso de 0 resultados com 'any')
    if not isinstance(subs, list):
        subs = []
except Exception as e:
    err = str(e)
    if "401" in err or "403" in err:
        st.error("❌ Sem permissão. Verifique `wp_user` e `wp_app_password` no secrets.toml.")
    elif "404" in err:
        st.error("❌ Endpoint não encontrado. Verifique se o plugin está ativo.")
    else:
        st.error(f"❌ Erro ao carregar: {err}")
    with st.expander("🔍 Detalhe técnico"):
        st.code(err)
    st.stop()

# ── Contador ───────────────────────────────────────────────────────────────
if not subs:
    st.info(f"Nenhuma oferenda com status **{status_label}**.")
    st.stop()

st.success(f"**{len(subs)}** oferenda(s) · status: **{status_label}**")

# ══════════════════════════════════════════════════════════════════════════
# LISTA
# ══════════════════════════════════════════════════════════════════════════
STATUS_ICON = {
    "publish": "✅", "pending": "⏳",
    "draft":   "📝", "trash":   "🗑️",
}

for s in subs:
    sid        = s.get("id")
    meta       = s.get("meta") or {}
    sub_status = s.get("status", "—")

    # ── Extrai campos — tolerante a múltiplas convenções de chave ──────
    name = (
        meta.get("_sender_display_name")
        or meta.get("sender_display_name")
        or meta.get("sender_name")
        or (s.get("title") or {}).get("rendered", "")
        or f"Devoto #{sid}"
    )
    msg     = meta.get("_message")      or meta.get("message",      "")
    img     = meta.get("_image_url")    or meta.get("image_url",    "")
    url_ext = meta.get("_external_url") or meta.get("external_url", "")
    vid     = meta.get("_visit_id")     or meta.get("visit_id",     "—")
    tour_id = meta.get("_tour_id")      or meta.get("tour_id",      "")

    icon = STATUS_ICON.get(sub_status, "⚪")

    with st.expander(f"{icon} [{sid}]  🙏 {name}  —  visita: `{vid}`"):

        # ── Info ───────────────────────────────────────────────────────
        c1, c2 = st.columns([3, 2])
        c1.markdown(f"**Devoto:** {name}")
        c1.markdown(f"**Visita:** `{vid}`")
        if tour_id:
            c1.markdown(f"**Tour:** `{tour_id}`")
        c2.markdown(f"**Status:** `{sub_status}`")
        c2.markdown(f"**ID WP:** `{sid}`")

        # ── Mensagem ───────────────────────────────────────────────────
        if msg:
            st.markdown("**📝 Mensagem:**")
            st.markdown(
                f"""<div style="background:#fdf8f3;border-left:4px solid #c8a97e;
                padding:12px 16px;border-radius:6px;font-style:italic;
                color:#444;margin:4px 0 12px 0">{msg}</div>""",
                unsafe_allow_html=True,
            )

        # ── Imagem ─────────────────────────────────────────────────────
        if img:
            st.image(img, width=340, caption="Imagem da oferenda")

        # ── Link externo ───────────────────────────────────────────────
        if url_ext:
            st.link_button("🔗 Ver link externo", url_ext)

        # ── Debug meta ────────────────────────────────────────────────
        with st.expander("🔍 Metadados completos", expanded=False):
            st.json(meta)

        st.divider()

        # ── Ações ──────────────────────────────────────────────────────
        if sub_status == "pending":
            ca, cr = st.columns(2)
            if ca.button("✅ Aprovar", key=f"ap_{sid}", type="primary"):
                with st.spinner("Aprovando..."):
                    try:
                        wp_client.approve_submission(sid)
                        st.success(f"🙏 Oferenda #{sid} aprovada! Hare Krishna!")
                        st.cache_data.clear()
                        st.rerun()
                    except Exception as e:
                        st.error(f"Erro: {e}")

            if cr.button("🗑️ Rejeitar", key=f"rj_{sid}", type="secondary"):
                with st.spinner("Rejeitando..."):
                    try:
                        wp_client.reject_submission(sid)
                        st.warning(f"Oferenda #{sid} rejeitada.")
                        st.cache_data.clear()
                        st.rerun()
                    except Exception as e:
                        st.error(f"Erro: {e}")

        elif sub_status == "publish":
            if st.button("🗑️ Mover para lixeira", key=f"tr_{sid}", type="secondary"):
                try:
                    wp_client.reject_submission(sid)
                    st.warning(f"#{sid} movida para lixeira.")
                    st.cache_data.clear()
                    st.rerun()
                except Exception as e:
                    st.error(f"Erro: {e}")

        elif sub_status in ("trash", "draft"):
            if st.button("♻️ Restaurar → Publicar", key=f"rs_{sid}"):
                try:
                    wp_client.approve_submission(sid)
                    st.success(f"#{sid} restaurada e publicada.")
                    st.cache_data.clear()
                    st.rerun()
                except Exception as e:
                    st.error(f"Erro: {e}")
