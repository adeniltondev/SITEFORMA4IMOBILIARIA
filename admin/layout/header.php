<?php
/**
 * Layout: Cabeçalho do painel administrativo
 * Incluído no topo de todas as páginas admin.
 *
 * Variáveis esperadas antes do include:
 *  $pageTitle  (string) – título da aba/página
 *  $activeMenu (string) – slug do menu ativo (dashboard|forms|submissions|settings)
 *
 * @package FORMA4
 */

// Carrega settings do banco uma vez por requisição
if (!isset($sysSettings)) {
    require_once dirname(__DIR__, 2) . '/includes/functions.php';
    $sysSettings = getAllSettings();
}

$appName      = e($sysSettings['app_name']     ?? APP_NAME);
$primaryColor = e($sysSettings['primary_color'] ?? '#2563EB');
$logoFile     = $sysSettings['logo_path'] ?? '';
$appUrl       = rtrim($sysSettings['app_url'] ?? APP_URL, '/');
$pageTitle    = $pageTitle ?? 'Painel';
$activeMenu   = $activeMenu ?? '';

// Flash message
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= $appName ?></title>

    <!-- Fonte Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS principal -->
    <link rel="stylesheet" href="<?= $appUrl ?>/assets/css/style.css">

    <!-- Cor primária dinâmica via variável CSS -->
    <style>
        :root { --primary: <?= $primaryColor ?>; }

        .nav-item--disabled {
            opacity: .5;
            cursor: not-allowed;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .badge-soon {
            margin-left: auto;
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: #f59e0b;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }

        /* ---- Share ---- */
        .share-section {
            padding: 0 10px 6px;
            border-top: 1px solid var(--border, #e2e8f0);
        }

        .share-toggle-btn {
            width: 100%;
            border: none;
            background: none;
            text-align: left;
            margin-top: 6px;
        }
        .share-toggle-btn:hover { background: var(--sidebar-hover); color: var(--primary); }
        .share-toggle-btn.open  { background: var(--sidebar-hover); color: var(--primary); }
        .share-toggle-btn.open .share-chevron { transform: rotate(180deg); }

        /* Dropdown renderizado como position:fixed via JS — sempre na frente */
        .share-dropdown {
            display: none;
            position: fixed;
            z-index: 9999;
            background: #fff;
            border: 1px solid var(--border, #e2e8f0);
            border-radius: 10px;
            padding: 14px 14px 10px;
            box-shadow: 0 -4px 24px rgba(0,0,0,.12);
            min-width: 240px;
            box-sizing: border-box;
            top: auto;
            bottom: auto;
        }
        .share-dropdown.open { display: block; }

        .share-group { margin-bottom: 12px; }
        .share-group-label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin: 0 0 7px 0;
        }

        .share-actions { display: flex; gap: 5px; }

        .share-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: filter .15s;
            flex: 1;
            justify-content: center;
            white-space: nowrap;
        }
        .share-btn svg { width: 13px; height: 13px; flex-shrink: 0; }
        .share-btn:hover { filter: brightness(.88); }

        .share-btn--whatsapp { background: #25d366; color: #fff; }
        .share-btn--email    { background: #3b82f6; color: #fff; }
        .share-btn--copy     { background: #e2e8f0; color: #334155; }
        .share-btn--copy.copied { background: #dcfce7; color: #16a34a; }
    </style>
</head>
<body class="admin-layout">

<!-- =========================================================
     SIDEBAR
     ========================================================= -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logoFile && is_file(LOGO_PATH . DIRECTORY_SEPARATOR . $logoFile)): ?>
            <img src="<?= $appUrl ?>/uploads/logos/<?= e($logoFile) ?>" alt="<?= $appName ?>" class="sidebar-logo">
        <?php else: ?>
            <span class="sidebar-brand"><?= $appName ?></span>
        <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= $appUrl ?>/admin/index.php"
           class="nav-item <?= $activeMenu === 'dashboard'    ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <span class="nav-item nav-item--disabled">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/></svg>
            Formulários
            <span class="badge-soon">Em breve</span>
        </span>
        <a href="<?= $appUrl ?>/admin/submissions.php"
           class="nav-item <?= $activeMenu === 'submissions'  ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            Envios
        </a>
        <a href="<?= $appUrl ?>/admin/settings.php"
           class="nav-item <?= $activeMenu === 'settings'     ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            Configurações
        </a>

    </nav>

    <!-- Compartilhar — fora do overflow-y:auto da nav -->
    <div class="share-section" id="shareNavWrapper">
        <button type="button" class="nav-item share-toggle-btn" id="shareToggleBtn" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
            Compartilhar
            <svg class="share-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;width:14px;height:14px;transition:transform .2s"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
    </div>

    <!-- Dropdown fixo — renderizado fora de qualquer overflow -->
    <div class="share-dropdown" id="shareDropdown" role="dialog" aria-label="Compartilhar formulários">
        <!-- Autorização de Venda -->
        <div class="share-group">
            <p class="share-group-label">Autorização de Venda</p>
            <div class="share-actions">
                <a href="https://wa.me/?text=<?= urlencode('Autorização de Venda: https://autorizacao.a4imobiliaria.com.br/autorizacao-venda.php') ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn--whatsapp" title="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
                <a href="mailto:?subject=<?= urlencode('Autorização de Venda') ?>&body=<?= urlencode('Acesse o formulário de Autorização de Venda: https://autorizacao.a4imobiliaria.com.br/autorizacao-venda.php') ?>"
                   class="share-btn share-btn--email" title="E-mail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    E-mail
                </a>
                <button type="button" class="share-btn share-btn--copy" title="Copiar link"
                        onclick="copyShareLink('https://autorizacao.a4imobiliaria.com.br/autorizacao-venda.php', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Copiar
                </button>
            </div>
        </div>

        <!-- Autorização de Locação -->
        <div class="share-group">
            <p class="share-group-label">Autorização de Locação</p>
            <div class="share-actions">
                <a href="https://wa.me/?text=<?= urlencode('Autorização de Locação: https://autorizacao.a4imobiliaria.com.br/autorizacao-locacao.php') ?>"
                   target="_blank" rel="noopener" class="share-btn share-btn--whatsapp" title="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
                <a href="mailto:?subject=<?= urlencode('Autorização de Locação') ?>&body=<?= urlencode('Acesse o formulário de Autorização de Locação: https://autorizacao.a4imobiliaria.com.br/autorizacao-locacao.php') ?>"
                   class="share-btn share-btn--email" title="E-mail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    E-mail
                </a>
                <button type="button" class="share-btn share-btn--copy" title="Copiar link"
                        onclick="copyShareLink('https://autorizacao.a4imobiliaria.com.br/autorizacao-locacao.php', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Copiar
                </button>
            </div>
        </div>
    </div>

    <div class="sidebar-footer">
        <a href="<?= $appUrl ?>/logout.php" class="nav-item nav-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Sair
        </a>
    </div>
</aside>

<!-- =========================================================
     CONTEÚDO PRINCIPAL
     ========================================================= -->
<main class="main-content">
    <!-- Top bar -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
        <div class="topbar-user">
            <span class="avatar"><?= mb_strtoupper(mb_substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></span>
            <span class="user-name"><?= e($_SESSION['user_name'] ?? '') ?></span>
        </div>
    </header>

    <!-- Flash message -->
    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Fechar">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Conteúdo da página começa aqui -->
    <div class="page-body">
