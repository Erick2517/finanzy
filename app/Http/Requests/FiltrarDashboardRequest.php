<?php

namespace App\Http\Requests;

use App\Services\Finanzas\Periodo;
use Illuminate\Foundation\Http\FormRequest;

class FiltrarDashboardRequest extends FormRequest
{
    /**
     * Si los filtros no son válidos se vuelve al panel sin parámetros, con los errores.
     */
    protected $redirectRoute = 'dashboard';

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
            'fecha_inicio' => ['nullable', 'required_with:fecha_fin', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'required_with:fecha_inicio', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
        ];
    }

    /**
     * Mensajes de formato comprensibles en lugar del patrón técnico (Y-m-d).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $formato = 'El campo :attribute debe ser una fecha con el formato AAAA-MM-DD (por ejemplo, 2026-11-18).';

        return [
            'fecha_inicio.date_format' => $formato,
            'fecha_fin.date_format' => $formato,
        ];
    }

    /**
     * Periodo solicitado o, si no se indicó ninguno, el mes actual.
     */
    public function periodo(): Periodo
    {
        $filtros = $this->validated();

        if (filled($filtros['fecha_inicio'] ?? null) && filled($filtros['fecha_fin'] ?? null)) {
            return new Periodo($filtros['fecha_inicio'], $filtros['fecha_fin']);
        }

        return Periodo::mesActual();
    }
}
