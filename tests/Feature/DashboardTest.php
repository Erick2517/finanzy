<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\Movimiento;
use App\Services\Finanzas\MovimientosEnMemoria;
use App\Services\Finanzas\TipoMovimiento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');
    }

    private function movimiento(string $fecha, TipoMovimiento $tipo, float $monto, string $categoria = 'General', string $descripcion = 'Movimiento'): Movimiento
    {
        return new Movimiento(CarbonImmutable::parse($fecha), $descripcion, $categoria, $tipo, $monto, 'Confirmado');
    }

    /**
     * Sustituye los datos simulados por una lista conocida de movimientos.
     *
     * @param  list<Movimiento>  $movimientos
     */
    private function conMovimientos(array $movimientos): void
    {
        $this->app->instance(FuenteDatosFinancieros::class, new MovimientosEnMemoria($movimientos));
    }

    private function verPanel(string $url = '/dashboard'): TestResponse
    {
        return $this->actingAs(User::factory()->create())->get($url);
    }

    /**
     * HTML de la tarjeta KPI con el título indicado.
     */
    private function tarjeta(TestResponse $respuesta, string $titulo): string
    {
        $tarjetas = explode('<div class="col-12 col-md-6 col-xxl-3">', $respuesta->getContent());

        foreach ($tarjetas as $tarjeta) {
            if (str_contains($tarjeta, ">{$titulo}<")) {
                return $tarjeta;
            }
        }

        $this->fail("No se encontró la tarjeta «{$titulo}».");
    }

    /**
     * @return array<string, mixed>
     */
    private function especificacionDelGrafico(TestResponse $respuesta, string $id): array
    {
        preg_match('/<canvas id="'.$id.'"[^>]*data-chart="([^"]*)"/', $respuesta->getContent(), $coincidencia);

        return json_decode(html_entity_decode($coincidencia[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Octubre: ingresos 5,000 y gastos 2,000. Noviembre: ingresos 6,000 y gastos 2,500.
     *
     * @return list<Movimiento>
     */
    private function octubreYNoviembre(): array
    {
        return [
            $this->movimiento('2026-10-01', TipoMovimiento::Ingreso, 5000, 'Salario'),
            $this->movimiento('2026-10-10', TipoMovimiento::Gasto, 2000, 'Alquiler'),
            $this->movimiento('2026-11-01', TipoMovimiento::Ingreso, 6000, 'Salario', 'Pago de nómina'),
            $this->movimiento('2026-11-05', TipoMovimiento::Gasto, 1500, 'Alquiler', 'Alquiler de oficina'),
            $this->movimiento('2026-11-08', TipoMovimiento::Gasto, 1000, 'Insumos', 'Compra de insumos'),
        ];
    }

    public function test_muestra_los_indicadores_del_mes_actual_comparados_con_el_mes_anterior(): void
    {
        $this->conMovimientos($this->octubreYNoviembre());

        $respuesta = $this->verPanel();

        $respuesta->assertOk()
            ->assertSee('<title>Panel Financiero | Finanzy</title>', false)
            ->assertSee('Noviembre 2026');
        $this->assertStringContainsString('$6,000.00', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('+20.0%', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('vs. octubre 2026', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('$2,500.00', $this->tarjeta($respuesta, 'Total de gastos'));
        $this->assertStringContainsString('$3,500.00', $this->tarjeta($respuesta, 'Balance'));
        $this->assertStringContainsString('$500.00', $this->tarjeta($respuesta, 'Variación del balance'));
        $this->assertStringContainsString('+16.7%', $this->tarjeta($respuesta, 'Variación del balance'));
    }

    public function test_un_aumento_de_ingresos_es_favorable_y_un_aumento_de_gastos_desfavorable(): void
    {
        $this->conMovimientos($this->octubreYNoviembre());

        $respuesta = $this->verPanel();

        $ingresos = $this->tarjeta($respuesta, 'Total de ingresos');
        $gastos = $this->tarjeta($respuesta, 'Total de gastos');
        $this->assertStringContainsString('small text-success', $ingresos);
        $this->assertStringContainsString('(favorable)', $ingresos);
        $this->assertStringContainsString('+25.0%', $gastos);
        $this->assertStringContainsString('bi-arrow-up-right', $gastos);
        $this->assertStringContainsString('small text-danger', $gastos);
        $this->assertStringContainsString('(desfavorable)', $gastos);
    }

    public function test_una_disminucion_de_gastos_es_favorable_y_una_de_ingresos_desfavorable(): void
    {
        $this->conMovimientos([
            $this->movimiento('2026-10-01', TipoMovimiento::Ingreso, 5000),
            $this->movimiento('2026-10-10', TipoMovimiento::Gasto, 2000),
            $this->movimiento('2026-11-01', TipoMovimiento::Ingreso, 4000),
            $this->movimiento('2026-11-10', TipoMovimiento::Gasto, 1000),
        ]);

        $respuesta = $this->verPanel();

        $ingresos = $this->tarjeta($respuesta, 'Total de ingresos');
        $gastos = $this->tarjeta($respuesta, 'Total de gastos');
        $this->assertStringContainsString('-20.0%', $ingresos);
        $this->assertStringContainsString('small text-danger', $ingresos);
        $this->assertStringContainsString('-50.0%', $gastos);
        $this->assertStringContainsString('bi-arrow-down-right', $gastos);
        $this->assertStringContainsString('small text-success', $gastos);
        $this->assertStringContainsString('(favorable)', $gastos);
    }

    public function test_el_balance_negativo_se_muestra_en_rojo_con_signo(): void
    {
        $this->conMovimientos([
            $this->movimiento('2026-11-01', TipoMovimiento::Ingreso, 1000),
            $this->movimiento('2026-11-10', TipoMovimiento::Gasto, 1500),
        ]);

        $respuesta = $this->verPanel();

        $this->assertMatchesRegularExpression('/text-danger[^"]*">-\$500\.00</', $this->tarjeta($respuesta, 'Balance'));
    }

    public function test_sin_cambios_las_variaciones_se_muestran_neutras(): void
    {
        $this->conMovimientos([
            $this->movimiento('2026-10-01', TipoMovimiento::Ingreso, 3000),
            $this->movimiento('2026-10-10', TipoMovimiento::Gasto, 1000),
            $this->movimiento('2026-11-01', TipoMovimiento::Ingreso, 3000),
            $this->movimiento('2026-11-10', TipoMovimiento::Gasto, 1000),
        ]);

        $respuesta = $this->verPanel();

        foreach (['Total de ingresos', 'Total de gastos', 'Variación del balance'] as $titulo) {
            $tarjeta = $this->tarjeta($respuesta, $titulo);
            $this->assertStringContainsString('0.0%', $tarjeta);
            $this->assertStringContainsString('Sin variación', $tarjeta);
            $this->assertStringNotContainsString('bi-arrow-up-right', $tarjeta);
            $this->assertStringNotContainsString('bi-arrow-down-right', $tarjeta);
        }
    }

    public function test_los_graficos_reciben_la_evolucion_de_seis_meses_y_la_distribucion_de_gastos(): void
    {
        $this->conMovimientos($this->octubreYNoviembre());

        $respuesta = $this->verPanel();

        $evolucion = $this->especificacionDelGrafico($respuesta, 'graficoEvolucion');
        $this->assertSame('bar', $evolucion['type']);
        $this->assertSame('currency', $evolucion['format']);
        $this->assertSame(['Jun 2026', 'Jul 2026', 'Ago 2026', 'Sep 2026', 'Oct 2026', 'Nov 2026'], $evolucion['labels']);
        $this->assertSame(['label' => 'Ingresos', 'data' => [0, 0, 0, 0, 5000, 6000], 'color' => 'success'], $evolucion['datasets'][0]);
        $this->assertSame(['label' => 'Gastos', 'data' => [0, 0, 0, 0, 2000, 2500], 'color' => 'danger'], $evolucion['datasets'][1]);

        $distribucion = $this->especificacionDelGrafico($respuesta, 'graficoDistribucionGastos');
        $this->assertSame('doughnut', $distribucion['type']);
        $this->assertSame(['Alquiler', 'Insumos'], $distribucion['labels']);
        $this->assertSame([1500, 1000], $distribucion['datasets'][0]['data']);
    }

    public function test_lista_los_movimientos_recientes_sin_acciones_de_edicion(): void
    {
        $this->conMovimientos($this->octubreYNoviembre());

        $respuesta = $this->verPanel();

        $respuesta->assertSeeInOrder(['Movimientos recientes', '08/11/2026', 'Compra de insumos', 'Insumos', 'Gasto', '-$1,000.00', 'Confirmado', '01/11/2026', 'Pago de nómina', 'Ingreso', '$6,000.00'])
            ->assertDontSee('10/10/2026')
            ->assertDontSee('bi-pencil-square')
            ->assertDontSee('bi-trash');
    }

    public function test_el_contenido_de_los_movimientos_se_escapa(): void
    {
        $this->conMovimientos([$this->movimiento('2026-11-02', TipoMovimiento::Gasto, 10, '<b>Cat</b>', '<script>alert(1)</script>')]);

        $respuesta = $this->verPanel();

        $respuesta->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_sin_movimientos_muestra_estados_vacios(): void
    {
        $this->conMovimientos([]);

        $respuesta = $this->verPanel();

        $respuesta->assertOk()
            ->assertSee('No hay ingresos ni gastos en los últimos seis meses. Prueba con un periodo anterior.')
            ->assertSee('No hay gastos registrados en este periodo. Prueba con otro periodo.')
            ->assertSee('No hay movimientos registrados en este periodo. Elige otro periodo en el selector superior.');
        $this->assertStringContainsString('$0.00', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('Sin variación', $this->tarjeta($respuesta, 'Total de ingresos'));
    }

    public function test_filtra_por_el_periodo_indicado(): void
    {
        $this->conMovimientos([
            $this->movimiento('2026-09-01', TipoMovimiento::Ingreso, 4000),
            ...$this->octubreYNoviembre(),
        ]);

        $respuesta = $this->verPanel('/dashboard?fecha_inicio=2026-10-01&fecha_fin=2026-10-31');

        $respuesta->assertOk()
            ->assertSee('Octubre 2026')
            ->assertSee('value="2026-10-01"', false);
        $this->assertStringContainsString('$5,000.00', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('+25.0%', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('vs. septiembre 2026', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('$2,000.00', $this->tarjeta($respuesta, 'Total de gastos'));
    }

    public function test_sin_movimientos_en_el_periodo_anterior_no_muestra_una_variacion_sin_base(): void
    {
        $this->conMovimientos($this->octubreYNoviembre());

        $respuesta = $this->verPanel('/dashboard?fecha_inicio=2026-10-01&fecha_fin=2026-10-31');

        $this->assertStringContainsString('$5,000.00', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringNotContainsString('%', $this->tarjeta($respuesta, 'Total de ingresos'));
    }

    public function test_filtra_por_un_rango_personalizado(): void
    {
        $this->conMovimientos($this->octubreYNoviembre());

        $respuesta = $this->verPanel('/dashboard?fecha_inicio=2026-11-01&fecha_fin=2026-11-06');

        $respuesta->assertOk()->assertSee('1 nov 2026 – 6 nov 2026');
        $this->assertStringContainsString('$1,500.00', $this->tarjeta($respuesta, 'Total de gastos'));
    }

    public function test_rechaza_un_rango_de_fechas_invertido(): void
    {
        $this->conMovimientos([]);

        $respuesta = $this->verPanel('/dashboard?fecha_inicio=2026-11-30&fecha_fin=2026-11-01');

        $respuesta->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['fecha_fin' => 'El campo fecha de fin debe ser una fecha posterior o igual a fecha de inicio.']);
    }

    public function test_rechaza_un_rango_incompleto(): void
    {
        $this->conMovimientos([]);

        $respuesta = $this->verPanel('/dashboard?fecha_inicio=2026-11-01');

        $respuesta->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['fecha_fin' => 'El campo fecha de fin es obligatorio cuando se indica fecha de inicio.']);
    }

    public function test_rechaza_una_fecha_con_formato_invalido(): void
    {
        $this->conMovimientos([]);

        $respuesta = $this->verPanel('/dashboard?fecha_inicio=01/11/2026&fecha_fin=2026-11-30');

        $respuesta->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['fecha_inicio' => 'El campo fecha de inicio debe ser una fecha con el formato AAAA-MM-DD (por ejemplo, 2026-11-18).']);
    }

    public function test_el_mensaje_de_validacion_se_muestra_en_espanol_junto_al_campo(): void
    {
        $this->conMovimientos([]);
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)
            ->followingRedirects()
            ->get('/dashboard?fecha_inicio=2026-11-30&fecha_fin=2026-11-01');

        $respuesta->assertOk()
            ->assertSee('<div id="fecha_fin-error" class="invalid-feedback">El campo fecha de fin debe ser una fecha posterior o igual a fecha de inicio.</div>', false)
            ->assertDontSee('The fecha de fin field');
    }

    public function test_el_selector_de_periodo_ofrece_accesos_rapidos_y_marca_el_activo(): void
    {
        $this->conMovimientos([]);

        $respuesta = $this->verPanel();

        $respuesta->assertSee('href="'.e(route('dashboard', ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31'])).'"', false)
            ->assertSee('href="'.e(route('dashboard', ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31'])).'"', false);
        $this->assertMatchesRegularExpression('/class="dropdown-item active"[^>]*aria-current="true"\s*>\s*Este mes/', $respuesta->getContent());
    }

    public function test_advierte_cuando_los_datos_son_simulados(): void
    {
        $respuesta = $this->verPanel();

        $respuesta->assertOk()->assertSee('Datos de demostración.');
    }

    public function test_no_muestra_la_advertencia_con_datos_reales(): void
    {
        $this->conMovimientos([]);

        $respuesta = $this->verPanel();

        $respuesta->assertOk()->assertDontSee('Datos de demostración.');
    }
}
