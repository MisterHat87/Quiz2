<?php

namespace App\Models;

use Database\Factories\GrupoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory;

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'grupo_docente')->withTimestamps();
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiantes_matriculados')
            ->using(EstudianteMatriculado::class)
            ->withPivot(['id', 'nota'])
            ->withTimestamps();
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(EstudianteMatriculado::class);
    }

    protected function casts(): array
    {
        return [
            'abierto' => 'boolean',
        ];
    }

    public function asignarDocente(Docente|int $codEmpleado): void
    {
        $docente = $codEmpleado instanceof Docente ? $codEmpleado : $this->buscarDocente($codEmpleado);

        // Asignar de nuevo al mismo docente no duplica la relación.
        if ($this->docentes()->whereKey($docente->id)->exists()) {
            return;
        }

        if ($this->docentes()->count() >= 2) {
            throw new \DomainException(
                'Un grupo no puede tener más de dos docentes.'
            );
        }

        $this->docentes()->attach($docente->id);

        $this->unsetRelation('docentes');
    }

    protected function buscarDocente(int $codEmpleado): Docente
    {
        return Docente::where('cod_empleado', $codEmpleado)->firstOrFail();
    }

    public function puedeAbrirse(): bool
    {
        $cantidadDocentes = $this->docentes()->count();

        return $cantidadDocentes >= 1
            && $cantidadDocentes <= 2
            && $this->estudiantes()->count() >= 15;
    }

    public function abrir(): void
    {
        if (! $this->puedeAbrirse()) {
            throw new \DomainException(
                'El grupo necesita uno o dos docentes y al menos 15 estudiantes.'
            );
        }

        $this->abierto = true;
        $this->save();
    }

    public function registrarNota(Estudiante $estudiante, float $nota): void
    {
        if ($nota < 0 || $nota > 10) {
            throw new \InvalidArgumentException('La nota debe estar entre 0 y 10.');
        }

        $matricula = $this->matriculas()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $matricula->nota = $nota;
        $matricula->save();

        $this->unsetRelation('estudiantes');
        $this->unsetRelation('matriculas');
    }
}
