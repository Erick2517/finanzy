<?php

namespace App\Services\Finanzas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * DATOS SIMULADOS — fuente temporal mientras no existan los módulos de ingresos,
 * gastos y categorías. No escribe ni lee la base de datos.
 *
 * Genera movimientos mensuales a partir de plantillas fijas. Los montos varían de un
 * mes a otro con una fórmula determinista (los mismos meses siempre dan las mismas
 * cifras) y nunca se generan movimientos posteriores a hoy.
 *
 * Cuando existan los módulos reales, se registrará otra implementación de
 * FuenteDatosFinancieros en AppServiceProvider y esta clase podrá eliminarse.
 */
class DatosFinancierosSimulados extends MovimientosEnMemoria
{
    /** Meses hacia atrás desde el mes actual para los que se generan movimientos. */
    private const MESES_DE_HISTORIA = 24;

    /** Días recientes cuyos movimientos se muestran como pendientes. */
    private const DIAS_PENDIENTES = 3;

    /**
     * @var list<array{dia: int, descripcion: string, categoria: string, tipo: TipoMovimiento, monto: float, cadaMeses?: int}>
     */
    private const PLANTILLAS = [
        ['dia' => 1, 'descripcion' => 'Pago de nómina recibido', 'categoria' => 'Salario', 'tipo' => TipoMovimiento::Ingreso, 'monto' => 5200.00],
        ['dia' => 12, 'descripcion' => 'Ventas al contado', 'categoria' => 'Ventas', 'tipo' => TipoMovimiento::Ingreso, 'monto' => 4150.00],
        ['dia' => 20, 'descripcion' => 'Honorarios por consultoría', 'categoria' => 'Servicios profesionales', 'tipo' => TipoMovimiento::Ingreso, 'monto' => 2300.00, 'cadaMeses' => 2],
        ['dia' => 3, 'descripcion' => 'Alquiler de oficina', 'categoria' => 'Alquiler', 'tipo' => TipoMovimiento::Gasto, 'monto' => 1500.00],
        ['dia' => 6, 'descripcion' => 'Recibo de energía eléctrica', 'categoria' => 'Servicios básicos', 'tipo' => TipoMovimiento::Gasto, 'monto' => 320.40],
        ['dia' => 8, 'descripcion' => 'Internet y telefonía', 'categoria' => 'Servicios básicos', 'tipo' => TipoMovimiento::Gasto, 'monto' => 95.00],
        ['dia' => 10, 'descripcion' => 'Compra de insumos', 'categoria' => 'Insumos', 'tipo' => TipoMovimiento::Gasto, 'monto' => 1780.00],
        ['dia' => 15, 'descripcion' => 'Publicidad en redes sociales', 'categoria' => 'Marketing', 'tipo' => TipoMovimiento::Gasto, 'monto' => 450.00],
        ['dia' => 18, 'descripcion' => 'Mantenimiento de equipo', 'categoria' => 'Mantenimiento', 'tipo' => TipoMovimiento::Gasto, 'monto' => 260.00, 'cadaMeses' => 3],
        ['dia' => 28, 'descripcion' => 'Planilla del personal', 'categoria' => 'Planilla', 'tipo' => TipoMovimiento::Gasto, 'monto' => 3150.00],
    ];

    public function __construct(?CarbonInterface $hoy = null)
    {
        parent::__construct($this->generar(CarbonImmutable::parse($hoy ?? now())));
    }

    public function esSimulada(): bool
    {
        return true;
    }

    /**
     * @return list<Movimiento>
     */
    private function generar(CarbonImmutable $hoy): array
    {
        $movimientos = [];
        $primerMes = $hoy->startOfMonth()->subMonthsNoOverflow(self::MESES_DE_HISTORIA);

        for ($mes = $primerMes; $mes->lessThanOrEqualTo($hoy); $mes = $mes->addMonthNoOverflow()) {
            $indiceMes = $mes->year * 12 + $mes->month;

            foreach (self::PLANTILLAS as $posicion => $plantilla) {
                $fecha = $mes->setDay(min($plantilla['dia'], $mes->daysInMonth));

                if ($fecha->greaterThan($hoy) || $indiceMes % ($plantilla['cadaMeses'] ?? 1) !== 0) {
                    continue;
                }

                $movimientos[] = new Movimiento(
                    fecha: $fecha,
                    descripcion: $plantilla['descripcion'],
                    categoria: $plantilla['categoria'],
                    tipo: $plantilla['tipo'],
                    monto: round($plantilla['monto'] * (1 + 0.12 * sin($indiceMes * 1.7 + $posicion)), 2),
                    estado: $fecha->diffInDays($hoy) < self::DIAS_PENDIENTES ? 'Pendiente' : 'Confirmado',
                );
            }
        }

        return $movimientos;
    }
}
