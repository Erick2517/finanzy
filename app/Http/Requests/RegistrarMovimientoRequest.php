<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de un movimiento financiero. La categoría debe pertenecer al usuario
 * autenticado: así nadie puede registrar movimientos en categorías ajenas.
 */
class RegistrarMovimientoRequest extends FormRequest
{
    /**
     * El acceso lo controla el middleware auth de la ruta.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('user_id', $this->user()->id)],
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'fecha' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.decimal' => 'El monto puede tener como máximo dos decimales.',
            'monto.max' => 'El monto no puede superar 999,999,999,999.99.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categoria_id' => 'categoría',
            'monto' => 'monto',
            'fecha' => 'fecha',
            'concepto' => 'concepto',
        ];
    }
}
