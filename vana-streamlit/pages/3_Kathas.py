# pages/3_Kathas.py
# -*- coding: utf-8 -*-
"""
🎙️ Katha Extractor — Produção de Hari-kathā via Streamlit.
"""

import json
import streamlit as st
from datetime import datetime

st.set_page_config(page_title="🎙️ Katha Extractor", layout="wide")
st.title("🎙️ Katha Extractor")
st.caption("Produção de Hari-kathā a partir de transcrições — v4.1")

_llm = None
_extractor = None
try:
    from services.llm_client import get_llm_client
    from services.katha_extractor import KathaExtractor
    _llm = get_llm_client()
    if _llm:
        _extractor = KathaExtractor(_llm)
except Exception:
    _llm = None
    _extractor = None

if not _extractor:
    st.error(
        "❌ LLM não configurado. Adicione `[llm]` no `secrets.toml` e reinicie."
    )
    st.stop()

_r2 = None
try:
    from services.r2_service import R2Service
    _r2_cfg = st.secrets.get("r2", {})
    if isinstance(_r2_cfg, dict) and _r2_cfg.get("endpoint"):
        _r2 = R2Service(
            endpoint=_r2_cfg.get("endpoint"),
            access_key=_r2_cfg.get("access_key"),
            secret_key=_r2_cfg.get("secret_key"),
            bucket=_r2_cfg.get("bucket"),
            public_base=_r2_cfg.get("public_base"),
        )
except Exception:
    _r2 = None

# Show LLM provider/model info (helpful for debugging and cost awareness)
try:
    _llm_cfg = st.secrets.get("llm", {}) if hasattr(st, "secrets") else {}
    if isinstance(_llm_cfg, dict) and _llm_cfg:
        prov = _llm_cfg.get("provider", "(provider)")
        model = _llm_cfg.get("model", "(model)")
        st.info(f"LLM provider: {prov} · model: {model} · costs may apply to LLM calls")
except Exception:
    pass

if "katha_state" not in st.session_state:
    st.session_state.katha_state = {
        "fase_atual": -1,
        "contexto": {},
        "txt_raw": "",
        "chunks": [],
        "fase_0_result": None,
        "fase_1_results": [],
        "fase_2_result": None,
        "fase_3_results": [],
        "fase_4_result": None,
        "total_usage": {"input_tokens": 0, "output_tokens": 0},
        "total_cost_usd": 0.0,
    }

ks = st.session_state.katha_state

with st.sidebar:
    st.markdown("### 🎙️ Pipeline Status")
    fases = [
        ("📝 Input", -1),
        ("🔵 Fase 0 — Mapeamento", 0),
        ("🟡 Fase 1 — Chunks", 1),
        ("🟠 Fase 2 — Esqueleto", 2),
        ("🔴 Fase 3 — Conteúdo", 3),
        ("⚫ Fase 4 — Final", 4),
    ]
    for label, fase_n in fases:
        if ks["fase_atual"] > fase_n:
            st.success(f"✅ {label}")
        elif ks["fase_atual"] == fase_n:
            st.info(f"🔄 {label}")
        else:
            st.caption(f"⬜ {label}")

    st.markdown("---")
    if ks["total_usage"].get("input_tokens", 0) > 0:
        st.metric("Tokens usados", f"{ks['total_usage']['input_tokens'] + ks['total_usage']['output_tokens']:,}")
        st.metric("Custo estimado", f"US$ {ks['total_cost_usd']:.4f}")

    if ks["fase_atual"] >= 0:
        if st.button("🔄 Recomeçar", type="secondary"):
            st.session_state.katha_state = {
                "fase_atual": -1,
                "contexto": {},
                "txt_raw": "",
                "chunks": [],
                "fase_0_result": None,
                "fase_1_results": [],
                "fase_2_result": None,
                "fase_3_results": [],
                "fase_4_result": None,
                "total_usage": {"input_tokens": 0, "output_tokens": 0},
                "total_cost_usd": 0.0,
            }
            st.rerun()

# Input phase (truncated UI for brevity)
if ks["fase_atual"] == -1:
    st.markdown("### 📝 Descrever a Kathā")
    c1, c2 = st.columns(2)
    with c1:
        youtube_url = st.text_input("YouTube URL", key="katha_yt_url")
        katha_date = st.date_input("Data da kathā", key="katha_date")
        language = st.selectbox("Idioma principal", ["hi", "en", "pt", "bn", "sa"], index=0, key="katha_lang")
        visit_ref = st.text_input("visit_ref (se conhecida)", key="katha_visit_ref")
    with c2:
        teaching_context = st.selectbox("Contexto do ensinamento", ["parikrama", "room", "morning", "evening", "festival", "unknown"], index=5, key="katha_context")
        location = st.text_input("Local", key="katha_location")
        event = st.text_input("Evento", key="katha_event")
        notes = st.text_area("Notas adicionais para o extrator", key="katha_notes", height=100)

    st.markdown("---")
    st.markdown("### 📄 Transcrição")
    txt_source = st.radio("Fonte do texto", ["📄 Upload TXT", "📋 Colar texto"], horizontal=True, key="txt_source")
    txt_raw = ""
    if txt_source == "📄 Upload TXT":
        uploaded = st.file_uploader("Upload da transcrição", type=["txt", "srt", "vtt"], key="katha_txt_upload")
        if uploaded:
            txt_raw = uploaded.getvalue().decode("utf-8", errors="replace")
            st.text_area("Preview", value=txt_raw[:2000] + ("..." if len(txt_raw) > 2000 else ""), height=200, disabled=True)
            st.caption(f"📐 {len(txt_raw):,} chars · {len(txt_raw.split(chr(10))):,} linhas")
    else:
        txt_raw = st.text_area("Cole a transcrição aqui", height=300, key="katha_txt_paste")

    if txt_raw:
        lines = txt_raw.strip().split("\n")
        n_lines = len(lines)
        chunk_size = 30 if n_lines < 100 else 50 if n_lines < 200 else 80
        est_chunks = max(1, n_lines // chunk_size)
        cost_est = KathaExtractor.estimate_cost(est_chunks)
        st.markdown("---")
        st.markdown("### 💰 Estimativa")
        ec1, ec2, ec3, ec4 = st.columns(4)
        ec1.metric("Linhas", n_lines)
        ec2.metric("Chunks (~)", est_chunks)
        ec3.metric("Chamadas LLM (~)", cost_est["estimated_calls"])
        ec4.metric("Custo (~)", f"R$ {cost_est['cost_brl']:.2f}")

    st.markdown("---")
    can_start = bool(txt_raw and len(txt_raw.strip()) > 100)
    if st.button("🚀 Iniciar Extração", disabled=not can_start, type="primary", use_container_width=True):
        ks["contexto"] = {"youtube_url": youtube_url, "katha_date": str(katha_date) if katha_date else None, "language": language, "visit_ref": visit_ref or None, "teaching_context": teaching_context, "location": location or None, "event": event or None, "notes": notes or None}
        ks["txt_raw"] = txt_raw
        ks["fase_atual"] = 0
        st.rerun()

# Remaining phases reuse the extractor; flow matches the design in the proposal.

# Helper to accumulate usage

def _acc_usage(ks: dict, result: dict):
    usage = result.get("usage", {})
    ks["total_usage"]["input_tokens"] += usage.get("input_tokens", 0)
    ks["total_usage"]["output_tokens"] += usage.get("output_tokens", 0)
    cost = (usage.get("input_tokens", 0) * 3.0 / 1_000_000) + (usage.get("output_tokens", 0) * 15.0 / 1_000_000)
    ks["total_cost_usd"] += cost
