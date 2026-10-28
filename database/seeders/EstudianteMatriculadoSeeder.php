<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EstudianteMatriculadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(GrupoSeeder::class);
    }
}
