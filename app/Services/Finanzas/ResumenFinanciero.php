<?php

namespace App\Services\Finanzas;

/**
 * Cálculos del panel financiero. No depende del origen de los datos: recibe
 * cualquier implementación de FuenteDatosFinancieros (simulada o real).
 */
class ResumenFinanciero
{
    /** Meses que muestra el gráfico de evolución de ingresos y gastos. */
    public const MESES_DE_EVOLUCION = 6;

    /** Movimientos que muestra la tabla de movimientos recientes. */
    public const MOVIMIENTOS_RECIENTES = 8;

    public function __construct(private FuenteDatosFinancieros $fuente) {}

    /**
     * @return array{
     *     esSimulado: bool,
     *     periodo: Periodo,
     *     periodoAnterior: Periodo,
     *     ingresos: array{monto: float, variacion: float|null},
     *     gastos: array{monto: float, variacion: float|null},
     *     balance: array{monto: float, anterior: float, diferencia: float, variacion: float|null},
     *     porcentajeGastos: float|null,
     *     evolucion: array{etiquetas: list<string>, ingresos: list<float>, gastos: list<float>},
     *     distribucionGastos: array{etiquetas: list<string>, valores: list<float>},
     *     movimientos: list<Movimiento>
     * }
     */
    public function generar(Periodo $periodo): array
    {
        $anterior = $periodo->anterior();

        $ingresos = $this->fuente->totalIngresos($periodo);
        $gastos = $this->fuente->totalGastos($periodo);
        $ingresosAnteriores = $this->fuente->totalIngresos($anterior);
        $gastosAnteriores = $this->fuente->totalGastos($anterior);

        // Mismas fórmulas que los reportes (plan interno ANAF115): resultado y porcentaje de gastos.
        $balance = ReporteFinanciero::resultadoFinanciero($ingresos, $gastos);
        $balanceAnterior = ReporteFinanciero::resultadoFinanciero($ingresosAnteriores, $gastosAnteriores);

        $distribucion = $this->fuente->gastosPorCategoria($periodo);

        return [
            'esSimulado' => $this->fuente->esSimulada(),
            'periodo' => $periodo,
            'periodoAnterior' => $anterior,
            'ingresos' => ['monto' => $ingresos, 'variacion' => self::variacionPorcentual($ingresos, $ingresosAnteriores)],
            'gastos' => ['monto' => $gastos, 'variacion' => self::variacionPorcentual($gastos, $gastosAnteriores)],
            'balance' => [
                'monto' => $balance,
                'anterior' => $balanceAnterior,
                'diferencia' => round($balance - $balanceAnterior, 2),
                'variacion' => self::variacionPorcentual($balance, $balanceAnterior),
            ],
            'porcentajeGastos' => ReporteFinanciero::porcentajeDeGastos($ingresos, $gastos),
            'evolucion' => $this->evolucion($periodo),
            'distribucionGastos' => ['etiquetas' => array_keys($distribucion), 'valores' => array_values($distribucion)],
            'movimientos' => $this->fuente->movimientosRecientes($periodo, self::MOVIMIENTOS_RECIENTES),
        ];
    }

    /**
     * Variación porcentual respecto al valor anterior, sobre su valor absoluto para que
     * pasar de un balance de -100 a -50 sea una mejora (+50 %).
     * Sin valor anterior (cero) no hay base de comparación: devuelve null, salvo que
     * ambos sean cero (0 %).
     */
    public static function variacionPorcentual(float $actual, float $anterior): ?float
    {
        if ($anterior == 0.0) {
            return $actual == 0.0 ? 0.0 : null;
        }

        return round(($actual - $anterior) / abs($anterior) * 100, 1);
    }

    /**
     * Ingresos y gastos de los últimos meses hasta el mes en que termina el periodo.
     * Los meses sin movimientos aparecen con cero.
     *
     * @return array{etiquetas: list<string>, ingresos: list<float>, gastos: list<float>}
     */
    private function evolucion(Periodo $periodo): array
    {
        $rango = Periodo::ultimosMeses(self::MESES_DE_EVOLUCION, $periodo->fin);
        $totales = $this->fuente->totalesPorMes($rango);

        $evolucion = ['etiquetas' => [], 'ingresos' => [], 'gastos' => []];

        foreach ($rango->meses() as $mes) {
            $clave = $mes->format('Y-m');

            $evolucion['etiquetas'][] = ucfirst(str_replace('.', '', $mes->locale('es')->translatedFormat('M Y')));
            $evolucion['ingresos'][] = $totales[$clave]['ingresos'] ?? 0.0;
            $evolucion['gastos'][] = $totales[$clave]['gastos'] ?? 0.0;
        }

        return $evolucion;
    }
}
