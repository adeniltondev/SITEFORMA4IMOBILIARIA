<?php
/**
 * Instalador de dependências via navegador (sem SSH)
 * ATENÇÃO: DELETE ESTE ARQUIVO APÓS O USO!
 *
 * Acesse: https://seudominio.com.br/run_composer.php
 */

// ── Proteção básica por senha ────────────────────────────────
define('INSTALLER_PASS', 'a4imob2026');     // Altere se quiser

session_start();

$authed = ($_SESSION['ci_auth'] ?? false);
if (!$authed) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pass'] ?? '') === INSTALLER_PASS) {
        $_SESSION['ci_auth'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?><!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Instalador</title>
<style>
  body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f1f5f9;}
  .box{background:#fff;padding:32px;border-radius:10px;box-shadow:0 4px 24px rgba(0,0,0,.12);text-align:center;min-width:300px;}
  h2{margin:0 0 20px;color:#1e293b;}
  input{padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;width:100%;font-size:14px;box-sizing:border-box;}
  button{margin-top:12px;width:100%;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;}
  button:hover{background:#1d4ed8;}
</style>
</head>
<body>
<div class="box">
  <h2>🔒 Instalador Composer</h2>
  <form method="POST">
    <input type="password" name="pass" placeholder="Senha de acesso" required autofocus>
    <button type="submit">Entrar</button>
  </form>
</div>
</body>
</html><?php
    exit;
}

// ── Ação solicitada ───────────────────────────────────────────
$action = $_GET['action'] ?? 'status';
$root   = __DIR__;

// ── Funções utilitárias ───────────────────────────────────────
function runCmd(string $cmd): array
{
    $output   = [];
    $exitCode = -1;

    if (function_exists('proc_open')) {
        $desc  = [1 => ['pipe','w'], 2 => ['pipe','w']];
        $pipes = [];
        $proc  = proc_open($cmd, $desc, $pipes, null, null);
        if (is_resource($proc)) {
            $stdout   = stream_get_contents($pipes[1]);
            $stderr   = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);
            $output   = array_merge(
                $stdout ? explode("\n", trim($stdout)) : [],
                $stderr ? explode("\n", trim($stderr)) : []
            );
        }
    } elseif (function_exists('exec')) {
        exec($cmd . ' 2>&1', $output, $exitCode);
    } elseif (function_exists('shell_exec')) {
        $out      = shell_exec($cmd . ' 2>&1');
        $output   = $out ? explode("\n", trim($out)) : [];
        $exitCode = 0;
    } else {
        $output   = ['ERRO: Nenhuma função de execução disponível (exec/proc_open/shell_exec estão desabilitadas).'];
        $exitCode = 1;
    }

    return ['output' => $output, 'exit' => $exitCode];
}

function phpBin(): string
{
    // Tenta detectar o PHP CLI disponível
    $candidates = [PHP_BINARY, 'php', 'php8.1', 'php8.0', 'php7.4'];
    foreach ($candidates as $bin) {
        if (is_executable($bin)) return $bin;
        $r = runCmd($bin . ' -r "echo 1;" 2>/dev/null');
        if (($r['output'][0] ?? '') === '1') return $bin;
    }
    return 'php';
}

function findComposer(): string
{
    global $root;
    if (is_file($root . '/composer.phar')) return phpBin() . ' ' . escapeshellarg($root . '/composer.phar');
    $r = runCmd('composer --version 2>/dev/null');
    if (str_contains(implode('', $r['output']), 'Composer'))  return 'composer';
    return '';
}

// ── Processar ações ───────────────────────────────────────────
$result = null;

if ($action === 'download_composer') {
    $target = $root . '/composer.phar';
    if (is_file($target)) {
        $result = ['ok' => true, 'msg' => 'composer.phar já existe.'];
    } elseif (ini_get('allow_url_fopen')) {
        $data = @file_get_contents('https://getcomposer.org/composer.phar');
        if ($data && strlen($data) > 500000) {
            file_put_contents($target, $data);
            $result = ['ok' => true, 'msg' => 'composer.phar baixado com sucesso! (' . round(strlen($data)/1024) . ' KB)'];
        } else {
            $result = ['ok' => false, 'msg' => 'Falha ao baixar composer.phar. Tente fazer upload manual (ver instrução abaixo).'];
        }
    } else {
        $result = ['ok' => false, 'msg' => 'allow_url_fopen está desabilitado. Faça upload manual do composer.phar.'];
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if ($action === 'install') {
    $composer = findComposer();
    if (!$composer) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Composer não encontrado. Baixe o composer.phar primeiro.']);
        exit;
    }
    $cmd = $composer . ' install --no-dev --optimize-autoloader --no-interaction 2>&1';
    $r   = runCmd('cd ' . escapeshellarg($root) . ' && ' . $cmd);
    $ok  = $r['exit'] === 0 && is_dir($root . '/vendor');
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'output' => $r['output'], 'exit' => $r['exit']]);
    exit;
}

if ($action === 'self_delete') {
    $me = __FILE__;
    header('Content-Type: application/json');
    unset($_SESSION['ci_auth']);
    session_destroy();
    if (unlink($me)) {
        echo json_encode(['ok' => true, 'msg' => 'Arquivo excluído com sucesso!']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Não foi possível excluir automaticamente. Delete manualmente pelo cPanel.']);
    }
    exit;
}

// ── Status ────────────────────────────────────────────────────
$hasVendor      = is_dir($root . '/vendor/autoload.php') || is_file($root . '/vendor/autoload.php');
$hasComposerPhar = is_file($root . '/composer.phar');
$hasComposerSys  = (bool) findComposer();
$phpVersion      = PHP_VERSION;
$execAvail       = function_exists('proc_open') ? 'proc_open' : (function_exists('exec') ? 'exec' : (function_exists('shell_exec') ? 'shell_exec' : 'nenhuma'));
$urlFopen        = ini_get('allow_url_fopen') ? 'Habilitado' : 'Desabilitado';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalador Composer — Forma4</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;min-height:100vh;padding:30px 16px;}
  .wrap{max-width:780px;margin:0 auto;}
  h1{font-size:22px;color:#1e293b;margin-bottom:4px;}
  .sub{color:#64748b;font-size:13px;margin-bottom:24px;}
  .card{background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:24px;margin-bottom:20px;}
  .card h2{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
  .info-row{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:13px;}
  .info-row:last-child{border-bottom:none;}
  .info-row .label{color:#64748b;}
  .badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;}
  .ok{background:#dcfce7;color:#15803d;}
  .warn{background:#fef9c3;color:#854d0e;}
  .err{background:#fee2e2;color:#b91c1c;}
  .step{display:flex;align-items:flex-start;gap:16px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px;}
  .step-num{width:32px;height:32px;background:#2563eb;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;}
  .step-num.done{background:#16a34a;}
  .step h3{font-size:14px;font-weight:600;color:#1e293b;margin-bottom:4px;}
  .step p{font-size:12.5px;color:#64748b;line-height:1.6;}
  .btn{padding:10px 20px;border-radius:7px;border:none;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:opacity .15s;}
  .btn:hover{opacity:.85;}
  .btn-blue{background:#2563eb;color:#fff;}
  .btn-green{background:#16a34a;color:#fff;}
  .btn-red{background:#dc2626;color:#fff;}
  .btn-gray{background:#e2e8f0;color:#374151;}
  .btn:disabled{opacity:.45;cursor:not-allowed;}
  .log{background:#0f172a;color:#94a3b8;font-family:monospace;font-size:11.5px;padding:14px;border-radius:6px;min-height:60px;max-height:320px;overflow-y:auto;white-space:pre-wrap;margin-top:12px;display:none;}
  .log.show{display:block;}
  .log .line-ok{color:#86efac;}
  .log .line-err{color:#fca5a5;}
  .progress{display:flex;align-items:center;gap:10px;margin-top:12px;display:none;}
  .spinner{width:18px;height:18px;border:2px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:spin .7s linear infinite;}
  @keyframes spin{to{transform:rotate(360deg)}}
  .alert-success{background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;padding:12px 16px;border-radius:7px;font-size:13px;margin-top:12px;display:none;}
  .alert-error{background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;padding:12px 16px;border-radius:7px;font-size:13px;margin-top:12px;display:none;}
</style>
</head>
<body>
<div class="wrap">
  <h1>⚙️ Instalador de Dependências</h1>
  <p class="sub">Instala DomPDF e PHPMailer via Composer — sem SSH. <strong>Delete este arquivo após concluir!</strong></p>

  <!-- Status do ambiente -->
  <div class="card">
    <h2>📋 Status do Ambiente</h2>
    <div class="info-row">
      <span class="label">Versão do PHP</span>
      <span class="badge <?= version_compare($phpVersion, '7.4', '>=') ? 'ok' : 'err' ?>"><?= e_s($phpVersion) ?></span>
    </div>
    <div class="info-row">
      <span class="label">Pasta <code>vendor/</code></span>
      <span class="badge <?= $hasVendor ? 'ok' : 'warn' ?>"><?= $hasVendor ? '✓ Instalada' : '✗ Não encontrada' ?></span>
    </div>
    <div class="info-row">
      <span class="label"><code>composer.phar</code> na raiz</span>
      <span class="badge <?= $hasComposerPhar ? 'ok' : 'warn' ?>"><?= $hasComposerPhar ? '✓ Presente' : '✗ Ausente' ?></span>
    </div>
    <div class="info-row">
      <span class="label">Composer no sistema</span>
      <span class="badge <?= $hasComposerSys ? 'ok' : 'warn' ?>"><?= $hasComposerSys ? '✓ Disponível' : '✗ Não encontrado' ?></span>
    </div>
    <div class="info-row">
      <span class="label">Funções de execução</span>
      <span class="badge <?= $execAvail !== 'nenhuma' ? 'ok' : 'err' ?>"><?= e_s($execAvail) ?></span>
    </div>
    <div class="info-row">
      <span class="label"><code>allow_url_fopen</code></span>
      <span class="badge <?= $urlFopen === 'Habilitado' ? 'ok' : 'warn' ?>"><?= $urlFopen ?></span>
    </div>
  </div>

  <?php if ($hasVendor): ?>
  <div class="card" style="background:#dcfce7;border:1px solid #bbf7d0;">
    <h2 style="color:#15803d;">✅ Dependências já instaladas!</h2>
    <p style="font-size:13px;color:#166534;">A pasta <code>vendor/</code> existe. O sistema está pronto para funcionar.</p>
    <p style="font-size:13px;color:#166534;margin-top:8px;"><strong>Clique em "Excluir este arquivo" abaixo por segurança.</strong></p>
  </div>
  <?php else: ?>

  <!-- Passo 1: Baixar composer.phar -->
  <div class="card">
    <h2>📦 Passo 1 — Obter o Composer</h2>

    <div class="step">
      <div class="step-num <?= $hasComposerPhar || $hasComposerSys ? 'done' : '' ?>">
        <?= $hasComposerPhar || $hasComposerSys ? '✓' : '1' ?>
      </div>
      <div style="flex:1">
        <h3>Opção A — Download automático</h3>
        <p>Baixa o <code>composer.phar</code> do site oficial direto para o servidor.</p>
        <button class="btn btn-blue" id="btnDownload" style="margin-top:10px;"
                <?= $hasComposerPhar ? 'disabled' : '' ?>>
          <?= $hasComposerPhar ? '✓ Já baixado' : '⬇ Baixar composer.phar' ?>
        </button>
        <div class="progress" id="progDownload"><div class="spinner"></div><span>Baixando...</span></div>
        <div class="alert-success" id="okDownload"></div>
        <div class="alert-error"   id="errDownload"></div>
      </div>
    </div>

    <div class="step" style="margin-bottom:0;">
      <div class="step-num">B</div>
      <div>
        <h3>Opção B — Upload manual (se A falhar)</h3>
        <p>
          1. Acesse <a href="https://getcomposer.org/composer.phar" target="_blank">getcomposer.org/composer.phar</a> e baixe o arquivo.<br>
          2. No <strong>cPanel → Gerenciador de Arquivos</strong>, vá para <code>public_html/</code>.<br>
          3. Faça upload do <code>composer.phar</code> na raiz do projeto.<br>
          4. Volte aqui e siga para o Passo 2.
        </p>
      </div>
    </div>
  </div>

  <!-- Passo 2: Instalar -->
  <div class="card">
    <h2>🚀 Passo 2 — Instalar Dependências</h2>
    <p style="font-size:13px;color:#64748b;margin-bottom:16px;">
      Executa <code>composer install --no-dev --optimize-autoloader</code> no servidor.<br>
      Isso instala <strong>DomPDF</strong> e <strong>PHPMailer</strong> na pasta <code>vendor/</code>.
    </p>
    <button class="btn btn-green" id="btnInstall">▶ Executar Composer Install</button>
    <div class="progress" id="progInstall"><div class="spinner"></div><span>Instalando dependências... (pode levar 1-3 minutos)</span></div>
    <div class="log" id="logInstall"></div>
    <div class="alert-success" id="okInstall"></div>
    <div class="alert-error"   id="errInstall"></div>
  </div>

  <?php endif; ?>

  <!-- Passo 3: Excluir arquivo -->
  <div class="card" style="border:2px solid #fecaca;">
    <h2 style="color:#dc2626;">🗑️ Passo 3 — Excluir este arquivo (OBRIGATÓRIO)</h2>
    <p style="font-size:13px;color:#64748b;margin-bottom:16px;">
      Por segurança, este arquivo deve ser excluído após a instalação.
      Qualquer pessoa com a URL pode executar comandos no servidor!
    </p>
    <button class="btn btn-red" id="btnDelete">🗑 Excluir run_composer.php</button>
    <div class="alert-success" id="okDelete"></div>
    <div class="alert-error"   id="errDelete"></div>
  </div>

</div>

<script>
function show(id)  { document.getElementById(id).style.display = 'block'; }
function hide(id)  { document.getElementById(id).style.display = 'none'; }
function setText(id, txt) { document.getElementById(id).innerHTML = txt; show(id); }

async function doFetch(url) {
    const r = await fetch(url);
    return await r.json();
}

// Download composer.phar
document.getElementById('btnDownload').addEventListener('click', async function() {
    this.disabled = true;
    hide('okDownload'); hide('errDownload');
    show('progDownload');
    try {
        const res = await doFetch('?action=download_composer');
        hide('progDownload');
        if (res.ok) { setText('okDownload', '✅ ' + res.msg); }
        else        { setText('errDownload', '❌ ' + res.msg); this.disabled = false; }
    } catch(e) {
        hide('progDownload');
        setText('errDownload', '❌ Erro de rede: ' + e.message);
        this.disabled = false;
    }
});

// Composer install
document.getElementById('btnInstall').addEventListener('click', async function() {
    this.disabled = true;
    hide('okInstall'); hide('errInstall');
    document.getElementById('logInstall').classList.remove('show');
    show('progInstall');
    try {
        const res = await doFetch('?action=install');
        hide('progInstall');
        const log = document.getElementById('logInstall');
        if (res.output && res.output.length) {
            log.innerHTML = res.output.map(l => {
                const cls = (l.includes('Error') || l.includes('error') || l.includes('failed')) ? 'line-err' : 'line-ok';
                return '<span class="' + cls + '">' + l.replace(/</g,'&lt;') + '</span>';
            }).join('\n');
            log.classList.add('show');
        }
        if (res.ok) {
            setText('okInstall', '✅ Instalação concluída! Pasta vendor/ criada. Agora exclua este arquivo.');
        } else {
            setText('errInstall', '❌ Falha (exit ' + res.exit + '). Veja o log acima. Tente a alternativa manual abaixo.');
            setText('errInstall', document.getElementById('errInstall').innerHTML +
                '<br><br><strong>Alternativa manual (sem Composer no servidor):</strong><br>' +
                '1. No seu <strong>PC Windows</strong>, execute:<br>' +
                '<code>cd C:\\Users\\User\\Documents\\SITEFORMA4IMOBILIARIA && composer install --no-dev</code><br>' +
                '2. Comprima a pasta <code>vendor/</code> em um .zip<br>' +
                '3. Envie pelo cPanel → Gerenciador de Arquivos → Extrair em public_html/');
            this.disabled = false;
        }
    } catch(e) {
        hide('progInstall');
        setText('errInstall', '❌ Erro: ' + e.message);
        this.disabled = false;
    }
});

// Self delete
document.getElementById('btnDelete').addEventListener('click', async function() {
    if (!confirm('Confirma exclusão de run_composer.php?')) return;
    this.disabled = true;
    try {
        const res = await doFetch('?action=self_delete');
        if (res.ok) {
            setText('okDelete', '✅ ' + res.msg + ' Redirecionando para o painel...');
            setTimeout(() => window.location.href = '/admin/index.php', 2000);
        } else {
            setText('errDelete', '⚠️ ' + res.msg);
        }
    } catch(e) {
        setText('errDelete', '❌ Erro: ' + e.message);
    }
});
</script>
</body>
</html>
<?php

function e_s(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
