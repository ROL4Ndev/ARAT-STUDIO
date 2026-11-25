<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class DemoRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'client_name' => fake()->randomElement(['Valeria', 'Lucía', 'Mateo', 'Camila', 'Diego', 'Sofía', 'Andrés', 'Mariana']).' '.fake()->randomElement(['Salazar', 'Vargas', 'Mendoza', 'Castro', 'Rojas', 'Paredes', 'Vega', 'Flores']),
            'client_phone' => '000000000',
            'city' => fake()->randomElement(['Lima', 'Arequipa', 'Trujillo', 'Huancayo']),
            'project_type' => 'Vivienda',
        ];
    }
}
