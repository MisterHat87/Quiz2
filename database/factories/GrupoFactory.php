<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_ciclo' => 2,
            'año' => 2026,
            'numero_grupo' => 1,
            'abierto' => true,
            'curso_id' => Curso::factory(),
        ];
    }
}
