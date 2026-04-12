# components/conflict_resolver.py
# -*- coding: utf-8 -*-
"""
Widget de resolução de conflito para o Streamlit.
"""

import json
import streamlit as st
from services.conflict_guard import diff_visits, stamp_revision


def render_conflict_resolver(gh, visit_ref: str, visit: dict, editor_name: str) -> bool:
    """
    Renderiza o painel de resolução de conflito se houver conflito pendente.

    Retorna True se não há conflito ou se já foi resolvido.
    """
    conflict = st.session_state.get("_conflict")
    if not conflict or not conflict.get("conflict"):
        return True

    st.warning("⚠️ **CONFLITO DETECTADO**")
    st.markdown(
        f"A visita `{visit_ref}` foi modificada por outra fonte desde sua última leitura."
    )

    ci1, ci2 = st.columns(2)
    with ci1:
        st.markdown("**📥 Versão remota (mais recente):**")
        st.caption(f"Por: **{conflict.get('remote_by', '?')}** via `{conflict.get('remote_source', '?')}`")
        st.caption(f"Em: {conflict.get('remote_at', '?')[:19]}")
        st.caption(f"Revision: `{conflict.get('remote_rev', '?')}`")
    with ci2:
        st.markdown("**📝 Sua versão (local):**")
        st.caption(f"Por: **{editor_name}** via `streamlit`")
        st.caption(f"Revision: `{conflict.get('local_rev', '?')}`")

    local = conflict.get("local_visit", {})
    remote = conflict.get("remote_visit", {})
    diffs = diff_visits(local, remote)

    if diffs:
        with st.expander(f"🔍 {len(diffs)} diferença(s) encontrada(s)", expanded=True):
            for d in diffs[:30]:
                path = d["path"]
                if d["type"] == "list_length":
                    st.caption(f"📋 `{path}`: local={d['local']} items, remoto={d['remote']} items")
                else:
                    local_v = str(d.get("local", ""))[:80]
                    remote_v = str(d.get("remote", ""))[:80]
                    st.caption(f"🔸 `{path}`:\n  Local: `{local_v}`\n  Remoto: `{remote_v}`")
            if len(diffs) > 30:
                st.caption(f"... e mais {len(diffs) - 30} diferenças")
    else:
        st.info("Nenhuma diferença estrutural detectada (pode ser apenas o timestamp).")

    st.markdown("---")
    st.markdown("### Resolução")
    r1, r2, r3 = st.columns(3)

    with r1:
        st.markdown("**🔵 Manter minha versão**")
        st.caption("Sobrescreve a versão remota com seus dados.")
        if st.button("Forçar minha versão", key="conflict_force_local", type="primary"):
            visit_stamped = stamp_revision(visit, source="streamlit", editor=editor_name)
            try:
                # Use save_visit (higher-level API)
                gh.save_visit(visit_ref, visit_stamped, editor_name, f"[streamlit] CONFLICT RESOLVED: kept local (rev:{visit_stamped['_revision']['id']})")
                st.session_state.pop("_conflict", None)
                st.success("✅ Sua versão foi salva (conflito resolvido).")
                st.rerun()
            except Exception as e:
                st.error(f"❌ {e}")

    with r2:
        st.markdown("**🟢 Aceitar versão remota**")
        st.caption("Descarta suas edições e recarrega do GitHub.")
        if st.button("Recarregar do GitHub", key="conflict_accept_remote"):
            st.session_state.pop("_conflict", None)
            st.session_state.pop("visit_data", None)
            st.session_state.pop("selected_visit", None)
            st.success("✅ Recarregando versão remota...")
            st.rerun()

    with r3:
        st.markdown("**🟡 Merge manual**")
        st.caption("Visualize ambas as versões e edite manualmente.")
        if st.button("Abrir merge", key="conflict_merge"):
            st.session_state["_merge_mode"] = True

    if st.session_state.get("_merge_mode"):
        st.markdown("---")
        st.markdown("### 🔀 Merge Manual")
        mc1, mc2 = st.columns(2)
        with mc1:
            st.markdown("**📝 Sua versão (editável)**")
            local_json = json.dumps(local, ensure_ascii=False, indent=2)
            merged_text = st.text_area("JSON local (edite para merge)", value=local_json, height=400, key="merge_editor")
        with mc2:
            st.markdown("**📥 Versão remota (referência)**")
            remote_json = json.dumps(remote, ensure_ascii=False, indent=2)
            st.text_area("JSON remoto (somente leitura)", value=remote_json, height=400, disabled=True, key="merge_remote_view")

        if st.button("✅ Salvar merge", key="conflict_save_merge", type="primary"):
            try:
                merged_visit = json.loads(merged_text)
                merged_visit = stamp_revision(merged_visit, source="streamlit", editor=editor_name)
                gh.save_visit(visit_ref, merged_visit, editor_name, f"[streamlit] CONFLICT RESOLVED: manual merge (rev:{merged_visit['_revision']['id']})")
                st.session_state.pop("_conflict", None)
                st.session_state.pop("_merge_mode", None)
                st.success("✅ Merge salvo!")
                st.rerun()
            except json.JSONDecodeError as e:
                st.error(f"❌ JSON inválido: {e}")
            except Exception as e:
                st.error(f"❌ Erro ao salvar: {e}")

    return False
