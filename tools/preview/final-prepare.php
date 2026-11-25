<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Database\Seeders\TariffParamSeeder;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (DB::getDriverName() !== 'sqlite' || ! str_ends_with(str_replace('\\', '/', $database), '/design-preview-final/demo.sqlite') || is_file($database)) {
    throw new RuntimeException('Solo se crea una nueva base design-preview-final.');
}
touch($database);
Artisan::call('migrate', ['--force' => true]);
$sourceRoot = base_path('storage/app/design-preview-v3');
$source = new PDO('sqlite:'.$sourceRoot.'/demo.sqlite');
DB::transaction(function () use ($source) {
    // Solo catálogos y cuentas efímeras de la demostración previa, sin sus ensayos comerciales.
    DB::table('services')->delete();
    $coverPointers = [];
    foreach (['users', 'categories', 'services', 'projects', 'project_images', 'project_cover_histories'] as $table) {
        foreach ($source->query('SELECT * FROM '.$table)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($table === 'projects') {
                $coverPointers[$row['id']] = $row['cover_image_id'];
                $row['cover_image_id'] = null;
                $row['is_featured'] = false;
            }
            DB::table($table)->insert($row);
        }
    }
    foreach ($coverPointers as $id => $pointer) {
        DB::table('projects')->where('id', $id)->update(['cover_image_id' => $pointer]);
    }
});
Artisan::call('db:seed', ['--class' => TariffParamSeeder::class, '--force' => true]);
foreach (['app/public', 'app/commercial'] as $folder) {
    File::copyDirectory($sourceRoot.'/'.$folder, storage_path($folder));
}
$state = json_decode(file_get_contents($sourceRoot.'/state.json'), true, flags: JSON_THROW_ON_ERROR);
$actor = DB::table('users')->where('email', $state['email'])->value('id');
Artisan::call('down');
try {
    if (Artisan::call('demo:prepare', ['--actor' => $actor, '--date' => '2026-11-24', '--apply' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
} finally {
    Artisan::call('up');
}
$state['request'] = DB::table('service_requests')->where('demo_scenario', 'emitida')->value('code');
$state['observed'] = DB::table('service_requests')->where('demo_scenario', 'observada')->value('code');
$state['scenarios'] = DB::table('service_requests')->whereNotNull('demo_scenario')->pluck('code', 'demo_scenario')->all();
file_put_contents(storage_path('state.json'), json_encode($state, JSON_PRETTY_PRINT));
echo 'Vista final aislada: 15 solicitudes ficticias y catálogos completos. Ninguna cuenta o dato real copiado.'.PHP_EOL;
