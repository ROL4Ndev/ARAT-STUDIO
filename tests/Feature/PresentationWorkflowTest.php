<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class PresentationWorkflowTest extends TestCase
{
    use CommercialFixtures, RefreshDatabase;

    public function test_expired_proposal_keeps_its_document_but_has_no_acceptance_action(): void
    {
        Storage::fake('commercial');
        Mail::fake();
        $this->tariff();
        $manager = User::factory()->create(['role' => 'gestor']);
        $request = $this->registerRequest();
        $this->approve($request, $manager);
        $quotation = $this->emit($this->draft($request, $manager), $manager);
        $this->travelTo($quotation->expires_at);
        [, $grant] = $this->clientGrant($request);

        $this->withSession(['client_grant' => $grant])->get(route('client.dossier'))
            ->assertOk()->assertSeeText('La propuesta ha vencido')
            ->assertSee(route('client.quotations.download', $quotation))
            ->assertDontSee('Aceptar propuesta vigente');

        Artisan::call('commercial:expire');
        $this->get(route('client.dossier'))->assertOk()->assertSeeText('La propuesta ha vencido')->assertDontSee('Aceptar propuesta vigente');
    }

    public function test_observed_dossier_prioritizes_public_instructions_without_exposing_internal_reason(): void
    {
        Mail::fake();
        $this->tariff();
        $manager = User::factory()->create(['role' => 'gestor']);
        $request = $this->registerRequest();
        $this->action($request, $manager, 'claim');
        $this->action($request, $manager, 'start');
        $this->action($request, $manager, 'observe', [
            'public_observation' => 'Adjunta el plano con las medidas de cada ambiente.',
            'reason' => 'MOTIVO_INTERNO_RESERVADO',
        ]);
        [, $grant] = $this->clientGrant($request);

        $this->withSession(['client_grant' => $grant])->get(route('client.dossier'))
            ->assertOk()->assertSeeText('Tu solicitud necesita una corrección')
            ->assertSeeText('Adjunta el plano con las medidas de cada ambiente.')
            ->assertSee('href="#corregir-solicitud"', false)
            ->assertDontSee('MOTIVO_INTERNO_RESERVADO')->assertDontSee('Aceptar propuesta vigente');
    }
}
