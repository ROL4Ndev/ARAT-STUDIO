<?php

$public = realpath(dirname(__DIR__, 2).'/public');
$path = realpath($public.rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
if ($path && str_starts_with($path, $public.DIRECTORY_SEPARATOR) && is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}
$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Illuminate\Http\Request::capture());
