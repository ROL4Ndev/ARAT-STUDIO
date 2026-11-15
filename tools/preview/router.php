<?php

use Illuminate\Http\Request;

$public = realpath(dirname(__DIR__, 2).'/public');
$path = realpath($public.rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
if ($path && str_starts_with($path, $public.DIRECTORY_SEPARATOR) && is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}
$app = require __DIR__.'/bootstrap.php';
if (str_starts_with($_SERVER['REQUEST_URI'], '/__preview-media/')) {
    $mediaRoot = realpath($app->storagePath('app/public'));
    $relative = rawurldecode(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen('/__preview-media/')));
    $media = $mediaRoot ? realpath($mediaRoot.DIRECTORY_SEPARATOR.$relative) : false;
    if (! $media || ! str_starts_with(strtolower($media), strtolower($mediaRoot.DIRECTORY_SEPARATOR)) || ! is_file($media)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: '.mime_content_type($media));
    header('X-Content-Type-Options: nosniff');
    readfile($media);
    exit;
}
$app->handleRequest(Request::capture());
