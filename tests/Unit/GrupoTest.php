<?php

namespace Tests\Unit;

use App\GrupoValidator;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class GrupoTest extends TestCase
{
    public function test_instancia_un_grupo_con_toda_su_informacion_asociada(): void
    {
        $curso = (new Curso)->forceFill([
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);
        $docentes = new Collection([
            (new Docente)->forceFill(['cod_empleado' => 1001, 'nombre_empleado' => 'Ana', 'apellidos_empleado' => 'Vargas Mora']),
            (new Docente)->forceFill(['cod_empleado' => 1002, 'nombre_empleado' => 'Luis', 'apellidos_empleado' => 'Rojas Solís']),
        ]);
        $grupo = (new Grupo)->forceFill([
            'numero_ciclo' => 2,
            'año' => 2026,
            'numero_grupo' => 1,
            'abierto' => true,
        ]);
        $estudiantes = new Collection;
        $matriculas = new Collection;

        for ($numero = 1; $numero <= 15; $numero++) {
            $estudiante = (new Estudiante)->forceFill([
                'carne' => sprintf('C%05d', $numero),
                'nombre' => 'Estudiante '.$numero,
                'apellidos' => 'Mora Rojas',
            ]);
            $estudiante->setRelation('consejero', $docentes->first());
            $matricula = (new EstudianteMatriculado)->forceFill(['nota' => 9.5]);
            $matricula->setRelation('grupo', $grupo);
            $matricula->setRelation('estudiante', $estudiante);
            $estudiante->setRelation('matricula', $matricula);
            $estudiantes->push($estudiante);
            $matriculas->push($matricula);
        }

        $grupo->setRelation('curso', $curso);
        $grupo->setRelation('docentes', $docentes);
        $grupo->setRelation('estudiantes', $estudiantes);
        $grupo->setRelation('matriculas', $matriculas);
        GrupoValidator::validarComposicion($grupo);
        GrupoValidator::validarNotasFinales($grupo);

        $this->assertSame(2, $grupo->numero_ciclo);
        $this->assertSame(2026, $grupo->año);
        $this->assertSame(1, $grupo->numero_grupo);
        $this->assertTrue($grupo->abierto);
        $this->assertSame('IF6100', $grupo->curso->sigla);
        $this->assertSame('Análisis y Diseño de Sistemas', $grupo->curso->nombre_curso);
        $this->assertSame(4, $grupo->curso->creditos);
        $this->assertCount(2, $grupo->docentes);
        $this->assertSame([1001, 1002], $grupo->docentes->pluck('cod_empleado')->all());
        $this->assertCount(15, $grupo->estudiantes);
        $this->assertCount(15, $grupo->matriculas);

        foreach ($grupo->estudiantes as $indice => $estudiante) {
            $this->assertSame(sprintf('C%05d', $indice + 1), $estudiante->carne);
            $this->assertSame('Estudiante '.($indice + 1), $estudiante->nombre);
            $this->assertSame('Mora Rojas', $estudiante->apellidos);
            $this->assertSame($docentes->first(), $estudiante->consejero);
            $this->assertSame(9.5, $estudiante->matricula->nota);
            $this->assertSame($grupo, $estudiante->matricula->grupo);
            $this->assertSame($estudiante, $estudiante->matricula->estudiante);
        }
    }
}
