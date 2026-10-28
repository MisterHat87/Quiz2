<?php

namespace App;

use App\Models\Grupo;

class GrupoValidator
{
    public static function validarComposicion(Grupo $grupo): void
    {
        $cantidadDocentes = $grupo->docentes->count();

        if ($cantidadDocentes < 1 || $cantidadDocentes > 2) {
            throw new \DomainException('El grupo debe tener uno o dos docentes.');
        }

        if ($grupo->estudiantes->count() < 15) {
            throw new \DomainException('El grupo debe tener al menos 15 estudiantes.');
        }
    }

    public static function validarNotasFinales(Grupo $grupo): void
    {
        foreach ($grupo->estudiantes as $estudiante) {
            if ($estudiante->matricula->nota === null) {
                throw new \DomainException('Cada estudiante matriculado debe tener una nota final.');
            }
        }
    }
}
