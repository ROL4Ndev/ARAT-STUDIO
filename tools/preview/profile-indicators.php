<?php

use App\Http\Controllers\Commercial\ServiceRequestController;
use App\Http\Requests\Commercial\FilterServiceRequests;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$user = User::where('email', 'admin@example.test')->firstOrFail();
if (! ServiceRequest::where('code', 'like', 'PERF-%')->exists()) {
    $template = DB::table('service_requests')->first();
    $rows = [];
    for ($index = 0; $index < 1000; $index++) {
        $row = (array) $template;
        unset($row['id']);
        $row['code'] = 'PERF-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);
        $row['submission_token'] = (string) Str::uuid();
        $row['status'] = 'evaluacion';
        $row['assigned_to'] = $user->id;
        $row['created_at'] = now('America/Lima')->subDays($index % 20)->format('Y-m-d H:i:s');
        $row['first_evaluated_at'] = now('UTC')->subDays($index % 20)->addMinutes(45)->format('Y-m-d H:i:s');
        $rows[] = $row;
    }
    foreach (array_chunk($rows, 50) as $chunk) {
        DB::table('service_requests')->insert($chunk);
    }
}
Auth::setUser($user);
$request = FilterServiceRequests::create('/gestion', 'GET');
$request->setContainer($app)->setRedirector($app['redirect']);
$request->setUserResolver(fn () => $user);
$request->validateResolved();
DB::enableQueryLog();
$start = hrtime(true);
$view = app()->call([app(ServiceRequestController::class), 'index'], ['request' => $request]);
$queries = DB::getQueryLog();
$data = $view->getData();
$profile = [
    'sample' => '1000 solicitudes sintéticas de rendimiento, exclusivamente SQLite aislado',
    'query_count' => count($queries),
    'controller_ms' => round((hrtime(true) - $start) / 1000000, 2),
    'evaluation_query' => array_values(array_filter(array_column($queries, 'query'), fn ($sql) => str_contains($sql, 'first_evaluated_at'))),
    'average_minutes' => $data['minutes'],
];
$phase = $argv[1] ?? 'before';
file_put_contents(base_path('docs/revision-2/indicators-'.$phase.'.json'), json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($profile, JSON_UNESCAPED_UNICODE).PHP_EOL;
