<?php

// Esta herramienta nunca carga la conexión de trabajo ni envía correos.
$root = dirname(__DIR__, 2);
$previewName = getenv('ARAT_PREVIEW_NAME') ?: 'design-preview';
if (! preg_match('/^design-preview(?:-[a-z0-9]+)?$/', $previewName)) {
    throw new RuntimeException('Nombre de demostración no válido.');
}
$preview = $root.'/storage/app/'.$previewName;
foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/commercial'] as $directory) {
    if (! is_dir($preview.'/'.$directory)) {
        mkdir($preview.'/'.$directory, 0777, true);
    }
}
foreach ([
    'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8765',
    'APP_CONFIG_CACHE' => $preview.'/config.php', 'DB_CONNECTION' => 'sqlite',
    'LARAVEL_STORAGE_PATH' => $preview,
    'DB_DATABASE' => $preview.'/demo.sqlite', 'DB_URL' => '',
    'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => str_replace('-', '_', $previewName),
    'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'COMMERCIAL_STORAGE_ROOT' => $preview.'/app/commercial', 'CLIENT_ACCESS_DEMO_ENABLED' => 'true',
    'PUBLIC_STORAGE_URL' => 'http://127.0.0.1:8765/__preview-media',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->useStoragePath($preview);

return $app;
