<?php
/**
 * Instalador de dependencias via navegador (sem SSH)
 * ATENCAO: DELETE ESTE ARQUIVO APOS O USO!
 */

function es($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function runCmd($cmd) {
    $out = []; $code = -1;
    if (function_exists('exec')) {
        exec($cmd . ' 2>&1', $out, $code);
    } elseif (function_exists('shell_exec')) {
        $r = shell_exec($cmd . ' 2>&1');
        $out = $r ? explode("\n", trim($r)) : [];
        $code = 0;
    } else {
        $out = ['ERRO: exec/shell_exec desabilitados.'];
        $code = 1;
    }
    return ['out' => $out, 'code' => $code];
}

function phpBin() {
    foreach ([PHP_BINARY, 'php', 'php8.2', 'php8.1', 'php8.0', 'php7.4'] as $b) {
        $r = runCmd($b . ' -r "echo 1;"');
        if (trim(implode('', $r['out'])) === '1') return $b;
    }
    return 'php';
}

function findComposer() {
    $root = __DIR__;
    if (is_file($root . '/composer.phar')) {
        return phpBin() . ' ' . escapeshellarg($root . '/composer.phar');
    }
    $r = runCmd('composer --version');
    if (strpos(implode('', $r['out']), 'Composer') !== false) return 'composer';
    return '';
}

define('PASS', 'a4imob2026');
define('CNAME', 'ci_ok');
define('CVAL',  md5('a4imob2026' . 'forma4'));

$authed = (isset($_COOKIE[CNAME]) && $_COOKIE[CNAME] === CVAL);

if (!$authed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
    if ($_POST['pass'] === PASS) {
        setcookie(CNAME, CVAL, time() + 3600, '/', '', false, true);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $loginErr = 'Senha incorreta.';
}

if ($authed && isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $root = __DIR__;
    $act  = $_GET['action'];

    if ($act === 'download_composer') {
        $t = $root . '/composer.phar';
        if (is_file($t)) { echo json_encode(['ok'=>true,'msg'=>'composer.phar ja existe!']); exit; }
        if (!ini_get('allow_url_fopen')) { echo json_encode(['ok'=>false,'msg'=>'allow_url_fopen desabilitado. Faca upload manual.']); exit; }
        $d = @file_get_contents('https://getcomposer.org/composer.phar');
        if ($d && strlen($d) > 100000) {
            file_put_contents($t, $d);
            echo json_encode(['ok'=>true,'msg'=>'composer.phar baixado! (' . round(strlen($d)/1024) . ' KB)']);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Falha ao baixar. Faca upload manual pelo cPanel.']);
        }
        exit;
    }

    if ($act === 'install') {
        $c = findComposer();
        if (!$c) { echo json_encode(['ok'=>false,'msg'=>'Composer nao encontrado.','out':[]]); exit; }
        $cmd = 'cd ' . escapeshellarg($root) . ' && ' . $c . ' install --no-dev --optimize-autoloader --no-interaction';
        $r = runCmd($cmd);
        echo json_encode(['ok'=>($r['code']===0 && is_dir($root.'/vendor')),'out'=>$r['out'],'code'=>$r['code']]);
        exit;
    }

    if ($act === 'self_delete') {
        setcookie(CNAME, '', time()-3600, '/');
        if (@unlink(__FILE__)) { echo json_encode(['ok'=>true,'msg'=>'Arquivo excluido!']); }
        else { echo json_encode(['ok'=>false,'msg'=>'Nao foi possivel excluir. Delete pelo cPanel.']); }
        exit;
    }

    if ($act === 'env') {
        echo json_encode([
            'php'     => PHP_VERSION,
            'os'      => PHP_OS,
            'exec'    => function_exists('exec')       ? 'ok' : 'disabled',
            'shell'   => function_exists('shell_exec') ? 'ok' : 'disabled',
            'fopen'   => ini_get('allow_url_fopen')    ? 'ok' : 'off',
            'vendor'  => is_dir(__DIR__.'/vendor')     ? 'yes' : 'no',
            'phar'    => is_file(__DIR__.'/composer.phar') ? 'yes' : 'no',
        ]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acao invalida']);
    exit;
}

$root = __DIR__;
$hasVendor = is_dir($root.'/vendor');
$hasPhar   = is_file($root.'/composer.phar');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalador</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f1f5f9;min-height:100vh;padding:24px 12px}
.w{max-width:700px;margin:0 auto}
h1{font-size:20px;color:#1e293b;margin-bottom:4px}
.sub{color:#64748b;font-size:13px;margin-bottom:20px}
.card{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);padding:20px;margin-bottom:16px}
.card h2{font-size:14px;font-weight:700;margin-bottom:12px}
.row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px}
.row:last-child{border-bottom:none}
.lbl{color:#64748b}
.b{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700}
.ok{background:#dcfce7;color:#15803d}.warn{background:#fef9c3;color:#854d0e}.err{background:#fee2e2;color:#b91c1c}
.btn{padding:10px 18px;border-radius:7px;border:none;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{opacity:.85}.btn:disabled{opacity:.4;cursor:not-allowed}
.blue{background:#2563eb;color:#fff}.green{background:#16a34a;color:#fff}
.red{background:#dc2626;color:#fff}.gray{background:#e2e8f0;color:#374151}
.bw{display:block;width:100%;margin-bottom:8px;text-align:center}
.log{background:#0f172a;color:#94a3b8;font-family:monospace;font-size:11px;padding:12px;border-radius:6px;max-height:260px;overflow-y:auto;white-space:pre-wrap;margin-top:10px;display:none}
.log.s{display:block}
.g{color:#86efac}.r{color:#fca5a5}
.spin{display:inline-block;width:14px;height:14px;border:2px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:sp .7s linear infinite;vertical-align:middle;margin-right:5px}
@keyframes sp{to{transform:rotate(360deg)}}
.msg{padding:10px 14px;border-radius:6px;font-size:13px;margin-top:10px;display:none}
.mok{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.merr{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
code{background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:12px}
.lb{max-width:340px;margin:60px auto}
.lb h2{text-align:center;margin-bottom:16px;font-size:20px}
.lb input{width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;margin-bottom:10px}
.lb .eerr{color:#dc2626;font-size:13px;text-align:center;margin-bottom:10px}
</style>
</head>
<body>
<div class="w">

<?php if (!$authed): ?>
<div class="lb card">
  <h2>Instalador Composer</h2>
  <?php if (!empty($loginErr)): ?><div class="eerr"><?= es($loginErr) ?></div><?php endif; ?>
  <form method="POST">
    <input type="password" name="pass" placeholder="Senha de acesso" autofocus required>
    <button class="btn blue" style="width:100%;padding:11px" type="submit">Entrar</button>
  </form>
</div>

<?php else: ?>
<h1>Instalador de Dependencias</h1>
<p class="sub">Instala DomPDF + PHPMailer via Composer sem SSH. <strong>Exclua apos usar!</strong></p>

<div class="card">
  <h2>Status</h2>
  <div class="row"><span class="lbl">PHP</span><span class="b <?= version_compare(PHP_VERSION,'7.4','>=') ? 'ok':'err' ?>"><?= es(PHP_VERSION) ?></span></div>
  <div class="row"><span class="lbl">vendor/</span><span class="b <?= $hasVendor ? 'ok':'warn' ?>"><?= $hasVendor ? 'Instalada':'Ausente' ?></span></div>
  <div class="row"><span class="lbl">composer.phar</span><span class="b <?= $hasPhar ? 'ok':'warn' ?>"><?= $hasPhar ? 'Presente':'Ausente' ?></span></div>
  <button class="btn gray" style="margin-top:10px" onclick="checkEnv()">Checar ambiente</button>
  <div class="log" id="logEnv"></div>
</div>

<?php if ($hasVendor): ?>
<div class="card" style="border:2px solid #bbf7d0">
  <h2 style="color:#15803d">Dependencias ja instaladas!</h2>
  <p style="font-size:13px;color:#166534">A pasta vendor/ existe. O sistema esta pronto.<br>Exclua este arquivo por seguranca.</p>
</div>
<?php else: ?>

<div class="card">
  <h2>Passo 1 - Obter o Composer</h2>
  <p style="font-size:13px;color:#64748b;margin-bottom:14px">Opcao A: download automatico</p>
  <button class="btn blue" id="btnDl" onclick="dlComposer()" <?= $hasPhar ? 'disabled' : '' ?>>
    <?= $hasPhar ? 'composer.phar ja existe' : 'Baixar composer.phar' ?>
  </button>
  <div class="msg mok"  id="okDl"></div>
  <div class="msg merr" id="errDl"></div>
  <hr style="margin:16px 0;border:none;border-top:1px solid #e2e8f0">
  <p style="font-size:13px;color:#64748b;margin-bottom:8px"><strong>Opcao B (manual):</strong></p>
  <p style="font-size:12.5px;color:#64748b;line-height:1.8">
    1. Baixe: <a href="https://getcomposer.org/composer.phar" target="_blank">getcomposer.org/composer.phar</a><br>
    2. cPanel → Gerenciador de Arquivos → public_html/ → Upload → envie composer.phar<br>
    3. Recarregue a pagina e va para o Passo 2
  </p>
</div>

<div class="card">
  <h2>Passo 2 - Instalar Dependencias</h2>
  <p style="font-size:13px;color:#64748b;margin-bottom:14px">
    Executa <code>composer install --no-dev --optimize-autoloader</code><br>
    Instala DomPDF (PDF) e PHPMailer (e-mail) na pasta vendor/
  </p>
  <button class="btn green" id="btnInst" onclick="doInstall()">Executar Composer Install</button>
  <div class="msg mok"  id="okInst"></div>
  <div class="msg merr" id="errInst"></div>
  <div class="log" id="logInst"></div>
</div>

<div class="card" style="border:1px dashed #94a3b8">
  <h2>Alternativa - Instalar no PC e enviar pelo cPanel</h2>
  <p style="font-size:13px;color:#374151;line-height:1.9">
    1. Instale Composer no Windows: <a href="https://getcomposer.org/Composer-Setup.exe" target="_blank">getcomposer.org/Composer-Setup.exe</a><br>
    2. Abra o CMD:<br>
    &nbsp;&nbsp;<code>cd "C:\Users\User\Documents\SITEFORMA4IMOBILIARIA"</code><br>
    &nbsp;&nbsp;<code>composer install --no-dev --optimize-autoloader</code><br>
    3. Sera criada a pasta <code>vendor/</code> no PC<br>
    4. Comprima a pasta vendor/ em um .zip<br>
    5. cPanel → Gerenciador de Arquivos → public_html/ → Upload → envie o zip → Extrair
  </p>
</div>

<?php endif; ?>

<div class="card" style="border:2px solid #fecaca">
  <h2 style="color:#dc2626">Passo Final - Excluir este arquivo (OBRIGATORIO)</h2>
  <p style="font-size:13px;color:#64748b;margin-bottom:14px">Este arquivo permite executar comandos no servidor. Exclua imediatamente apos usar.</p>
  <button class="btn red" onclick="deleteSelf()">Excluir run_composer.php</button>
  <div class="msg mok"  id="okDel"></div>
  <div class="msg merr" id="errDel"></div>
</div>

<?php endif; ?>
</div>

<script>
function sh(id,txt,cls){var e=document.getElementById(id);e.innerHTML=txt;e.className='msg '+cls;e.style.display='block';}
function hide(id){document.getElementById(id).style.display='none';}
async function api(a){var r=await fetch('?action='+a);if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}

async function dlComposer(){
  var btn=document.getElementById('btnDl');
  btn.disabled=true;btn.innerHTML='<span class="spin"></span>Baixando...';
  hide('okDl');hide('errDl');
  try{var d=await api('download_composer');
    if(d.ok){sh('okDl','OK: '+d.msg,'mok');btn.innerHTML='Baixado!';}
    else{sh('errDl','Erro: '+d.msg,'merr');btn.disabled=false;btn.innerHTML='Baixar composer.phar';}
  }catch(e){sh('errDl','Erro: '+e.message,'merr');btn.disabled=false;btn.innerHTML='Baixar composer.phar';}
}

async function doInstall(){
  var btn=document.getElementById('btnInst');
  btn.disabled=true;btn.innerHTML='<span class="spin"></span>Instalando... (1-3 min)';
  hide('okInst');hide('errInst');
  document.getElementById('logInst').className='log';
  try{
    var d=await api('install');
    if(d.out&&d.out.length){
      var lg=document.getElementById('logInst');
      lg.innerHTML=d.out.map(l=>'<span class="'+((/error|err:|fail|fatal/i.test(l))?'r':'g')+'">'+l.replace(/</g,'&lt;')+'</span>').join('\n');
      lg.className='log s';
    }
    if(d.ok){sh('okInst','Instalacao concluida! vendor/ criado. Agora exclua este arquivo.','mok');btn.innerHTML='Instalado!';}
    else{sh('errInst','Falha (codigo '+d.code+'). Veja o log. Use a alternativa manual.','merr');btn.disabled=false;btn.innerHTML='Tentar Novamente';}
  }catch(e){sh('errInst','Erro: '+e.message,'merr');btn.disabled=false;btn.innerHTML='Executar Composer Install';}
}

async function deleteSelf(){
  if(!confirm('Excluir run_composer.php?'))return;
  try{
    var d=await api('self_delete');
    if(d.ok){sh('okDel',d.msg,'mok');setTimeout(()=>location.href='/admin/index.php',2000);}
    else{sh('errDel',d.msg+' Delete pelo cPanel manualmente.','merr');}
  }catch(e){sh('errDel','Exclua manualmente pelo cPanel.','merr');}
}

async function checkEnv(){
  try{
    var d=await api('env');
    var lg=document.getElementById('logEnv');
    lg.innerHTML=Object.entries(d).map(([k,v])=>'<span class="g">'+k+':</span> '+v).join('\n');
    lg.className='log s';
  }catch(e){alert('Erro: '+e.message);}
}
</script>
</body>
</html>
