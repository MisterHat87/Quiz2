<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('numero_ciclo');
            $table->unsignedSmallInteger('año');
            $table->unsignedSmallInteger('numero_grupo');
            $table->boolean('abierto');
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->unique(['curso_id', 'año', 'numero_ciclo', 'numero_grupo']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
