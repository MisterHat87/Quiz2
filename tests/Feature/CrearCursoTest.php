<?php

use App\Models\Curso;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('crea un curso mediante la API y lo guarda en la base de datos', function () {
    $datos = Curso::factory()->raw();

    $response = $this->postJson(route('cursos.store'), $datos);

    $response->assertCreated()->assertJsonPath('data.sigla', $datos['sigla']);
    $this->assertDatabaseHas('cursos', ['id' => $response->json('data.id'), ...$datos]);
    $this->assertDatabaseCount('cursos', 1);
});

it('requiere los datos del curso sin guardar registros invalidos', function () {
    $this->postJson(route('cursos.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sigla', 'nombre_curso', 'creditos']);

    $this->assertDatabaseCount('cursos', 0);
});

it('rechaza una sigla que ya existe', function () {
    $curso = Curso::factory()->create();

    $this->postJson(route('cursos.store'), Curso::factory()->raw(['sigla' => $curso->sigla]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sigla');

    $this->assertDatabaseCount('cursos', 1);
});
