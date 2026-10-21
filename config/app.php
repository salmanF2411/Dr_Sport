<?php
declare(strict_types=1);

// DR.SPORT Application Configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', 'DR.SPORT');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.1');
}

// Ensure timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting settings
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Mendapatkan base path direktori web aplikasi.
 * Mendukung akses melalui subfolder XAMPP (contoh: /Sport) maupun root domain VirtualHost.
 */
function get_base_path(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';

    if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
        $relative = substr($projectRoot, strlen($docRoot));
        $basePath = '/' . trim(str_replace('\\', '/', $relative), '/');
        if ($basePath === '/') {
            $basePath = '';
        }
    } else {
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $dirName = basename($projectRoot);
        $pos = strpos($script, '/' . $dirName);
        if ($pos !== false) {
            $basePath = substr($script, 0, $pos + strlen($dirName) + 1);
            $basePath = '/' . trim($basePath, '/');
        } else {
            $basePath = '';
        }
    }

    return $basePath;
}

/**
 * Menghasilkan URL absolut ke aset atau route di dalam project.
 */
function base_url(string $path = ''): string
{
    $base = get_base_path();
    $path = ltrim($path, '/');
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '/' : $base . '/') . $path;
}
