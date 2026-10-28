<?php

namespace Database\Factories;

use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstudianteMatriculado>
 */
class EstudianteMatriculadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'estudiante_id' => Estudiante::factory(),
            'nota' => null,
        ];
    }
}
