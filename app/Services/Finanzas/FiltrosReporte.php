<?php

namespace App\Services\Finanzas;

/**
 * Filtros ya validados de un reporte: tipo, periodo y categoría opcional.
 */
final readonly class FiltrosReporte
{
    public function __construct(
        public TipoReporte $tipo,
        public Periodo $periodo,
        public ?string $categoria = null,
        public ?string $nombreCategoria = null,
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
        ], fn (?string $valor) => filled($valor));
    }
}
