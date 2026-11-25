<?php

// Depuración acotada de ensayos identificados; nunca desactiva claves foráneas.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('app/private/final-maintenance');
$receipt = json_decode(file_get_contents($directory.'/backup-receipt.json'), true, flags: JSON_THROW_ON_ERROR);
if (! $receipt['restore_verified'] || hash_file('sha256', $directory.'/arat-studios.sql') !== $receipt['sql_sha256']) {
    throw new RuntimeException('Falta respaldo restaurado y verificado.');
}
$rehearsal = in_array('--restore', $argv, true);
$apply = in_array('--apply', $argv, true);
if ($rehearsal) {
    if (! preg_match('/^arat_restore_final_[0-9_]+$/', $receipt['restore_database'])) {
        throw new RuntimeException('Restauración inválida.');
    }
    config(['database.connections.mysql.database' => $receipt['restore_database']]);
    DB::purge('mysql');
}
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== ($rehearsal ? $receipt['restore_database'] : 'arat_studios')) {
    throw new RuntimeException('Conexión inesperada.');
}
if ($apply && ! $app->isDownForMaintenance()) {
    throw new RuntimeException('Activa mantenimiento y detén cola/scheduler antes de escribir.');
}
if (DB::table('jobs')->exists() || DB::table('work_intents')->where('status', 'processing')->exists()) {
    throw new RuntimeException('Hay trabajos activos o en cola; se conserva la base.');
}
$team = DB::table('users')->whereIn('id', [1, 2, 8, 9])->orderBy('id')->get()->toArray();
if (count($team) !== 4) {
    throw new RuntimeException('No coincide el inventario de cuentas conservadas.');
}
$requests = DB::table('service_requests')->whereIn('id', range(1, 8))->get()->filter(function ($r) {
    $reserved = (bool) preg_match('/@example\.(test|com)$/i', $r->client_email);
    $fixture = ($r->client_name === 'Cliente ficticio de verificación' && $r->description === 'Proyecto ficticio para verificar el cierre de entrega.')
        || ($r->client_name === 'Cliente ficticio límites' && $r->description === 'Verificación ficticia de adjuntos cerca del límite autorizado.')
        || ($r->id === 7 && $r->client_name === 'Gabriel Ramos - PRUEBA')
        || ($r->id === 8 && $r->client_name === 'Daniela Torres — Prueba académica');

    return $reserved && $fixture;
})->pluck('id')->all();
if (DB::table('service_requests')->whereNotIn('id', $requests)->whereIn('related_request_id', $requests)->exists()) {
    throw new RuntimeException('Una solicitud conservada depende del ensayo; requiere revisión manual.');
}
$ids = ['service_requests' => $requests];
foreach (['quotations', 'request_revisions', 'request_files', 'service_request_histories', 'technical_evaluations', 'client_access_tokens', 'client_grants'] as $table) {
    $ids[$table] = DB::table($table)->whereIn('service_request_id', $requests)->pluck('id')->all();
}
$ids['notification_records'] = DB::table('notification_records')->where(function ($query) use ($ids) {
    foreach (['received' => 'service_requests', 'observed' => 'request_revisions', 'corrected' => 'request_revisions', 'quotation' => 'quotations', 'access' => 'client_access_tokens', 'email_change' => 'client_access_tokens'] as $kind => $table) {
        $query->orWhere(fn ($q) => $q->where('kind', $kind)->whereIn('resource_id', $ids[$table]));
    }
})->pluck('id')->all();
$ids['notification_attempts'] = DB::table('notification_attempts')->whereIn('notification_record_id', $ids['notification_records'])->pluck('id')->all();
$ids['work_intents'] = DB::table('work_intents')->where(fn ($q) => $q->where(fn ($q) => $q->where('kind', 'pdf')->whereIn('resource_id', $ids['quotations']))
    ->orWhere(fn ($q) => $q->where('kind', 'notification')->whereIn('resource_id', $ids['notification_records'])))->pluck('id')->all();
$ids['users'] = DB::table('users')->whereIn('id', range(3, 7))->where('name', 'Gestor de verificación ficticio')
    ->whereNotIn('id', DB::table('audit_events')->whereNotNull('actor_id')->select('actor_id'))
    ->whereNull('role')->get()->filter(fn ($u) => (bool) preg_match('/@example\.test$/i', $u->email))->pluck('id')->all();
// El historial de auditoría es inmutable, también cuando describe ensayos retirados.
// Sus instantáneas y actores se conservan íntegros.
$ids['audit_events'] = [];
$files = array_values(array_unique(array_filter([
    ...DB::table('request_files')->whereIn('id', $ids['request_files'])->pluck('path')->all(),
    ...DB::table('service_requests')->whereIn('id', $requests)->pluck('attachment_path')->all(),
    ...DB::table('quotations')->whereIn('id', $ids['quotations'])->pluck('pdf_path')->all(),
])));
$before = [];
$expected = [];
foreach ($ids as $table => $selected) {
    $before[$table] = DB::table($table)->count();
    $expected[$table] = $before[$table] - count($selected);
}
$plan = ['database' => DB::connection()->getDatabaseName(), 'before' => $before, 'expected_after_cleanup' => $expected,
    'ids' => $ids, 'files' => $files, 'criteria' => 'Ensayos explícitos y dominio reservado; coincidencia exacta del inventario; conservar origen dudoso y cuatro cuentas de equipo.'];
$planHash = hash('sha256', serialize($ids));
if (! $apply) {
    file_put_contents($directory.'/cleanup-plan.json', json_encode($plan + ['hash' => $planHash], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['mode' => 'plan', 'before' => $before, 'expected_after_cleanup' => $expected, 'files' => count($files)]).PHP_EOL;
    exit;
}
$savedPlan = json_decode(file_get_contents($directory.'/cleanup-plan.json'), true, flags: JSON_THROW_ON_ERROR);
if ($savedPlan['hash'] !== $planHash) {
    throw new RuntimeException('La selección cambió desde el plan.');
}
if (! $rehearsal) {
    $trial = json_decode(file_get_contents($directory.'/cleanup-rehearsal.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! $trial['verified'] || $trial['plan_hash'] !== $planHash) {
        throw new RuntimeException('Falta ensayo aislado del mismo plan.');
    }
}
DB::transaction(function () use ($ids, $requests, $team) {
    // Romper únicamente los punteros internos de los ensayos; respetar el grafo de dependencias.
    DB::table('service_requests')->whereIn('id', $requests)->update(['current_revision_id' => null, 'observed_revision_id' => null, 'current_quotation_id' => null, 'related_request_id' => null]);
    foreach (['notification_attempts', 'work_intents', 'notification_records', 'service_request_histories', 'technical_evaluations', 'request_files', 'quotations', 'request_revisions', 'client_grants', 'client_access_tokens', 'service_requests', 'users'] as $table) {
        DB::table($table)->whereIn('id', $ids[$table])->delete();
    }
    if (DB::table('users')->whereIn('id', [1, 2, 8, 9])->orderBy('id')->get()->toArray() != $team) {
        throw new RuntimeException('Una cuenta conservada cambió; se revierte la transacción.');
    }
});
$after = [];
foreach ($ids as $table => $_) {
    $after[$table] = DB::table($table)->count();
}
if ($after !== $expected) {
    throw new RuntimeException('Los recuentos no coinciden con el plan.');
}
$orphans = [];
foreach (DB::select('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL') as $fk) {
    $count = DB::table($fk->TABLE_NAME.' as child')->leftJoin($fk->REFERENCED_TABLE_NAME.' as parent', 'child.'.$fk->COLUMN_NAME, '=', 'parent.'.$fk->REFERENCED_COLUMN_NAME)
        ->whereNotNull('child.'.$fk->COLUMN_NAME)->whereNull('parent.'.$fk->REFERENCED_COLUMN_NAME)->count();
    if ($count) {
        $orphans[] = $fk->TABLE_NAME.'.'.$fk->COLUMN_NAME;
    }
}
if ($orphans) {
    throw new RuntimeException('Se detectaron referencias huérfanas.');
}
$root = $rehearsal ? $directory.'/restore-files/storage/app/commercial' : config('filesystems.disks.commercial.root');
$quarantine = $directory.($rehearsal ? '/quarantine-trial' : '/quarantine');
$moved = [];
$retained = [];
foreach ($files as $file) {
    if (str_contains($file, '..') || str_starts_with($file, '/') || str_contains($file, ':')) {
        throw new RuntimeException('Ruta privada inesperada.');
    }
    $referenced = false;
    foreach (DB::select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        foreach (DB::table($table)->get() as $value) {
            if (str_contains(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), $file)) {
                $referenced = true;
                break 2;
            }
        }
    }
    if ($referenced) {
        $retained[] = $file;
        continue;
    }
    if (! is_file($root.'/'.$file)) {
        continue;
    }
    $target = $quarantine.'/'.$file;
    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), 0700, true);
    }
    $hash = hash_file('sha256', $root.'/'.$file);
    if (! rename($root.'/'.$file, $target) || hash_file('sha256', $target) !== $hash) {
        throw new RuntimeException('No se confirmó la cuarentena.');
    }
    $moved[$file] = $hash;
}
file_put_contents($directory.($rehearsal ? '/cleanup-rehearsal.json' : '/cleanup-result.json'), json_encode([
    'verified' => true, 'plan_hash' => $planHash, 'before' => $before, 'after' => $after,
    'quarantined' => $moved, 'files_still_referenced' => $retained, 'orphan_count' => 0, 'team_unchanged' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['mode' => $rehearsal ? 'rehearsal' : 'local', 'before' => $before, 'after' => $after, 'quarantined' => count($moved), 'orphans' => 0, 'team_unchanged' => true]).PHP_EOL;
