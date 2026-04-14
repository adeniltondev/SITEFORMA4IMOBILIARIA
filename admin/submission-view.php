<?php
/**
 * Visualização detalhada de um envio + download de PDF
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db   = Database::getInstance();
$subId = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$subId) {
    setFlash('Envio inválido.', 'error');
    header('Location: ' . APP_URL . '/admin/submissions.php');
    exit;
}

$submission = $db->fetchOne(
    'SELECT s.*, f.title AS form_title, f.slug AS form_slug, f.fields, f.pdf_template, f.id AS form_id_val
     FROM submissions s
     JOIN forms f ON f.id = s.form_id
     WHERE s.id = ? LIMIT 1',
    [$subId]
);

if (!$submission) {
    setFlash('Envio não encontrado.', 'error');
    header('Location: ' . APP_URL . '/admin/submissions.php');
    exit;
}

// -------------------------------------------------------
// Download PDF
// -------------------------------------------------------
if (isset($_GET['download']) && $_GET['download'] == '1') {
    if (!empty($submission['pdf_path'])) {
        $pdfFile = PDF_PATH . DIRECTORY_SEPARATOR . basename($submission['pdf_path']);
        if (is_file($pdfFile)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="envio_' . $subId . '.pdf"');
            header('Content-Length: ' . filesize($pdfFile));
            readfile($pdfFile);
            exit;
        }
    }
    setFlash('Arquivo PDF não encontrado.', 'error');
    header('Location: ' . APP_URL . '/admin/submission-view.php?id=' . $subId);
    exit;
}

// -------------------------------------------------------
// Reenviar e-mail
// -------------------------------------------------------
if (isset($_POST['resend_email']) && validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
    if (!empty($submission['pdf_path'])) {
        require_once APP_PATH . '/includes/mailer.php';
        $sysSettings = getAllSettings();
        $form        = ['id' => $submission['form_id_val'], 'title' => $submission['form_title'], 'fields' => $submission['fields']];
        $subArr      = ['id' => $submission['id'], 'data' => $submission['data'], 'created_at' => $submission['created_at']];
        $sent        = sendSubmissionEmail($subArr, $form, $submission['pdf_path'], $sysSettings);

        if ($sent) {
            $db->query('UPDATE submissions SET email_sent = 1 WHERE id = ?', [$subId]);
            setFlash('E-mail reenviado com sucesso!', 'success');
        } else {
            setFlash('Falha ao reenviar e-mail. Verifique as configurações SMTP.', 'error');
        }
    } else {
        setFlash('Sem PDF disponível para enviar.', 'error');
    }

    header('Location: ' . APP_URL . '/admin/submission-view.php?id=' . $subId);
    exit;
}

// -------------------------------------------------------
// Regenerar PDF
// -------------------------------------------------------
if (isset($_POST['regen_pdf']) && validateCSRF($_POST[CSRF_TOKEN_NAME] ?? '')) {
    require_once APP_PATH . '/includes/pdf.php';
    $sysSettings = getAllSettings();
    $form        = [
        'id'           => $submission['form_id_val'],
        'title'        => $submission['form_title'],
        'fields'       => $submission['fields'],
        'pdf_template' => $submission['pdf_template'],
    ];
    $submData   = json_decode($submission['data'], true);
    $subArr     = [
        'id'         => $submission['id'],
        'data'       => $submData,
        'created_at' => $submission['created_at'],
    ];

    // Remove PDF antigo
    if (!empty($submission['pdf_path'])) {
        deleteUploadedFile($submission['pdf_path']);
    }

    $newPdf = generatePDF($form, $subArr, $sysSettings);
    if ($newPdf) {
        $db->query('UPDATE submissions SET pdf_path = ? WHERE id = ?', [$newPdf, $subId]);
        setFlash('PDF regenerado com sucesso!', 'success');
    } else {
        setFlash('Falha ao regenerar o PDF.', 'error');
    }

    header('Location: ' . APP_URL . '/admin/submission-view.php?id=' . $subId);
    exit;
}

// -------------------------------------------------------
// Prepara dados para exibição
// -------------------------------------------------------
$submData  = json_decode($submission['data'], true) ?? [];
$fields    = decodeFields($submission['fields']);
$form      = [
    'id'    => $submission['form_id_val'],
    'title' => $submission['form_title'],
    'slug'  => $submission['form_slug'],
];

$sysSettings = getAllSettings();
$appUrl      = rtrim($sysSettings['app_url'] ?? APP_URL, '/');
$pageTitle   = 'Envio #' . $subId;
$activeMenu  = 'submissions';
require_once __DIR__ . '/layout/header.php';
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="<?= $appUrl ?>/admin/submissions.php">Envios</a>
            <span>›</span>
            <span>#<?= $subId ?></span>
        </div>
        <h2>Detalhes do Envio #<?= $subId ?></h2>
        <p><?= e($submission['form_title']) ?> — <?= formatDate($submission['created_at'], true) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if (!empty($submission['pdf_path'])): ?>
            <a href="?id=<?= $subId ?>&download=1" class="btn btn-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Baixar PDF
            </a>
        <?php endif; ?>
        <a href="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $form['id'] ?>" class="btn btn-secondary">← Voltar</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;">

    <!-- Dados do envio -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Dados Preenchidos</h3>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40%;">Campo</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $field): ?>
                    <?php
                        $name  = $field['name'] ?? '';
                        $label = $field['label'] ?? $name;
                        $type  = $field['type']  ?? 'text';
                        $value = $submData[$name] ?? '—';

                        if ($type === 'checkbox') {
                            $value = $value == '1' ? 'Sim ✓' : 'Não';
                        }
                    ?>
                    <tr>
                        <td style="font-weight:600;background:#fafbfc;"><?= e($label) ?></td>
                        <td><?= nl2br(e((string) $value)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Painel lateral -->
    <div>
        <!-- Info do envio -->
        <div class="card mb-16">
            <div class="card-header"><h3 class="card-title">Informações</h3></div>
            <div class="card-body">
                <table style="width:100%;font-size:13px;">
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">ID</td>
                        <td style="text-align:right;font-weight:600;">#<?= $subId ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">Data</td>
                        <td style="text-align:right;"><?= formatDate($submission['created_at'], true) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">IP</td>
                        <td style="text-align:right;"><?= e($submission['ip_address'] ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">PDF</td>
                        <td style="text-align:right;">
                            <?php if (!empty($submission['pdf_path'])): ?>
                                <span class="badge badge-success">Gerado</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Não gerado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:4px 0;">E-mail</td>
                        <td style="text-align:right;">
                            <?php if ($submission['email_sent']): ?>
                                <span class="badge badge-info">Enviado</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Não enviado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Ações -->
        <div class="card mb-16">
            <div class="card-header"><h3 class="card-title">Ações</h3></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
                <!-- Regenerar PDF -->
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="regen_pdf" value="1">
                    <button type="submit" class="btn btn-secondary w-full" style="justify-content:center;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                        Regenerar PDF
                    </button>
                </form>

                <!-- Reenviar e-mail -->
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="resend_email" value="1">
                    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Reenviar E-mail
                    </button>
                </form>

                <hr class="divider" style="margin:4px 0;">

                <!-- Excluir -->
                <form method="POST" action="<?= $appUrl ?>/admin/submission-delete.php"
                      data-confirm="Excluir este envio definitivamente?">
                    <?= csrfField() ?>
                    <input type="hidden" name="submission_id" value="<?= $subId ?>">
                    <input type="hidden" name="redirect" value="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $form['id'] ?>">
                    <button type="submit" class="btn btn-danger w-full" style="justify-content:center;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        Excluir Envio
                    </button>
                </form>
            </div>
        </div>

        <!-- Link do formulário -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">Formulário</h3></div>
            <div class="card-body">
                <p class="text-sm text-muted mb-8"><?= e($submission['form_title']) ?></p>
                <a href="<?= $appUrl ?>/admin/submissions.php?form_id=<?= (int) $form['id'] ?>"
                   class="btn btn-secondary btn-sm w-full" style="justify-content:center;">
                    Ver Todos os Envios
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
