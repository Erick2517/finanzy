<?php

namespace App\Services\Finanzas;

/**
 * Filtros ya validados de un reporte: tipo, periodo, categoría opcional y, también
 * opcional, solo ingresos o solo gastos.
 */
final readonly class FiltrosReporte
{
    public function __construct(
        public TipoReporte $tipo,
        public Periodo $periodo,
        public ?string $categoria = null,
        public ?string $nombreCategoria = null,
        public ?TipoMovimiento $tipoMovimiento = null,
    ) {}

    /**
     * Parámetros de URL que reproducen este reporte (para la exportación y los enlaces).
     *
     * @return array<string, string>
     */
    public function parametros(): array
    {
        return array_filter([
            'tipo' => $this->tipo->value,
            'fecha_inicio' => $this->tipo->valorDeCampo($this->periodo->inicio),
            'fecha_fin' => $this->tipo->valorDeCampo($this->periodo->fin),
            'categoria_id' => $this->categoria,
            'tipo_movimiento' => $this->tipoMovimiento?->value,
        ], fn (?string $valor) => filled($valor));
    }

    /**
     * Texto del filtro de tipo de movimiento para los resúmenes del reporte.
     */
    public function etiquetaMovimientos(): string
    {
        return match ($this->tipoMovimiento) {
            TipoMovimiento::Ingreso => 'Solo ingresos',
            TipoMovimiento::Gasto => 'Solo gastos',
            null => 'Ingresos y gastos',
        };
    }

    /**
     * Nombre del archivo exportado, igual para PDF y CSV.
     * Ej.: reporte-mensual-2026-06-a-2026-11.pdf o reporte-diario-2026-11-01-a-2026-11-18-12-gasto.csv
     */
    public function nombreDeArchivo(string $extension): string
    {
        $partes = [
            'reporte',
            $this->tipo->value,
            $this->tipo->valorDeCampo($this->periodo->inicio),
            'a',
            $this->tipo->valorDeCampo($this->periodo->fin),
            $this->categoria,
            $this->tipoMovimiento?->value,
        ];

        return implode('-', array_filter($partes)).'.'.$extension;
    }
}
