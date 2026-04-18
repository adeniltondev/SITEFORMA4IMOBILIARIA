<?php
/**
 * Geração de PDF via DomPDF
 *
 * Suporta dois templates:
 *  - 'default'       : layout genérico para qualquer formulário
 *  - 'authorization' : contrato de Autorização de Venda com Exclusividade
 *
 * @package FORMA4
 */

require_once __DIR__ . '/functions.php';

// Carrega o autoload do Composer (DomPDF)
$composerAutoload = APP_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!file_exists($composerAutoload)) {
    throw new RuntimeException('Vendor não encontrado. Execute: composer install');
}
require_once $composerAutoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// ============================================================
// FUNÇÃO PRINCIPAL
// ============================================================

/**
 * Gera o PDF de uma submissão e o salva no servidor.
 *
 * @param array  $form       Dados do formulário (title, fields, pdf_template)
 * @param array  $submission Dados da submissão (id, data como JSON decodificado)
 * @param array  $settings   Configurações do sistema
 * @return string|false      Caminho relativo do arquivo PDF ou false em caso de erro
 */
function generatePDF(array $form, array $submission, array $settings = [])
{
    try {
        // Seleciona o template HTML
        $template = $form['pdf_template'] ?? 'default';
        $data     = is_array($submission['data'])
            ? $submission['data']
            : json_decode($submission['data'], true);

        if ($template === 'authorization') {
            $html = buildAuthorizationHTML($form, $submission, $data, $settings);
        } elseif ($template === 'locacao') {
            $html = buildLocacaoHTML($form, $submission, $data, $settings);
        } else {
            $html = buildDefaultHTML($form, $submission, $data, $settings);
        }

        // Configura DomPDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false); // Segurança: sem recursos externos
        $options->set('chroot', APP_PATH);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Salva o arquivo
        if (!is_dir(PDF_PATH)) {
            mkdir(PDF_PATH, 0755, true);
        }

        $filename = sprintf(
            'form%d_sub%d_%s.pdf',
            (int) $form['id'],
            (int) $submission['id'],
            date('Ymd_His')
        );
        $fullPath = PDF_PATH . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($fullPath, $dompdf->output());

        // Retorna caminho relativo (relativo a /uploads)
        return 'pdfs/' . $filename;

    } catch (Exception $e) {
        error_log('[FORMA4 PDF] Erro ao gerar PDF: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// TEMPLATE: FORMULÁRIO GENÉRICO
// ============================================================

/**
 * Monta HTML do PDF genérico.
 */
function buildDefaultHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName    = e($settings['app_name'] ?? APP_NAME);
    $logoPath   = !empty($settings['logo_path'])
        ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path']
        : '';
    $logoHtml   = buildLogoImg($logoPath, $appName);
    $formTitle  = e($form['title']);
    $submDate   = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'), true);
    $submId     = (int) $submission['id'];
    $fields     = decodeFields($form['fields']);
    $primaryColor = $settings['primary_color'] ?? '#2563EB';

    // Linhas de dados
    $rows = '';
    foreach ($fields as $field) {
        $name  = $field['name']  ?? '';
        $ftype = $field['type']  ?? 'text';
        $label = e($field['label'] ?? $name);

        if ($ftype === 'file') {
            $value = !empty($data[$name]) ? '&#x1F4CE; Documento anexado' : '—';
        } elseif ($ftype === 'checkbox') {
            $value = !empty($data[$name]) ? 'Sim' : 'Não';
        } else {
            $value = e($data[$name] ?? '—');
        }

        $rows .= "
        <tr>
            <td class=\"field-label\">{$label}</td>
            <td class=\"field-value\">{$value}</td>
        </tr>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
  .page { padding: 30px 35px; }
  .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid {$primaryColor}; padding-bottom: 14px; margin-bottom: 20px; }
  .header .brand { font-size: 18px; font-weight: bold; color: {$primaryColor}; }
  .header .logo img { max-height: 55px; max-width: 150px; }
  .doc-title { text-align: center; margin: 14px 0 20px; }
  .doc-title h1 { font-size: 16px; font-weight: bold; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
  .meta-info { display: flex; justify-content: space-between; font-size: 10px; color: #64748b; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  table th { background: {$primaryColor}; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
  .field-label { background: #f1f5f9; padding: 7px 10px; width: 40%; font-weight: bold; border: 1px solid #e2e8f0; }
  .field-value { padding: 7px 10px; border: 1px solid #e2e8f0; }
  .footer { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
  .watermark { position: fixed; top: 40%; left: 10%; transform: rotate(-35deg); font-size: 70px; color: rgba(0,0,0,0.04); font-weight: bold; text-transform: uppercase; z-index: -1; letter-spacing: 10px; }
</style>
</head>
<body>
<div class="watermark">CONFIDENCIAL</div>
<div class="page">
  <div class="header">
    <div class="brand">{$appName}</div>
    <div class="logo">{$logoHtml}</div>
  </div>

  <div class="doc-title">
    <h1>{$formTitle}</h1>
  </div>

  <div class="meta-info">
    <span>Nº do Envio: <strong>#{$submId}</strong></span>
    <span>Data: <strong>{$submDate}</strong></span>
  </div>

  <table>
    <tr><th colspan="2">Dados do Formulário</th></tr>
    {$rows}
  </table>

  <div class="footer">
    Documento gerado automaticamente por {$appName} &mdash; {$submDate}
  </div>
</div>
</body>
</html>
HTML;
}

// ============================================================
// TEMPLATE: AUTORIZAÇÃO DE VENDA COM EXCLUSIVIDADE
// ============================================================

/**
 * Monta HTML do contrato de Autorização de Venda com Exclusividade.
 * Replica o layout do documento físico da A4 Imobiliária.
 */
function buildAuthorizationHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName      = e($settings['app_name'] ?? APP_NAME);
    $primaryColor = $settings['primary_color'] ?? '#0e4f6c';
    $logoPath     = !empty($settings['logo_path'])
        ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path']
        : '';
    $submId       = (int) $submission['id'];
    $submDate     = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));
    $anoAtual     = date('Y');

    // Helper
    $d = function (string $key, string $default = '') use ($data): string {
        $v = trim($data[$key] ?? '');
        return e($v !== '' ? $v : $default);
    };

    // Contratante
    $nomeContratante = $d('nome_razao_social');
    $sexo            = $d('sexo');
    $dataNasc        = $d('data_nascimento');
    $rg              = $d('rg');
    $orgaoExp        = $d('orgao_expedidor');
    $cpf             = $d('cpf');
    $naturalidade    = $d('naturalidade');
    $nacionalidade   = $d('nacionalidade');
    $cnpj            = $d('cnpj');
    $nomeFant        = $d('nome_fantasia');
    $estadoCivil     = $d('estado_civil');
    $conjuge         = $d('conjuge');
    $telefones       = $d('telefones');
    $endRes          = $d('endereco_residencial');
    $bairroRes       = $d('bairro_residencial');
    $cidUfRes        = $d('cidade_uf_residencial');
    $cepRes          = $d('cep_residencial');
    $telFixo         = $d('telefone_fixo');
    $celular         = $d('celular');
    $endCom          = $d('endereco_comercial');
    $bairroCom       = $d('bairro_comercial');
    $cidUfCom        = $d('cidade_uf_comercial');
    $cepCom          = $d('cep_comercial');
    $emails          = $d('emails');

    // Exclusividade
    $comExclusividade = $d('com_exclusividade', '');
    $excSim = ($comExclusividade === 'Sim') ? '&#x2713;' : '&nbsp;';
    $excNao = ($comExclusividade === 'N&atilde;o') ? '&#x2713;' : '&nbsp;';
    // Fallback sem HTML entities
    if ($comExclusividade === 'Não') { $excNao = '&#x2713;'; }

    // Imóvel
    $tipoImovel     = $d('tipo_imovel');
    $situacaoImovel = $d('situacao_imovel');
    $endImovel      = $d('endereco_imovel');
    $bairroImovel   = $d('bairro_imovel');
    $cidUfImovel    = $d('cidade_uf_imovel');
    $cepImovel      = $d('cep_imovel');
    $pontoRef       = $d('ponto_referencia');
    $registroImovel = $d('registro_imovel');
    $matriculaIptu  = $d('matricula_iptu');

    // Descrição
    $numDorm      = $d('num_dormitorios');
    $numSalas     = $d('num_salas');
    $numSuites    = $d('num_suites');
    $garagens     = $d('garagens');
    $areaPriv     = $d('area_privativa');
    $temVaranda   = $d('tem_varanda');
    $temElevador  = $d('tem_elevador');
    $lazer        = $d('lazer_completo');
    $garagemCob   = $d('garagem_coberta');
    $obsDesc      = $d('obs_descricao', '');

    // Condições
    $valorMin        = $d('valor_minimo_venda', '_______________');
    $valorMinExtenso = $d('valor_minimo_extenso');
    $obsPreco        = $d('obs_preco', '');
    $valorCondo      = $d('valor_condominio', '_______________');
    $condoExtenso    = $d('valor_condominio_extenso');
    $formasPag       = $d('formas_pagamento');
    $comissao        = $d('porcentagem_comissao', '___');
    $prazo           = $d('prazo_exclusividade', '___');

    // Assinaturas
    $nomeCorretor = $d('nome_corretor');
    $test1Nome    = $d('testemunha_1_nome');
    $test1Cpf     = $d('testemunha_1_cpf');
    $test2Nome    = $d('testemunha_2_nome');
    $test2Cpf     = $d('testemunha_2_cpf');

    // Logo base64
    $logoBannerHtml = '';
    if (!empty($logoPath) && is_file($logoPath)) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logoPath);
        if (in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
            $b64 = base64_encode(file_get_contents($logoPath));
            $logoBannerHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"max-height:65px;max-width:130px;\">";
        }
    }
    $bannerLogoCell = $logoBannerHtml
        ? "<div style=\"background:rgba(255,255,255,.12);padding:8px 10px;border-radius:4px;text-align:center;\">{$logoBannerHtml}</div>"
        : "<div class=\"brand-box\"><span class=\"bname\">{$appName}</span><br><span class=\"bsub\">Imobili&aacute;ria</span></div>";

    // Documentos anexados
    $docLabels = [
        'doc_cpf_rg'    => 'RG / CPF do Propriet&aacute;rio',
        'doc_iptu'      => 'Carn&ecirc; / IPTU',
        'doc_matricula' => 'Matr&iacute;cula do Im&oacute;vel',
        'doc_outros'    => 'Outros Documentos',
    ];
    $docsRowsAuth = '';
    foreach ($docLabels as $key => $label) {
        $filePath = trim($data[$key] ?? '');
        if ($filePath === '') continue;
        $fileName = e(basename($filePath));
        $fileUrl  = e(APP_URL . '/uploads/' . ltrim($filePath, '/'));
        $docsRowsAuth .= "<tr>"
            . "<td style='width:35%;'><span class='fl'>{$label}</span></td>"
            . "<td><span class='fv' style='word-break:break-all;'>&#x1F4CE; "
            . "<a href='{$fileUrl}' style='color:#0e4f6c;text-decoration:underline;'>{$fileName}</a>"
            . "</span></td>"
            . "</tr>";
    }
    $docsHtmlAuth = $docsRowsAuth !== ''
        ? "<div class='section' style='margin-top:10px;'>"
          . "<div class='sec-title'>Documentos Anexados</div>"
          . "<table class='ft'>{$docsRowsAuth}</table>"
          . "</div>"
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1a2332; background: #fff; line-height: 1.45; }
  .page { padding: 20px 28px; }
  /* Banner */
  .banner { background: #0e4f6c; }
  .banner-inner { padding: 14px 20px; display: table; width: 100%; }
  .banner-logo  { display: table-cell; vertical-align: middle; width: 130px; }
  .banner-title { display: table-cell; vertical-align: middle; text-align: center; }
  .banner-title h1 { color: #fff; font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
  .banner-title p  { color: rgba(255,255,255,.7); font-size: 7.5px; margin-top: 3px; }
  .brand-box { background: rgba(255,255,255,.15); padding: 8px 10px; text-align: center; border-radius: 4px; }
  .brand-box .bname { color: #fff; font-size: 14px; font-weight: bold; }
  .brand-box .bsub  { color: rgba(255,255,255,.75); font-size: 8px; }
  /* Seções */
  .section { margin-bottom: 9px; }
  .sec-title { font-weight: bold; font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid #1a2332; padding-bottom: 2px; margin-bottom: 0; }
  /* Tabela campos */
  .ft { width: 100%; border-collapse: collapse; }
  .ft td { border: 1px solid #b0bec5; padding: 2px 5px; }
  .fl { color: #546e7a; font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: .2px; display: block; }
  .fv { color: #1a2332; font-size: 8.5px; display: block; min-height: 10px; }
  /* Exclusividade */
  .exc-bar { border: 1.5px solid #0e4f6c; border-radius: 3px; margin: 7px 0; background: #e8f4fd; padding: 4px 10px; }
  .exc-label { color: #0e4f6c; font-size: 9px; font-weight: bold; text-transform: uppercase; }
  .exc-opts  { display: inline-block; margin-left: 12px; font-size: 9px; }
  .cb { display: inline-block; width: 9px; height: 9px; border: 1px solid #546e7a; margin-right: 2px; text-align: center; line-height: 9px; font-size: 8px; vertical-align: middle; }
  .cb-row  { padding: 3px 6px; border: 1px solid #b0bec5; border-top: none; font-size: 8.5px; }
  .cb-first { border-top: 1px solid #b0bec5; }
  /* Legal */
  .legal { font-size: 8px; line-height: 1.7; color: #374151; text-align: justify; margin: 7px 0; padding: 7px 10px; background: #f8fafc; border-left: 3px solid #0e4f6c; }
  /* Cláusulas */
  .clause { font-size: 8px; line-height: 1.7; color: #374151; text-align: justify; margin-bottom: 4px; }
  .cl { font-weight: bold; }
  /* Assinaturas */
  .sigs { width: 100%; margin-top: 14px; border-collapse: collapse; }
  .sigs td { width: 25%; text-align: center; padding: 5px 4px 3px; vertical-align: bottom; }
  .sline { border-top: 1px solid #1a2332; padding-top: 2px; margin-bottom: 2px; font-size: 7.5px; min-height: 22px; }
  .stitle { font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
  .ssub   { font-size: 7px; color: #64748b; font-style: italic; }
  /* Watermark */
  .watermark { position: fixed; top: 35%; left: 5%; transform: rotate(-35deg); font-size: 70px; color: rgba(0,0,0,0.04); font-weight: bold; text-transform: uppercase; z-index: -1; letter-spacing: 8px; }
  /* Footer */
  .doc-footer { border-top: 1px solid #e2e8f0; margin-top: 10px; padding-top: 5px; text-align: center; font-size: 7px; color: #94a3b8; }
</style>
</head>
<body>
<div class="watermark">CONFIDENCIAL</div>
<div class="page">

  <!-- BANNER -->
  <div class="banner">
    <div class="banner-inner">
      <div class="banner-logo">{$bannerLogoCell}</div>
      <div class="banner-title">
        <h1>Autoriza&ccedil;&atilde;o de Venda com Exclusividade</h1>
        <p>Contrato n&ordm; AVE-{$submId} &mdash; {$submDate}</p>
      </div>
    </div>
  </div>

  <!-- EXCLUSIVIDADE -->
  <div class="exc-bar">
    <span class="exc-label">&#9733; Com Exclusividade:</span>
    <span class="exc-opts">
      <span class="cb">{$excSim}</span> Sim &nbsp;&nbsp;
      <span class="cb">{$excNao}</span> N&atilde;o
    </span>
  </div>

  <!-- DADOS DO CONTRATANTE -->
  <div class="section">
    <div class="sec-title">Dados do Contratante</div>
    <table class="ft">
      <tr>
        <td style="width:62%"><span class="fl">Nome / Raz&atilde;o Social</span><span class="fv">{$nomeContratante}</span></td>
        <td><span class="fl">Sexo</span><span class="fv">{$sexo}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Data de Nascimento</span><span class="fv">{$dataNasc}</span></td>
        <td><span class="fl">RG n&ordm;</span><span class="fv">{$rg}</span></td>
        <td><span class="fl">&Oacute;rg&atilde;o Expedidor</span><span class="fv">{$orgaoExp}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CPF n&ordm;</span><span class="fv">{$cpf}</span></td>
        <td><span class="fl">Naturalidade</span><span class="fv">{$naturalidade}</span></td>
        <td><span class="fl">Nacionalidade</span><span class="fv">{$nacionalidade}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CNPJ n&ordm;</span><span class="fv">{$cnpj}</span></td>
        <td colspan="2"><span class="fl">Nome de Fantasia</span><span class="fv">{$nomeFant}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">C&ocirc;njuge</span><span class="fv">{$conjuge}</span></td>
        <td><span class="fl">Telefones</span><span class="fv">{$telefones}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Residencial</span><span class="fv">{$endRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroRes}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfRes}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Telefone Fixo</span><span class="fv">{$telFixo}</span></td>
        <td colspan="2"><span class="fl">Celular</span><span class="fv">{$celular}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Comercial</span><span class="fv">{$endCom}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroCom}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfCom}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepCom}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">E-mail(s)</span><span class="fv">{$emails}</span></td>
      </tr>
    </table>
  </div>

  <!-- PARÁGRAFO LEGAL -->
  <div class="legal">
    O CONTRATANTE acima, propriet&aacute;rio e leg&iacute;timo possuidor do im&oacute;vel abaixo relacionado, contrata a
    <strong>{$appName}</strong>, inscrita no Conselho Regional dos corretores de im&oacute;veis com o n&ordm; 218 PJ,
    para promover de forma <strong>EXCLUSIVA</strong> a <strong>VENDA</strong> do seu im&oacute;vel abaixo descrito,
    pelo prazo m&iacute;nimo de <strong>({$prazo}) dias</strong>, prorrog&aacute;vel automaticamente por per&iacute;odo
    igual e sucessivo, at&eacute; que uma das partes se manifeste em contr&aacute;rio, por escrito, pelo pre&ccedil;o e
    condi&ccedil;&otilde;es estipuladas nesta autoriza&ccedil;&atilde;o de <strong>VENDA</strong>.
  </div>

  <!-- DADOS DO IMÓVEL -->
  <div class="section">
    <div class="sec-title">Dados do Im&oacute;vel</div>
    <div class="cb-row cb-first"><strong>Tipo:</strong> {$tipoImovel}</div>
    <div class="cb-row"><strong>Situa&ccedil;&atilde;o:</strong> {$situacaoImovel}</div>
    <table class="ft" style="border-top:none;">
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o</span><span class="fv">{$endImovel}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroImovel}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfImovel}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepImovel}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Ponto de Refer&ecirc;ncia</span><span class="fv">{$pontoRef}</span></td>
      </tr>
      <tr>
        <td><span class="fl">N&ordm; e Registro do Im&oacute;vel</span><span class="fv">{$registroImovel}</span></td>
        <td colspan="2"><span class="fl">Matr&iacute;cula de IPTU n&ordm;</span><span class="fv">{$matriculaIptu}</span></td>
      </tr>
    </table>
  </div>

  <!-- DESCRIÇÃO DO IMÓVEL -->
  <div class="section">
    <div class="sec-title">Descri&ccedil;&atilde;o do Im&oacute;vel</div>
    <table class="ft">
      <tr>
        <td><span class="fl">Dorm.</span><span class="fv">{$numDorm}</span></td>
        <td><span class="fl">Salas</span><span class="fv">{$numSalas}</span></td>
        <td><span class="fl">Su&iacute;tes</span><span class="fv">{$numSuites}</span></td>
        <td><span class="fl">Garagens</span><span class="fv">{$garagens}</span></td>
        <td><span class="fl">&Aacute;rea Privativa</span><span class="fv">{$areaPriv} m&sup2;</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Varanda?</span><span class="fv">{$temVaranda}</span></td>
        <td colspan="3"><span class="fl">Elevador?</span><span class="fv">{$temElevador}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Lazer Completo?</span><span class="fv">{$lazer}</span></td>
        <td colspan="3"><span class="fl">Garagem Coberta?</span><span class="fv">{$garagemCob}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Observa&ccedil;&otilde;es</span><span class="fv" style="min-height:20px;">{$obsDesc}</span></td>
      </tr>
    </table>
  </div>

  <!-- CONDIÇÕES PRETENDIDAS -->
  <div class="section">
    <div class="sec-title">Condi&ccedil;&otilde;es Pretendidas</div>
    <table class="ft">
      <tr>
        <td style="width:30%"><span class="fl">Valor M&iacute;nimo de Venda R$</span><span class="fv"><strong>{$valorMin}</strong></span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$valorMinExtenso}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Observa&ccedil;&otilde;es do Pre&ccedil;o</span><span class="fv" style="min-height:14px;">{$obsPreco}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Valor do Condom&iacute;nio R$</span><span class="fv">{$valorCondo}</span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$condoExtenso}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Formas de Pagamento</span><span class="fv">{$formasPag}</span></td>
        <td><span class="fl">Comiss&atilde;o (%)</span><span class="fv">{$comissao}%</span></td>
      </tr>
    </table>
  </div>

  <!-- CLÁUSULAS -->
  <div class="clause">
    <span class="cl">a)</span>&nbsp; Sobre o valor da <strong>VENDA</strong> do im&oacute;vel contratado, o CONTRATANTE pagar&aacute; a CONTRATADA {$comissao}%,
    pagamento esse que dever&aacute; ser feito no ato do recebimento dos valores da referida negocia&ccedil;&atilde;o.
  </div>
  <div class="clause">
    <span class="cl">b)</span>&nbsp; Nos termos do presente, o(a) CONTRATANTE autoriza &agrave; <strong>{$appName}</strong> a ofertar publicamente
    o im&oacute;vel de sua propriedade acima descrito, cuja &agrave;s custas ser&atilde;o de responsabilidade da CONTRATADA, fotografar o im&oacute;vel e suas
    depend&ecirc;ncias internas fazendo se publicar as fotos nos ve&iacute;culos e meios de comunica&ccedil;&atilde;o que desejar, inclusive na internet,
    afixar placas, faixas ou letreiros no im&oacute;vel, realizar visita&ccedil;&otilde;es e demonstra&ccedil;&otilde;es aos interessados.
  </div>
  <div class="clause">
    <span class="cl">c)</span>&nbsp; O Propriet&aacute;rio declara que o dito im&oacute;vel encontra-se livre e desembara&ccedil;ado de quaisquer &ocirc;nus ou
    restri&ccedil;&otilde;es que impe&ccedil;a sua <strong>VENDA</strong>, comprometendo-se em apresentar &agrave;s suas custas a documenta&ccedil;&atilde;o
    exigida em transa&ccedil;&otilde;es de VENDA, t&atilde;o logo que solicitado.
  </div>
  <p style="font-size:8px;text-align:justify;line-height:1.7;margin-top:4px;">
    E por estarem de pleno acordo, assinam a presente op&ccedil;&atilde;o em 02 (duas) vias de igual teor, na presen&ccedil;a de duas testemunhas,
    ficando eleito o foro da comarca de Aracaju para dirimir qualquer d&uacute;vida que venha a ocorrer.
  </p>
  <p style="font-size:9px;text-align:right;margin-top:8px;">
    Aracaju, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de {$anoAtual}.
  </p>

  <!-- ASSINATURAS -->
  <table class="sigs">
    <tr>
      <td>
        <div class="sline">{$nomeContratante}</div>
        <div class="stitle">CONTRATANTE</div>
      </td>
      <td>
        <div class="sline">&nbsp;</div>
        <div class="stitle">CONTRATANTE</div>
        <div class="ssub">C&ocirc;njuge</div>
      </td>
      <td>
        <div class="sline">&nbsp;</div>
        <div class="stitle">{$appName}</div>
        <div class="ssub">Contratada</div>
      </td>
      <td>
        <div class="sline">{$nomeCorretor}</div>
        <div class="stitle">CORRETOR(A)</div>
        <div class="ssub">Credenciado</div>
      </td>
    </tr>
  </table>

  <p style="font-size:8px;font-weight:bold;text-transform:uppercase;margin-top:12px;">Testemunhas:</p>
  <table class="sigs">
    <tr>
      <td style="width:50%;">
        <div class="sline">{$test1Nome}</div>
        <div class="ssub">CPF: {$test1Cpf}</div>
      </td>
      <td style="width:50%;">
        <div class="sline">{$test2Nome}</div>
        <div class="ssub">CPF: {$test2Cpf}</div>
      </td>
    </tr>
  </table>

  {$docsHtmlAuth}

  <div class="doc-footer">
    {$appName} &mdash; Av. Hermes Fontes, n&ordm; 1524, Bairro Luzia &ndash; CEP 49.048.010 &ndash; Aracaju/SE &mdash;
    (79) 3304-0000 / 99691-0000 &mdash; contato@a4imobiliaria.com.br
    &mdash; Documento gerado em {$submDate}
  </div>

</div>
</body>
</html>
HTML;
}

// ============================================================
// TEMPLATE: AUTORIZAÇÃO DE LOCAÇÃO COM EXCLUSIVIDADE
// ============================================================

/**
 * Monta HTML do contrato de Autorização de Locação com Exclusividade.
 */
function buildLocacaoHTML(array $form, array $submission, array $data, array $settings): string
{
    $appName      = e($settings['app_name'] ?? APP_NAME);
    $primaryColor = $settings['primary_color'] ?? '#0e4f6c';
    $logoPath     = !empty($settings['logo_path'])
        ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path']
        : '';
    $submId       = (int) $submission['id'];
    $submDate     = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));
    $anoAtual     = date('Y');

    // Helper: retorna valor ou string vazia
    $d = function (string $key, string $default = '') use ($data): string {
        $v = trim($data[$key] ?? '');
        return e($v !== '' ? $v : $default);
    };

    // ── Dados do contratante ──────────────────────────────────────────
    $nomeContratante = $d('nome_razao_social');
    $sexo            = $d('sexo');
    $dataNasc        = $d('data_nascimento');
    $rg              = $d('rg');
    $orgaoExp        = $d('orgao_expedidor');
    $cpf             = $d('cpf');
    $naturalidade    = $d('naturalidade');
    $nacionalidade   = $d('nacionalidade');
    $cnpj            = $d('cnpj');
    $nomeFant        = $d('nome_fantasia');
    $estadoCivil     = $d('estado_civil');
    $conjuge         = $d('conjuge');
    $telefones       = $d('telefones');
    $endRes          = $d('endereco_residencial');
    $bairroRes       = $d('bairro_residencial');
    $cidUfRes        = $d('cidade_uf_residencial');
    $cepRes          = $d('cep_residencial');
    $telFixo         = $d('telefone_fixo');
    $celular         = $d('celular');
    $endCom          = $d('endereco_comercial');
    $bairroCom       = $d('bairro_comercial');
    $cidUfCom        = $d('cidade_uf_comercial');
    $cepCom          = $d('cep_comercial');
    $emails          = $d('emails');

    // ── Exclusividade ────────────────────────────────────────────────
    $comExclusividade = $d('com_exclusividade', '( )');

    // ── Imóvel ───────────────────────────────────────────────────────
    $tipoImovel      = $d('tipo_imovel');
    $endImovel       = $d('endereco_imovel');
    $bairroImovel    = $d('bairro_imovel');
    $cidUfImovel     = $d('cidade_uf_imovel');
    $cepImovel       = $d('cep_imovel');
    $pontoRef        = $d('ponto_referencia');
    $registroImovel  = $d('registro_imovel');
    $matriculaIptu   = $d('matricula_iptu');
    $energisaUc      = $d('energisa_uc');
    $deso            = $d('deso');
    $energisaUcNum   = $d('energisa_uc_num');
    $desoMatNum      = $d('deso_matricula_num');

    // ── Descrição ────────────────────────────────────────────────────
    $numDorm    = $d('num_dormitorios');
    $numSalas   = $d('num_salas');
    $numSuites  = $d('num_suites');
    $garagens   = $d('garagens');
    $areaPriv   = $d('area_privativa');
    $temVaranda = $d('tem_varanda');
    $temElevador= $d('tem_elevador');
    $lazer      = $d('lazer_completo');
    $obsDesc    = $d('obs_descricao', '');

    // ── Valor da locação ─────────────────────────────────────────────
    $valorLocacao       = $d('valor_locacao', '_______________');
    $valorLocacaoExtenso= $d('valor_locacao_extenso');
    $obsPreco           = $d('obs_preco', '');
    $valorCondo         = $d('valor_condominio', '_______________');
    $dataVencimento     = $d('data_vencimento');
    $valorIptuAnual     = $d('valor_iptu_anual', '_______________');
    $comissao           = $d('porcentagem_comissao', '___');
    $prazo              = $d('prazo_exclusividade', '___');

    // ── Assinaturas ──────────────────────────────────────────────────
    $nomeCorretor = $d('nome_corretor');
    $test1Nome    = $d('testemunha_1_nome');
    $test1Cpf     = $d('testemunha_1_cpf');
    $test2Nome    = $d('testemunha_2_nome');
    $test2Cpf     = $d('testemunha_2_cpf');

    // ── Logo base64 para o banner ────────────────────────────────────
    $logoBannerHtml = '';
    if (!empty($logoPath) && is_file($logoPath)) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logoPath);
        if (in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
            $b64 = base64_encode(file_get_contents($logoPath));
            $logoBannerHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"max-height:65px;max-width:120px;\">";
        }
    }
    $bannerLogoCell = $logoBannerHtml
        ? "<div style=\"background:rgba(255,255,255,.12);padding:6px 8px;border-radius:4px;text-align:center;\">{$logoBannerHtml}</div>"
        : "<div class=\"brand-box\"><span class=\"bname\">{$appName}</span><br><span class=\"bsub\">Imobili&aacute;ria</span></div>";

    // ── Checkbox visual ──────────────────────────────────────────────
    $excSim = ($comExclusividade === 'Sim') ? '&#x2713;' : '&nbsp;';
    $excNao = ($comExclusividade === 'Não') ? '&#x2713;' : '&nbsp;';

    // ── Documentos anexados ──────────────────────────────────────────
    $docLabels = [
        'doc_cpf_rg'    => 'RG / CPF do Propriet&aacute;rio',
        'doc_iptu'      => 'Carn&ecirc; / Comprovante de IPTU',
        'doc_matricula' => 'Matr&iacute;cula do Im&oacute;vel',
        'doc_outros'    => 'Outros Documentos',
    ];
    $docsRows = '';
    foreach ($docLabels as $key => $label) {
        $filePath = trim($data[$key] ?? '');
        if ($filePath === '') continue;
        $fileName = e(basename($filePath));
        $fileUrl  = e(APP_URL . '/uploads/' . ltrim($filePath, '/'));
        $docsRows .= "<tr>"
            . "<td style='width:35%;'><span class='fl'>{$label}</span></td>"
            . "<td><span class='fv' style='word-break:break-all;'>&#x1F4CE; "
            . "<a href='{$fileUrl}' style='color:#0e4f6c;text-decoration:underline;'>{$fileName}</a>"
            . "</span></td>"
            . "</tr>";
    }
    $docsHtml = $docsRows !== ''
        ? "<div class='section' style='margin-top:10px;'>"
          . "<div class='sec-title'>Documentos Anexados</div>"
          . "<table class='ft'>{$docsRows}</table>"
          . "</div>"
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1a2332; background: #fff; line-height: 1.45; }
  .page { padding: 20px 28px; }
  /* Banner */
  .banner { background: #0e4f6c; }
  .banner-inner { padding: 14px 20px; display: table; width: 100%; }
  .banner-logo { display: table-cell; vertical-align: middle; width: 120px; }
  .banner-title { display: table-cell; vertical-align: middle; text-align: center; }
  .banner-title h1 { color: #fff; font-size: 15px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
  .banner-title p  { color: rgba(255,255,255,.7); font-size: 8px; margin-top: 3px; }
  .brand-box { background: rgba(255,255,255,.15); padding: 8px 10px; text-align: center; border-radius: 4px; }
  .brand-box .bname { color: #fff; font-size: 14px; font-weight: bold; }
  .brand-box .bsub  { color: rgba(255,255,255,.75); font-size: 8px; }
  /* Seções */
  .section { margin-bottom: 10px; }
  .sec-title { font-weight: bold; font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; border-bottom: 2px solid #1a2332; padding-bottom: 2px; margin-bottom: 0; }
  /* Tabela de campos */
  .ft { width: 100%; border-collapse: collapse; }
  .ft td { border: 1px solid #b0bec5; padding: 2px 5px; font-size: 8px; }
  .fl { color: #546e7a; font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: .2px; display: block; }
  .fv { color: #1a2332; font-size: 8.5px; display: block; min-height: 10px; }
  /* Exclusividade */
  .exc-bar { display: table; width: 100%; border: 1.5px solid #0e4f6c; border-radius: 3px; margin-top: 8px; margin-bottom: 8px; background: #e8f4fd; padding: 4px 8px; }
  .exc-label { color: #0e4f6c; font-size: 9px; font-weight: bold; text-transform: uppercase; }
  .exc-opts { display: inline-block; margin-left: 12px; font-size: 9px; }
  .cb { display: inline-block; width: 9px; height: 9px; border: 1px solid #546e7a; margin-right: 2px; text-align: center; line-height: 9px; font-size: 8px; vertical-align: middle; }
  .cb-row { padding: 3px 5px; border: 1px solid #b0bec5; font-size: 8.5px; border-top: none; }
  .cb-first { border-top: 1px solid #b0bec5; }
  /* Legal */
  .legal { font-size: 8px; line-height: 1.7; color: #374151; text-align: justify; margin: 8px 0; padding: 8px 10px; background: #f8fafc; border-left: 3px solid #0e4f6c; }
  /* Cláusulas */
  .clause { font-size: 8px; line-height: 1.7; color: #374151; text-align: justify; margin-bottom: 5px; }
  .cl { font-weight: bold; }
  /* Assinaturas */
  .sigs { width: 100%; margin-top: 16px; border-collapse: collapse; }
  .sigs td { width: 25%; text-align: center; padding: 6px 4px 3px; vertical-align: bottom; }
  .sline { border-top: 1px solid #1a2332; padding-top: 2px; margin-bottom: 2px; font-size: 7.5px; min-height: 24px; }
  .stitle { font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
  .ssub   { font-size: 7px; color: #64748b; font-style: italic; }
  /* Watermark */
  .watermark { position: fixed; top: 35%; left: 5%; transform: rotate(-35deg); font-size: 70px; color: rgba(0,0,0,0.04); font-weight: bold; text-transform: uppercase; z-index: -1; letter-spacing: 8px; }
  /* Footer */
  .doc-footer { border-top: 1px solid #e2e8f0; margin-top: 12px; padding-top: 5px; text-align: center; font-size: 7px; color: #94a3b8; }
</style>
</head>
<body>
<div class="watermark">CONFIDENCIAL</div>
<div class="page">

  <!-- BANNER -->
  <div class="banner">
    <div class="banner-inner">
      <div class="banner-logo">{$bannerLogoCell}</div>
      <div class="banner-title">
        <h1>Autorização de Locação com Exclusividade</h1>
        <p>Contrato n&ordm; ALE-{$submId} &mdash; {$submDate}</p>
      </div>
    </div>
  </div>

  <!-- EXCLUSIVIDADE -->
  <div class="exc-bar" style="margin-top:8px;">
    <span class="exc-label">&#9733; Com Exclusividade:</span>
    <span class="exc-opts">
      <span class="cb">{$excSim}</span> Sim &nbsp;&nbsp;
      <span class="cb">{$excNao}</span> Não
    </span>
  </div>

  <!-- DADOS DO CONTRATANTE -->
  <div class="section">
    <div class="sec-title">Dados do Contratante</div>
    <table class="ft">
      <tr>
        <td style="width:60%"><span class="fl">Nome / Razão Social</span><span class="fv">{$nomeContratante}</span></td>
        <td><span class="fl">Sexo</span><span class="fv">{$sexo}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Data de Nascimento</span><span class="fv">{$dataNasc}</span></td>
        <td><span class="fl">RG n&ordm;</span><span class="fv">{$rg}</span></td>
        <td><span class="fl">&Oacute;rg&atilde;o Expedidor</span><span class="fv">{$orgaoExp}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CPF n&ordm;</span><span class="fv">{$cpf}</span></td>
        <td><span class="fl">Naturalidade</span><span class="fv">{$naturalidade}</span></td>
        <td><span class="fl">Nacionalidade</span><span class="fv">{$nacionalidade}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CNPJ n&ordm;</span><span class="fv">{$cnpj}</span></td>
        <td colspan="2"><span class="fl">Nome de Fantasia</span><span class="fv">{$nomeFant}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">C&ocirc;njuge</span><span class="fv">{$conjuge}</span></td>
        <td><span class="fl">Telefones</span><span class="fv">{$telefones}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Residencial</span><span class="fv">{$endRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroRes}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfRes}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepRes}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Telefone Fixo</span><span class="fv">{$telFixo}</span></td>
        <td colspan="2"><span class="fl">Celular / WhatsApp</span><span class="fv">{$celular}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endere&ccedil;o Comercial</span><span class="fv">{$endCom}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroCom}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfCom}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepCom}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">E-mail(s)</span><span class="fv">{$emails}</span></td>
      </tr>
    </table>
  </div>

  <!-- PARÁGRAFO LEGAL -->
  <div class="legal">
    O CONTRATANTE acima, propriet&aacute;rio e leg&iacute;timo possuidor do im&oacute;vel abaixo relacionado, contrata a
    <strong>{$appName}</strong>, inscrita no CRECI, para promover de forma <strong>EXCLUSIVA</strong>
    a <strong>LOCA&Ccedil;&Atilde;O</strong> do seu im&oacute;vel acima descrito, pelo prazo m&iacute;nimo de
    <strong>({$prazo}) dias</strong>, prorrog&aacute;veis automaticamente por per&iacute;odo igual e sucessivo,
    at&eacute; que uma das partes se manifeste em contr&aacute;rio, por escrito, pelo pre&ccedil;o e condi&ccedil;&otilde;es
    estipuladas nesta autoriza&ccedil;&atilde;o de <strong>LOCA&Ccedil;&Atilde;O</strong> do im&oacute;vel.
  </div>

  <!-- DADOS DO IMÓVEL -->
  <div class="section">
    <div class="sec-title">Dados do Im&oacute;vel</div>
    <div class="cb-row cb-first"><strong>Tipo:</strong> {$tipoImovel}</div>
    <table class="ft" style="border-top:none;">
      <tr>
        <td colspan="4"><span class="fl">Endere&ccedil;o Completo</span><span class="fv">{$endImovel}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroImovel}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfImovel}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepImovel}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Ponto de Refer&ecirc;ncia</span><span class="fv">{$pontoRef}</span></td>
      </tr>
      <tr>
        <td><span class="fl">N&ordm; e Registro do Im&oacute;vel</span><span class="fv">{$registroImovel}</span></td>
        <td><span class="fl">Matr&iacute;cula de IPTU</span><span class="fv">{$matriculaIptu}</span></td>
        <td><span class="fl">Energisa / UC</span><span class="fv">{$energisaUc}</span></td>
        <td><span class="fl">Deso</span><span class="fv">{$deso}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Energisa/UC N&ordm;</span><span class="fv">{$energisaUcNum}</span></td>
        <td colspan="2"><span class="fl">Deso Matr&iacute;cula N&ordm;</span><span class="fv">{$desoMatNum}</span></td>
      </tr>
    </table>
  </div>

  <!-- DESCRIÇÃO DO IMÓVEL -->
  <div class="section">
    <div class="sec-title">Descri&ccedil;&atilde;o do Im&oacute;vel</div>
    <table class="ft">
      <tr>
        <td><span class="fl">Dorm.</span><span class="fv">{$numDorm}</span></td>
        <td><span class="fl">Salas</span><span class="fv">{$numSalas}</span></td>
        <td><span class="fl">Su&iacute;tes</span><span class="fv">{$numSuites}</span></td>
        <td><span class="fl">Garagens</span><span class="fv">{$garagens}</span></td>
        <td><span class="fl">&Aacute;rea Privativa</span><span class="fv">{$areaPriv} m&sup2;</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Varanda?</span><span class="fv">{$temVaranda}</span></td>
        <td colspan="3"><span class="fl">Elevador?</span><span class="fv">{$temElevador}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Lazer Completo?</span><span class="fv">{$lazer}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Observa&ccedil;&otilde;es das descri&ccedil;&otilde;es do im&oacute;vel</span><span class="fv" style="min-height:22px;">{$obsDesc}</span></td>
      </tr>
    </table>
  </div>

  <!-- VALOR DA LOCAÇÃO -->
  <div class="section">
    <div class="sec-title">Valor da Loca&ccedil;&atilde;o</div>
    <table class="ft">
      <tr>
        <td style="width:30%"><span class="fl">Valor Pretendido R$</span><span class="fv"><strong>{$valorLocacao}</strong></span></td>
        <td><span class="fl">Por Extenso</span><span class="fv">{$valorLocacaoExtenso}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Observa&ccedil;&otilde;es do Pre&ccedil;o</span><span class="fv" style="min-height:16px;">{$obsPreco}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Valor do Condom&iacute;nio R$</span><span class="fv">{$valorCondo}</span></td>
        <td><span class="fl">Data de Vencimento</span><span class="fv">{$dataVencimento}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Valor Anual do IPTU R$</span><span class="fv">{$valorIptuAnual}</span></td>
        <td><span class="fl">Comiss&atilde;o (%)</span><span class="fv">{$comissao}%</span></td>
      </tr>
    </table>
  </div>

  <!-- CLÁUSULAS -->
  <div class="clause">
    <span class="cl">a)</span>&nbsp; Sobre o valor da <strong>LOCA&Ccedil;&Atilde;O</strong> do im&oacute;vel contratado, o CONTRATANTE pagar&aacute; a CONTRATADA {$comissao}%,
    pagamento esse que dever&aacute; ser feito no ato do recebimento dos valores da referida negocia&ccedil;&atilde;o.
  </div>
  <div class="clause">
    <span class="cl">b)</span>&nbsp; Nos termos do presente, o(a) CONTRATANTE autoriza &agrave; <strong>{$appName}</strong> a ofertar publicamente
    o im&oacute;vel de sua propriedade acima descrito, cuja &agrave;s custas ser&atilde;o de responsabilidade da CONTRATADA, fotografar o im&oacute;vel e suas
    depend&ecirc;ncias internas fazendo se publicar as fotos nos ve&iacute;culos e meios de comunica&ccedil;&atilde;o que desejar, inclusive na internet,
    afixar placas, faixas ou letreiros no im&oacute;vel, realizar visita&ccedil;&otilde;es e demonstra&ccedil;&otilde;es aos interessados.
  </div>
  <div class="clause">
    <span class="cl">c)</span>&nbsp; O Propriet&aacute;rio declara que o dito im&oacute;vel encontra-se livre e desembara&ccedil;ado de quaisquer &ocirc;nus ou
    restri&ccedil;&otilde;es que impe&ccedil;a sua <strong>LOCA&Ccedil;&Atilde;O</strong>, comprometendo-se em apresentar &agrave;s suas custas a documenta&ccedil;&atilde;o
    exigida em transa&ccedil;&otilde;es de LOCA&Ccedil;&Atilde;O, t&atilde;o logo que solicitado.
  </div>
  <p style="font-size:8px;text-align:justify;line-height:1.7;margin-top:5px;">
    E por estarem de pleno acordo, assinam a presente op&ccedil;&atilde;o em 02 (duas) vias de igual teor, na presen&ccedil;a de duas testemunhas,
    ficando eleito o foro da comarca de Aracaju para dirimir qualquer d&uacute;vida que venha a ocorrer.
  </p>
  <p style="font-size:9px;text-align:right;margin-top:8px;">
    Aracaju, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de {$anoAtual}.
  </p>

  <!-- ASSINATURAS -->
  <table class="sigs">
    <tr>
      <td>
        <div class="sline">{$nomeContratante}</div>
        <div class="stitle">CONTRATANTE</div>
      </td>
      <td>
        <div class="sline">&nbsp;</div>
        <div class="stitle">CONTRATANTE</div>
        <div class="ssub">C&ocirc;njuge</div>
      </td>
      <td>
        <div class="sline">&nbsp;</div>
        <div class="stitle">{$appName}</div>
        <div class="ssub">Contratada</div>
      </td>
      <td>
        <div class="sline">{$nomeCorretor}</div>
        <div class="stitle">CORRETOR(A)</div>
        <div class="ssub">Credenciado</div>
      </td>
    </tr>
  </table>

  <p style="font-size:8px;font-weight:bold;text-transform:uppercase;margin-top:12px;">Testemunhas:</p>
  <table class="sigs">
    <tr>
      <td style="width:50%;">
        <div class="sline">{$test1Nome}</div>
        <div class="ssub">CPF: {$test1Cpf}</div>
      </td>
      <td style="width:50%;">
        <div class="sline">{$test2Nome}</div>
        <div class="ssub">CPF: {$test2Cpf}</div>
      </td>
    </tr>
  </table>

  {$docsHtml}

  <div class="doc-footer">
    {$appName} &mdash; Av. Hermes Fontes, n&ordm; 1524, Bairro Luzia &ndash; CEP 49.048.010 &ndash; Aracaju/SE &mdash;
    (79) 3304-0000 / 99691-0000 &mdash; contato@a4imobiliaria.com.br
    &mdash; Documento gerado em {$submDate}
  </div>

</div>
</body>
</html>
HTML;
}

// ============================================================
// UTILITÁRIO: LOGO EM BASE64 PARA PDF
// ============================================================

/**
 * Converte a imagem do logo para base64 para incluir no PDF.
 * DomPDF com isRemoteEnabled=false não carrega URLs externas,
 * então embedamos a imagem.
 *
 * @param string $logoAbsPath Caminho absoluto da imagem
 * @param string $altText
 * @return string HTML img ou string vazia
 */
function buildLogoImg(string $logoAbsPath, string $altText): string
{
    if (empty($logoAbsPath) || !is_file($logoAbsPath)) {
        return '';
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logoAbsPath);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        return '';
    }

    $b64 = base64_encode(file_get_contents($logoAbsPath));
    $alt = htmlspecialchars($altText, ENT_QUOTES, 'UTF-8');

    return "<img src=\"data:{$mime};base64,{$b64}\" alt=\"{$alt}\" style=\"max-height:55px;max-width:150px;\">";
}
