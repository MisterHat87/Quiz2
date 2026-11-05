<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

afterEach(function () {
    Mockery::close();
});

test('CP-01: atributos y curso en memoria con abierto booleano', function () {
    $curso = (new Curso)->forceFill([
        'id' => 1,
        'sigla' => 'IF6100',
        'nombre_curso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);

    $grupo = (new Grupo)->forceFill([
        'numero_ciclo' => 2,
        'año' => 2026,
        'numero_grupo' => 1,
        'abierto' => 1,
        'curso_id' => 1,
    ]);
    $grupo->setRelation('curso', $curso);

    expect($grupo->numero_ciclo)->toBe(2);
    expect($grupo->año)->toBe(2026);
    expect($grupo->numero_grupo)->toBe(1);
    expect($grupo->abierto)->toBeTrue();
    expect($grupo->curso->sigla)->toBe('IF6100');
    expect($grupo->curso->creditos)->toBe(4);
});

test('CP-02: distingue los dos grupos abiertos en memoria', function () {
    $curso = (new Curso)->forceFill([
        'id' => 1,
        'sigla' => 'IF6100',
    ]);
    $grupos = new Collection;

    foreach ([
        1 => true,
        2 => true,
        3 => false,
    ] as $numero => $abierto) {
        $grupos->push((new Grupo)->forceFill([
            'curso_id' => 1,
            'numero_ciclo' => 2,
            'año' => 2026,
            'numero_grupo' => $numero,
            'abierto' => $abierto,
        ]));
    }
    $curso->setRelation('grupos', $grupos);

    expect($curso->grupos)->toHaveCount(3);
    expect($curso->grupos
        ->where('abierto', true)
        ->pluck('numero_grupo')
        ->all())
        ->toBe([1, 2]);

    expect($curso->grupos->pluck('curso_id')->unique()->all())->toBe([1]);
});

test('CP-03: asigna dos docentes mediante una persistencia simulada', function () {
    $grupo = Mockery::mock(Grupo::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $relacion = Mockery::mock(BelongsToMany::class);
    $asignados = [];
    $grupo
        ->shouldReceive('docentes')
        ->andReturn($relacion);
    $relacion
        ->shouldReceive('whereKey')
        ->andReturnSelf();
    $relacion
        ->shouldReceive('exists')
        ->andReturnFalse();
    $relacion
        ->shouldReceive('count')
        ->andReturnUsing(function () use (&$asignados) {
            return count($asignados);
        });
    $relacion
        ->shouldReceive('attach')
        ->twice()
        ->andReturnUsing(function ($id) use (&$asignados) {
            $asignados[] = $id;
        });

    foreach ([1001, 1002] as $codigo) {
        $docente = (new Docente)->forceFill([
            'id' => $codigo - 1000,
            'cod_empleado' => $codigo,
        ]);
        $grupo
            ->shouldReceive('buscarDocente')
            ->with($codigo)
            ->once()
            ->andReturn($docente);
        $grupo->asignarDocente($codigo);
    }

    expect($asignados)->toBe([1, 2]);
});

test('CP-04: rechaza al tercer docente antes de guardar la asociación', function () {
    $grupo = Mockery::mock(Grupo::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $docente = (new Docente)->forceFill([
        'id' => 3,
        'cod_empleado' => 1003,
    ]);
    $relacion = Mockery::mock(BelongsToMany::class);
    $grupo
        ->shouldReceive('buscarDocente')
        ->with(1003)
        ->andReturn($docente);
    $grupo
        ->shouldReceive('docentes')
        ->andReturn($relacion);
    $relacion
        ->shouldReceive('whereKey')
        ->with(3)
        ->andReturnSelf();
    $relacion
        ->shouldReceive('exists')
        ->andReturnFalse();
    $relacion
        ->shouldReceive('count')
        ->andReturn(2);
    $relacion->shouldNotReceive('attach');

    expect(fn () => $grupo->asignarDocente(1003))->toThrow(DomainException::class);
});

test('CP-05: con catorce estudiantes no abre ni guarda', function () {
    $grupo = Mockery::mock(Grupo::class)->makePartial();
    $grupo->abierto = false;
    $docentes = Mockery::mock(BelongsToMany::class);
    $estudiantes = Mockery::mock(BelongsToMany::class);
    $grupo
        ->shouldReceive('docentes')
        ->andReturn($docentes);
    $grupo
        ->shouldReceive('estudiantes')
        ->andReturn($estudiantes);
    $docentes
        ->shouldReceive('count')
        ->andReturn(1);
    $estudiantes
        ->shouldReceive('count')
        ->andReturn(14);
    $grupo->shouldNotReceive('save');

    expect($grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $grupo->abrir())->toThrow(DomainException::class);
    expect($grupo->abierto)->toBeFalse();
});

test('CP-06: con quince estudiantes y un docente abre y solicita guardar', function () {
    $grupo = Mockery::mock(Grupo::class)->makePartial();
    $grupo->abierto = false;
    $docentes = Mockery::mock(BelongsToMany::class);
    $estudiantes = Mockery::mock(BelongsToMany::class);
    $grupo
        ->shouldReceive('docentes')
        ->andReturn($docentes);
    $grupo
        ->shouldReceive('estudiantes')
        ->andReturn($estudiantes);
    $docentes
        ->shouldReceive('count')
        ->andReturn(1);
    $estudiantes
        ->shouldReceive('count')
        ->andReturn(15);
    $grupo
        ->shouldReceive('save')
        ->once()
        ->andReturnUsing(function () use ($grupo) {
            expect($grupo->abierto)->toBeTrue();

            return true;
        });

    expect($grupo->puedeAbrirse())->toBeTrue();
    $grupo->abrir();

    expect($grupo->abierto)->toBeTrue();
});
