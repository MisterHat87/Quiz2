<?php

namespace App\Models;

use Database\Factories\DocenteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    /** @use HasFactory<DocenteFactory> */
    use HasFactory;

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'docente_grupo')->withTimestamps();
    }

    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class, 'consejero_id');
    }
}
