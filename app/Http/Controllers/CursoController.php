<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCursoRequest;
use App\Models\Curso;
use Illuminate\Http\JsonResponse;

class CursoController extends Controller
{
    public function store(StoreCursoRequest $request): JsonResponse
    {
        $curso = new Curso;
        $curso->sigla = $request->validated('sigla');
        $curso->nombre_curso = $request->validated('nombre_curso');
        $curso->creditos = $request->validated('creditos');
        $curso->save();

        return response()->json(['data' => $curso], 201);
    }
}
