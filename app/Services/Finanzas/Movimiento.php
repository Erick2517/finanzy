<?php

namespace App\Services\Finanzas;

use Carbon\CarbonImmutable;

/**
 * Ingreso o gasto tal como lo muestra el panel. El monto siempre es positivo:
 * el tipo indica si suma (ingreso) o resta (gasto).
 */
final readonly class Movimiento
{
    public function __construct(
        public CarbonImmutable $fecha,
        public string $descripcion,
        public string $categoria,
        public TipoMovimiento $tipo,
        public float $monto,
        public string $estado,
    ) {}
}
