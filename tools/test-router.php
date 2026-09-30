<?php
/** Router for the isolated PHP built-in-server fixture, never a production front controller. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server' || getenv('NORTHLINE_TESTING') !== '1') {
    http_response_code(403); exit;
}
$root = realpath('/tmp/northline-wp');
if (!$root) { http_response_code(500); exit('Missing isolated WordPress fixture.'); }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = realpath($root . '/' . ltrim(rawurldecode($path), '/'));
if ($file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) return false;
require $root . '/index.php';
