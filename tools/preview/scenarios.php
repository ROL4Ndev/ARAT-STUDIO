<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Date;
use Tests\Support\CommercialFixtures;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$state = json_decode(file_get_contents(storage_path('state.json')), true);
if (isset($state['expired'])) {
    echo "Los escenarios adicionales ya están preparados.\n";
    exit;
}
$fixtures = new class {
    use CommercialFixtures;

    public function expired(User $user): string
    {
        Date::setTestNow(now()->subDays(16));
        try {
            $request = $this->registerRequest();
            $this->approve($request, $user);
            $this->emit($this->draft($request, $user), $user);

            return $request->code;
        } finally {
            Date::setTestNow();
        }
    }
};
$state['expired'] = $fixtures->expired(User::where('email', $state['email'])->firstOrFail());
file_put_contents(storage_path('state.json'), json_encode($state, JSON_PRETTY_PRINT));
echo "Propuesta vencida preparada en la base aislada.\n";
