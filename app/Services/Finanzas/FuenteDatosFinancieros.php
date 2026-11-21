<?php

namespace App\Services\Finanzas;

/**
 * Origen de los datos financieros del panel y de los reportes.
 *
 * Implementaciones:
 * - MovimientosFinancierosEloquent: datos reales del usuario autenticado (predeterminada,
 *   registrada en AppServiceProvider).
 * - DatosFinancierosSimulados: datos de demostración, sin base de datos.
 * - MovimientosEnMemoria: lista fija de movimientos (pruebas automatizadas).
 *
 * Si los módulos definitivos de ingresos y gastos usan otras tablas, basta con crear
 * otra implementación y registrarla en AppServiceProvider.
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
