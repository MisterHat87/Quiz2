<?php

namespace App\Models;

use Database\Factories\EstudianteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    /** @use HasFactory<EstudianteFactory> */
    use HasFactory;

    public function consejero(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'consejero_id');
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'estudiante_matriculados')
            ->as('matricula')
            ->withPivot(['id', 'nota'])
            ->withTimestamps();
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(EstudianteMatriculado::class);
    }
}
