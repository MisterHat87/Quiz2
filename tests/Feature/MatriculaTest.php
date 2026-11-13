<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

mutates(Grupo::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    $curso = Curso::factory()->create([
        'sigla' => 'IF6100',
        'nombre_curso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);
    $this->grupo = Grupo::factory()->for($curso)->create([
        'numero_ciclo' => 2,
        'año' => 2026,
        'numero_grupo' => 1,
        'abierto' => false,
    ]);
    $this->ana = Docente::factory()->create([
        'cod_empleado' => 1001,
        'nombre_empleado' => 'Ana',
        'apellidos_empleado' => 'Mora Vargas',
    ]);
});

test('CP-07: sin docentes no se abre aunque tenga veinte estudiantes', function () {
    $estudiantes = Estudiante::factory()->count(20)->for($this->ana, 'consejero')->create();
    $this->grupo->estudiantes()->attach($estudiantes->modelKeys());

    expect($this->grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $this->grupo->abrir())->toThrow(DomainException::class);

    expect($this->grupo->fresh()->abierto)->toBeFalse();
    expect($this->grupo->fresh()->docentes)->toBeEmpty();
    expect($this->grupo->fresh()->estudiantes)->toHaveCount(20);
    $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id, 'abierto' => false]);
});

test('CP-08: persiste el consejero del estudiante y su relación inversa', function () {
    expect($this->ana->estudiantes)->toBeEmpty();

    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
    ]);

    expect($estudiante->fresh()->consejero->cod_empleado)->toBe(1001);
    expect($this->ana->fresh()->estudiantes->pluck('carne')->all())->toBe(['C12345']);
    $this->assertDatabaseHas('estudiantes', [
        'id' => $estudiante->id,
        'carne' => 'C12345',
        'consejero_id' => $this->ana->id,
    ]);
});

test('CP-09: cambiar de consejero reemplaza al anterior', function () {
    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
    ]);
    $luis = Docente::factory()->create([
        'cod_empleado' => 1002,
        'nombre_empleado' => 'Luis',
        'apellidos_empleado' => 'Rojas Solano',
    ]);

    $estudiante->consejero()->associate($luis);
    $estudiante->save();

    expect($estudiante->fresh()->consejero->cod_empleado)->toBe(1002);
    expect($this->ana->fresh()->estudiantes)->toBeEmpty();
    expect($luis->fresh()->estudiantes->pluck('carne')->all())->toBe(['C12345']);
    $this->assertDatabaseCount('estudiantes', 1);
    $this->assertDatabaseHas('estudiantes', [
        'id' => $estudiante->id,
        'consejero_id' => $luis->id,
    ]);
    $this->assertDatabaseMissing('estudiantes', [
        'id' => $estudiante->id,
        'consejero_id' => $this->ana->id,
    ]);
});

test('CP-10: la matrícula usa el pivote EstudianteMatriculado con nota pendiente', function () {
    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
    ]);

    $this->grupo->estudiantes()->attach($estudiante->id);

    $matricula = $this->grupo->fresh()->estudiantes->first()->pivot;
    expect($matricula)->toBeInstanceOf(EstudianteMatriculado::class);
    expect($matricula->id)->toBe(EstudianteMatriculado::sole()->id);
    expect($matricula->nota)->toBeNull();
    expect($estudiante->fresh()->grupos->first()->pivot)->toBeInstanceOf(EstudianteMatriculado::class);
    $this->assertDatabaseCount('estudiantes_matriculados', 1);
    $this->assertDatabaseHas('estudiantes_matriculados', [
        'grupo_id' => $this->grupo->id,
        'estudiante_id' => $estudiante->id,
        'nota' => null,
    ]);
});

test('CP-11: registra la nota como float y conserva las otras matrículas', function () {
    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
    ]);
    $otroEstudiante = Estudiante::factory()->for($this->ana, 'consejero')->create();
    $otroGrupo = Grupo::factory()->for($this->grupo->curso)->create(['numero_grupo' => 2]);
    $this->grupo->estudiantes()->attach([$estudiante->id, $otroEstudiante->id]);
    $otroGrupo->estudiantes()->attach($estudiante->id, ['nota' => 7.0]);
    $matricula = $this->grupo->matriculas()->where('estudiante_id', $estudiante->id)->firstOrFail();
    $otraMatricula = $this->grupo->matriculas()->where('estudiante_id', $otroEstudiante->id)->firstOrFail();
    $matriculaOtroGrupo = $otroGrupo->matriculas()->firstOrFail();
    expect($matricula->nota)->toBeNull();
    expect($this->grupo->estudiantes->find($estudiante->id)->pivot->nota)->toBeNull();
    expect($this->grupo->matriculas->find($matricula->id)->nota)->toBeNull();

    $this->grupo->registrarNota($estudiante, 8.5);

    $guardada = EstudianteMatriculado::where('grupo_id', $this->grupo->id)
        ->where('estudiante_id', $estudiante->id)->firstOrFail();
    expect($guardada->nota)->toBe(8.5);
    expect($guardada->only(['id', 'grupo_id', 'estudiante_id', 'created_at']))
        ->toEqual($matricula->only(['id', 'grupo_id', 'estudiante_id', 'created_at']));
    expect($otraMatricula->fresh()->getAttributes())->toBe($otraMatricula->getAttributes());
    expect($matriculaOtroGrupo->fresh()->getAttributes())->toBe($matriculaOtroGrupo->getAttributes());
    expect($this->grupo->estudiantes->find($estudiante->id)->pivot->nota)->toBe(8.5);
    expect($this->grupo->matriculas->find($matricula->id)->nota)->toBe(8.5);
    $this->assertDatabaseCount('estudiantes_matriculados', 3);
});

test('CP-12: rechaza notas fuera de rango y conserva la nota pendiente', function (float $nota) {
    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
    ]);
    $this->grupo->estudiantes()->attach($estudiante->id);
    $matricula = $this->grupo->matriculas()->firstOrFail();

    expect(fn () => $this->grupo->registrarNota($estudiante, $nota))
        ->toThrow(InvalidArgumentException::class);

    expect($matricula->fresh()->nota)->toBeNull();
    expect($matricula->fresh()->getAttributes())->toBe($matricula->getAttributes());
    $this->assertDatabaseCount('estudiantes_matriculados', 1);
})->with([-1, 10.5]);

test('CP-13: rechaza una matrícula duplicada y conserva un solo registro', function () {
    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
    ]);
    $this->grupo->estudiantes()->attach($estudiante->id);
    $matricula = $this->grupo->matriculas()->firstOrFail();

    expect(fn () => $this->grupo->estudiantes()->attach($estudiante->id))
        ->toThrow(QueryException::class);

    expect($matricula->fresh()->getAttributes())->toBe($matricula->getAttributes());
    expect($this->grupo->fresh()->estudiantes->modelKeys())->toBe([$estudiante->id]);
    $this->assertDatabaseCount('estudiantes_matriculados', 1);
    $this->assertDatabaseHas('estudiantes_matriculados', [
        'grupo_id' => $this->grupo->id,
        'estudiante_id' => $estudiante->id,
    ]);
});

test('registra las notas en los límites de la escala como float', function (float $nota) {
    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create();
    $this->grupo->estudiantes()->attach($estudiante->id);

    $this->grupo->registrarNota($estudiante, $nota);

    expect($this->grupo->matriculas()->firstOrFail()->nota)->toBe($nota);
    expect($estudiante->fresh()->grupos->first()->pivot->nota)->toBe($nota);
})->with([0.0, 10.0]);

test('no registra notas de un estudiante que solo está matriculado en otro grupo', function () {
    $estudiante = Estudiante::factory()->for($this->ana, 'consejero')->create();
    $otroGrupo = Grupo::factory()->create();
    $otroGrupo->estudiantes()->attach($estudiante->id);

    expect(fn () => $this->grupo->registrarNota($estudiante, 8.5))
        ->toThrow(ModelNotFoundException::class);

    expect($otroGrupo->matriculas()->firstOrFail()->nota)->toBeNull();
    $this->assertDatabaseCount('estudiantes_matriculados', 1);
    $this->assertDatabaseMissing('estudiantes_matriculados', ['grupo_id' => $this->grupo->id]);
});

test('asigna instancias de Docente sin duplicarlas y rechaza la tercera', function () {
    $luis = Docente::factory()->create(['cod_empleado' => 1002]);
    $carla = Docente::factory()->create(['cod_empleado' => 1003]);

    $this->grupo->asignarDocente($this->ana);
    $this->grupo->asignarDocente($luis);
    $this->grupo->asignarDocente($this->ana);

    expect(fn () => $this->grupo->asignarDocente($carla))->toThrow(DomainException::class);
    expect($this->grupo->fresh()->docentes->modelKeys())
        ->toEqualCanonicalizing([$this->ana->id, $luis->id]);
    $this->assertDatabaseCount('grupo_docente', 2);
});

test('C3: asignar un docente actualiza la relación previamente cargada', function () {
    expect($this->grupo->docentes)->toBeEmpty();

    $this->grupo->asignarDocente($this->ana);

    expect($this->grupo->docentes->modelKeys())->toBe([$this->ana->id]);
    $this->assertDatabaseHas('grupo_docente', [
        'grupo_id' => $this->grupo->id,
        'docente_id' => $this->ana->id,
    ]);
});

test('C3: abre el grupo con dos docentes y quince estudiantes', function () {
    $luis = Docente::factory()->create(['cod_empleado' => 1002]);
    $this->grupo->asignarDocente($this->ana);
    $this->grupo->asignarDocente($luis);
    $estudiantes = Estudiante::factory()->count(15)->for($this->ana, 'consejero')->create();
    $this->grupo->estudiantes()->attach($estudiantes->modelKeys());

    expect($this->grupo->puedeAbrirse())->toBeTrue();
    $this->grupo->abrir();

    expect($this->grupo->fresh()->abierto)->toBeTrue();
    expect($this->grupo->fresh()->docentes)->toHaveCount(2);
    $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id, 'abierto' => true]);
});

test('C3: rechaza abrir un grupo con tres docentes asociados directamente', function () {
    $otrosDocentes = Docente::factory()->count(2)->create();
    $this->grupo->docentes()->attach([$this->ana->id, ...$otrosDocentes->modelKeys()]);
    $estudiantes = Estudiante::factory()->count(15)->for($this->ana, 'consejero')->create();
    $this->grupo->estudiantes()->attach($estudiantes->modelKeys());

    expect($this->grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $this->grupo->abrir())->toThrow(DomainException::class);

    expect($this->grupo->fresh()->abierto)->toBeFalse();
    expect($this->grupo->fresh()->docentes)->toHaveCount(3);
    $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id, 'abierto' => false]);
});
