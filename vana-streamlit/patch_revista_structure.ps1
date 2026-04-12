# patch_revista_structure.ps1
# Executa da raiz do vana_crud
# Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
# .\patch_revista_structure.ps1

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ROOT       = $PSScriptRoot
$COMP_DIR   = Join-Path $ROOT "components"
$SVC_DIR    = Join-Path $ROOT "services"
$PAGES_DIR  = Join-Path $ROOT "pages"
$API_DIR    = Join-Path $ROOT "api"

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Vana Mission Control - Patch Revista       " -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# ── 1. Valida pre-requisitos ───────────────────────────────
Write-Host "1. Validando pre-requisitos..." -ForegroundColor Yellow

$required = @(
    (Join-Path $ROOT "app.py"),
    (Join-Path $API_DIR "github_client.py"),
    (Join-Path $API_DIR "wp_client.py"),
    (Join-Path $API_DIR "hmac_client.py")
)
foreach ($f in $required) {
    if (-not (Test-Path $f)) {
        Write-Host "   ERRO: $f nao encontrado!" -ForegroundColor Red
        Write-Host "   Execute patch_github_client.ps1 primeiro." -ForegroundColor Red
        exit 1
    }
    Write-Host "   OK: $([System.IO.Path]::GetFileName($f))" -ForegroundColor Green
}

# ── 2. Renomeia days_editor.py para _legacy ────────────────
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

# ── 3. Cria diretorios necessarios ────────────────────────
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

# __init__.py nos diretorios novos
foreach ($dir in @($SVC_DIR, $PAGES_DIR, $COMP_DIR)) {
    $init = Join-Path $dir "__init__.py"
    if (-not (Test-Path $init)) {
        New-Item -ItemType File -Path $init | Out-Null
        Write-Host "   CRIADO: $([System.IO.Path]::GetFileName($dir))\__init__.py" -ForegroundColor Green
    }
}

# ── 4. Cria arquivos placeholder ───────────────────────────
Write-Host ""
Write-Host "4. Criando arquivos placeholder..." -ForegroundColor Yellow

$placeholders = @{
    (Join-Path $SVC_DIR   "wp_service.py")    = "# wp_service.py - TODO: copiar da proposta e adaptar secrets"
    (Join-Path $SVC_DIR   "r2_service.py")    = "# r2_service.py - TODO: copiar da proposta e adaptar secrets"
    (Join-Path $SVC_DIR   "pdf_service.py")   = "# pdf_service.py - TODO: copiar da proposta"
    (Join-Path $COMP_DIR  "block_editor.py")  = "# block_editor.py - TODO: copiar da proposta"
    (Join-Path $PAGES_DIR "4_Revista.py")     = "# 4_Revista.py - TODO: copiar da proposta e adaptar para github_client"
}

foreach ($entry in $placeholders.GetEnumerator()) {
    $fpath = $entry.Key
    $fcomment = $entry.Value
    if (-not (Test-Path $fpath)) {
        [System.IO.File]::WriteAllText($fpath, $fcomment, [System.Text.Encoding]::UTF8)
        Write-Host "   CRIADO: $([System.IO.Path]::GetFileName($fpath))" -ForegroundColor Green
    } else {
        Write-Host "   EXISTE: $([System.IO.Path]::GetFileName($fpath)) (nao sobrescrito)" -ForegroundColor Gray
    }
}

# ── 5. Adiciona secrets ao secrets.toml ───────────────────
Write-Host ""
Write-Host "5. Verificando secrets.toml para R2 e WP ingest..." -ForegroundColor Yellow

$SECRETS = Join-Path $ROOT ".streamlit\secrets.toml"
if (Test-Path $SECRETS) {
    $sc = Get-Content $SECRETS -Raw

    if ($sc -notmatch '\[r2\]') {
        $r2Block = "`n[r2]`nendpoint    = `"https://SEU_ACCOUNT.r2.cloudflarestorage.com`"`naccess_key  = `"SEU_ACCESS_KEY`"`nsecret_key  = `"SEU_SECRET_KEY`"`nbucket      = `"vanamadhuryam`"`npublic_base = `"https://r2.vanamadhuryam.org`"`n"
        Add-Content -Path $SECRETS -Value $r2Block -Encoding UTF8
        Write-Host "   OK: Bloco [r2] adicionado" -ForegroundColor Green
    } else {
        Write-Host "   [r2] ja existe" -ForegroundColor Gray
    }

    if ($sc -notmatch 'ingest_secret') {
        $ingestLine = "`n# WP ingest secret (revista)`ningest_secret = `"SEU_INGEST_SECRET`"`n"
        Add-Content -Path $SECRETS -Value $ingestLine -Encoding UTF8
        Write-Host "   OK: ingest_secret adicionado ao secrets.toml" -ForegroundColor Green
    } else {
        Write-Host "   ingest_secret ja existe" -ForegroundColor Gray
    }
} else {
    Write-Host "   secrets.toml nao encontrado - crie manualmente" -ForegroundColor Yellow
}

# ── 6. Resumo final ────────────────────────────────────────
Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Patch concluido!" -ForegroundColor Green
Write-Host ""
Write-Host "  Estrutura criada:" -ForegroundColor White
Write-Host "    components\block_editor.py     <- copiar da proposta" -ForegroundColor Yellow
Write-Host "    services\wp_service.py         <- copiar da proposta" -ForegroundColor Yellow
Write-Host "    services\r2_service.py         <- copiar da proposta" -ForegroundColor Yellow
Write-Host "    services\pdf_service.py        <- copiar da proposta" -ForegroundColor Yellow
Write-Host "    pages\4_Revista.py             <- copiar + adaptar" -ForegroundColor Yellow
Write-Host ""
Write-Host "  DESCARTAR da proposta:" -ForegroundColor White
Write-Host "    config.py                      <- usa st.secrets" -ForegroundColor Gray
Write-Host "    services\github_service.py     <- github_client.py ja faz isso" -ForegroundColor Gray
Write-Host "    components\passage_picker.py   <- github_client.py ja faz isso" -ForegroundColor Gray
Write-Host ""
Write-Host "  Proximos passos:" -ForegroundColor White
Write-Host "  1. Preencha .streamlit\secrets.toml ([r2] e ingest_secret)" -ForegroundColor Gray
Write-Host "  2. Copie os arquivos da proposta (marcados em amarelo acima)" -ForegroundColor Gray
Write-Host "  3. Em 4_Revista.py substitua:" -ForegroundColor Gray
Write-Host "     svc[github] -> gh (instancia de GitHubClient)" -ForegroundColor Gray
Write-Host "     svc[wp]     -> wp (instancia de WPService)" -ForegroundColor Gray
Write-Host "     svc[r2]     -> r2 (instancia de R2Service)" -ForegroundColor Gray
Write-Host "     svc[pdf]    -> pdf (instancia de PDFService)" -ForegroundColor Gray
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""
