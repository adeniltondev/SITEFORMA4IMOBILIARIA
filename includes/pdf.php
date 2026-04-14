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
        $label = e($field['label'] ?? $name);
        $value = e($data[$name] ?? '—');

        if (($field['type'] ?? '') === 'checkbox') {
            $value = !empty($data[$name]) ? 'Sim' : 'Não';
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
    $appName     = e($settings['app_name'] ?? APP_NAME);
    $logoPath    = !empty($settings['logo_path'])
        ? LOGO_PATH . DIRECTORY_SEPARATOR . $settings['logo_path']
        : '';
    $logoHtml    = buildLogoImg($logoPath, $appName);
    $submId      = (int) $submission['id'];
    $submDate    = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'));

    // Helper: retorna valor do campo ou linha em branco
    $d = function (string $key, string $default = '') use ($data): string {
        $v = trim($data[$key] ?? '');
        return e($v !== '' ? $v : $default);
    };
    $blank = '&nbsp;';

    $prazo    = $d('prazo_exclusividade', '___');
    $comissao = $d('porcentagem_comissao', '___');
    $valorMinimo   = $d('valor_minimo_venda', '_______________');
    $valorExtenso  = $d('valor_minimo_extenso');
    $valorCondo    = $d('valor_condominio', '_______________');
    $condoExtenso  = $d('valor_condominio_extenso');
    $dataAssinatura = $submDate;

    // Contratante
    $nomeContratante  = $d('nome_razao_social');
    $sexo             = $d('sexo');
    $dataNasc         = $d('data_nascimento');
    $rg               = $d('rg');
    $orgaoExp         = $d('orgao_expedidor');
    $cpf              = $d('cpf');
    $naturalidade     = $d('naturalidade');
    $nacionalidade    = $d('nacionalidade');
    $cnpj             = $d('cnpj');
    $nomeFant         = $d('nome_fantasia');
    $estadoCivil      = $d('estado_civil');
    $conjuge          = $d('conjuge');
    $telefones        = $d('telefones');
    $endRes           = $d('endereco_residencial');
    $bairroRes        = $d('bairro_residencial');
    $cidUfRes         = $d('cidade_uf_residencial');
    $cepRes           = $d('cep_residencial');
    $telFixo          = $d('telefone_fixo');
    $celular          = $d('celular');
    $endCom           = $d('endereco_comercial');
    $bairroCom        = $d('bairro_comercial');
    $cidUfCom         = $d('cidade_uf_comercial');
    $cepCom           = $d('cep_comercial');
    $emails           = $d('emails');
    // Imóvel
    $tipoImovel       = $d('tipo_imovel');
    $situacaoImovel   = $d('situacao_imovel');
    $endImovel        = $d('endereco_imovel');
    $bairroImovel     = $d('bairro_imovel');
    $cidUfImovel      = $d('cidade_uf_imovel');
    $cepImovel        = $d('cep_imovel');
    $pontoRef         = $d('ponto_referencia');
    $registroImovel   = $d('registro_imovel');
    $matriculaIptu    = $d('matricula_iptu');
    // Descrição
    $numDorm          = $d('num_dormitorios');
    $numSalas         = $d('num_salas');
    $numSuites        = $d('num_suites');
    $garagens         = $d('garagens');
    $areaPriv         = $d('area_privativa');
    $temVaranda       = $d('tem_varanda');
    $temElevador      = $d('tem_elevador');
    $lazer            = $d('lazer_completo');
    $garagemCob       = $d('garagem_coberta');
    $obsDesc          = $d('obs_descricao', '');
    // Condições
    $obsPreco         = $d('obs_preco', '');
    $formasPag        = $d('formas_pagamento');
    // Assinaturas
    $nomeCorretor     = $d('nome_corretor');
    $test1Nome        = $d('testemunha_1_nome');
    $test1Cpf         = $d('testemunha_1_cpf');
    $test2Nome        = $d('testemunha_2_nome');
    $test2Cpf         = $d('testemunha_2_cpf');
    $anoAtual         = date('Y');

    // Logo como base64 para o banner
    $logoBannerHtml = '';
    if (!empty($logoPath) && is_file($logoPath)) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($logoPath);
        if (in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
            $b64 = base64_encode(file_get_contents($logoPath));
            $logoBannerHtml = "<img src=\"data:{$mime};base64,{$b64}\" style=\"max-height:65px;max-width:120px;\">";
        }
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1a2332; background: #fff; line-height: 1.5; }
  .page { padding: 22px 30px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid {$primaryColor}; padding-bottom: 12px; margin-bottom: 16px; }
  .header .brand { }
  .brand-name { font-size: 17px; font-weight: bold; color: {$primaryColor}; }
  .brand-sub { font-size: 9px; color: #64748b; }
  .header .logo img { max-height: 55px; max-width: 150px; }
  .doc-title { text-align: center; margin: 10px 0 16px; }
  .doc-title h1 { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #0f172a; }
  .doc-title p { font-size: 9.5px; color: #64748b; margin-top: 4px; }
  .section { margin-bottom: 16px; }
  .section-title { background: {$primaryColor}; color: #fff; padding: 5px 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
  .grid-2 { display: flex; flex-wrap: wrap; gap: 0; }
  .field-item { width: 50%; display: flex; border: 1px solid #e2e8f0; border-collapse: collapse; }
  .field-item.full { width: 100%; }
  .field-item.w33 { width: 33.33%; }
  .field-label { background: #f8fafc; padding: 5px 8px; font-size: 9px; color: #64748b; width: 40%; min-width: 90px; font-weight: bold; border-right: 1px solid #e2e8f0; }
  .field-value { padding: 5px 8px; font-size: 10px; color: #0f172a; flex: 1; }
  .legal-text { font-size: 9px; color: #334155; margin-bottom: 14px; text-align: justify; line-height: 1.7; }
  .signatures { margin-top: 28px; display: flex; justify-content: space-around; }
  .sig-block { text-align: center; width: 42%; }
  .sig-line { border-top: 1px solid #1e293b; margin-bottom: 5px; }
  .sig-name { font-size: 9px; font-weight: bold; }
  .sig-label { font-size: 8px; color: #64748b; }
  .footer { text-align: center; font-size: 8.5px; color: #94a3b8; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 8px; }
  .doc-meta { display: flex; justify-content: space-between; font-size: 9px; color: #64748b; margin-bottom: 14px; }
  .watermark { position: fixed; top: 35%; left: 5%; transform: rotate(-35deg); font-size: 70px; color: rgba(0,0,0,0.04); font-weight: bold; text-transform: uppercase; z-index: -1; letter-spacing: 8px; }
  /* Banner header */
  .banner { background: #0e4f6c; }
  .banner-inner { padding: 16px 22px; display: table; width: 100%; }
  .banner-logo { display: table-cell; vertical-align: middle; width: 130px; }
  .banner-title { display: table-cell; vertical-align: middle; text-align: center; }
  .banner-title h1 { color: #fff; font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
  .banner-title p { color: rgba(255,255,255,.7); font-size: 8px; margin-top: 3px; }
  .brand-box { background: rgba(255,255,255,.15); padding: 8px 10px; text-align: center; border-radius: 4px; }
  .brand-box .bname { color: #fff; font-size: 15px; font-weight: bold; }
  .brand-box .bsub  { color: rgba(255,255,255,.75); font-size: 8px; }
  /* Seções */
  .section { margin-bottom: 12px; }
  .sec-title { font-weight: bold; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; border-bottom: 2px solid #1a2332; padding-bottom: 3px; margin-bottom: 0; }
  /* Tabela de campos */
  .ft { width: 100%; border-collapse: collapse; }
  .ft td { border: 1px solid #b0bec5; padding: 3px 6px; font-size: 8.5px; }
  .fl { color: #546e7a; font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .2px; display: block; }
  .fv { color: #1a2332; font-size: 9px; display: block; min-height: 11px; }
  /* Checkbox marks */
  .cb { display: inline-block; width: 8px; height: 8px; border: 1px solid #546e7a; margin-right: 2px; text-align: center; line-height: 8px; font-size: 7px; vertical-align: middle; }
  .cb-row { padding: 4px 6px; border: 1px solid #b0bec5; font-size: 8.5px; border-top: none; }
  .cb-first { border-top: 1px solid #b0bec5; }
  /* Legal */
  .legal { font-size: 8.5px; line-height: 1.75; color: #374151; text-align: justify; margin: 10px 0; padding: 10px 12px; background: #f8fafc; border-left: 3px solid #0e4f6c; }
  /* Cláusulas */
  .clause { font-size: 8.5px; line-height: 1.75; color: #374151; text-align: justify; margin-bottom: 6px; }
  .cl { font-weight: bold; }
  /* Assinaturas */
  .sigs { width: 100%; margin-top: 20px; border-collapse: collapse; }
  .sigs td { width: 25%; text-align: center; padding: 8px 4px 4px; vertical-align: bottom; }
  .sline { border-top: 1px solid #1a2332; padding-top: 3px; margin-bottom: 2px; font-size: 8px; min-height: 28px; }
  .stitle { font-size: 8px; font-weight: bold; text-transform: uppercase; }
  .ssub   { font-size: 7px; color: #64748b; font-style: italic; }
  /* Footer */
  .doc-footer { border-top: 1px solid #e2e8f0; margin-top: 14px; padding-top: 6px; text-align: center; font-size: 7.5px; color: #94a3b8; }
  .doc-footer a { color: #64748b; }
</style>
</head>
<body>
<div class="watermark">CONFIDENCIAL</div>
<div class="page">

  <!-- ===== BANNER ===== -->
  <div class="banner">
    <div class="banner-inner">
      <div class="banner-logo">
        <?php if ($logoBannerHtml): ?>
          <div style="background:rgba(255,255,255,.12);padding:6px 8px;border-radius:4px;text-align:center;">{$logoBannerHtml}</div>
        <?php else: ?>
          <div class="brand-box"><span class="bname">{$appName}</span><br><span class="bsub">Imobiliária</span></div>
        <?php endif; ?>
      </div>
      <div class="banner-title">
        <h1>Autorização de Venda com Exclusividade</h1>
        <p>Contrato nº AVE-{$submId} &mdash; {$dataAssinatura}</p>
      </div>
    </div>
  </div>

  <!-- ===== DADOS DO CONTRATANTE ===== -->
  <div class="section" style="margin-top:10px;">
    <div class="sec-title">Dados do Contratante</div>
    <table class="ft">
      <tr>
        <td style="width:60%"><span class="fl">Nome / Razão Social</span><span class="fv">{$nomeContratante}</span></td>
        <td><span class="fl">Sexo</span><span class="fv">{$sexo}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Data de Nascimento</span><span class="fv">{$dataNasc}</span></td>
        <td><span class="fl">RG nº</span><span class="fv">{$rg}</span></td>
        <td><span class="fl">Órgão Expedidor</span><span class="fv">{$orgaoExp}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CPF nº</span><span class="fv">{$cpf}</span></td>
        <td><span class="fl">Naturalidade</span><span class="fv">{$naturalidade}</span></td>
        <td><span class="fl">Nacionalidade</span><span class="fv">{$nacionalidade}</span></td>
      </tr>
      <tr>
        <td><span class="fl">CNPJ nº</span><span class="fv">{$cnpj}</span></td>
        <td colspan="2"><span class="fl">Nome de Fantasia</span><span class="fv">{$nomeFant}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Estado Civil</span><span class="fv">{$estadoCivil}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Cônjuge</span><span class="fv">{$conjuge}</span></td>
        <td><span class="fl">Telefones</span><span class="fv">{$telefones}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Endereço Residencial</span><span class="fv">{$endRes}</span></td>
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
        <td colspan="3"><span class="fl">Endereço Comercial</span><span class="fv">{$endCom}</span></td>
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

  <!-- Parágrafo legal -->
  <div class="legal">
    O CONTRATANTE acima, proprietário e legítimo possuidor do imóvel abaixo relacionado, contrata a <strong>{$appName}</strong>
    para promover de forma <strong>EXCLUSIVA</strong> a <strong>VENDA</strong> do seu imóvel acima descrito, pelo prazo mínimo de
    <strong>{$prazo} dias</strong>, prorrogáveis automaticamente por período igual e sucessivo, até que uma das partes
    se manifeste em contrário, por escrito, pelo preço e condições estipuladas nesta autorização de <strong>VENDA</strong>.
  </div>

  <!-- ===== DADOS DO IMÓVEL ===== -->
  <div class="section">
    <div class="sec-title">Dados do Imóvel</div>
    <div class="cb-row cb-first"><strong>Tipo:</strong> {$tipoImovel}</div>
    <div class="cb-row"><strong>Situação:</strong> {$situacaoImovel}</div>
    <table class="ft" style="border-top:none;">
      <tr>
        <td colspan="3"><span class="fl">Endereço</span><span class="fv">{$endImovel}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Bairro</span><span class="fv">{$bairroImovel}</span></td>
        <td><span class="fl">Cidade / UF</span><span class="fv">{$cidUfImovel}</span></td>
        <td><span class="fl">CEP</span><span class="fv">{$cepImovel}</span></td>
      </tr>
      <tr>
        <td colspan="3"><span class="fl">Ponto de Referência</span><span class="fv">{$pontoRef}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Nº e Registro do Imóvel</span><span class="fv">{$registroImovel}</span></td>
        <td><span class="fl">Matrícula de IPTU nº</span><span class="fv">{$matriculaIptu}</span></td>
      </tr>
    </table>
  </div>

  <!-- ===== DESCRIÇÃO DO IMÓVEL ===== -->
  <div class="section">
    <div class="sec-title">Descrição do Imóvel</div>
    <table class="ft">
      <tr>
        <td><span class="fl">Dormitórios</span><span class="fv">{$numDorm}</span></td>
        <td><span class="fl">Salas</span><span class="fv">{$numSalas}</span></td>
        <td><span class="fl">Suítes</span><span class="fv">{$numSuites}</span></td>
        <td><span class="fl">Garagens</span><span class="fv">{$garagens}</span></td>
        <td><span class="fl">Área Privativa</span><span class="fv">{$areaPriv} m²</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Tem varanda?</span><span class="fv">{$temVaranda}</span></td>
        <td colspan="3"><span class="fl">Tem elevador?</span><span class="fv">{$temElevador}</span></td>
      </tr>
      <tr>
        <td colspan="2"><span class="fl">Lazer completo?</span><span class="fv">{$lazer}</span></td>
        <td colspan="3"><span class="fl">Garagem coberta?</span><span class="fv">{$garagemCob}</span></td>
      </tr>
      <tr>
        <td colspan="5"><span class="fl">Observações sobre as descrições</span><span class="fv" style="min-height:28px;">{$obsDesc}</span></td>
      </tr>
    </table>
  </div>

  <!-- ===== CONDIÇÕES PRETENDIDAS ===== -->
  <div class="section">
    <div class="sec-title">Condições Pretendidas</div>
    <table class="ft">
      <tr>
        <td><span class="fl">Valor mínimo de venda R$</span><span class="fv"><strong>{$valorMinimo}</strong> ({$valorExtenso})</span></td>
      </tr>
      <tr>
        <td><span class="fl">Observações do preço</span><span class="fv" style="min-height:18px;">{$obsPreco}</span></td>
      </tr>
      <tr>
        <td><span class="fl">Valor do condomínio R$</span><span class="fv">{$valorCondo} ({$condoExtenso})</span></td>
      </tr>
      <tr>
        <td style="width:30%"><span class="fl">Comissão (%)</span><span class="fv">{$comissao}%</span></td>
        <td style="width:30%"><span class="fl">Prazo de Exclusividade</span><span class="fv">{$prazo} dias</span></td>
        <td><span class="fl">Formas de Pagamento Aceitas</span><span class="fv">{$formasPag}</span></td>
      </tr>
    </table>
  </div>

  <!-- Cláusulas -->
  <div class="clause">
    <span class="cl">a)</span> Sobre o valor da <strong>VENDA</strong> do imóvel contratado, o CONTRATANTE pagará a CONTRATADA {$comissao}%,
    pagamento esse que deverá ser feito no ato do recebimento dos valores da referida negociação.
  </div>
  <div class="clause">
    <span class="cl">b)</span> Nos termos do presente, o(a) CONTRATANTE autoriza à <strong>{$appName}</strong> a ofertar publicamente
    o imóvel de sua propriedade acima descrito, cuja às custas serão de responsabilidade da CONTRATADA, fotografar o imóvel e suas
    dependências internas fazendo se publicar as fotos nos veículos e meios de comunicação que desejar, inclusive na internet,
    afixar placas, faixas ou letreiros no imóvel, realizar visitações e demonstrações aos interessados.
  </div>
  <div class="clause">
    <span class="cl">c)</span> O Proprietário declara que o dito imóvel encontra-se livre e desembaraçado de quaisquer ônus ou
    restrições que impeçam sua <strong>VENDA</strong>, comprometendo-se em apresentar às suas custas a documentação exigida
    em transações de VENDA, tão logo que solicitado.
  </div>
  <p style="font-size:8.5px;text-align:justify;line-height:1.75;margin-top:6px;">
    E por estarem de pleno acordo, assinam a presente opção em 02 (duas) vias de igual teor, na presença de duas testemunhas,
    ficando eleito o foro da comarca de Aracaju para dirimir qualquer dúvida que venha a ocorrer.
  </p>
  <p style="font-size:9px;text-align:right;margin-top:10px;">
    Aracaju, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; de {$anoAtual}.
  </p>

  <!-- ===== ASSINATURAS ===== -->
  <table class="sigs">
    <tr>
      <td>
        <div class="sline">{$nomeContratante}</div>
        <div class="stitle">CONTRATANTE</div>
      </td>
      <td>
        <div class="sline">&nbsp;</div>
        <div class="stitle">CONTRATANTE</div>
        <div class="ssub">Cônjuge</div>
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

  <p style="font-size:8.5px;font-weight:bold;text-transform:uppercase;margin-top:14px;">Testemunhas:</p>
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

  <div class="doc-footer">
    {$appName} &mdash; Av. Hermes Fontes, nº 1524, Bairro Luzia &ndash; CEP 49.048.010 &ndash; Aracaju/SE &mdash;
    (79) 3304-0000 / 99691-0000 &mdash; contato@a4imobiliaria.com.br
    &mdash; Documento gerado em {$dataAssinatura}
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
