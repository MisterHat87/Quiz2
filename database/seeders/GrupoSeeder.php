<?php

namespace Database\Seeders;

use App\GrupoValidator;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Database\Seeder;

class GrupoSeeder extends Seeder
{
    public function run(): void
    {
        $curso = Curso::factory()->create([
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);
        $docentes = Docente::factory()->count(2)->create();
        $grupo = Grupo::factory()->for($curso)->create();
        $grupo->docentes()->attach($docentes->modelKeys());
        $estudiantes = Estudiante::factory()->count(15)->for($docentes->first(), 'consejero')->create();

        foreach ($estudiantes as $estudiante) {
            $grupo->estudiantes()->attach($estudiante->id, ['nota' => 9.0]);
        }

        GrupoValidator::validarComposicion($grupo);
        GrupoValidator::validarNotasFinales($grupo);
    }
}
