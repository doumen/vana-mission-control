"""
Vana Mission Control — Painel Streamlit
Entry point + Login Guard
"""
import streamlit as st

st.set_page_config(
    page_title="Vana Mission Control",
    page_icon="🪷",
    layout="wide",
    initial_sidebar_state="expanded",
)

# ── Login Guard ───────────────────────────────────────────────
def check_login():
    if st.session_state.get("authenticated"):
        return True

    st.title("🪷 Vana Mission Control")
    st.subheader("Acesso Restrito")
    pwd = st.text_input("Senha", type="password", placeholder="Digite a senha do painel")

    if st.button("Entrar", type="primary"):
        if pwd == st.secrets["vana"]["app_password"]:
            st.session_state.authenticated = True
            st.rerun()
        else:
            st.error("❌ Senha incorreta. Hare Krishna!")
    return False


if not check_login():
    st.stop()

# ── Sidebar ───────────────────────────────────────────────────
st.sidebar.title("🪷 Vana Mission Control")
st.sidebar.caption("Painel de Gestão da Missão")
st.sidebar.divider()

if st.sidebar.button("🚪 Sair"):
    st.session_state.authenticated = False
    st.rerun()

# ── Home ──────────────────────────────────────────────────────
st.title("🪷 Bem-vindo ao Vana Mission Control")
st.markdown("""
Utilize o menu lateral para navegar:

| Página | Função |
|---|---|
| 🗺️ **Tours** | Criar, editar e enviar tours para lixeira |
| 📅 **Visits** | Criar, editar e ingerir visitas (schema 3.1) |
| 🙏 **Oferendas** | Moderar submissions dos devotos |
""")

st.info("Jaya Srila Bhaktivedanta Vana Goswami Maharaj! 🙏")
