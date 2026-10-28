<?php

use App\GrupoValidator;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('recupera el curso los docentes los consejeros y las notas del grupo', function () {
    $curso = Curso::factory()->create(['sigla' => 'IF6100']);
    $docentes = Docente::factory()->count(2)->create();
    $estudiantes = Estudiante::factory()->count(15)->for($docentes->first(), 'consejero')->create();
    $grupo = Grupo::factory()->for($curso)->create();
    $grupo->docentes()->attach($docentes->modelKeys());

    foreach ($estudiantes as $estudiante) {
        $grupo->estudiantes()->attach($estudiante->id, ['nota' => 9.5]);
    }

    $recuperado = Grupo::with(['curso', 'docentes', 'estudiantes.consejero', 'matriculas.estudiante'])->findOrFail($grupo->id);
    GrupoValidator::validarComposicion($recuperado);
    GrupoValidator::validarNotasFinales($recuperado);

    expect($recuperado->curso->is($curso))->toBeTrue();
    expect($recuperado->docentes->modelKeys())->toEqualCanonicalizing($docentes->modelKeys());
    expect($recuperado->estudiantes)->toHaveCount(15);
    expect($recuperado->matriculas)->toHaveCount(15);
    expect($curso->grupos->modelKeys())->toBe([$grupo->id]);
    expect($docentes->first()->grupos->modelKeys())->toBe([$grupo->id]);
    expect($docentes->first()->estudiantes)->toHaveCount(15);

    foreach ($recuperado->estudiantes as $estudiante) {
        expect($estudiante->consejero->is($docentes->first()))->toBeTrue();
        expect($estudiante->matricula)->toBeInstanceOf(Pivot::class);
        expect($estudiante->matricula->nota)->toBe(9.5);
        expect($estudiante->grupos->first()->matricula->nota)->toBe(9.5);
    }

    $matricula = $recuperado->matriculas->first();
    expect($matricula->grupo->is($grupo))->toBeTrue();
    expect($matricula->estudiante->id)->toBeIn($estudiantes->modelKeys());
    $this->assertModelExists($matricula);
});

it('guarda una nota diferente para el mismo estudiante en cada grupo', function () {
    $estudiante = Estudiante::factory()->create();
    $grupos = Grupo::factory()->count(2)->create();
    $grupos[0]->estudiantes()->attach($estudiante->id, ['nota' => 9.5]);
    $grupos[1]->estudiantes()->attach($estudiante->id, ['nota' => 7.0]);

    $grupos[0]->estudiantes()->updateExistingPivot($estudiante->id, ['nota' => 10.0]);

    expect($grupos[0]->estudiantes()->first()->matricula->nota)->toEqual(10.0);
    expect($grupos[1]->estudiantes()->first()->matricula->nota)->toEqual(7.0);
    expect($estudiante->matriculas)->toHaveCount(2);
});

it('rechaza una segunda matricula del estudiante en el mismo grupo', function () {
    $matricula = EstudianteMatriculado::factory()->create();

    expect(fn () => EstudianteMatriculado::factory()->create([
        'grupo_id' => $matricula->grupo_id,
        'estudiante_id' => $matricula->estudiante_id,
    ]))->toThrow(QueryException::class);
});

it('requiere un consejero existente para guardar un estudiante', function () {
    expect(fn () => Estudiante::factory()->create(['consejero_id' => null]))
        ->toThrow(QueryException::class);
});

it('valida la cantidad de docentes y estudiantes del diagrama', function (int $docentes, int $estudiantes, bool $valido) {
    $grupo = Grupo::factory()->make();
    $grupo->setRelation('docentes', Docente::factory()->count($docentes)->make());
    $grupo->setRelation('estudiantes', Estudiante::factory()->count($estudiantes)->make(['consejero_id' => 1]));

    if ($valido) {
        GrupoValidator::validarComposicion($grupo);
        expect($grupo->docentes->count())->toBe($docentes);
    } else {
        expect(fn () => GrupoValidator::validarComposicion($grupo))->toThrow(DomainException::class);
    }
})->with([
    'un docente y quince estudiantes' => [1, 15, true],
    'dos docentes y dieciseis estudiantes' => [2, 16, true],
    'sin docentes' => [0, 15, false],
    'tres docentes' => [3, 15, false],
    'catorce estudiantes' => [1, 14, false],
]);

it('admite una nota pendiente y la exige al validar las notas finales', function () {
    $matricula = EstudianteMatriculado::factory()->create();
    $grupo = $matricula->grupo;

    expect($matricula->nota)->toBeNull();
    expect(fn () => GrupoValidator::validarNotasFinales($grupo))->toThrow(DomainException::class);

    $matricula->nota = 0.0;
    $matricula->save();
    $grupo->refresh();
    GrupoValidator::validarNotasFinales($grupo);

    expect($grupo->estudiantes->first()->matricula->nota)->toEqual(0.0);
});
