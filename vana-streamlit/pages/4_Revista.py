# pages/4_Revista.py
# -*- coding: utf-8 -*-
import streamlit as st
from datetime import datetime, timezone
from api.github_client       import GitHubClient
from services.wp_service     import WPService
from services.r2_service     import R2Service
from services.pdf_service    import PDFService
from components.block_editor import render_block

BLOCK_TYPES = [
    ("context_vaishnava",   "Momento Sagrado"),
    ("opening",             "Abertura Editorial"),
    ("gurudeva_photos",     "Fotos de Gurudeva"),
    ("teaching_atmosphere", "O que estava no ar"),
    ("sangha_portrait",     "Retrato da Sanga"),
    ("sangha_voices",       "Vozes da Sanga"),
    ("sangha_photos",       "Momentos da Sanga"),
    ("closing",             "Para Levar"),
]

STATE_LABELS = {"coleta": "Coleta aberta", "edicao": "Em edicao", "publicada": "Publicada"}
STATE_COLORS = {"coleta": "orange",        "edicao": "blue",      "publicada": "green"}


@st.cache_resource
def get_clients():
    gh  = GitHubClient(
        token  = st.secrets["github"]["token"],
        repo   = st.secrets["github"]["repo"],
        branch = st.secrets["github"].get("branch", "main"),
    )
    wp  = WPService(
        base   = st.secrets["vana"]["api_base"],
        secret = st.secrets["vana"]["ingest_secret"],
    )
    r2  = R2Service(
        endpoint    = st.secrets["r2"]["endpoint"],
        access_key  = st.secrets["r2"]["access_key"],
        secret_key  = st.secrets["r2"]["secret_key"],
        bucket      = st.secrets["r2"]["bucket"],
        public_base = st.secrets["r2"]["public_base"],
    )
    pdf = PDFService()
    return gh, wp, r2, pdf


def main():
    st.title("Revista - Painel Editorial")
    try:
        gh, wp, r2, pdf = get_clients()
    except Exception as e:
        st.error("Erro ao conectar servicos: " + str(e))
        st.stop()

    with st.sidebar:
        st.markdown("### Revista")
        st.divider()
        visit_ref   = st.text_input("Codigo da visita", value="", placeholder="vrindavan-2026-02")
        editor_name = st.text_input("Seu nome", placeholder="Madhava Dasa")
        st.divider()
        if st.button("Recarregar", use_container_width=True):
            st.cache_data.clear()
            st.rerun()

    if not visit_ref:
        st.info("Digite o codigo da visita na sidebar.")
        st.stop()

    @st.cache_data(ttl=30)
    def load_data(vref):
        return gh.get_visit(vref), gh.get_editorial(vref)

    try:
        visit, editorial = load_data(visit_ref)
    except Exception as e:
        st.error("Erro ao carregar dados: " + str(e))
        st.stop()

    if not visit:
        st.warning("visit.json nao encontrado para: " + visit_ref)
        st.stop()

    state = editorial.get("state", "coleta")
    c1, c2, c3 = st.columns([4, 2, 2])
    c1.markdown("## Revista `" + visit_ref + "`")
    color = STATE_COLORS.get(state, "gray")
    c2.markdown("<span style=color:" + color + ";font-weight:bold>" + STATE_LABELS.get(state, state) + "</span>", unsafe_allow_html=True)
    if state == "coleta" and editor_name:
        with c3:
            if st.button("Iniciar edicao", type="primary"):
                editorial["state"]              = "edicao"
                editorial["meta"]["editor"]     = editor_name
                editorial["meta"]["started_at"] = datetime.now(timezone.utc).isoformat()
                editorial = gh.append_audit(editorial, "state_changed", editor_name, **{"from":"coleta","to":"edicao"})
                gh.save_editorial(visit_ref, editorial, editor_name, "state: coleta para edicao")
                wp.notify_state_change(visit_ref, "edicao")
                st.cache_data.clear()
                st.rerun()
    st.divider()

    if state == "coleta":
        _view_coleta(visit, editorial, visit_ref, editor_name, gh, wp)
    elif state == "edicao":
        _view_edicao(visit, editorial, visit_ref, editor_name, gh, wp, r2, pdf)
    elif state == "publicada":
        _view_publicada(editorial)
    else:
        st.error("Estado desconhecido: " + state)


def _view_coleta(visit, editorial, visit_ref, editor_name, gh, wp):
    coleta = editorial.get("coleta", {})
    c1, c2 = st.columns(2)
    with c1:
        st.markdown("### Status da Coleta")
        opened = coleta.get("opened_at", "")
        st.metric("Aberta desde", opened[:10] if opened else "?")
        st.metric("Notificacoes", len(coleta.get("notify_list", [])))
        if coleta.get("paused"):
            st.warning("Pausada por " + coleta.get("paused_by","?"))
            if editor_name and st.button("Retomar coleta"):
                editorial["coleta"].update({"paused":False,"paused_at":None,"paused_by":None})
                editorial = gh.append_audit(editorial, "coleta_resumed", editor_name)
                gh.save_editorial(visit_ref, editorial, editor_name, "coleta: retomada")
                st.cache_data.clear(); st.rerun()
        else:
            st.success("Coleta ativa")
            if editor_name and st.button("Pausar coleta"):
                editorial["coleta"].update({"paused":True,"paused_at":datetime.now(timezone.utc).isoformat(),"paused_by":editor_name})
                editorial = gh.append_audit(editorial, "coleta_paused", editor_name)
                gh.save_editorial(visit_ref, editorial, editor_name, "coleta: pausada")
                wp.notify_state_change(visit_ref, "coleta_paused")
                st.cache_data.clear(); st.rerun()
    with c2:
        st.markdown("### Passages Mais Votados")
        st.info("Conectar ao endpoint /vana/v1/top-passages")
    st.divider()
    st.markdown("### Lista de Notificacao")
    notify_list = coleta.get("notify_list", [])
    notify_str  = st.text_area("Emails (um por linha)", value="\n".join(notify_list), height=100)
    if st.button("Salvar lista", type="secondary"):
        new_list = [e.strip() for e in notify_str.splitlines() if e.strip()]
        editorial["coleta"]["notify_list"] = new_list
        editorial = gh.append_audit(editorial, "notify_list_updated", editor_name or "anon", count=len(new_list))
        gh.save_editorial(visit_ref, editorial, editor_name or "anon", "coleta: notify_list atualizada")
        st.success("Lista salva com " + str(len(new_list)) + " email(s).")
        st.cache_data.clear()
    st.divider()
    _render_audit(editorial)


def _view_edicao(visit, editorial, visit_ref, editor_name, gh, wp, r2, pdf):
    with st.expander("Informacoes da Revista", expanded=True):
        c1, c2 = st.columns(2)
        with c1:
            title_pt   = st.text_input("Titulo PT",  value=editorial["meta"].get("title_pt") or "",   key="meta_title_pt")
            preview_pt = st.text_area("Previa PT",   value=editorial["meta"].get("preview_pt") or "", height=80, key="meta_preview_pt")
        with c2:
            title_en   = st.text_input("Titulo EN",  value=editorial["meta"].get("title_en") or "",   key="meta_title_en")
            preview_en = st.text_area("Previa EN",   value=editorial["meta"].get("preview_en") or "", height=80, key="meta_preview_en")
        cover_ref = st.text_input("Ref foto de capa", value=editorial["meta"].get("cover_photo_ref") or "", key="meta_cover")
        if st.button("Salvar meta", type="secondary"):
            editorial["meta"].update({"title_pt":title_pt,"title_en":title_en,"preview_pt":preview_pt,"preview_en":preview_en,"cover_photo_ref":cover_ref})
            editorial = gh.append_audit(editorial, "meta_updated", editor_name or "anon")
            gh.save_editorial(visit_ref, editorial, editor_name or "anon", "meta: atualizada")
            st.success("Meta salva!"); st.cache_data.clear()
    st.divider()
    st.markdown("### Blocos da Revista")
    blocks = editorial.get("blocks", [])
    for i, block in enumerate(blocks):
        with st.container(border=True):
            cm, ct, cd = st.columns([1,8,1])
            with cm:
                if i > 0 and st.button("cima", key="up_"+str(i)):
                    blocks[i], blocks[i-1] = blocks[i-1], blocks[i]
                    for j,b in enumerate(blocks): b["order"]=j+1
                    editorial["blocks"] = blocks
                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+str(i)+": cima")
                    st.cache_data.clear(); st.rerun()
                if i < len(blocks)-1 and st.button("baixo", key="dn_"+str(i)):
                    blocks[i], blocks[i+1] = blocks[i+1], blocks[i]
                    for j,b in enumerate(blocks): b["order"]=j+1
                    editorial["blocks"] = blocks
                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+str(i)+": baixo")
                    st.cache_data.clear(); st.rerun()
            with ct:
                label = next((l for t,l in BLOCK_TYPES if t==block["type"]), block["type"])
                st.markdown("**" + label + "**")
            with cd:
                if st.button("X", key="del_"+str(i)):
                    blocks.pop(i)
                    editorial["blocks"] = blocks
                    editorial = gh.append_audit(editorial, "block_removed", editor_name or "anon", block=block["type"])
                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+block["type"]+": removido")
                    st.cache_data.clear(); st.rerun()
            updated = render_block(block, visit, gh, visit_ref)
            if updated != block.get("content"):
                block["content"] = updated
                editorial["blocks"] = blocks
                gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+block["type"]+": editado")
    st.divider()
    st.markdown("### Adicionar Bloco")
    existing = {b["type"] for b in blocks}
    available = [(t,l) for t,l in BLOCK_TYPES if t not in existing]
    if available:
        cols = st.columns(4)
        for idx,(btype,blabel) in enumerate(available):
            with cols[idx%4]:
                if st.button(blabel, key="add_"+btype, use_container_width=True):
                    blocks.append({"order":len(blocks)+1,"type":btype,"locked":False,"content":{}})
                    editorial["blocks"] = blocks
                    editorial = gh.append_audit(editorial, "block_added", editor_name or "anon", block=btype)
                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+btype+": adicionado")
                    st.cache_data.clear(); st.rerun()
    else:
        st.success("Todos os blocos presentes!")
    st.divider()
    st.markdown("### Publicacao (Supervisor)")
    meta  = editorial.get("meta",{})
    ready = bool(meta.get("title_pt")) and bool(meta.get("preview_pt")) and len(blocks)>=3
    if not ready:
        missing = []
        if not meta.get("title_pt"):   missing.append("titulo PT")
        if not meta.get("preview_pt"): missing.append("previa PT")
        if len(blocks)<3:              missing.append("min 3 blocos")
        st.warning("Faltam: " + ", ".join(missing))
    else:
        supervisor = st.text_input("Nome do Supervisor", placeholder="Govinda Dasa", key="supervisor_name")
        if supervisor and st.button("Gerar PDF e Publicar", type="primary"):
            with st.spinner("Publicando..."):
                _publicar(visit, editorial, visit_ref, supervisor, gh, wp, r2, pdf)
    st.divider()
    _render_audit(editorial)


def _view_publicada(editorial):
    meta    = editorial.get("meta",{})
    exports = editorial.get("exports",{})
    stats   = editorial.get("stats",{})
    st.success("Revista publicada!")
    c1,c2,c3 = st.columns(3)
    pub = meta.get("published_at","")
    c1.metric("Publicada em", pub[:10] if pub else "?")
    c2.metric("Editor",      meta.get("editor","?"))
    c3.metric("Supervisor",  meta.get("supervisor","?"))
    st.divider()
    col1, col2 = st.columns(2)
    with col1:
        st.markdown("### Downloads")
        pt = exports.get("pdf_pt_url")
        en = exports.get("pdf_en_url")
        if pt: st.markdown("[PDF Portugues](" + pt + ")")
        if en: st.markdown("[PDF English](" + en + ")")
        if not pt and not en: st.info("Nenhum PDF disponivel.")
    with col2:
        st.markdown("### Estatisticas")
        st.metric("Devotos", stats.get("devotees_count","?"))
        st.metric("Paises",  stats.get("countries_count","?"))
        st.metric("Reacoes", stats.get("total_reactions","?"))
    st.divider()
    _render_audit(editorial)


def _publicar(visit, editorial, visit_ref, supervisor, gh, wp, r2, pdf):
    try:
        pdf_pt = pdf.generate(editorial, visit, "pt", {})
        pdf_en = pdf.generate(editorial, visit, "en", {})
        st.write("PDFs gerados.")
    except Exception as e:
        st.error("Erro PDF: " + str(e)); return
    try:
        url_pt = r2.upload_pdf(visit_ref, "pt", pdf_pt)
        url_en = r2.upload_pdf(visit_ref, "en", pdf_en)
        st.write("Upload R2: OK")
    except Exception as e:
        st.error("Erro R2: " + str(e)); return
    cover_url = None
    cover_ref = editorial["meta"].get("cover_photo_ref")
    if cover_ref:
        cover_url = st.secrets["r2"]["public_base"].rstrip("/") + "/visits/" + visit_ref + "/" + cover_ref
    now = datetime.now(timezone.utc).isoformat()
    editorial["state"]                 = "publicada"
    editorial["meta"]["supervisor"]    = supervisor
    editorial["meta"]["published_at"]  = now
    editorial["exports"]["pdf_pt_url"] = url_pt
    editorial["exports"]["pdf_en_url"] = url_en
    editorial["exports"]["cover_url"]  = cover_url
    editorial = gh.append_audit(editorial, "state_changed", supervisor, note="Aprovado", **{"from":"edicao","to":"publicada"})
    editorial = gh.append_audit(editorial, "pdf_generated", "streamlit-auto", langs=["pt","en"])
    try:
        gh.save_editorial(visit_ref, editorial, supervisor, "publicada")
        st.write("GitHub: OK")
    except Exception as e:
        st.error("Erro GitHub: " + str(e)); return
    try:
        ok = wp.notify_publicada(visit_ref, editorial)
        if ok: st.write("WordPress: OK")
        else:  st.warning("WordPress retornou erro.")
    except Exception as e:
        st.warning("Falha WP: " + str(e))
    st.success("Revista publicada!")
    st.balloons()
    st.cache_data.clear()


def _render_audit(editorial):
    with st.expander("Historico de acoes"):
        audit = editorial.get("audit",[])
        if not audit:
            st.caption("Nenhuma acao registrada.")
            return
        for entry in reversed(audit):
            at     = entry.get("at","")[:16]
            action = entry.get("action","?")
            by     = entry.get("by","?")
            note   = entry.get("note","")
            line   = "`" + at + "` - **" + action + "** por *" + by + "*"
            if note: line += " - " + note
            st.markdown(line)


main()
