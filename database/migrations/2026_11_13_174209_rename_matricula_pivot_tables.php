<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('estudiante_matriculados', 'estudiantes_matriculados');
        Schema::rename('docente_grupo', 'grupo_docente');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('grupo_docente', 'docente_grupo');
        Schema::rename('estudiantes_matriculados', 'estudiante_matriculados');
    }
};
