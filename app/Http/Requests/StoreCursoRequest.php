<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCursoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sigla' => ['required', 'string', 'max:20', 'unique:cursos,sigla'],
            'nombre_curso' => ['required', 'string', 'max:255'],
            'creditos' => ['required', 'integer', 'min:0', 'max:255'],
        ];
    }
}
