<?php

namespace Tests\Feature\Finanzas;

use App\Services\Finanzas\FiltrosReporte;
use App\Services\Finanzas\Movimiento;
use App\Services\Finanzas\MovimientosEnMemoria;
use App\Services\Finanzas\Periodo;
use App\Services\Finanzas\ReporteFinanciero;
use App\Services\Finanzas\TipoMovimiento;
use App\Services\Finanzas\TipoReporte;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReporteFinancieroTest extends TestCase
{
    private function movimiento(string $fecha, TipoMovimiento $tipo, float $monto, string $categoria): Movimiento
    {
        return new Movimiento(CarbonImmutable::parse($fecha), "{$categoria} {$fecha}", $categoria, $tipo, $monto, 'Confirmado');
    }

    /**
     * @return list<Movimiento>
     */
    private function movimientos(): array
    {
        return [
            $this->movimiento('2026-09-30', TipoMovimiento::Ingreso, 9999, 'Ventas'),
            $this->movimiento('2026-10-03', TipoMovimiento::Ingreso, 4000, 'Salario'),
            $this->movimiento('2026-10-15', TipoMovimiento::Gasto, 1000, 'Insumos'),
            $this->movimiento('2026-11-02', TipoMovimiento::Ingreso, 5000, 'Salario'),
            $this->movimiento('2026-11-05', TipoMovimiento::Gasto, 1500, 'Alquiler'),
            $this->movimiento('2026-11-10', TipoMovimiento::Gasto, 500, 'Insumos'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reporte(TipoReporte $tipo, string $inicio, string $fin, ?string $categoria = null): array
    {
        $servicio = new ReporteFinanciero(new MovimientosEnMemoria($this->movimientos()));

        return $servicio->generar(new FiltrosReporte($tipo, new Periodo($inicio, $fin), $categoria));
    }

    public function test_calcula_ingresos_gastos_resultado_y_porcentaje_de_gastos(): void
    {
        $reporte = $this->reporte(TipoReporte::Mensual, '2026-10-01', '2026-11-30');

        $this->assertSame(9000.0, $reporte['ingresos']);
        $this->assertSame(3000.0, $reporte['gastos']);
        $this->assertSame(6000.0, $reporte['resultado']);
        $this->assertSame(33.3, $reporte['porcentajeGastos']);
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float|null}>
     */
    public static function formulas(): array
    {
        return [
            'resultado positivo' => [9000, 3000, 6000.0, 33.3],
            'gastos mayores que los ingresos' => [1000, 1500, -500.0, 150.0],
            'sin ingresos: el porcentaje no se calcula' => [0, 200, -200.0, null],
            'sin movimientos' => [0, 0, 0.0, null],
        ];
    }

    #[DataProvider('formulas')]
    public function test_aplica_las_formulas_del_resultado_y_del_porcentaje(float $ingresos, float $gastos, float $resultado, ?float $porcentaje): void
    {
        $this->assertSame($resultado, ReporteFinanciero::resultadoFinanciero($ingresos, $gastos));
        $this->assertSame($porcentaje, ReporteFinanciero::porcentajeDeGastos($ingresos, $gastos));
    }

    public function test_filtra_todos_los_calculos_por_categoria(): void
    {
        $reporte = $this->reporte(TipoReporte::Mensual, '2026-10-01', '2026-11-30', 'insumos');

        $this->assertSame(0.0, $reporte['ingresos']);
        $this->assertSame(1500.0, $reporte['gastos']);
        $this->assertNull($reporte['porcentajeGastos']);
        $this->assertSame(['Insumos'], $reporte['distribucionGastos']['etiquetas']);
        $this->assertSame(['Insumos', 'Insumos'], array_map(fn (Movimiento $m) => $m->categoria, $reporte['movimientos']));
    }

    public function test_la_distribucion_incluye_el_porcentaje_de_cada_categoria(): void
    {
        $reporte = $this->reporte(TipoReporte::Mensual, '2026-10-01', '2026-11-30');

        $this->assertSame([
            'etiquetas' => ['Insumos', 'Alquiler'],
            'valores' => [1500.0, 1500.0],
            'porcentajes' => [50.0, 50.0],
        ], $reporte['distribucionGastos']);
    }

    public function test_la_evolucion_mensual_incluye_meses_sin_movimientos_y_el_resultado(): void
    {
        $reporte = $this->reporte(TipoReporte::Mensual, '2026-09-01', '2026-12-31');

        $this->assertSame([
            'etiquetas' => ['Sep 2026', 'Oct 2026', 'Nov 2026', 'Dic 2026'],
            'ingresos' => [9999.0, 4000.0, 5000.0, 0.0],
            'gastos' => [0.0, 1000.0, 2000.0, 0.0],
            'resultados' => [9999.0, 3000.0, 3000.0, 0.0],
        ], $reporte['evolucion']);
    }

    public function test_la_evolucion_diaria_agrupa_por_dia(): void
    {
        $reporte = $this->reporte(TipoReporte::Diario, '2026-11-01', '2026-11-05');

        $this->assertSame(['01/11', '02/11', '03/11', '04/11', '05/11'], $reporte['evolucion']['etiquetas']);
        $this->assertSame([0.0, 5000.0, 0.0, 0.0, 0.0], $reporte['evolucion']['ingresos']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 1500.0], $reporte['evolucion']['gastos']);
    }

    public function test_la_evolucion_anual_agrupa_por_anio(): void
    {
        $reporte = $this->reporte(TipoReporte::Anual, '2025-01-01', '2026-12-31');

        $this->assertSame(['2025', '2026'], $reporte['evolucion']['etiquetas']);
        $this->assertSame([0.0, 18999.0], $reporte['evolucion']['ingresos']);
    }

    public function test_con_un_solo_grupo_no_hay_evolucion(): void
    {
        $reporte = $this->reporte(TipoReporte::Mensual, '2026-11-01', '2026-11-30');

        $this->assertNull($reporte['evolucion']);
    }

    public function test_el_detalle_va_en_orden_cronologico(): void
    {
        $reporte = $this->reporte(TipoReporte::Mensual, '2026-10-01', '2026-11-30');

        $this->assertSame(
            ['2026-10-03', '2026-10-15', '2026-11-02', '2026-11-05', '2026-11-10'],
            array_map(fn (Movimiento $m) => $m->fecha->toDateString(), $reporte['movimientos']),
        );
    }

    public function test_las_categorias_disponibles_se_ordenan_por_nombre(): void
    {
        $fuente = new MovimientosEnMemoria([
            $this->movimiento('2026-11-01', TipoMovimiento::Gasto, 1, 'Servicios básicos'),
            $this->movimiento('2026-11-02', TipoMovimiento::Gasto, 1, 'Alquiler'),
            $this->movimiento('2026-11-03', TipoMovimiento::Gasto, 1, 'Servicios básicos'),
        ]);

        $this->assertSame(['alquiler' => 'Alquiler', 'servicios-basicos' => 'Servicios básicos'], $fuente->categorias());
    }
}
