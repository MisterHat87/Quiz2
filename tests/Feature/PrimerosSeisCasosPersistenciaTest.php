<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->curso = Curso::factory()->create([
        'sigla' => 'IF6100',
        'nombre_curso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);

    $this->grupo = Grupo::factory()->for($this->curso)->create([
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

test('CP-01: registra y recupera el grupo con su curso', function () {
    $this->grupo->abierto = true;
    $this->grupo->save();

    $grupo = Grupo::find($this->grupo->id);

    expect($grupo->numero_ciclo)->toBe(2);
    expect($grupo->año)->toBe(2026);
    expect($grupo->numero_grupo)->toBe(1);
    expect($grupo->abierto)->toBeTrue();
    expect($grupo->curso->sigla)->toBe('IF6100');
    expect($grupo->curso->creditos)->toBe(4);

    $this->assertDatabaseCount('grupos', 1);
});

test('CP-02: recupera tres grupos del curso y dos abiertos', function () {
    $this->grupo->abierto = true;
    $this->grupo->save();

    foreach ([
        2 => true,
        3 => false,
    ] as $numero => $abierto) {
        Grupo::factory()->for($this->curso)->create([
            'numero_ciclo' => 2,
            'año' => 2026,
            'numero_grupo' => $numero,
            'abierto' => $abierto,
        ]);
    }
    $grupos = $this->curso->grupos;

    expect($grupos)->toHaveCount(3);
    expect($grupos
        ->where('abierto', true)
        ->pluck('numero_grupo')
        ->all())
        ->toEqualCanonicalizing([1, 2]);

    expect($grupos->pluck('curso_id')->unique()->all())->toBe([$this->curso->id]);
});

test('CP-03: persiste dos docentes y la relación inversa', function () {
    Docente::factory()->create([
        'cod_empleado' => 1002,
        'nombre_empleado' => 'Luis',
        'apellidos_empleado' => 'Rojas Solano',
    ]);
    $this->grupo->asignarDocente(1001);
    $this->grupo->asignarDocente(1002);

    expect($this->grupo->docentes)->toHaveCount(2);
    expect($this->grupo->docentes
        ->pluck('cod_empleado')
        ->all())
        ->toEqualCanonicalizing([1001, 1002]);

    expect($this->ana->grupos()->whereKey($this->grupo->id)->exists())->toBeTrue();

    $this->assertDatabaseCount('grupo_docente', 2);
});

test('CP-04: rechaza al tercero y conserva los dos docentes', function () {
    Docente::factory()->create([
        'cod_empleado' => 1002,
    ]);
    $tercero = Docente::factory()->create([
        'cod_empleado' => 1003,
        'nombre_empleado' => 'Carla',
        'apellidos_empleado' => 'Jiménez Araya',
    ]);
    $this->grupo->asignarDocente(1001);
    $this->grupo->asignarDocente(1002);

    expect(fn () => $this->grupo
        ->asignarDocente(1003))
        ->toThrow(DomainException::class);

    expect($this->grupo
        ->fresh()->docentes
        ->pluck('cod_empleado')
        ->all())
        ->toEqualCanonicalizing([1001, 1002]);

    $this->assertDatabaseCount('grupo_docente', 2);

    $this->assertDatabaseMissing('grupo_docente', [
        'grupo_id' => $this->grupo->id,
        'docente_id' => $tercero->id,
    ]);
});

test('CP-05: con catorce matriculados permanece cerrado en la base de datos', function () {
    $this->grupo->asignarDocente(1001);
    $estudiantes = Estudiante::factory()
        ->count(14)
        ->for($this->ana, 'consejero')
        ->create();
    $this->grupo->estudiantes()->attach($estudiantes->modelKeys());

    expect($this->grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $this->grupo->abrir())->toThrow(DomainException::class);
    expect($this->grupo->fresh()->abierto)->toBeFalse();
});

test('CP-06: con quince matriculados persiste la apertura', function () {
    $this->grupo->asignarDocente(1001);
    $estudiantes = Estudiante::factory()
        ->count(15)
        ->for($this->ana, 'consejero')
        ->create();
    $this->grupo->estudiantes()->attach($estudiantes->modelKeys());

    expect($this->grupo->puedeAbrirse())->toBeTrue();
    $this->grupo->abrir();

    expect($this->grupo->fresh()->abierto)->toBeTrue();
});
