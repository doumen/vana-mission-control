# components/block_editor.py
# -*- coding: utf-8 -*-
import streamlit as st


def render_block(block: dict, visit: dict, gh, visit_ref: str) -> dict:
    btype   = block["type"]
    content = block.get("content", {})
    editors = {
        "context_vaishnava":   _edit_context_vaishnava,
        "opening":             _edit_text_block,
        "closing":             _edit_text_block,
        "gurudeva_photos":     _edit_photo_block,
        "sangha_photos":       _edit_photo_block,
        "teaching_atmosphere": _edit_teaching,
        "sangha_portrait":     _edit_sangha_portrait,
        "sangha_voices":       _edit_sangha_voices,
    }
    fn = editors.get(btype)
    if fn:
        return fn(content, visit, gh, visit_ref, btype)
    st.json(content)
    return content


def _edit_text_block(content, visit, gh, visit_ref, btype):
    c1, c2 = st.columns(2)
    pt = c1.text_area("Texto PT", value=content.get("text_pt", ""), height=150, key=btype+"_pt")
    en = c2.text_area("Texto EN", value=content.get("text_en", ""), height=150, key=btype+"_en")
    return {"text_pt": pt, "text_en": en}


def _edit_context_vaishnava(content, visit, gh, visit_ref, btype):
    days = visit.get("days", [])
    tithi_opts = {d.get("date_local","?"): d.get("tithi", {}) for d in days if d.get("tithi")}
    c1, c2 = st.columns(2)
    with c1:
        loc_pt  = st.text_input("Local PT",    value=content.get("location_pt",""),  key="ctx_loc_pt")
        dei_pt  = st.text_input("Deidades PT", value=content.get("deities_pt",""),   key="ctx_dei_pt")
        tithi_data = {}
        if tithi_opts:
            sel = st.selectbox("Tithi do dia", list(tithi_opts.keys()), key="ctx_tithi_day")
            tithi_data = tithi_opts[sel]
        tname_pt = st.text_input("Tithi PT",       value=content.get("tithi_name_pt") or tithi_data.get("festival",""),  key="ctx_tname_pt")
        masa_pt  = st.text_input("Masa PT",        value=content.get("masa_pt")       or tithi_data.get("masa",""),      key="ctx_masa_pt")
        obs_pt   = st.text_area("Observancia PT",  value=content.get("observance_pt") or tithi_data.get("observance_pt",""), height=80, key="ctx_obs_pt")
        note_pt  = st.text_area("Nota editorial PT", value=content.get("editorial_note_pt",""), height=120, key="ctx_note_pt")
    with c2:
        loc_en   = st.text_input("Local EN",       value=content.get("location_en",""),  key="ctx_loc_en")
        dei_en   = st.text_input("Deidades EN",    value=content.get("deities_en",""),   key="ctx_dei_en")
        tname_en = st.text_input("Tithi EN",       value=content.get("tithi_name_en",""),key="ctx_tname_en")
        masa_en  = st.text_input("Masa EN",        value=content.get("masa_en",""),      key="ctx_masa_en")
        obs_en   = st.text_area("Observancia EN",  value=content.get("observance_en",""),height=80, key="ctx_obs_en")
        note_en  = st.text_area("Nota editorial EN",value=content.get("editorial_note_en",""),height=120,key="ctx_note_en")
    return {
        "location_pt": loc_pt,   "location_en": loc_en,
        "deities_pt":  dei_pt,   "deities_en":  dei_en,
        "tithi_name_pt": tname_pt, "tithi_name_en": tname_en,
        "masa_pt": masa_pt,      "masa_en": masa_en,
        "observance_pt": obs_pt, "observance_en": obs_en,
        "editorial_note_pt": note_pt, "editorial_note_en": note_en,
    }


def _edit_photo_block(content, visit, gh, visit_ref, btype):
    photos   = content.get("photos", [])
    only_g   = (btype == "gurudeva_photos")
    all_refs = gh.get_photo_refs(visit_ref, only_gurudeva=only_g)
    selected = st.multiselect("Selecionar fotos", options=all_refs,
                              default=[p["ref"] for p in photos if p["ref"] in all_refs],
                              key="photos_"+btype)
    result = []
    for ref in selected:
        ex = next((p for p in photos if p["ref"] == ref), {})
        with st.expander(ref):
            c1, c2 = st.columns(2)
            cap_pt = c1.text_input("Legenda PT", value=ex.get("caption_editorial_pt",""), key="cap_pt_"+ref)
            cap_en = c2.text_input("Legenda EN", value=ex.get("caption_editorial_en",""), key="cap_en_"+ref)
        result.append({"ref": ref, "caption_editorial_pt": cap_pt, "caption_editorial_en": cap_en})
    return {"photos": result}


def _edit_teaching(content, visit, gh, visit_ref, btype):
    c1, c2   = st.columns(2)
    intro_pt = c1.text_area("Intro PT", value=content.get("editorial_intro_pt",""), height=100, key="teach_intro_pt")
    intro_en = c2.text_area("Intro EN", value=content.get("editorial_intro_en",""), height=100, key="teach_intro_en")
    passages    = content.get("passages", [])
    all_refs    = gh.get_passage_refs(visit_ref)
    sel_refs    = st.multiselect("Passages", options=all_refs,
                                 default=[p["ref"] for p in passages if p["ref"] in all_refs],
                                 key="teach_passages")
    result = []
    for ref in sel_refs:
        ex = next((p for p in passages if p["ref"] == ref), {})
        with st.expander(ref):
            c1, c2 = st.columns(2)
            ctx_pt = c1.text_area("Contexto PT", value=ex.get("context_pt",""), height=80, key="pctx_pt_"+ref)
            ctx_en = c2.text_area("Contexto EN", value=ex.get("context_en",""), height=80, key="pctx_en_"+ref)
        result.append({"ref": ref, "context_pt": ctx_pt or None, "context_en": ctx_en or None})
    return {"editorial_intro_pt": intro_pt, "editorial_intro_en": intro_en, "passages": result}


def _edit_sangha_portrait(content, visit, gh, visit_ref, btype):
    ROLES = [
        ("kirtan_leader", "Kirtan",            "Kirtan"),
        ("mridanga",      "Mridanga",           "Mridanga"),
        ("kitchen",       "Cozinha/Prasadam",   "Kitchen/Prasadam"),
        ("pujari",        "Pujari",             "Pujari"),
        ("organizer",     "Organizadores",      "Organizers"),
        ("question",      "Pergunta a Gurudeva","Question to Gurudeva"),
    ]
    performers = gh.get_performers(visit_ref)
    roles      = content.get("roles", [])
    result     = []
    for key, label_pt, label_en in ROLES:
        ex          = next((r for r in roles if r.get("role_pt") == label_pt), {})
        suggestions = [p["name"] for p in performers if p.get("role") == key]
        with st.expander(label_pt):
            if suggestions:
                st.caption("Sugerido: " + ", ".join(suggestions))
            names_str = st.text_input("Nomes (virgula)", value=", ".join(ex.get("names", suggestions)), key="role_"+key)
            note_pt   = st.text_input("Nota PT", value=ex.get("note_pt",""), key="rnote_pt_"+key)
            note_en   = st.text_input("Nota EN", value=ex.get("note_en",""), key="rnote_en_"+key)
            names = [n.strip() for n in names_str.split(",") if n.strip()]
            if names:
                result.append({"role_pt": label_pt, "role_en": label_en,
                               "names": names, "note_pt": note_pt or None, "note_en": note_en or None})
    return {"roles": result}


def _edit_sangha_voices(content, visit, gh, visit_ref, btype):
    c1, c2  = st.columns(2)
    note_pt = c1.text_area("Nota PT", value=content.get("editorial_note_pt",""), height=80, key="sv_note_pt")
    note_en = c2.text_area("Nota EN", value=content.get("editorial_note_en",""), height=80, key="sv_note_en")
    refs_str = st.text_input("IDs submissions aprovadas (virgula)",
                             value=", ".join(str(r) for r in content.get("submission_refs",[])),
                             key="sv_refs")
    refs = [int(r.strip()) for r in refs_str.split(",") if r.strip().isdigit()]
    return {"editorial_note_pt": note_pt, "editorial_note_en": note_en, "submission_refs": refs}
