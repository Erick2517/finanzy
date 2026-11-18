<?php

namespace App\Http\Requests;

use App\Services\Finanzas\FiltrosReporte;
use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\TipoReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filtros de un reporte. Los campos fecha_inicio y fecha_fin cambian de formato según
 * el tipo: fechas (diario), meses AAAA-MM (mensual) o años AAAA (anual).
 */
class GenerarReporteRequest extends FormRequest
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
    public function rules(FuenteDatosFinancieros $fuente): array
    {
        $tipo = $this->tipo();

        $formato = $tipo === TipoReporte::Anual
            ? ['integer', 'between:2000,2100']
            : ['date_format:'.$tipo->formato()];

        $orden = $tipo === TipoReporte::Anual ? 'gte:fecha_inicio' : 'after_or_equal:fecha_inicio';

        return [
            'tipo' => ['nullable', Rule::enum(TipoReporte::class)],
            'fecha_inicio' => ['nullable', 'required_with:fecha_fin', ...$formato],
            'fecha_fin' => ['nullable', 'required_with:fecha_inicio', ...$formato, $orden],
            'categoria_id' => ['nullable', 'string', Rule::in(array_keys($fuente->categorias()))],
        ];
    }

    /**
     * El periodo no puede superar el máximo de días, meses o años de su tipo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->filled(['fecha_inicio', 'fecha_fin'])) {
                    return;
                }

                $tipo = $this->tipo();
                $periodo = $tipo->periodoEntre((string) $this->input('fecha_inicio'), (string) $this->input('fecha_fin'));

                if ($tipo->cantidadDeGrupos($periodo) > $tipo->maximoDeGrupos()) {
                    $validator->errors()->add(
                        'fecha_fin',
                        sprintf('El reporte %s no puede abarcar más de %d %s.', mb_strtolower($tipo->etiqueta()), $tipo->maximoDeGrupos(), $tipo->unidad()[1]),
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        [$singular] = $this->tipo()->unidad();

        return [
            'tipo' => 'tipo de reporte',
            'fecha_inicio' => "{$singular} inicial",
            'fecha_fin' => "{$singular} final",
            'categoria_id' => 'categoría',
        ];
    }

    /**
     * Mensajes de formato comprensibles en lugar de los patrones técnicos (Y-m-d, Y-m).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $formato = match ($this->tipo()) {
            TipoReporte::Diario => ['date_format' => 'El campo :attribute debe ser una fecha con el formato AAAA-MM-DD (por ejemplo, 2026-11-18).'],
            TipoReporte::Mensual => ['date_format' => 'El campo :attribute debe usar el formato AAAA-MM (por ejemplo, 2026-11).'],
            TipoReporte::Anual => ['integer' => 'El campo :attribute debe ser un año de cuatro cifras (por ejemplo, 2026).'],
        };

        $mensajes = [];
        foreach (['fecha_inicio', 'fecha_fin'] as $campo) {
            foreach ($formato as $regla => $mensaje) {
                $mensajes["{$campo}.{$regla}"] = $mensaje;
            }
        }

        return $mensajes;
    }

    /**
     * Ante un error se vuelve al reporte del mismo tipo, con los errores y lo escrito.
     */
    protected function getRedirectUrl(): string
    {
        $tipo = TipoReporte::tryFrom((string) $this->query('tipo'));

        return route('reportes.index', $tipo ? ['tipo' => $tipo->value] : []);
    }

    /**
     * Tipo solicitado o, si no se indicó (o no es válido), el mensual.
     */
    public function tipo(): TipoReporte
    {
        return TipoReporte::tryFrom((string) $this->input('tipo')) ?? TipoReporte::Mensual;
    }

    public function filtros(FuenteDatosFinancieros $fuente): FiltrosReporte
    {
        $validados = $this->validated();
        $tipo = $this->tipo();

        $periodo = filled($validados['fecha_inicio'] ?? null) && filled($validados['fecha_fin'] ?? null)
            ? $tipo->periodoEntre((string) $validados['fecha_inicio'], (string) $validados['fecha_fin'])
            : $tipo->periodoPorDefecto();

        $categoria = $validados['categoria_id'] ?? null;

        return new FiltrosReporte($tipo, $periodo, $categoria, $categoria ? $fuente->categorias()[$categoria] : null);
    }
}
