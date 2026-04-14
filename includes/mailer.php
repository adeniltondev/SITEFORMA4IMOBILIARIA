<?php
/**
 * Envio de e-mail via PHPMailer / SMTP
 *
 * @package FORMA4
 */

require_once __DIR__ . '/functions.php';

$composerAutoload = APP_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!file_exists($composerAutoload)) {
    throw new RuntimeException('Vendor não encontrado. Execute: composer install');
}
require_once $composerAutoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ============================================================
// FUNÇÃO PRINCIPAL
// ============================================================

/**
 * Envia o PDF de uma submissão por e-mail.
 *
 * @param array  $submission Dados da submissão
 * @param array  $form       Dados do formulário
 * @param string $pdfPath    Caminho relativo do PDF (ex: pdfs/arquivo.pdf)
 * @param array  $settings   Configurações do sistema
 * @return bool
 */
function sendSubmissionEmail(
    array $submission,
    array $form,
    string $pdfPath,
    array $settings = []
): bool {
    $smtpHost    = $settings['smtp_host']      ?? '';
    $smtpPort    = (int) ($settings['smtp_port'] ?? 465);
    $smtpUser    = $settings['smtp_user']      ?? '';
    $smtpPass    = $settings['smtp_pass']      ?? '';
    $fromName    = $settings['smtp_from_name'] ?? ($settings['app_name'] ?? APP_NAME);
    $fromEmail   = $settings['smtp_from_email'] ?? $smtpUser;
    $smtpSecure  = strtolower($settings['smtp_secure'] ?? 'ssl');
    $recipient   = $settings['email_recipient'] ?? '';

    // Se não há destinatário ou credenciais, aborta silenciosamente
    if (empty($recipient) || empty($smtpHost) || empty($smtpUser)) {
        error_log('[FORMA4 MAIL] Configurações SMTP incompletas. E-mail não enviado.');
        return false;
    }

    $pdfAbsPath = PDF_PATH . DIRECTORY_SEPARATOR . basename($pdfPath);
    if (!is_file($pdfAbsPath)) {
        error_log('[FORMA4 MAIL] Arquivo PDF não encontrado: ' . $pdfAbsPath);
        return false;
    }

    try {
        $mail = new PHPMailer(true);

        // Servidor SMTP
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->Port       = $smtpPort;

        // Tipo de criptografia
        if ($smtpSecure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpSecure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Remetente e destinatário
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipient);
        $mail->CharSet = 'UTF-8';

        // Conteúdo
        $submId    = (int) $submission['id'];
        $formTitle = $form['title'] ?? 'Formulário';
        $submDate  = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'), true);

        $mail->isHTML(true);
        $mail->Subject = "[{$fromName}] Novo envio: {$formTitle} (#{$submId})";
        $mail->Body    = buildEmailBody($form, $submission, $settings);
        $mail->AltBody = "Novo envio recebido: {$formTitle} | #{$submId} | Data: {$submDate}";

        // Anexa o PDF
        $mail->addAttachment($pdfAbsPath, "envio_{$submId}.pdf");

        $mail->send();
        return true;

    } catch (PHPMailerException $e) {
        error_log('[FORMA4 MAIL] Erro ao enviar e-mail: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// TEMPLATE HTML DO E-MAIL
// ============================================================

/**
 * Monta o corpo HTML do e-mail de notificação.
 */
function buildEmailBody(array $form, array $submission, array $settings): string
{
    $appName    = e($settings['app_name'] ?? APP_NAME);
    $appUrl     = $settings['app_url']   ?? APP_URL;
    $formTitle  = e($form['title']);
    $submId     = (int) $submission['id'];
    $submDate   = formatDate($submission['created_at'] ?? date('Y-m-d H:i:s'), true);
    $primaryColor = $settings['primary_color'] ?? '#2563EB';

    $data   = is_array($submission['data'])
        ? $submission['data']
        : json_decode($submission['data'], true);
    $fields = decodeFields($form['fields']);

    $rows = '';
    foreach ($fields as $field) {
        $name  = $field['name'] ?? '';
        $label = e($field['label'] ?? $name);
        $value = e($data[$name] ?? '—');

        $rows .= "
        <tr>
            <td style=\"padding:8px 12px;background:#f8fafc;font-size:12px;color:#64748b;font-weight:bold;width:35%;border:1px solid #e2e8f0;\">{$label}</td>
            <td style=\"padding:8px 12px;font-size:12px;color:#1e293b;border:1px solid #e2e8f0;\">{$value}</td>
        </tr>";
    }

    $adminLink = "{$appUrl}/admin/submission-view.php?id={$submId}";

    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">
  <div style="max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    <div style="background:{$primaryColor};padding:24px 30px;">
      <h1 style="margin:0;color:#fff;font-size:20px;">{$appName}</h1>
      <p style="margin:4px 0 0;color:rgba(255,255,255,.8);font-size:13px;">Notificação de novo envio de formulário</p>
    </div>
    <div style="padding:28px 30px;">
      <h2 style="margin:0 0 4px;font-size:16px;color:#1e293b;">{$formTitle}</h2>
      <p style="margin:0 0 20px;font-size:12px;color:#64748b;">Envio <strong>#{$submId}</strong> &mdash; {$submDate}</p>

      <table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
        {$rows}
      </table>

      <p style="margin:0 0 20px;font-size:12px;color:#64748b;">
        O PDF com todos os dados está em anexo neste e-mail.
      </p>

      <a href="{$adminLink}" style="display:inline-block;background:{$primaryColor};color:#fff;padding:10px 22px;border-radius:6px;font-size:13px;text-decoration:none;font-weight:bold;">
        Ver no Painel
      </a>
    </div>
    <div style="background:#f8fafc;padding:14px 30px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e2e8f0;">
      Mensagem gerada automaticamente por {$appName}. Não responda este e-mail.
    </div>
  </div>
</body>
</html>
HTML;
}
