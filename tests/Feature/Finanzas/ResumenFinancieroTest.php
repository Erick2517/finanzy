<?php

namespace Tests\Feature\Finanzas;

use App\Services\Finanzas\Movimiento;
use App\Services\Finanzas\MovimientosEnMemoria;
use App\Services\Finanzas\Periodo;
use App\Services\Finanzas\ResumenFinanciero;
use App\Services\Finanzas\TipoMovimiento;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResumenFinancieroTest extends TestCase
{
    private function movimiento(string $fecha, TipoMovimiento $tipo, float $monto, string $categoria = 'General'): Movimiento
    {
        return new Movimiento(CarbonImmutable::parse($fecha), "Movimiento del {$fecha}", $categoria, $tipo, $monto, 'Confirmado');
    }

    /**
     * @param  list<Movimiento>  $movimientos
     * @return array<string, mixed>
     */
    private function resumenDeNoviembre(array $movimientos): array
    {
        $servicio = new ResumenFinanciero(new MovimientosEnMemoria($movimientos));

        return $servicio->generar(new Periodo('2026-11-01', '2026-11-30'));
    }

    public function test_suma_ingresos_y_gastos_del_periodo_y_calcula_el_balance(): void
    {
        $resumen = $this->resumenDeNoviembre([
            $this->movimiento('2026-11-02', TipoMovimiento::Ingreso, 5000),
            $this->movimiento('2026-11-20', TipoMovimiento::Ingreso, 1200.50),
            $this->movimiento('2026-11-05', TipoMovimiento::Gasto, 3000),
            $this->movimiento('2026-11-30', TipoMovimiento::Gasto, 450.25),
            $this->movimiento('2026-12-01', TipoMovimiento::Ingreso, 9999),
        ]);

        $this->assertSame(6200.5, $resumen['ingresos']['monto']);
        $this->assertSame(3450.25, $resumen['gastos']['monto']);
        $this->assertSame(2750.25, $resumen['balance']['monto']);
    }

    public function test_el_balance_es_negativo_cuando_los_gastos_superan_a_los_ingresos(): void
    {
        $resumen = $this->resumenDeNoviembre([
            $this->movimiento('2026-11-02', TipoMovimiento::Ingreso, 1000),
            $this->movimiento('2026-11-05', TipoMovimiento::Gasto, 1500),
        ]);

        $this->assertSame(-500.0, $resumen['balance']['monto']);
    }

    public function test_compara_cada_indicador_con_el_periodo_anterior(): void
    {
        $resumen = $this->resumenDeNoviembre([
            $this->movimiento('2026-10-02', TipoMovimiento::Ingreso, 4000),
            $this->movimiento('2026-10-05', TipoMovimiento::Gasto, 2000),
            $this->movimiento('2026-11-02', TipoMovimiento::Ingreso, 5000),
            $this->movimiento('2026-11-05', TipoMovimiento::Gasto, 1500),
        ]);

        $this->assertSame('2026-10-01', $resumen['periodoAnterior']->inicio->toDateString());
        $this->assertSame(25.0, $resumen['ingresos']['variacion']);
        $this->assertSame(-25.0, $resumen['gastos']['variacion']);
        $this->assertSame(2000.0, $resumen['balance']['anterior']);
        $this->assertSame(1500.0, $resumen['balance']['diferencia']);
        $this->assertSame(75.0, $resumen['balance']['variacion']);
    }

    public function test_sin_cambios_respecto_al_periodo_anterior_la_variacion_es_cero(): void
    {
        $resumen = $this->resumenDeNoviembre([
            $this->movimiento('2026-10-02', TipoMovimiento::Ingreso, 3000),
            $this->movimiento('2026-11-02', TipoMovimiento::Ingreso, 3000),
        ]);

        $this->assertSame(0.0, $resumen['ingresos']['variacion']);
        $this->assertSame(0.0, $resumen['balance']['diferencia']);
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float|null}>
     */
    public static function variaciones(): array
    {
        return [
            'aumento' => [110, 100, 10.0],
            'disminución' => [90, 100, -10.0],
            'sin cambio' => [100, 100, 0.0],
            'redondeo a un decimal' => [200, 300, -33.3],
            'sin valor anterior: no hay base de comparación' => [50, 0, null],
            'ambos en cero' => [0, 0, 0.0],
            'balance negativo que mejora' => [-50, -100, 50.0],
            'balance negativo que empeora' => [-150, -100, -50.0],
        ];
    }

    #[DataProvider('variaciones')]
    public function test_calcula_la_variacion_porcentual(float $actual, float $anterior, ?float $esperada): void
    {
        $this->assertSame($esperada, ResumenFinanciero::variacionPorcentual($actual, $anterior));
    }

    public function test_la_evolucion_cubre_seis_meses_con_ceros_en_los_meses_sin_movimientos(): void
    {
        $resumen = $this->resumenDeNoviembre([
            $this->movimiento('2026-05-15', TipoMovimiento::Ingreso, 7777),
            $this->movimiento('2026-06-10', TipoMovimiento::Ingreso, 1000),
            $this->movimiento('2026-09-10', TipoMovimiento::Gasto, 400),
            $this->movimiento('2026-11-10', TipoMovimiento::Ingreso, 2000),
            $this->movimiento('2026-11-12', TipoMovimiento::Gasto, 500),
        ]);

        $this->assertSame([
            'etiquetas' => ['Jun 2026', 'Jul 2026', 'Ago 2026', 'Sep 2026', 'Oct 2026', 'Nov 2026'],
            'ingresos' => [1000.0, 0.0, 0.0, 0.0, 0.0, 2000.0],
            'gastos' => [0.0, 0.0, 0.0, 400.0, 0.0, 500.0],
        ], $resumen['evolucion']);
    }

    public function test_distribuye_solo_los_gastos_del_periodo_por_categoria_de_mayor_a_menor(): void
    {
        $resumen = $this->resumenDeNoviembre([
            $this->movimiento('2026-11-03', TipoMovimiento::Gasto, 300, 'Servicios'),
            $this->movimiento('2026-11-04', TipoMovimiento::Gasto, 1500, 'Alquiler'),
            $this->movimiento('2026-11-05', TipoMovimiento::Gasto, 200, 'Servicios'),
            $this->movimiento('2026-11-06', TipoMovimiento::Ingreso, 9000, 'Ventas'),
            $this->movimiento('2026-10-06', TipoMovimiento::Gasto, 9000, 'Planilla'),
        ]);

        $this->assertSame(['etiquetas' => ['Alquiler', 'Servicios'], 'valores' => [1500.0, 500.0]], $resumen['distribucionGastos']);
    }

    public function test_lista_los_movimientos_recientes_del_periodo_del_mas_nuevo_al_mas_antiguo(): void
    {
        $movimientos = [$this->movimiento('2026-10-31', TipoMovimiento::Gasto, 10)];

        for ($dia = 1; $dia <= 10; $dia++) {
            $movimientos[] = $this->movimiento(sprintf('2026-11-%02d', $dia), TipoMovimiento::Gasto, $dia);
        }

        $resumen = $this->resumenDeNoviembre($movimientos);

        $this->assertCount(ResumenFinanciero::MOVIMIENTOS_RECIENTES, $resumen['movimientos']);
        $this->assertSame('2026-11-10', $resumen['movimientos'][0]->fecha->toDateString());
        $this->assertSame('2026-11-03', $resumen['movimientos'][7]->fecha->toDateString());
    }
}
