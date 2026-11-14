<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CommercialFixtures;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (config('database.default') !== 'sqlite' || $database !== dirname(__DIR__, 2).'/storage/app/design-preview/demo.sqlite' || file_exists($database)) {
    throw new RuntimeException('Solo se permite crear una base SQLite de demostración nueva. No se modifica ninguna base existente.');
}
touch($database);
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--class' => Database\Seeders\CategorySeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => Database\Seeders\ProjectSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => Database\Seeders\TariffParamSeeder::class, '--force' => true]);
$password = bin2hex(random_bytes(12));
$user = User::factory()->create(['name' => 'Equipo ARAT · Demostración', 'email' => 'admin@example.test', 'role' => 'admin', 'password' => Hash::make($password)]);
$fixtures = new class {
    use CommercialFixtures;

    public function prepare(User $user): array
    {
        $this->tariff();
        $request = $this->registerRequest();
        $this->approve($request, $user);
        $old = $this->emit($this->draft($request, $user), $user);
        $quote = $this->emit($this->draft($request, $user, '13500.00', $old), $user);
        $observed = $this->registerRequest('120');
        $this->action($observed, $user, 'assign', ['assigned_to' => $user->id]);
        $this->action($observed, $user, 'start');
        $this->action($observed, $user, 'observe', ['public_observation' => 'Necesitamos el plano con las medidas y una descripción de los ambientes que deseas remodelar.']);
        $this->registerRequest('60');

        return ['request' => $request->code, 'observed' => $observed->code, 'quotation' => $quote->id];
    }
};
$state = $fixtures->prepare($user);
file_put_contents(storage_path('state.json'), json_encode($state + ['email' => $user->email, 'password' => $password], JSON_PRETTY_PRINT));
echo "Demostración preparada en SQLite aislado. Credenciales efímeras en storage/app/design-preview/state.json.\n";
