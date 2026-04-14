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
    $primaryColor = $settings['primary_color'] ?? '#2563EB';

    // Campos específicos
    $d = function (string $key, string $default = '_______________') use ($data): string {
        return e(!empty($data[$key]) ? $data[$key] : $default);
    };

    $valorFormatado  = !empty($data['valor_minimo']) ? formatCurrency($data['valor_minimo']) : '_______________';
    $prazo           = $d('prazo_exclusividade') . ' dias';
    $comissao        = $d('comissao') . '%';
    $dataAssinatura  = !empty($data['data_assinatura'])
        ? formatDate($data['data_assinatura'])
        : $submDate;

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1e293b; background: #fff; line-height: 1.6; }
  .page { padding: 28px 38px; }
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
  .watermark { position: fixed; top: 42%; left: 5%; transform: rotate(-35deg); font-size: 65px; color: rgba(0,0,0,0.035); font-weight: bold; text-transform: uppercase; z-index: -1; letter-spacing: 8px; }
</style>
</head>
<body>
<div class="watermark">CONFIDENCIAL</div>
<div class="page">

  <!-- Cabeçalho -->
  <div class="header">
    <div class="brand">
      <div class="brand-name">{$appName}</div>
      <div class="brand-sub">Corretora de Imóveis</div>
    </div>
    <div class="logo">{$logoHtml}</div>
  </div>

  <!-- Título -->
  <div class="doc-title">
    <h1>Autorização de Venda com Exclusividade</h1>
    <p>Contrato firmado entre as partes abaixo identificadas</p>
  </div>

  <!-- Metadados -->
  <div class="doc-meta">
    <span>Contrato Nº: <strong>AVE-{$submId}</strong></span>
    <span>Data de Autorização: <strong>{$dataAssinatura}</strong></span>
  </div>

  <!-- Identificação do Imóvel -->
  <div class="section">
    <div class="section-title">1. Identificação do Imóvel</div>
    <div class="grid-2">
      <div class="field-item w33">
        <span class="field-label">Tipo</span>
        <span class="field-value">{$d('tipo_imovel')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">CEP</span>
        <span class="field-value">{$d('cep')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">Estado</span>
        <span class="field-value">{$d('estado')}</span>
      </div>
      <div class="field-item full">
        <span class="field-label">Endereço</span>
        <span class="field-value">{$d('endereco')}</span>
      </div>
      <div class="field-item">
        <span class="field-label">Bairro</span>
        <span class="field-value">{$d('bairro')}</span>
      </div>
      <div class="field-item">
        <span class="field-label">Cidade</span>
        <span class="field-value">{$d('cidade')}</span>
      </div>
    </div>
  </div>

  <!-- Características do Imóvel -->
  <div class="section">
    <div class="section-title">2. Características do Imóvel</div>
    <div class="grid-2">
      <div class="field-item w33">
        <span class="field-label">Área Total</span>
        <span class="field-value">{$d('area_total')} m²</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">Quartos</span>
        <span class="field-value">{$d('quartos')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">Suítes</span>
        <span class="field-value">{$d('suites')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">Área Constr.</span>
        <span class="field-value">{$d('area_construida')} m²</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">Banheiros</span>
        <span class="field-value">{$d('banheiros')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">Garagem</span>
        <span class="field-value">{$d('garagem')} vaga(s)</span>
      </div>
      <div class="field-item full">
        <span class="field-label">Descrição</span>
        <span class="field-value">{$d('descricao_imovel', '')}</span>
      </div>
    </div>
  </div>

  <!-- Dados do Proprietário -->
  <div class="section">
    <div class="section-title">3. Dados do Proprietário (Contratante)</div>
    <div class="grid-2">
      <div class="field-item full">
        <span class="field-label">Nome Completo</span>
        <span class="field-value">{$d('contratante_nome')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">CPF</span>
        <span class="field-value">{$d('contratante_cpf')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">RG</span>
        <span class="field-value">{$d('contratante_rg')}</span>
      </div>
      <div class="field-item w33">
        <span class="field-label">Estado Civil</span>
        <span class="field-value">{$d('contratante_estado_civil')}</span>
      </div>
      <div class="field-item">
        <span class="field-label">Telefone</span>
        <span class="field-value">{$d('contratante_telefone')}</span>
      </div>
      <div class="field-item">
        <span class="field-label">E-mail</span>
        <span class="field-value">{$d('contratante_email', '')}</span>
      </div>
    </div>
  </div>

  <!-- Condições da Autorização -->
  <div class="section">
    <div class="section-title">4. Condições da Autorização</div>
    <div class="grid-2">
      <div class="field-item">
        <span class="field-label">Valor Mínimo</span>
        <span class="field-value"><strong>{$valorFormatado}</strong></span>
      </div>
      <div class="field-item">
        <span class="field-label">Comissão</span>
        <span class="field-value"><strong>{$comissao}</strong></span>
      </div>
      <div class="field-item">
        <span class="field-label">Exclusividade</span>
        <span class="field-value">{$prazo}</span>
      </div>
      <div class="field-item">
        <span class="field-label">Pag. Aceito</span>
        <span class="field-value">{$d('forma_pagamento', '')}</span>
      </div>
      <div class="field-item full">
        <span class="field-label">Observações</span>
        <span class="field-value">{$d('observacoes', '')}</span>
      </div>
    </div>
  </div>

  <!-- Cláusulas -->
  <div class="section">
    <div class="legal-text">
      <strong>CLÁUSULA 1ª – DO OBJETO:</strong> Pelo presente instrumento particular, o(a) PROPRIETÁRIO(A) acima identificado(a) autoriza exclusivamente a imobiliária <strong>{$appName}</strong> a intermediar a venda do imóvel descrito neste documento, nas condições aqui estabelecidas.<br><br>
      <strong>CLÁUSULA 2ª – DA EXCLUSIVIDADE:</strong> Durante o prazo de exclusividade de <strong>{$prazo}</strong>, fica vedado ao(à) proprietário(a) negociar diretamente ou por meio de terceiros sem anuência expressa da imobiliária, sob pena de pagamento integral da comissão acordada.<br><br>
      <strong>CLÁUSULA 3ª – DA COMISSÃO:</strong> Concluída a venda, o(a) proprietário(a) pagará à imobiliária a comissão de <strong>{$comissao}</strong> sobre o valor efetivo da transação, devida no ato da assinatura do contrato de compra e venda ou instrumento equivalente.<br><br>
      <strong>CLÁUSULA 4ª – DO FORO:</strong> As partes elegem o foro da comarca do imóvel para dirimir eventuais controvérsias decorrentes deste instrumento.
    </div>
  </div>

  <!-- Assinaturas -->
  <div class="signatures">
    <div class="sig-block">
      <div class="sig-line"></div>
      <div class="sig-name">{$d('contratante_nome')}</div>
      <div class="sig-label">Proprietário(a) / Contratante</div>
    </div>
    <div class="sig-block">
      <div class="sig-line"></div>
      <div class="sig-name">{$appName}</div>
      <div class="sig-label">Imobiliária / Contratada</div>
    </div>
  </div>

  <div class="footer">
    Contrato Nº AVE-{$submId} &mdash; Gerado em {$dataAssinatura} &mdash; {$appName}
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
