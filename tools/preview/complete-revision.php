<?php

use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CommercialFixtures;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (config('database.default') !== 'sqlite' || ! str_contains(str_replace('\\', '/', $database), '/design-preview-v2/demo.sqlite')) {
    throw new RuntimeException('Esta preparación solo acepta el SQLite aislado design-preview-v2.');
}
// Conservar la muestra del perfil; retirar únicamente sus filas sintéticas del escaparate visual.
if (DB::table('service_requests')->where('code', 'like', 'PERF-%')->exists()) {
    if (! is_file(storage_path('performance-snapshot.sqlite'))) {
        copy($database, storage_path('performance-snapshot.sqlite'));
    }
    DB::table('service_requests')->where('code', 'like', 'PERF-%')->delete();
}
Project::whereIn('slug', ['departamento-mr', 'proyecto-opv', 'proyecto-sala-t'])->update(['is_featured' => true]);
foreach (['departamento-mr', 'proyecto-opv', 'proyecto-sala-t'] as $order => $slug) {
    Project::where('slug', $slug)->update(['featured_order' => $order + 1]);
}
$state = json_decode(file_get_contents(storage_path('state.json')), true);
if (! isset($state['manager'])) {
    $password = Str::random(32);
    $manager = User::factory()->create(['name' => 'Gestora · Demostración', 'email' => 'gestora@example.test', 'role' => 'gestor', 'password' => $password]);
    $empty = User::factory()->create(['name' => 'Gestor sin expedientes · Demostración', 'email' => 'sin-expedientes@example.test', 'role' => 'gestor', 'password' => $password]);
    $fixtures = new class
    {
        use CommercialFixtures;

        public function assigned(User $user): ServiceRequest
        {
            $request = $this->registerRequest('70');
            $this->action($request, $user, 'claim');
            $this->action($request, $user, 'start');

            return $request;
        }
    };
    $fixtures->assigned($manager);
    $state += ['manager' => $manager->id, 'manager_email' => $manager->email, 'manager_password' => $password, 'empty_email' => $empty->email];
    file_put_contents(storage_path('state.json'), json_encode($state, JSON_PRETTY_PRINT));
}
$images = ProjectImage::get();
$thumbnailBytes = $images->sum(fn ($image) => Storage::disk('public')->size($image->thumbnail_path));
$originalBytes = $images->sum(fn ($image) => filesize(public_path($image->path)));
file_put_contents(base_path('docs/revision-2/media-summary.json'), json_encode([
    'environment' => 'SQLite aislado de demostración; ningún registro real modificado',
    'projects' => Project::count(), 'drafts' => Project::where('is_active', false)->count(),
    'images' => $images->count(), 'original_bytes' => $originalBytes, 'thumbnail_bytes' => $thumbnailBytes,
    'thumbnail_width' => 480, 'files_reused_without_copying_originals' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Demostración lista; muestra de rendimiento apartada y credenciales solo en storage ignorado.'.PHP_EOL;
