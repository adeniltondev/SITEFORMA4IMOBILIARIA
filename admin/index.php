<?php
/**
 * Dashboard administrativo – página inicial do painel
 * @package FORMA4
 */

define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/includes/config.php';
require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/functions.php';

requireLogin();

$db = Database::getInstance();

// Totais para os cards de estatística
$totalForms       = $db->fetchOne('SELECT COUNT(*) AS n FROM forms')['n'] ?? 0;
$totalSubmissions = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions')['n'] ?? 0;
$totalPDFs        = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions WHERE pdf_path IS NOT NULL AND pdf_path != ""')['n'] ?? 0;
$todaySubmissions = $db->fetchOne('SELECT COUNT(*) AS n FROM submissions WHERE DATE(created_at) = CURDATE()')['n'] ?? 0;

// Últimos 10 envios
$recentSubmissions = $db->fetchAll(
    'SELECT s.id, s.created_at, s.pdf_path, s.email_sent, f.title AS form_title, f.slug
     FROM submissions s
     JOIN forms f ON f.id = s.form_id
     ORDER BY s.created_at DESC
     LIMIT 10'
);

// Formulários mais ativos
$topForms = $db->fetchAll(
    'SELECT f.id, f.title, f.slug, COUNT(s.id) AS total
     FROM forms f
     LEFT JOIN submissions s ON s.form_id = f.id
     GROUP BY f.id
     ORDER BY total DESC
     LIMIT 5'
);

$sysSettings = getAllSettings();
$pageTitle   = 'Dashboard';
$activeMenu  = 'dashboard';
require_once __DIR__ . '/layout/header.php';
?>

<!-- =========================================================
     STATS
     ========================================================= -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $totalForms) ?></div>
            <div class="stat-label">Formulários</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293L8.586 13.293A1 1 0 007.879 13H4"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $totalSubmissions) ?></div>
            <div class="stat-label">Envios Totais</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $totalPDFs) ?></div>
            <div class="stat-label">PDFs Gerados</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $todaySubmissions) ?></div>
            <div class="stat-label">Envios Hoje</div>
        </div>
    </div>
</div>

<!-- =========================================================
     CONTEÚDO PRINCIPAL (2 colunas)
     ========================================================= -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start;">

    <!-- Últimos envios -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Últimos Envios</h2>
            <a href="<?= $appUrl ?>/admin/submissions.php" class="btn btn-secondary btn-sm">Ver todos</a>
        </div>
        <div class="table-responsive">
            <?php if (empty($recentSubmissions)): ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5"/></svg>
                    <h3>Nenhum envio ainda</h3>
                    <p>Os envios aparecerão aqui quando alguém preencher um formulário.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Formulário</th>
                            <th>Data</th>
                            <th>PDF</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSubmissions as $sub): ?>
                        <tr>
                            <td class="text-muted text-sm"><?= (int) $sub['id'] ?></td>
                            <td><?= e($sub['form_title']) ?></td>
                            <td class="text-sm text-muted"><?= formatDate($sub['created_at'], true) ?></td>
                            <td>
                                <?php if ($sub['pdf_path']): ?>
                                    <span class="badge badge-success">✓ Gerado</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= $appUrl ?>/admin/submission-view.php?id=<?= (int) $sub['id'] ?>" class="btn btn-ghost btn-sm">Ver</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulários mais ativos -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Formulários</h2>
            <a href="<?= $appUrl ?>/admin/forms.php" class="btn btn-secondary btn-sm">Gerenciar</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($topForms)): ?>
                <div class="empty-state" style="padding:30px 16px;">
                    <p style="font-size:13px;color:var(--muted);">Crie seu primeiro formulário.</p>
                    <a href="<?= $appUrl ?>/admin/form-create.php" class="btn btn-primary btn-sm" style="margin-top:10px;">+ Novo Formulário</a>
                </div>
            <?php else: ?>
                <ul style="list-style:none;padding:0;margin:0;">
                    <?php foreach ($topForms as $f): ?>
                    <li style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <div style="min-width:0;">
                            <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($f['title']) ?></div>
                            <div class="text-muted text-sm"><?= (int) $f['total'] ?> envio(s)</div>
                        </div>
                        <a href="<?= $appUrl ?>/form.php?slug=<?= e($f['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">Abrir</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
