<?php

namespace Tests\Feature\Finanzas;

use App\Services\Finanzas\DatosFinancierosSimulados;
use App\Services\Finanzas\Movimiento;
use App\Services\Finanzas\Periodo;
use App\Services\Finanzas\TipoMovimiento;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DatosFinancierosSimuladosTest extends TestCase
{
    public function test_se_identifican_como_datos_simulados(): void
    {
        $this->assertTrue((new DatosFinancierosSimulados(CarbonImmutable::parse('2026-11-18')))->esSimulada());
    }

    public function test_no_genera_movimientos_posteriores_a_hoy(): void
    {
        $hoy = CarbonImmutable::parse('2026-11-18 12:00:00');
        $datos = new DatosFinancierosSimulados($hoy);

        $movimientos = $datos->movimientosRecientes(Periodo::mesActual($hoy), 50);

        $this->assertNotEmpty($movimientos);
        foreach ($movimientos as $movimiento) {
            $this->assertTrue($movimiento->fecha->lessThanOrEqualTo($hoy), "Movimiento futuro: {$movimiento->fecha}");
        }
        $this->assertNotContains('Planilla del personal', array_map(fn (Movimiento $m) => $m->descripcion, $movimientos));
    }

    public function test_las_cifras_de_un_mes_no_cambian_entre_ejecuciones(): void
    {
        $octubre = new Periodo('2026-10-01', '2026-10-31');

        $primera = new DatosFinancierosSimulados(CarbonImmutable::parse('2026-11-18'));
        $segunda = new DatosFinancierosSimulados(CarbonImmutable::parse('2026-12-05'));

        $this->assertSame($primera->totalIngresos($octubre), $segunda->totalIngresos($octubre));
        $this->assertSame($primera->totalGastos($octubre), $segunda->totalGastos($octubre));
        $this->assertGreaterThan(0, $primera->totalIngresos($octubre));
        $this->assertGreaterThan(0, $primera->totalGastos($octubre));
    }

    public function test_los_movimientos_de_los_ultimos_dias_quedan_pendientes(): void
    {
        $hoy = CarbonImmutable::parse('2026-11-16 09:00:00');
        $datos = new DatosFinancierosSimulados($hoy);

        $estados = collect($datos->movimientosRecientes(Periodo::mesActual($hoy), 50))
            ->mapWithKeys(fn (Movimiento $m) => [$m->descripcion => $m->estado]);

        $this->assertSame('Pendiente', $estados['Publicidad en redes sociales']);
        $this->assertSame('Confirmado', $estados['Alquiler de oficina']);
    }

    public function test_incluye_ingresos_y_gastos_de_varias_categorias(): void
    {
        $datos = new DatosFinancierosSimulados(CarbonImmutable::parse('2026-11-30 23:00:00'));
        $noviembre = Periodo::mesActual(CarbonImmutable::parse('2026-11-30'));

        $tipos = collect($datos->movimientosRecientes($noviembre, 50))->map(fn (Movimiento $m) => $m->tipo)->unique();

        $this->assertEqualsCanonicalizing([TipoMovimiento::Ingreso, TipoMovimiento::Gasto], $tipos->values()->all());
        $this->assertGreaterThanOrEqual(4, count($datos->gastosPorCategoria($noviembre)));
    }
}
