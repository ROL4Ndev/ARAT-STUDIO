<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios') {
    throw new RuntimeException('Se esperaba la base local arat_studios.');
}
$directory = storage_path('app/private/final-maintenance');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$counts = [];
foreach (DB::select('SHOW TABLES') as $row) {
    $table = array_values((array) $row)[0];
    $counts[$table] = DB::table($table)->count();
}
$users = DB::table('users')->orderBy('id')->get();
$requests = DB::table('service_requests')->orderBy('id')->get();
file_put_contents($directory.'/inventory-private.json', json_encode([
    'database' => DB::connection()->getDatabaseName(), 'counts' => $counts,
    'users' => $users, 'requests' => $requests,
    'foreign_keys' => DB::select('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode([
    'database' => DB::connection()->getDatabaseName(), 'counts' => $counts,
    'accounts' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role, 'active' => $u->is_active,
        'requests_assigned' => $requests->where('assigned_to', $u->id)->count(),
        'reserved_email' => (bool) preg_match('/@(?:example\.(?:com|test|org)|[^@]+\.test)$/i', $u->email)]),
    'request_origins' => $requests->groupBy(fn ($r) => $r->client_name.' | '.explode('@', $r->client_email)[1].' | '.mb_substr($r->description, 0, 120))
        ->map(fn ($rows) => ['count' => $rows->count(), 'ids' => $rows->pluck('id'), 'states' => $rows->countBy('status')]),
    'catalogs' => ['services' => DB::table('services')->get(['id', 'code', 'name', 'estimation_mode', 'is_active']),
        'tariffs' => DB::table('tariff_params')->get(['id', 'lod_level', 'base_price_m2', 'lod_factor', 'status']),
        'projects' => DB::table('projects')->get(['id', 'slug', 'is_active', 'is_featured']),
        'categories' => DB::table('categories')->get(['id', 'name', 'is_active'])],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
