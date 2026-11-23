<?php

namespace App\Services\Finanzas;

/**
 * Cálculos de los reportes financieros. Igual que ResumenFinanciero, no depende del
 * origen de los datos: obtiene los movimientos filtrados de FuenteDatosFinancieros y
 * reutiliza los totales y agrupaciones de MovimientosEnMemoria sobre esa selección.
 */
class ReporteFinanciero
{
    public function __construct(private FuenteDatosFinancieros $fuente) {}

    /**
     * @return array{
     *     esSimulado: bool,
     *     filtros: FiltrosReporte,
     *     ingresos: float,
     *     gastos: float,
     *     resultado: float,
     *     porcentajeGastos: float|null,
     *     distribucionGastos: array{etiquetas: list<string>, valores: list<float>, porcentajes: list<float>},
     *     evolucion: array{etiquetas: list<string>, ingresos: list<float>, gastos: list<float>, resultados: list<float>}|null,
     *     movimientos: list<Movimiento>
     * }
     */
    public function generar(FiltrosReporte $filtros): array
    {
        $periodo = $filtros->periodo;
        $movimientos = $this->fuente->movimientos($periodo, $filtros->categoria);

        if ($filtros->tipoMovimiento !== null) {
            $movimientos = array_values(array_filter(
                $movimientos,
                fn (Movimiento $movimiento) => $movimiento->tipo === $filtros->tipoMovimiento,
            ));
        }

        $seleccion = new MovimientosEnMemoria($movimientos);

        $ingresos = $seleccion->totalIngresos($periodo);
        $gastos = $seleccion->totalGastos($periodo);

        return [
            'esSimulado' => $this->fuente->esSimulada(),
            'filtros' => $filtros,
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'resultado' => self::resultadoFinanciero($ingresos, $gastos),
            'porcentajeGastos' => self::porcentajeDeGastos($ingresos, $gastos),
            'distribucionGastos' => $this->distribucion($seleccion->gastosPorCategoria($periodo), $gastos),
            'evolucion' => $this->evolucion($seleccion, $filtros),
            'movimientos' => array_reverse($movimientos),
        ];
    }

    /**
     * Resultado financiero = ingresos totales − gastos totales.
     */
    public static function resultadoFinanciero(float $ingresos, float $gastos): float
    {
        return round($ingresos - $gastos, 2);
    }

    /**
     * Porcentaje de gastos = (gastos totales / ingresos totales) × 100.
     * Sin ingresos no se puede calcular: devuelve null.
     */
    public static function porcentajeDeGastos(float $ingresos, float $gastos): ?float
    {
        if ($ingresos <= 0) {
            return null;
        }

        return round($gastos / $ingresos * 100, 1);
    }

    /**
     * @param  array<string, float>  $porCategoria
     * @return array{etiquetas: list<string>, valores: list<float>, porcentajes: list<float>}
     */
    private function distribucion(array $porCategoria, float $totalGastos): array
    {
        return [
            'etiquetas' => array_keys($porCategoria),
            'valores' => array_values($porCategoria),
            'porcentajes' => array_map(
                fn (float $valor) => $totalGastos > 0 ? round($valor / $totalGastos * 100, 1) : 0.0,
                array_values($porCategoria),
            ),
        ];
    }

    /**
     * Ingresos, gastos y resultado de cada día, mes o año del periodo (con ceros en los
     * grupos sin movimientos). Con un solo grupo no hay evolución que mostrar: null.
     *
     * @return array{etiquetas: list<string>, ingresos: list<float>, gastos: list<float>, resultados: list<float>}|null
     */
    private function evolucion(MovimientosEnMemoria $seleccion, FiltrosReporte $filtros): ?array
    {
        $grupos = $filtros->tipo->grupos($filtros->periodo);

        if (count($grupos) < 2) {
            return null;
        }

        $totales = $seleccion->totalesAgrupados($filtros->periodo, $filtros->tipo->formato());
        $evolucion = ['etiquetas' => [], 'ingresos' => [], 'gastos' => [], 'resultados' => []];

        foreach ($grupos as $grupo) {
            $ingresos = $totales[$grupo['clave']]['ingresos'] ?? 0.0;
            $gastos = $totales[$grupo['clave']]['gastos'] ?? 0.0;

            $evolucion['etiquetas'][] = $grupo['etiqueta'];
            $evolucion['ingresos'][] = $ingresos;
            $evolucion['gastos'][] = $gastos;
            $evolucion['resultados'][] = self::resultadoFinanciero($ingresos, $gastos);
        }

        return $evolucion;
    }
}
