# ============================================================
# instalar_deps.ps1 — Instala dependencias PHP localmente
# e gera vendor_upload.zip pronto para enviar ao cPanel
# ============================================================
$ProgressPreference = 'SilentlyContinue'
$ErrorActionPreference = 'Stop'

$dir = Split-Path -Parent $MyInvocation.MyCommand.Path
Write-Host ""
Write-Host "=== Instalador de Dependencias FORMA4 ===" -ForegroundColor Cyan
Write-Host "Pasta do projeto: $dir"
Write-Host ""

# --- 1. Verificar se PHP ja esta disponivel ---
$phpExe = $null
foreach ($candidate in @('php', 'php.exe')) {
    try {
        $v = & $candidate -r "echo 1;" 2>$null
        if ($v -eq '1') { $phpExe = $candidate; break }
    } catch {}
}

# --- 2. Baixar PHP portable se nao encontrado ---
if (-not $phpExe) {
    $phpDir = "$dir\_php_tmp"
    $phpZip = "$dir\_php_tmp.zip"

    if (-not (Test-Path "$phpDir\php.exe")) {
        Write-Host "PHP nao encontrado. Baixando PHP 8.2 portable..." -ForegroundColor Yellow
        $phpUrl = "https://windows.php.net/downloads/releases/latest/php-8.2-nts-Win32-vs16-x64.zip"
        try {
            Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip -UseBasicParsing
        } catch {
            # Fallback: tentar php 8.1
            $phpUrl = "https://windows.php.net/downloads/releases/latest/php-8.1-nts-Win32-vs16-x64.zip"
            Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip -UseBasicParsing
        }
        Write-Host "Extraindo PHP..." -ForegroundColor Yellow
        Expand-Archive -Path $phpZip -DestinationPath $phpDir -Force
        Remove-Item $phpZip -Force

        # Habilitar extensoes necessarias no php.ini
        $ini = "$phpDir\php.ini-development"
        if (Test-Path $ini) {
            $content = Get-Content $ini -Raw
            $content = $content -replace ';extension=zip',     'extension=zip'
            $content = $content -replace ';extension=mbstring','extension=mbstring'
            $content = $content -replace ';extension=openssl', 'extension=openssl'
            $content = $content -replace ';extension=curl',    'extension=curl'
            $content = $content -replace ';extension=dom',     'extension=dom'
            $content = $content -replace ';extension=fileinfo','extension=fileinfo'
            # Adicionar extension_dir se nao tiver
            if ($content -notmatch '^extension_dir') {
                $content = $content -replace 'extension_dir = "ext"', 'extension_dir = "ext"'
            }
            Set-Content "$phpDir\php.ini" $content -Encoding UTF8
        }
        Write-Host "PHP pronto." -ForegroundColor Green
    }
    $phpExe = "$phpDir\php.exe"
}

Write-Host "PHP: $phpExe" -ForegroundColor Green

# --- 3. Baixar composer.phar ---
$composerPhar = "$dir\composer.phar"
if (-not (Test-Path $composerPhar)) {
    Write-Host "Baixando composer.phar..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri "https://getcomposer.org/composer.phar" -OutFile $composerPhar -UseBasicParsing
    Write-Host "composer.phar baixado." -ForegroundColor Green
} else {
    Write-Host "composer.phar ja existe." -ForegroundColor Green
}

# --- 4. Rodar composer install ---
Write-Host ""
Write-Host "Rodando composer install (pode demorar 1-3 minutos)..." -ForegroundColor Yellow
Write-Host ""

$env:COMPOSER_NO_INTERACTION = "1"
& $phpExe $composerPhar install --no-dev --optimize-autoloader --no-interaction --working-dir="$dir"

if (-not (Test-Path "$dir\vendor")) {
    Write-Host ""
    Write-Host "ERRO: vendor/ nao foi criado. Verifique as mensagens acima." -ForegroundColor Red
    Read-Host "Pressione Enter para sair"
    exit 1
}

Write-Host ""
Write-Host "vendor/ criado com sucesso!" -ForegroundColor Green

# --- 5. Criar zip para upload ---
$zipPath = "$dir\vendor_upload.zip"
Write-Host "Compactando vendor/ para upload..." -ForegroundColor Yellow

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Add-Type -Assembly System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory("$dir\vendor", $zipPath)

$sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host " PRONTO! vendor_upload.zip criado ($sizeMB MB)" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximos passos:" -ForegroundColor White
Write-Host "1. Abra o cPanel -> Gerenciador de Arquivos" -ForegroundColor White
Write-Host "2. Navegue ate public_html/" -ForegroundColor White
Write-Host "3. Clique em Upload e envie: vendor_upload.zip" -ForegroundColor White
Write-Host "4. Selecione o arquivo e clique em Extrair" -ForegroundColor White
Write-Host "   (sera criada a pasta vendor/ no servidor)" -ForegroundColor White
Write-Host ""
Write-Host "Apos extrair, o sistema de PDF e email funcionara!" -ForegroundColor Green
Write-Host ""

Read-Host "Pressione Enter para sair"
