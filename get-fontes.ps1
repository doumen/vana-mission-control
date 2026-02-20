# 1. Configurações de nomes
# Captura o nome da pasta atual, remove espaços e monta o prefixo "fontes_"
$FolderName = (Get-Item ".").Name -replace '\s+', ''
$OutputFile = "fontes_$FolderName.txt"
$MyName = $MyInvocation.MyCommand.Name

# 2. Universo de arquivos WordPress e Web
$Extensoes = @(
    '.php', '.phtml', '.html', '.htm',          # Core PHP e HTML
    '.js', '.ts', '.jsx', '.tsx',               # Scripts (Temas e Plugins)
    '.css', '.scss', '.sass',                   # Estilos
    '.sql',                                     # Dumps de banco
    '.md', '.txt',                              # Documentação
    '.htaccess', '.user.ini',                   # Configurações de Servidor
    '.py'                                       # Scripts Python
)

# 3. Padrões de exclusão
$Ignorar = "node_modules|\.git|upgrade|cache|temp|dist|bin|obj"

if (Test-Path $OutputFile) { Remove-Item $OutputFile }

Write-Host "Iniciando varredura no universo WordPress..." -ForegroundColor Cyan

# 4. Busca recursiva
$Arquivos = Get-ChildItem -Path "." -Recurse -File | Where-Object {
    ($_.Extension -in $Extensoes -or $_.Name -eq ".htaccess") -and 
    $_.FullName -notmatch $Ignorar -and
    $_.Name -ne $OutputFile -and
    $_.Name -ne $MyName
}

$Total = ($Arquivos | Measure-Object).Count
Write-Host "Arquivos encontrados: $Total" -ForegroundColor Yellow

# 5. Processamento
foreach ($File in $Arquivos) {
    try {
        if ($File.Name -eq "wp-config.php") {
            Write-Host "⚠️  CUIDADO: wp-config.php incluído!" -ForegroundColor DarkYellow
        }

        Write-Host "Lendo: $($File.FullName)" -ForegroundColor Gray
        
        $Header = "`n" + ("=" * 80) + "`n"
        $Header += "ARQUIVO: $($File.FullName)`n"
        $Header += ("=" * 80) + "`n"
        
        Add-Content -Path $OutputFile -Value $Header
        
        $Conteudo = Get-Content -Path $File.FullName -Raw -ErrorAction Stop
        Add-Content -Path $OutputFile -Value $Conteudo
        
        Add-Content -Path $OutputFile -Value "`n--- FIM DO CONTEÚDO ---`n"
    } catch {
        Write-Host "Erro ao processar $($File.Name): $_" -ForegroundColor Red
    }
}

Write-Host "`nPronto! O conteúdo foi consolidado em: $OutputFile" -ForegroundColor Green