<?php
/**
 * Router for PHP's built-in server only:  php -S localhost:8000 router.php
 * Blocks the private folders (Apache does this with .htaccess) and serves static files.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(data|includes)(/|$)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($path, '.php')) return false; // static asset
if ($path === '/' || (is_dir($file) && is_file(rtrim($file, '/') . '/index.php'))) {
    $_SERVER['SCRIPT_FILENAME'] = rtrim($file, '/') . '/index.php';
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require $_SERVER['SCRIPT_FILENAME'];
    return true;
}
if (is_file($file)) return false; // let PHP run the page
http_response_code(404);
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/404.php';
require __DIR__ . '/404.php';
