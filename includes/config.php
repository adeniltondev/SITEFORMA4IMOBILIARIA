<?php
/**
 * Configurações da aplicação FORMA4 Imobiliária
 *
 * ATENÇÃO: Preencha os dados abaixo antes de instalar o sistema.
 * NÃO versione este arquivo com credenciais reais.
 *
 * @package FORMA4
 */

// ============================================================
// BANCO DE DADOS
// ============================================================
define('DB_HOST',    'localhost');        // Servidor MySQL (geralmente localhost no cPanel)
define('DB_USER',    'autorizacaoa4imo_dsaldja');  // Usuário do banco
define('DB_PASS',    'Vlgd!MA0c$3&%dKy');    // Senha do banco
define('DB_NAME',    'autorizacaoa4imo_formasdac');    // Nome do banco de dados
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// APLICAÇÃO
// ============================================================
define('APP_NAME',    'Forma4 Imobiliária');
define('APP_VERSION', '1.0.0');

// URL base sem barra final (ex: https://seudominio.com.br)
define('APP_URL', 'https://autorizacao.a4imobiliaria.com.br');

// Caminho absoluto raiz do projeto
define('APP_PATH',    dirname(__DIR__));

// ============================================================
// CAMINHOS DE ARQUIVOS
// ============================================================
define('UPLOAD_PATH', APP_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('PDF_PATH',    UPLOAD_PATH . DIRECTORY_SEPARATOR . 'pdfs');
define('LOGO_PATH',   UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logos');

// ============================================================
// SESSÃO
// ============================================================
define('SESSION_NAME',     'f4imob_sess');
define('SESSION_LIFETIME', 7200); // segundos (2 horas)

// ============================================================
// SEGURANÇA
// ============================================================
define('CSRF_TOKEN_NAME', 'csrf_token');

// ============================================================
// UPLOAD
// ============================================================
define('MAX_UPLOAD_SIZE',    2 * 1024 * 1024); // 2 MB
define('ALLOWED_IMG_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
