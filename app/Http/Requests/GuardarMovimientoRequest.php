<?php

namespace App\Http\Requests;

use App\Models\MovimientoFinanciero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $movimiento = $this->route('movimiento');
        $tipo = $movimiento instanceof MovimientoFinanciero
            ? $movimiento->categoria->tipo
            : $this->input('tipo');
        $reglasTipo = ['required', Rule::in(['ingreso', 'gasto'])];

        if ($movimiento instanceof MovimientoFinanciero) {
            $reglasTipo[] = Rule::in([$tipo]);
        }

        return [
            'tipo' => $reglasTipo,
            'movimiento_id' => ['nullable', 'integer'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('tipo', $tipo)],
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
            'tipo' => 'tipo de movimiento',
            'categoria_id' => 'categoría',
            'monto' => 'monto',
            'fecha' => 'fecha',
            'concepto' => 'concepto',
        ];
    }
}
