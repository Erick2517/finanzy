<?php

namespace App\Services\Finanzas;

/**
 * Origen de los datos financieros del panel y de los reportes.
 *
 * Mientras no existan los módulos de ingresos, gastos y categorías se usa
 * DatosFinancierosSimulados. Para conectar los datos reales basta con crear una
 * implementación que consulte esos modelos y registrarla en AppServiceProvider.
 */
interface FuenteDatosFinancieros
{
    /**
     * Indica si los datos son de demostración (el panel lo advierte al usuario).
     */
    public function esSimulada(): bool;

    public function totalIngresos(Periodo $periodo): float;

    public function totalGastos(Periodo $periodo): float;

    /**
     * Totales de cada mes del periodo, con clave 'Y-m'. Los meses sin movimientos pueden omitirse.
     *
     * @return array<string, array{ingresos: float, gastos: float}>
     */
    public function totalesPorMes(Periodo $periodo): array;

    /**
     * Total de gastos por categoría, ordenado de mayor a menor.
     *
     * @return array<string, float>
     */
    public function gastosPorCategoria(Periodo $periodo): array;

    /**
     * Últimos movimientos del periodo, del más reciente al más antiguo.
     *
     * @return list<Movimiento>
     */
    public function movimientosRecientes(Periodo $periodo, int $limite): array;

    /**
     * Categorías por las que se puede filtrar: identificador => nombre visible,
     * ordenadas por nombre. El identificador es opaco para la interfaz (en los
     * datos reales será la clave de la categoría).
     *
     * @return array<string, string>
     */
    public function categorias(): array;

    /**
     * Todos los movimientos del periodo, opcionalmente de una sola categoría,
     * del más reciente al más antiguo.
     *
     * @return list<Movimiento>
     */
    public function movimientos(Periodo $periodo, ?string $categoria = null): array;
}
