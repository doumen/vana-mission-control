# patch_revista_completo.ps1
# Executa da raiz do vana_crud:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\patch_revista_completo.ps1

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ROOT      = $PSScriptRoot
$API_DIR   = Join-Path $ROOT "api"
$SVC_DIR   = Join-Path $ROOT "services"
$COMP_DIR  = Join-Path $ROOT "components"
$PAGES_DIR = Join-Path $ROOT "pages"
$SECRETS   = Join-Path $ROOT ".streamlit\secrets.toml"

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Vana Mission Control - Patch Revista       " -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# ══════════════════════════════════════════════════════════════
# 1. PRE-REQUISITOS
# ══════════════════════════════════════════════════════════════
Write-Host "1. Validando pre-requisitos..." -ForegroundColor Yellow

$required = @(
    (Join-Path $ROOT    "app.py"),
    (Join-Path $API_DIR "github_client.py"),
    (Join-Path $API_DIR "wp_client.py"),
    (Join-Path $API_DIR "hmac_client.py"),
    (Join-Path $API_DIR "__init__.py")
)
foreach ($f in $required) {
    if (-not (Test-Path $f)) {
        Write-Host "   ERRO: $f nao encontrado!" -ForegroundColor Red
        Write-Host "   Execute patch_github_client.ps1 primeiro." -ForegroundColor Red
        exit 1
    }
    Write-Host "   OK: $([System.IO.Path]::GetFileName($f))" -ForegroundColor Green
}

# ══════════════════════════════════════════════════════════════
# 2. RENOMEIA days_editor.py -> _legacy
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "2. Renomeando days_editor.py para legacy..." -ForegroundColor Yellow

$DAYS_OLD = Join-Path $COMP_DIR "days_editor.py"
$DAYS_NEW = Join-Path $COMP_DIR "days_editor_legacy.py"

if (Test-Path $DAYS_OLD) {
    if (-not (Test-Path $DAYS_NEW)) {
        Rename-Item $DAYS_OLD $DAYS_NEW
        Write-Host "   OK: days_editor.py -> days_editor_legacy.py" -ForegroundColor Green
    } else {
        Write-Host "   days_editor_legacy.py ja existe" -ForegroundColor Gray
    }
} else {
    Write-Host "   days_editor.py nao encontrado (ja renomeado?)" -ForegroundColor Gray
}

# ══════════════════════════════════════════════════════════════
# 3. CRIA DIRETORIOS
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "3. Criando diretorios..." -ForegroundColor Yellow

foreach ($dir in @($SVC_DIR, $PAGES_DIR)) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir | Out-Null
        Write-Host "   CRIADO: $([System.IO.Path]::GetFileName($dir))/" -ForegroundColor Green
    } else {
        Write-Host "   OK: $([System.IO.Path]::GetFileName($dir))/ ja existe" -ForegroundColor Gray
    }
}

foreach ($dir in @($SVC_DIR, $PAGES_DIR, $COMP_DIR)) {
    $init = Join-Path $dir "__init__.py"
    if (-not (Test-Path $init)) {
        New-Item -ItemType File -Path $init | Out-Null
        Write-Host "   CRIADO: __init__.py em $([System.IO.Path]::GetFileName($dir))/" -ForegroundColor Green
    }
}

# ══════════════════════════════════════════════════════════════
# HELPER — escreve arquivo UTF-8
# ══════════════════════════════════════════════════════════════
function Write-PyFile($path, $lines) {
    $content = $lines -join "`n"
    [System.IO.File]::WriteAllText($path, $content, [System.Text.UTF8Encoding]::new($false))
    Write-Host "   ESCRITO: $([System.IO.Path]::GetFileName($path))" -ForegroundColor Green
}

# ══════════════════════════════════════════════════════════════
# 4. services/wp_service.py
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "4. Escrevendo services/wp_service.py..." -ForegroundColor Yellow

$WP_SVC = Join-Path $SVC_DIR "wp_service.py"
Write-PyFile $WP_SVC @(
    '# services/wp_service.py',
    '# -*- coding: utf-8 -*-',
    'import hmac as _hmac',
    'import hashlib',
    'import time',
    'import json',
    'import requests',
    '',
    '',
    'class WPService:',
    '',
    '    def __init__(self, base: str, secret: str):',
    '        self.base   = base.rstrip("/")',
    '        self.secret = secret',
    '',
    '    def notify_publicada(self, visit_ref: str, editorial: dict) -> bool:',
    '        payload = {',
    '            "visit_ref":    visit_ref,',
    '            "state":        "publicada",',
    '            "title_pt":     editorial["meta"].get("title_pt"),',
    '            "title_en":     editorial["meta"].get("title_en"),',
    '            "preview_pt":   editorial["meta"].get("preview_pt"),',
    '            "preview_en":   editorial["meta"].get("preview_en"),',
    '            "cover_url":    editorial["exports"].get("cover_url"),',
    '            "pdf_pt_url":   editorial["exports"].get("pdf_pt_url"),',
    '            "pdf_en_url":   editorial["exports"].get("pdf_en_url"),',
    '            "published_at": editorial["meta"].get("published_at"),',
    '            "stats":        editorial.get("stats", {}),',
    '        }',
    '        r = requests.post(',
    '            self.base + "/vana/v1/ingest-revista",',
    '            json    = payload,',
    '            headers = self._headers(payload),',
    '            timeout = 15,',
    '        )',
    '        return r.status_code == 200',
    '',
    '    def notify_state_change(self, visit_ref: str, state: str) -> bool:',
    '        payload = {"visit_ref": visit_ref, "state": state}',
    '        r = requests.post(',
    '            self.base + "/vana/v1/ingest-revista",',
    '            json    = payload,',
    '            headers = self._headers(payload),',
    '            timeout = 10,',
    '        )',
    '        return r.status_code == 200',
    '',
    '    def _headers(self, payload: dict) -> dict:',
    '        ts   = str(int(time.time()))',
    '        body = json.dumps(payload, separators=(",", ":"))',
    '        sig  = _hmac.new(',
    '            self.secret.encode(),',
    '            (ts + "." + body).encode(),',
    '            hashlib.sha256,',
    '        ).hexdigest()',
    '        return {',
    '            "X-Vana-Timestamp": ts,',
    '            "X-Vana-Signature": sig,',
    '            "Content-Type":     "application/json",',
    '        }',
    ''
)

# ══════════════════════════════════════════════════════════════
# 5. services/r2_service.py
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "5. Escrevendo services/r2_service.py..." -ForegroundColor Yellow

$R2_SVC = Join-Path $SVC_DIR "r2_service.py"
Write-PyFile $R2_SVC @(
    '# services/r2_service.py',
    '# -*- coding: utf-8 -*-',
    'import boto3',
    'from botocore.client import Config as BotoConfig',
    '',
    '',
    'class R2Service:',
    '',
    '    def __init__(',
    '        self,',
    '        endpoint:    str,',
    '        access_key:  str,',
    '        secret_key:  str,',
    '        bucket:      str,',
    '        public_base: str,',
    '    ):',
    '        self.client = boto3.client(',
    '            "s3",',
    '            endpoint_url          = endpoint,',
    '            aws_access_key_id     = access_key,',
    '            aws_secret_access_key = secret_key,',
    '            config                = BotoConfig(signature_version="s3v4"),',
    '        )',
    '        self.bucket      = bucket',
    '        self.public_base = public_base.rstrip("/")',
    '',
    '    def upload_pdf(self, visit_ref: str, lang: str, pdf_bytes: bytes) -> str:',
    '        key = "revistas/" + visit_ref + "/" + lang + ".pdf"',
    '        self.client.put_object(',
    '            Bucket       = self.bucket,',
    '            Key          = key,',
    '            Body         = pdf_bytes,',
    '            ContentType  = "application/pdf",',
    '            CacheControl = "public, max-age=31536000",',
    '        )',
    '        return self.public_base + "/" + key',
    '',
    '    def upload_cover(',
    '        self,',
    '        visit_ref:    str,',
    '        img_bytes:    bytes,',
    '        content_type: str = "image/jpeg",',
    '    ) -> str:',
    '        key = "revistas/" + visit_ref + "/cover.jpg"',
    '        self.client.put_object(',
    '            Bucket       = self.bucket,',
    '            Key          = key,',
    '            Body         = img_bytes,',
    '            ContentType  = content_type,',
    '            CacheControl = "public, max-age=31536000",',
    '        )',
    '        return self.public_base + "/" + key',
    ''
)

# ══════════════════════════════════════════════════════════════
# 6. services/pdf_service.py
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "6. Escrevendo services/pdf_service.py..." -ForegroundColor Yellow

$PDF_SVC = Join-Path $SVC_DIR "pdf_service.py"
Write-PyFile $PDF_SVC @(
    '# services/pdf_service.py',
    '# -*- coding: utf-8 -*-',
    'from weasyprint import HTML, CSS',
    'from jinja2 import Environment, FileSystemLoader',
    'from datetime import datetime',
    'import os',
    '',
    '',
    'class PDFService:',
    '',
    '    def __init__(self, templates_dir: str = "templates/revista"):',
    '        self.jinja = Environment(',
    '            loader = FileSystemLoader(templates_dir)',
    '        )',
    '',
    '    def generate(',
    '        self,',
    '        editorial: dict,',
    '        visit:     dict,',
    '        lang:      str,',
    '        passages:  dict,',
    '    ) -> bytes:',
    '        template = self.jinja.get_template("revista_" + lang + ".html")',
    '        html_str = template.render(',
    '            editorial = editorial,',
    '            visit     = visit,',
    '            lang      = lang,',
    '            passages  = passages,',
    '            generated = datetime.utcnow().strftime("%d/%m/%Y"),',
    '        )',
    '        css_path = os.path.join(',
    '            self.jinja.loader.searchpath[0],',
    '            "style_" + lang + ".css"',
    '        )',
    '        stylesheets = [CSS(filename=css_path)] if os.path.exists(css_path) else []',
    '        return HTML(string=html_str).write_pdf(stylesheets=stylesheets)',
    ''
)

# ══════════════════════════════════════════════════════════════
# 7. components/block_editor.py
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "7. Escrevendo components/block_editor.py..." -ForegroundColor Yellow

$BLK_ED = Join-Path $COMP_DIR "block_editor.py"
Write-PyFile $BLK_ED @(
    '# components/block_editor.py',
    '# -*- coding: utf-8 -*-',
    'import streamlit as st',
    '',
    '',
    'def render_block(block: dict, visit: dict, gh, visit_ref: str) -> dict:',
    '    btype   = block["type"]',
    '    content = block.get("content", {})',
    '    editors = {',
    '        "context_vaishnava":   _edit_context_vaishnava,',
    '        "opening":             _edit_text_block,',
    '        "closing":             _edit_text_block,',
    '        "gurudeva_photos":     _edit_photo_block,',
    '        "sangha_photos":       _edit_photo_block,',
    '        "teaching_atmosphere": _edit_teaching,',
    '        "sangha_portrait":     _edit_sangha_portrait,',
    '        "sangha_voices":       _edit_sangha_voices,',
    '    }',
    '    fn = editors.get(btype)',
    '    if fn:',
    '        return fn(content, visit, gh, visit_ref, btype)',
    '    st.json(content)',
    '    return content',
    '',
    '',
    'def _edit_text_block(content, visit, gh, visit_ref, btype):',
    '    c1, c2 = st.columns(2)',
    '    pt = c1.text_area("Texto PT", value=content.get("text_pt", ""), height=150, key=btype+"_pt")',
    '    en = c2.text_area("Texto EN", value=content.get("text_en", ""), height=150, key=btype+"_en")',
    '    return {"text_pt": pt, "text_en": en}',
    '',
    '',
    'def _edit_context_vaishnava(content, visit, gh, visit_ref, btype):',
    '    days = visit.get("days", [])',
    '    tithi_opts = {d.get("date_local","?"): d.get("tithi", {}) for d in days if d.get("tithi")}',
    '    c1, c2 = st.columns(2)',
    '    with c1:',
    '        loc_pt  = st.text_input("Local PT",    value=content.get("location_pt",""),  key="ctx_loc_pt")',
    '        dei_pt  = st.text_input("Deidades PT", value=content.get("deities_pt",""),   key="ctx_dei_pt")',
    '        tithi_data = {}',
    '        if tithi_opts:',
    '            sel = st.selectbox("Tithi do dia", list(tithi_opts.keys()), key="ctx_tithi_day")',
    '            tithi_data = tithi_opts[sel]',
    '        tname_pt = st.text_input("Tithi PT",       value=content.get("tithi_name_pt") or tithi_data.get("festival",""),  key="ctx_tname_pt")',
    '        masa_pt  = st.text_input("Masa PT",        value=content.get("masa_pt")       or tithi_data.get("masa",""),      key="ctx_masa_pt")',
    '        obs_pt   = st.text_area("Observancia PT",  value=content.get("observance_pt") or tithi_data.get("observance_pt",""), height=80, key="ctx_obs_pt")',
    '        note_pt  = st.text_area("Nota editorial PT", value=content.get("editorial_note_pt",""), height=120, key="ctx_note_pt")',
    '    with c2:',
    '        loc_en   = st.text_input("Local EN",       value=content.get("location_en",""),  key="ctx_loc_en")',
    '        dei_en   = st.text_input("Deidades EN",    value=content.get("deities_en",""),   key="ctx_dei_en")',
    '        tname_en = st.text_input("Tithi EN",       value=content.get("tithi_name_en",""),key="ctx_tname_en")',
    '        masa_en  = st.text_input("Masa EN",        value=content.get("masa_en",""),      key="ctx_masa_en")',
    '        obs_en   = st.text_area("Observancia EN",  value=content.get("observance_en",""),height=80, key="ctx_obs_en")',
    '        note_en  = st.text_area("Nota editorial EN",value=content.get("editorial_note_en",""),height=120,key="ctx_note_en")',
    '    return {',
    '        "location_pt": loc_pt,   "location_en": loc_en,',
    '        "deities_pt":  dei_pt,   "deities_en":  dei_en,',
    '        "tithi_name_pt": tname_pt, "tithi_name_en": tname_en,',
    '        "masa_pt": masa_pt,      "masa_en": masa_en,',
    '        "observance_pt": obs_pt, "observance_en": obs_en,',
    '        "editorial_note_pt": note_pt, "editorial_note_en": note_en,',
    '    }',
    '',
    '',
    'def _edit_photo_block(content, visit, gh, visit_ref, btype):',
    '    photos   = content.get("photos", [])',
    '    only_g   = (btype == "gurudeva_photos")',
    '    all_refs = gh.get_photo_refs(visit_ref, only_gurudeva=only_g)',
    '    selected = st.multiselect("Selecionar fotos", options=all_refs,',
    '                              default=[p["ref"] for p in photos if p["ref"] in all_refs],',
    '                              key="photos_"+btype)',
    '    result = []',
    '    for ref in selected:',
    '        ex = next((p for p in photos if p["ref"] == ref), {})',
    '        with st.expander(ref):',
    '            c1, c2 = st.columns(2)',
    '            cap_pt = c1.text_input("Legenda PT", value=ex.get("caption_editorial_pt",""), key="cap_pt_"+ref)',
    '            cap_en = c2.text_input("Legenda EN", value=ex.get("caption_editorial_en",""), key="cap_en_"+ref)',
    '        result.append({"ref": ref, "caption_editorial_pt": cap_pt, "caption_editorial_en": cap_en})',
    '    return {"photos": result}',
    '',
    '',
    'def _edit_teaching(content, visit, gh, visit_ref, btype):',
    '    c1, c2   = st.columns(2)',
    '    intro_pt = c1.text_area("Intro PT", value=content.get("editorial_intro_pt",""), height=100, key="teach_intro_pt")',
    '    intro_en = c2.text_area("Intro EN", value=content.get("editorial_intro_en",""), height=100, key="teach_intro_en")',
    '    passages    = content.get("passages", [])',
    '    all_refs    = gh.get_passage_refs(visit_ref)',
    '    sel_refs    = st.multiselect("Passages", options=all_refs,',
    '                                 default=[p["ref"] for p in passages if p["ref"] in all_refs],',
    '                                 key="teach_passages")',
    '    result = []',
    '    for ref in sel_refs:',
    '        ex = next((p for p in passages if p["ref"] == ref), {})',
    '        with st.expander(ref):',
    '            c1, c2 = st.columns(2)',
    '            ctx_pt = c1.text_area("Contexto PT", value=ex.get("context_pt",""), height=80, key="pctx_pt_"+ref)',
    '            ctx_en = c2.text_area("Contexto EN", value=ex.get("context_en",""), height=80, key="pctx_en_"+ref)',
    '        result.append({"ref": ref, "context_pt": ctx_pt or None, "context_en": ctx_en or None})',
    '    return {"editorial_intro_pt": intro_pt, "editorial_intro_en": intro_en, "passages": result}',
    '',
    '',
    'def _edit_sangha_portrait(content, visit, gh, visit_ref, btype):',
    '    ROLES = [',
    '        ("kirtan_leader", "Kirtan",            "Kirtan"),',
    '        ("mridanga",      "Mridanga",           "Mridanga"),',
    '        ("kitchen",       "Cozinha/Prasadam",   "Kitchen/Prasadam"),',
    '        ("pujari",        "Pujari",             "Pujari"),',
    '        ("organizer",     "Organizadores",      "Organizers"),',
    '        ("question",      "Pergunta a Gurudeva","Question to Gurudeva"),',
    '    ]',
    '    performers = gh.get_performers(visit_ref)',
    '    roles      = content.get("roles", [])',
    '    result     = []',
    '    for key, label_pt, label_en in ROLES:',
    '        ex          = next((r for r in roles if r.get("role_pt") == label_pt), {})',
    '        suggestions = [p["name"] for p in performers if p.get("role") == key]',
    '        with st.expander(label_pt):',
    '            if suggestions:',
    '                st.caption("Sugerido: " + ", ".join(suggestions))',
    '            names_str = st.text_input("Nomes (virgula)", value=", ".join(ex.get("names", suggestions)), key="role_"+key)',
    '            note_pt   = st.text_input("Nota PT", value=ex.get("note_pt",""), key="rnote_pt_"+key)',
    '            note_en   = st.text_input("Nota EN", value=ex.get("note_en",""), key="rnote_en_"+key)',
    '            names = [n.strip() for n in names_str.split(",") if n.strip()]',
    '            if names:',
    '                result.append({"role_pt": label_pt, "role_en": label_en,',
    '                               "names": names, "note_pt": note_pt or None, "note_en": note_en or None})',
    '    return {"roles": result}',
    '',
    '',
    'def _edit_sangha_voices(content, visit, gh, visit_ref, btype):',
    '    c1, c2  = st.columns(2)',
    '    note_pt = c1.text_area("Nota PT", value=content.get("editorial_note_pt",""), height=80, key="sv_note_pt")',
    '    note_en = c2.text_area("Nota EN", value=content.get("editorial_note_en",""), height=80, key="sv_note_en")',
    '    refs_str = st.text_input("IDs submissions aprovadas (virgula)",',
    '                             value=", ".join(str(r) for r in content.get("submission_refs",[])),',
    '                             key="sv_refs")',
    '    refs = [int(r.strip()) for r in refs_str.split(",") if r.strip().isdigit()]',
    '    return {"editorial_note_pt": note_pt, "editorial_note_en": note_en, "submission_refs": refs}',
    ''
)

# ══════════════════════════════════════════════════════════════
# 8. pages/4_Revista.py
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "8. Escrevendo pages/4_Revista.py..." -ForegroundColor Yellow

$REVISTA = Join-Path $PAGES_DIR "4_Revista.py"
Write-PyFile $REVISTA @(
    '# pages/4_Revista.py',
    '# -*- coding: utf-8 -*-',
    'import streamlit as st',
    'from datetime import datetime, timezone',
    'from api.github_client       import GitHubClient',
    'from services.wp_service     import WPService',
    'from services.r2_service     import R2Service',
    'from services.pdf_service    import PDFService',
    'from components.block_editor import render_block',
    '',
    'BLOCK_TYPES = [',
    '    ("context_vaishnava",   "Momento Sagrado"),',
    '    ("opening",             "Abertura Editorial"),',
    '    ("gurudeva_photos",     "Fotos de Gurudeva"),',
    '    ("teaching_atmosphere", "O que estava no ar"),',
    '    ("sangha_portrait",     "Retrato da Sanga"),',
    '    ("sangha_voices",       "Vozes da Sanga"),',
    '    ("sangha_photos",       "Momentos da Sanga"),',
    '    ("closing",             "Para Levar"),',
    ']',
    '',
    'STATE_LABELS = {"coleta": "Coleta aberta", "edicao": "Em edicao", "publicada": "Publicada"}',
    'STATE_COLORS = {"coleta": "orange",        "edicao": "blue",      "publicada": "green"}',
    '',
    '',
    '@st.cache_resource',
    'def get_clients():',
    '    gh  = GitHubClient(',
    '        token  = st.secrets["github"]["token"],',
    '        repo   = st.secrets["github"]["repo"],',
    '        branch = st.secrets["github"].get("branch", "main"),',
    '    )',
    '    wp  = WPService(',
    '        base   = st.secrets["vana"]["api_base"],',
    '        secret = st.secrets["vana"]["ingest_secret"],',
    '    )',
    '    r2  = R2Service(',
    '        endpoint    = st.secrets["r2"]["endpoint"],',
    '        access_key  = st.secrets["r2"]["access_key"],',
    '        secret_key  = st.secrets["r2"]["secret_key"],',
    '        bucket      = st.secrets["r2"]["bucket"],',
    '        public_base = st.secrets["r2"]["public_base"],',
    '    )',
    '    pdf = PDFService()',
    '    return gh, wp, r2, pdf',
    '',
    '',
    'def main():',
    '    st.title("Revista - Painel Editorial")',
    '    try:',
    '        gh, wp, r2, pdf = get_clients()',
    '    except Exception as e:',
    '        st.error("Erro ao conectar servicos: " + str(e))',
    '        st.stop()',
    '',
    '    with st.sidebar:',
    '        st.markdown("### Revista")',
    '        st.divider()',
    '        visit_ref   = st.text_input("Codigo da visita", value="", placeholder="vrindavan-2026-02")',
    '        editor_name = st.text_input("Seu nome", placeholder="Madhava Dasa")',
    '        st.divider()',
    '        if st.button("Recarregar", use_container_width=True):',
    '            st.cache_data.clear()',
    '            st.rerun()',
    '',
    '    if not visit_ref:',
    '        st.info("Digite o codigo da visita na sidebar.")',
    '        st.stop()',
    '',
    '    @st.cache_data(ttl=30)',
    '    def load_data(vref):',
    '        return gh.get_visit(vref), gh.get_editorial(vref)',
    '',
    '    try:',
    '        visit, editorial = load_data(visit_ref)',
    '    except Exception as e:',
    '        st.error("Erro ao carregar dados: " + str(e))',
    '        st.stop()',
    '',
    '    if not visit:',
    '        st.warning("visit.json nao encontrado para: " + visit_ref)',
    '        st.stop()',
    '',
    '    state = editorial.get("state", "coleta")',
    '    c1, c2, c3 = st.columns([4, 2, 2])',
    '    c1.markdown("## Revista `" + visit_ref + "`")',
    '    color = STATE_COLORS.get(state, "gray")',
    '    c2.markdown("<span style=color:" + color + ";font-weight:bold>" + STATE_LABELS.get(state, state) + "</span>", unsafe_allow_html=True)',
    '    if state == "coleta" and editor_name:',
    '        with c3:',
    '            if st.button("Iniciar edicao", type="primary"):',
    '                editorial["state"]              = "edicao"',
    '                editorial["meta"]["editor"]     = editor_name',
    '                editorial["meta"]["started_at"] = datetime.now(timezone.utc).isoformat()',
    '                editorial = gh.append_audit(editorial, "state_changed", editor_name, **{"from":"coleta","to":"edicao"})',
    '                gh.save_editorial(visit_ref, editorial, editor_name, "state: coleta para edicao")',
    '                wp.notify_state_change(visit_ref, "edicao")',
    '                st.cache_data.clear()',
    '                st.rerun()',
    '    st.divider()',
    '',
    '    if state == "coleta":',
    '        _view_coleta(visit, editorial, visit_ref, editor_name, gh, wp)',
    '    elif state == "edicao":',
    '        _view_edicao(visit, editorial, visit_ref, editor_name, gh, wp, r2, pdf)',
    '    elif state == "publicada":',
    '        _view_publicada(editorial)',
    '    else:',
    '        st.error("Estado desconhecido: " + state)',
    '',
    '',
    'def _view_coleta(visit, editorial, visit_ref, editor_name, gh, wp):',
    '    coleta = editorial.get("coleta", {})',
    '    c1, c2 = st.columns(2)',
    '    with c1:',
    '        st.markdown("### Status da Coleta")',
    '        opened = coleta.get("opened_at", "")',
    '        st.metric("Aberta desde", opened[:10] if opened else "?")',
    '        st.metric("Notificacoes", len(coleta.get("notify_list", [])))',
    '        if coleta.get("paused"):',
    '            st.warning("Pausada por " + coleta.get("paused_by","?"))',
    '            if editor_name and st.button("Retomar coleta"):',
    '                editorial["coleta"].update({"paused":False,"paused_at":None,"paused_by":None})',
    '                editorial = gh.append_audit(editorial, "coleta_resumed", editor_name)',
    '                gh.save_editorial(visit_ref, editorial, editor_name, "coleta: retomada")',
    '                st.cache_data.clear(); st.rerun()',
    '        else:',
    '            st.success("Coleta ativa")',
    '            if editor_name and st.button("Pausar coleta"):',
    '                editorial["coleta"].update({"paused":True,"paused_at":datetime.now(timezone.utc).isoformat(),"paused_by":editor_name})',
    '                editorial = gh.append_audit(editorial, "coleta_paused", editor_name)',
    '                gh.save_editorial(visit_ref, editorial, editor_name, "coleta: pausada")',
    '                wp.notify_state_change(visit_ref, "coleta_paused")',
    '                st.cache_data.clear(); st.rerun()',
    '    with c2:',
    '        st.markdown("### Passages Mais Votados")',
    '        st.info("Conectar ao endpoint /vana/v1/top-passages")',
    '    st.divider()',
    '    st.markdown("### Lista de Notificacao")',
    '    notify_list = coleta.get("notify_list", [])',
    '    notify_str  = st.text_area("Emails (um por linha)", value="\n".join(notify_list), height=100)',
    '    if st.button("Salvar lista", type="secondary"):',
    '        new_list = [e.strip() for e in notify_str.splitlines() if e.strip()]',
    '        editorial["coleta"]["notify_list"] = new_list',
    '        editorial = gh.append_audit(editorial, "notify_list_updated", editor_name or "anon", count=len(new_list))',
    '        gh.save_editorial(visit_ref, editorial, editor_name or "anon", "coleta: notify_list atualizada")',
    '        st.success("Lista salva com " + str(len(new_list)) + " email(s).")',
    '        st.cache_data.clear()',
    '    st.divider()',
    '    _render_audit(editorial)',
    '',
    '',
    'def _view_edicao(visit, editorial, visit_ref, editor_name, gh, wp, r2, pdf):',
    '    with st.expander("Informacoes da Revista", expanded=True):',
    '        c1, c2 = st.columns(2)',
    '        with c1:',
    '            title_pt   = st.text_input("Titulo PT",  value=editorial["meta"].get("title_pt") or "",   key="meta_title_pt")',
    '            preview_pt = st.text_area("Previa PT",   value=editorial["meta"].get("preview_pt") or "", height=80, key="meta_preview_pt")',
    '        with c2:',
    '            title_en   = st.text_input("Titulo EN",  value=editorial["meta"].get("title_en") or "",   key="meta_title_en")',
    '            preview_en = st.text_area("Previa EN",   value=editorial["meta"].get("preview_en") or "", height=80, key="meta_preview_en")',
    '        cover_ref = st.text_input("Ref foto de capa", value=editorial["meta"].get("cover_photo_ref") or "", key="meta_cover")',
    '        if st.button("Salvar meta", type="secondary"):',
    '            editorial["meta"].update({"title_pt":title_pt,"title_en":title_en,"preview_pt":preview_pt,"preview_en":preview_en,"cover_photo_ref":cover_ref})',
    '            editorial = gh.append_audit(editorial, "meta_updated", editor_name or "anon")',
    '            gh.save_editorial(visit_ref, editorial, editor_name or "anon", "meta: atualizada")',
    '            st.success("Meta salva!"); st.cache_data.clear()',
    '    st.divider()',
    '    st.markdown("### Blocos da Revista")',
    '    blocks = editorial.get("blocks", [])',
    '    for i, block in enumerate(blocks):',
    '        with st.container(border=True):',
    '            cm, ct, cd = st.columns([1,8,1])',
    '            with cm:',
    '                if i > 0 and st.button("cima", key="up_"+str(i)):',
    '                    blocks[i], blocks[i-1] = blocks[i-1], blocks[i]',
    '                    for j,b in enumerate(blocks): b["order"]=j+1',
    '                    editorial["blocks"] = blocks',
    '                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+str(i)+": cima")',
    '                    st.cache_data.clear(); st.rerun()',
    '                if i < len(blocks)-1 and st.button("baixo", key="dn_"+str(i)):',
    '                    blocks[i], blocks[i+1] = blocks[i+1], blocks[i]',
    '                    for j,b in enumerate(blocks): b["order"]=j+1',
    '                    editorial["blocks"] = blocks',
    '                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+str(i)+": baixo")',
    '                    st.cache_data.clear(); st.rerun()',
    '            with ct:',
    '                label = next((l for t,l in BLOCK_TYPES if t==block["type"]), block["type"])',
    '                st.markdown("**" + label + "**")',
    '            with cd:',
    '                if st.button("X", key="del_"+str(i)):',
    '                    blocks.pop(i)',
    '                    editorial["blocks"] = blocks',
    '                    editorial = gh.append_audit(editorial, "block_removed", editor_name or "anon", block=block["type"])',
    '                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+block["type"]+": removido")',
    '                    st.cache_data.clear(); st.rerun()',
    '            updated = render_block(block, visit, gh, visit_ref)',
    '            if updated != block.get("content"):',
    '                block["content"] = updated',
    '                editorial["blocks"] = blocks',
    '                gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+block["type"]+": editado")',
    '    st.divider()',
    '    st.markdown("### Adicionar Bloco")',
    '    existing = {b["type"] for b in blocks}',
    '    available = [(t,l) for t,l in BLOCK_TYPES if t not in existing]',
    '    if available:',
    '        cols = st.columns(4)',
    '        for idx,(btype,blabel) in enumerate(available):',
    '            with cols[idx%4]:',
    '                if st.button(blabel, key="add_"+btype, use_container_width=True):',
    '                    blocks.append({"order":len(blocks)+1,"type":btype,"locked":False,"content":{}})',
    '                    editorial["blocks"] = blocks',
    '                    editorial = gh.append_audit(editorial, "block_added", editor_name or "anon", block=btype)',
    '                    gh.save_editorial(visit_ref, editorial, editor_name or "anon", "bloco "+btype+": adicionado")',
    '                    st.cache_data.clear(); st.rerun()',
    '    else:',
    '        st.success("Todos os blocos presentes!")',
    '    st.divider()',
    '    st.markdown("### Publicacao (Supervisor)")',
    '    meta  = editorial.get("meta",{})',
    '    ready = bool(meta.get("title_pt")) and bool(meta.get("preview_pt")) and len(blocks)>=3',
    '    if not ready:',
    '        missing = []',
    '        if not meta.get("title_pt"):   missing.append("titulo PT")',
    '        if not meta.get("preview_pt"): missing.append("previa PT")',
    '        if len(blocks)<3:              missing.append("min 3 blocos")',
    '        st.warning("Faltam: " + ", ".join(missing))',
    '    else:',
    '        supervisor = st.text_input("Nome do Supervisor", placeholder="Govinda Dasa", key="supervisor_name")',
    '        if supervisor and st.button("Gerar PDF e Publicar", type="primary"):',
    '            with st.spinner("Publicando..."):',
    '                _publicar(visit, editorial, visit_ref, supervisor, gh, wp, r2, pdf)',
    '    st.divider()',
    '    _render_audit(editorial)',
    '',
    '',
    'def _view_publicada(editorial):',
    '    meta    = editorial.get("meta",{})',
    '    exports = editorial.get("exports",{})',
    '    stats   = editorial.get("stats",{})',
    '    st.success("Revista publicada!")',
    '    c1,c2,c3 = st.columns(3)',
    '    pub = meta.get("published_at","")',
    '    c1.metric("Publicada em", pub[:10] if pub else "?")',
    '    c2.metric("Editor",      meta.get("editor","?"))',
    '    c3.metric("Supervisor",  meta.get("supervisor","?"))',
    '    st.divider()',
    '    col1, col2 = st.columns(2)',
    '    with col1:',
    '        st.markdown("### Downloads")',
    '        pt = exports.get("pdf_pt_url")',
    '        en = exports.get("pdf_en_url")',
    '        if pt: st.markdown("[PDF Portugues](" + pt + ")")',
    '        if en: st.markdown("[PDF English](" + en + ")")',
    '        if not pt and not en: st.info("Nenhum PDF disponivel.")',
    '    with col2:',
    '        st.markdown("### Estatisticas")',
    '        st.metric("Devotos", stats.get("devotees_count","?"))',
    '        st.metric("Paises",  stats.get("countries_count","?"))',
    '        st.metric("Reacoes", stats.get("total_reactions","?"))',
    '    st.divider()',
    '    _render_audit(editorial)',
    '',
    '',
    'def _publicar(visit, editorial, visit_ref, supervisor, gh, wp, r2, pdf):',
    '    try:',
    '        pdf_pt = pdf.generate(editorial, visit, "pt", {})',
    '        pdf_en = pdf.generate(editorial, visit, "en", {})',
    '        st.write("PDFs gerados.")',
    '    except Exception as e:',
    '        st.error("Erro PDF: " + str(e)); return',
    '    try:',
    '        url_pt = r2.upload_pdf(visit_ref, "pt", pdf_pt)',
    '        url_en = r2.upload_pdf(visit_ref, "en", pdf_en)',
    '        st.write("Upload R2: OK")',
    '    except Exception as e:',
    '        st.error("Erro R2: " + str(e)); return',
    '    cover_url = None',
    '    cover_ref = editorial["meta"].get("cover_photo_ref")',
    '    if cover_ref:',
    '        cover_url = st.secrets["r2"]["public_base"].rstrip("/") + "/visits/" + visit_ref + "/" + cover_ref',
    '    now = datetime.now(timezone.utc).isoformat()',
    '    editorial["state"]                 = "publicada"',
    '    editorial["meta"]["supervisor"]    = supervisor',
    '    editorial["meta"]["published_at"]  = now',
    '    editorial["exports"]["pdf_pt_url"] = url_pt',
    '    editorial["exports"]["pdf_en_url"] = url_en',
    '    editorial["exports"]["cover_url"]  = cover_url',
    '    editorial = gh.append_audit(editorial, "state_changed", supervisor, note="Aprovado", **{"from":"edicao","to":"publicada"})',
    '    editorial = gh.append_audit(editorial, "pdf_generated", "streamlit-auto", langs=["pt","en"])',
    '    try:',
    '        gh.save_editorial(visit_ref, editorial, supervisor, "publicada")',
    '        st.write("GitHub: OK")',
    '    except Exception as e:',
    '        st.error("Erro GitHub: " + str(e)); return',
    '    try:',
    '        ok = wp.notify_publicada(visit_ref, editorial)',
    '        if ok: st.write("WordPress: OK")',
    '        else:  st.warning("WordPress retornou erro.")',
    '    except Exception as e:',
    '        st.warning("Falha WP: " + str(e))',
    '    st.success("Revista publicada!")',
    '    st.balloons()',
    '    st.cache_data.clear()',
    '',
    '',
    'def _render_audit(editorial):',
    '    with st.expander("Historico de acoes"):',
    '        audit = editorial.get("audit",[])',
    '        if not audit:',
    '            st.caption("Nenhuma acao registrada.")',
    '            return',
    '        for entry in reversed(audit):',
    '            at     = entry.get("at","")[:16]',
    '            action = entry.get("action","?")',
    '            by     = entry.get("by","?")',
    '            note   = entry.get("note","")',
    '            line   = "`" + at + "` - **" + action + "** por *" + by + "*"',
    '            if note: line += " - " + note',
    '            st.markdown(line)',
    '',
    '',
    'main()',
    ''
)

# ══════════════════════════════════════════════════════════════
# 9. SECRETS — adiciona blocos [r2] e ingest_secret
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "9. Atualizando secrets.toml..." -ForegroundColor Yellow

if (Test-Path $SECRETS) {
    $sc = Get-Content $SECRETS -Raw
    if ($sc -notmatch '\[r2\]') {
        $r2 = "`n[r2]`nendpoint    = `"https://SEU_ACCOUNT.r2.cloudflarestorage.com`"`naccess_key  = `"SEU_ACCESS_KEY`"`nsecret_key  = `"SEU_SECRET_KEY`"`nbucket      = `"vanamadhuryam`"`npublic_base = `"https://r2.vanamadhuryam.org`"`n"
        Add-Content -Path $SECRETS -Value $r2 -Encoding UTF8
        Write-Host "   OK: bloco [r2] adicionado" -ForegroundColor Green
    } else {
        Write-Host "   [r2] ja existe" -ForegroundColor Gray
    }
    if ($sc -notmatch 'ingest_secret') {
        Add-Content -Path $SECRETS -Value "`ningest_secret = `"SEU_INGEST_SECRET`"" -Encoding UTF8
        Write-Host "   OK: ingest_secret adicionado" -ForegroundColor Green
    } else {
        Write-Host "   ingest_secret ja existe" -ForegroundColor Gray
    }
} else {
    Write-Host "   secrets.toml nao encontrado - crie manualmente" -ForegroundColor Yellow
}

# ══════════════════════════════════════════════════════════════
# 10. VALIDA SINTAXE
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "10. Validando sintaxe Python..." -ForegroundColor Yellow

$pyFiles = @($WP_SVC, $R2_SVC, $PDF_SVC, $BLK_ED, $REVISTA)
foreach ($f in $pyFiles) {
    try {
        $r = python -c "import ast; ast.parse(open(r'$f').read()); print('OK')" 2>&1
        if ("$r" -match "OK") {
            Write-Host "   OK: $([System.IO.Path]::GetFileName($f))" -ForegroundColor Green
        } else {
            Write-Host "   ERRO: $([System.IO.Path]::GetFileName($f)) - $r" -ForegroundColor Red
        }
    } catch {
        Write-Host "   Python nao encontrado no PATH" -ForegroundColor Yellow
        break
    }
}

# ══════════════════════════════════════════════════════════════
# RESUMO
# ══════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Patch concluido!" -ForegroundColor Green
Write-Host ""
Write-Host "  Arquivos criados:" -ForegroundColor White
Write-Host "    services\wp_service.py" -ForegroundColor Gray
Write-Host "    services\r2_service.py" -ForegroundColor Gray
Write-Host "    services\pdf_service.py" -ForegroundColor Gray
Write-Host "    components\block_editor.py" -ForegroundColor Gray
Write-Host "    pages\4_Revista.py" -ForegroundColor Gray
Write-Host ""
Write-Host "  Proximos passos:" -ForegroundColor White
Write-Host "  1. Preencha .streamlit\secrets.toml" -ForegroundColor Yellow
Write-Host "     [r2] endpoint, access_key, secret_key" -ForegroundColor Gray
Write-Host "     [vana] ingest_secret" -ForegroundColor Gray
Write-Host "  2. Crie templates\revista\revista_pt.html" -ForegroundColor Yellow
Write-Host "     (necessario para gerar PDF)" -ForegroundColor Gray
Write-Host "  3. pip install boto3 weasyprint jinja2" -ForegroundColor Yellow
Write-Host "  4. streamlit run app.py" -ForegroundColor Yellow
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""
