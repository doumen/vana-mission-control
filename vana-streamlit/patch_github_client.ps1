# patch_github_client.ps1
# Executa da raiz do vana_crud:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\patch_github_client.ps1

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ROOT    = $PSScriptRoot
$API_DIR = Join-Path $ROOT "api"
$TARGET  = Join-Path $API_DIR "github_client.py"
$BACKUP  = Join-Path $API_DIR "github_client.py.bak"
$SECRETS = Join-Path $ROOT ".streamlit\secrets.toml"

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Vana Mission Control - Patch github_client " -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# 1. Valida estrutura
Write-Host "1. Validando estrutura do projeto..." -ForegroundColor Yellow
$required = @(
    (Join-Path $ROOT "app.py"),
    (Join-Path $API_DIR "wp_client.py"),
    (Join-Path $API_DIR "hmac_client.py"),
    (Join-Path $API_DIR "__init__.py")
)
foreach ($f in $required) {
    if (-not (Test-Path $f)) {
        Write-Host "   ERRO: nao encontrado: $f" -ForegroundColor Red
        Write-Host "   Execute a partir da raiz do vana_crud." -ForegroundColor Red
        exit 1
    }
    Write-Host "   OK: $([System.IO.Path]::GetFileName($f))" -ForegroundColor Green
}

# 2. Backup
Write-Host ""
Write-Host "2. Verificando arquivo existente..." -ForegroundColor Yellow
if (Test-Path $TARGET) {
    Copy-Item $TARGET $BACKUP -Force
    Write-Host "   Backup criado: github_client.py.bak" -ForegroundColor Green
} else {
    Write-Host "   Arquivo novo (sem backup necessario)" -ForegroundColor Gray
}

# 3. Verifica se github_client.py existe (criado manualmente no Passo 1)
Write-Host ""
Write-Host "3. Verificando github_client.py..." -ForegroundColor Yellow
if (-not (Test-Path $TARGET)) {
    Write-Host "   ERRO: api\github_client.py nao encontrado!" -ForegroundColor Red
    Write-Host "   Salve o arquivo Python do Passo 1 antes de continuar." -ForegroundColor Red
    exit 1
}
Write-Host "   OK: github_client.py encontrado" -ForegroundColor Green

# 4. Valida sintaxe Python
Write-Host ""
Write-Host "4. Validando sintaxe Python..." -ForegroundColor Yellow
$pyPath = $TARGET -replace '\\', '/'
try {
    $result = python -c "import ast; ast.parse(open(r'$TARGET').read()); print('SYNTAX_OK')" 2>&1
    if ("$result" -match "SYNTAX_OK") {
        Write-Host "   OK: Sintaxe Python valida" -ForegroundColor Green
    } else {
        Write-Host "   ERRO de sintaxe: $result" -ForegroundColor Red
    }
} catch {
    Write-Host "   Python nao encontrado no PATH - valide manualmente" -ForegroundColor Yellow
}

# 5. Testa import
Write-Host ""
Write-Host "5. Testando import..." -ForegroundColor Yellow
try {
    $importResult = python -c "import sys; sys.path.insert(0,'.'); from api.github_client import GitHubClient; print('IMPORT_OK')" 2>&1
    if ("$importResult" -match "IMPORT_OK") {
        Write-Host "   OK: Import bem-sucedido" -ForegroundColor Green
    } else {
        Write-Host "   Resultado: $importResult" -ForegroundColor Yellow
    }
} catch {
    Write-Host "   Teste de import falhou" -ForegroundColor Yellow
}

# 6. Atualiza secrets.toml
Write-Host ""
Write-Host "6. Verificando secrets.toml..." -ForegroundColor Yellow
if (Test-Path $SECRETS) {
    $secretsContent = Get-Content $SECRETS -Raw
    if ($secretsContent -notmatch '\[github\]') {
        $githubBlock = "`n[github]`ntoken  = `"ghp_SEU_TOKEN_AQUI`"`nrepo   = `"sua-org/vana-mission-control`"`nbranch = `"main`"`n"
        Add-Content -Path $SECRETS -Value $githubBlock -Encoding UTF8
        Write-Host "   OK: Bloco [github] adicionado ao secrets.toml" -ForegroundColor Green
        Write-Host "   ATENCAO: Edite token e repo em .streamlit\secrets.toml" -ForegroundColor Yellow
    } else {
        Write-Host "   Bloco [github] ja existe no secrets.toml" -ForegroundColor Gray
    }
} else {
    Write-Host "   secrets.toml nao encontrado - crie .streamlit\secrets.toml manualmente" -ForegroundColor Yellow
}

# 7. Resumo
Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  Patch concluido!" -ForegroundColor Green
Write-Host ""
Write-Host "  Proximos passos:" -ForegroundColor White
Write-Host "  1. Edite .streamlit\secrets.toml" -ForegroundColor Gray
Write-Host "     [github]" -ForegroundColor Gray
Write-Host "     token  = seu token real" -ForegroundColor Gray
Write-Host "     repo   = org/vana-mission-control" -ForegroundColor Gray
Write-Host "  2. Use no Streamlit:" -ForegroundColor Gray
Write-Host "     from api.github_client import GitHubClient" -ForegroundColor Gray
Write-Host "     gh = GitHubClient(" -ForegroundColor Gray
Write-Host "           token  = st.secrets['github']['token']," -ForegroundColor Gray
Write-Host "           repo   = st.secrets['github']['repo']," -ForegroundColor Gray
Write-Host "         )" -ForegroundColor Gray
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""
