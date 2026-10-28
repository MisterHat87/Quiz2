<?php

namespace App\Models;

use Database\Factories\EstudianteMatriculadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstudianteMatriculado extends Model
{
    /** @use HasFactory<EstudianteMatriculadoFactory> */
    use HasFactory;

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
