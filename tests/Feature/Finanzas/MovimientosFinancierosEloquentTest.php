<?php

namespace Tests\Feature\Finanzas;

use App\Models\Categoria;
use App\Models\MovimientoFinanciero;
use App\Models\User;
use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\MovimientosFinancierosEloquent;
use App\Services\Finanzas\Periodo;
use App\Services\Finanzas\TipoMovimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovimientosFinancierosEloquentTest extends TestCase
{
    use RefreshDatabase;

    private function categoria(User $usuario, string $nombre, string $tipo): Categoria
    {
        return Categoria::create(['user_id' => $usuario->id, 'nombre' => $nombre, 'tipo' => $tipo]);
    }

    private function movimiento(Categoria $categoria, string $fecha, float $monto, string $concepto = 'Movimiento'): MovimientoFinanciero
    {
        return MovimientoFinanciero::create([
            'user_id' => $categoria->user_id,
            'categoria_id' => $categoria->id,
            'monto' => $monto,
            'fecha' => $fecha,
            'concepto' => $concepto,
        ]);
    }

    private function fuenteDe(User $usuario): FuenteDatosFinancieros
    {
        $this->actingAs($usuario);

        return $this->app->make(FuenteDatosFinancieros::class);
    }

    private function noviembre(): Periodo
    {
        return new Periodo('2026-11-01', '2026-11-30');
    }

    public function test_la_aplicacion_usa_los_datos_reales_por_defecto(): void
    {
        $fuente = $this->fuenteDe(User::factory()->create());

        $this->assertInstanceOf(MovimientosFinancierosEloquent::class, $fuente);
        $this->assertFalse($fuente->esSimulada());
    }

    public function test_el_tipo_de_cada_movimiento_es_el_de_su_categoria(): void
    {
        $usuario = User::factory()->create();
        $this->movimiento($this->categoria($usuario, 'Salario', 'ingreso'), '2026-11-02', 5000);
        $this->movimiento($this->categoria($usuario, 'Alquiler', 'gasto'), '2026-11-03', 1500.25);

        $fuente = $this->fuenteDe($usuario);

        $this->assertSame(5000.0, $fuente->totalIngresos($this->noviembre()));
        $this->assertSame(1500.25, $fuente->totalGastos($this->noviembre()));
        $this->assertSame(['Alquiler' => 1500.25], $fuente->gastosPorCategoria($this->noviembre()));
    }

    public function test_incluye_el_primer_y_el_ultimo_dia_del_periodo_y_excluye_los_demas(): void
    {
        $usuario = User::factory()->create();
        $gastos = $this->categoria($usuario, 'Insumos', 'gasto');
        $this->movimiento($gastos, '2026-10-31', 1);
        $this->movimiento($gastos, '2026-11-01', 10);
        $this->movimiento($gastos, '2026-11-30', 100);
        $this->movimiento($gastos, '2026-12-01', 1000);

        $this->assertSame(110.0, $this->fuenteDe($usuario)->totalGastos($this->noviembre()));
    }

    public function test_solo_devuelve_los_datos_del_usuario_autenticado(): void
    {
        $propietario = User::factory()->create();
        $otro = User::factory()->create();
        $this->movimiento($this->categoria($propietario, 'Salario', 'ingreso'), '2026-11-02', 5000, 'Nómina privada');

        $fuente = $this->fuenteDe($otro);

        $this->assertSame(0.0, $fuente->totalIngresos($this->noviembre()));
        $this->assertSame([], $fuente->movimientos($this->noviembre()));
        $this->assertSame([], $fuente->categorias());
    }

    public function test_sin_sesion_no_devuelve_datos(): void
    {
        $usuario = User::factory()->create();
        $this->movimiento($this->categoria($usuario, 'Salario', 'ingreso'), '2026-11-02', 5000);

        $fuente = $this->app->make(FuenteDatosFinancieros::class);

        $this->assertSame([], $fuente->movimientos($this->noviembre()));
        $this->assertSame([], $fuente->categorias());
    }

    public function test_convierte_cada_registro_en_un_movimiento_del_panel(): void
    {
        $usuario = User::factory()->create();
        $this->movimiento($this->categoria($usuario, 'Ventas', 'ingreso'), '2026-11-02', 300, 'Venta al contado');

        [$movimiento] = $this->fuenteDe($usuario)->movimientos($this->noviembre());

        $this->assertSame('2026-11-02', $movimiento->fecha->toDateString());
        $this->assertSame('Venta al contado', $movimiento->descripcion);
        $this->assertSame('Ventas', $movimiento->categoria);
        $this->assertSame(TipoMovimiento::Ingreso, $movimiento->tipo);
        $this->assertSame(300.0, $movimiento->monto);
        $this->assertSame('Confirmado', $movimiento->estado);
    }

    public function test_filtra_por_categoria_y_ordena_del_mas_reciente_al_mas_antiguo(): void
    {
        $usuario = User::factory()->create();
        $insumos = $this->categoria($usuario, 'Insumos', 'gasto');
        $this->movimiento($insumos, '2026-11-03', 10, 'Primero');
        $this->movimiento($insumos, '2026-11-09', 20, 'Segundo');
        $this->movimiento($this->categoria($usuario, 'Alquiler', 'gasto'), '2026-11-05', 1500);

        $movimientos = $this->fuenteDe($usuario)->movimientos($this->noviembre(), (string) $insumos->id);

        $this->assertSame(['Segundo', 'Primero'], array_map(fn ($movimiento) => $movimiento->descripcion, $movimientos));
    }

    public function test_las_categorias_se_identifican_por_su_clave_y_distinguen_nombres_repetidos(): void
    {
        $usuario = User::factory()->create();
        $otrosIngresos = $this->categoria($usuario, 'Otros', 'ingreso');
        $otrosGastos = $this->categoria($usuario, 'Otros', 'gasto');
        $alquiler = $this->categoria($usuario, 'Alquiler', 'gasto');

        $this->assertSame([
            (string) $alquiler->id => 'Alquiler',
            (string) $otrosGastos->id => 'Otros (Gasto)',
            (string) $otrosIngresos->id => 'Otros (Ingreso)',
        ], $this->fuenteDe($usuario)->categorias());
    }
}
