<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Finanzas\FiltrosReporte;
use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\Movimiento;
use App\Services\Finanzas\MovimientosEnMemoria;
use App\Services\Finanzas\Periodo;
use App\Services\Finanzas\ReporteFinanciero;
use App\Services\Finanzas\TipoMovimiento;
use App\Services\Finanzas\TipoReporte;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');
    }

    private function movimiento(string $fecha, TipoMovimiento $tipo, float $monto, string $categoria, ?string $descripcion = null): Movimiento
    {
        return new Movimiento(CarbonImmutable::parse($fecha), $descripcion ?? "{$categoria} del {$fecha}", $categoria, $tipo, $monto, 'Confirmado');
    }

    /**
     * Entre junio y noviembre de 2026: ingresos 9,000 y gastos 3,000.
     *
     * @return list<Movimiento>
     */
    private function movimientosConocidos(): array
    {
        return [
            $this->movimiento('2025-06-01', TipoMovimiento::Ingreso, 2000, 'Ventas'),
            $this->movimiento('2026-10-03', TipoMovimiento::Ingreso, 4000, 'Salario'),
            $this->movimiento('2026-10-15', TipoMovimiento::Gasto, 1000, 'Insumos'),
            $this->movimiento('2026-11-02', TipoMovimiento::Ingreso, 5000, 'Salario'),
            $this->movimiento('2026-11-05', TipoMovimiento::Gasto, 1500, 'Alquiler'),
            $this->movimiento('2026-11-10', TipoMovimiento::Gasto, 500, 'Insumos'),
        ];
    }

    /**
     * @param  list<Movimiento>|null  $movimientos
     */
    private function conMovimientos(?array $movimientos = null): void
    {
        $this->app->instance(FuenteDatosFinancieros::class, new MovimientosEnMemoria($movimientos ?? $this->movimientosConocidos()));
    }

    private function verReporte(string $consulta = ''): TestResponse
    {
        return $this->actingAs(User::factory()->create())->get('/reportes'.$consulta);
    }

    private function tarjeta(TestResponse $respuesta, string $titulo): string
    {
        foreach (explode('<div class="col-12 col-md-6 col-xxl-3">', $respuesta->getContent()) as $tarjeta) {
            if (str_contains($tarjeta, ">{$titulo}<")) {
                return $tarjeta;
            }
        }

        $this->fail("No se encontró la tarjeta «{$titulo}».");
    }

    /**
     * @return array<string, mixed>
     */
    private function grafico(TestResponse $respuesta, string $id): array
    {
        preg_match('/<canvas id="'.$id.'"[^>]*data-chart="([^"]*)"/', $respuesta->getContent(), $coincidencia);
        $this->assertNotEmpty($coincidencia, "No se encontró el gráfico {$id}.");

        return json_decode(html_entity_decode($coincidencia[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $this->get('/reportes')->assertRedirect('/login');
        $this->get('/reportes/pdf')->assertRedirect('/login');
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function roles(): array
    {
        return ['administrador' => ['Administrador'], 'empleado' => ['Empleado'], 'sin rol' => [null]];
    }

    #[DataProvider('roles')]
    public function test_cualquier_usuario_autenticado_puede_consultar_los_reportes(?string $rol): void
    {
        $this->seed(RoleSeeder::class);
        $this->conMovimientos();
        $usuario = User::factory()->create();
        if ($rol) {
            $usuario->assignRole($rol);
        }

        $respuesta = $this->actingAs($usuario)->get('/reportes');

        $respuesta->assertOk()
            ->assertSee('<title>Reportes financieros | Finanzy</title>', false)
            ->assertSee('Reportes financieros');
    }

    public function test_por_defecto_muestra_el_reporte_mensual_de_los_ultimos_seis_meses(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte();

        $respuesta->assertOk()
            ->assertSee('input type="month" placeholder="AAAA-MM" id="fecha_inicio" name="fecha_inicio" value="2026-06"', false)
            ->assertSee('id="fecha_fin" name="fecha_fin" value="2026-11"', false)
            ->assertSee('<input type="hidden" name="tipo" value="mensual">', false)
            ->assertSee('Reporte mensual · 1 jun 2026 – 30 nov 2026', false)
            ->assertSee('Mes inicial');
        $this->assertMatchesRegularExpression('/class="nav-link active"[^>]*aria-current="page"\s*>\s*Mensual/', $respuesta->getContent());
    }

    public function test_calcula_los_indicadores_del_periodo(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte();

        $this->assertStringContainsString('$9,000.00', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('$3,000.00', $this->tarjeta($respuesta, 'Total de gastos'));
        $this->assertStringContainsString('$6,000.00', $this->tarjeta($respuesta, 'Resultado financiero'));
        $this->assertStringContainsString('33.3%', $this->tarjeta($respuesta, 'Gastos sobre ingresos'));
        $this->assertStringContainsString('De los ingresos del periodo', $this->tarjeta($respuesta, 'Gastos sobre ingresos'));
    }

    public function test_el_reporte_diario_usa_fechas_y_una_evolucion_por_dia(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=diario');

        $respuesta->assertOk()
            ->assertSee('<input type="date" id="fecha_inicio" name="fecha_inicio" value="2026-11-01"', false)
            ->assertSee('value="2026-11-18"', false)
            ->assertSee('Fecha inicial');
        $evolucion = $this->grafico($respuesta, 'graficoEvolucionReporte');
        $this->assertSame('line', $evolucion['type']);
        $this->assertCount(18, $evolucion['labels']);
        $this->assertSame('01/11', $evolucion['labels'][0]);
        $this->assertStringContainsString('$5,000.00', $this->tarjeta($respuesta, 'Total de ingresos'));
    }

    public function test_el_reporte_anual_usa_anios_y_una_evolucion_por_anio(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=anual');

        $respuesta->assertOk()
            ->assertSee('type="number" min="2000" max="2100" step="1" inputmode="numeric" placeholder="AAAA" id="fecha_inicio" name="fecha_inicio" value="2024"', false)
            ->assertSee('Año inicial');
        $evolucion = $this->grafico($respuesta, 'graficoEvolucionReporte');
        $this->assertSame('bar', $evolucion['type']);
        $this->assertSame(['2024', '2025', '2026'], $evolucion['labels']);
        $this->assertSame([0, 2000, 9000], $evolucion['datasets'][0]['data']);
    }

    public function test_aplica_el_periodo_indicado(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=mensual&fecha_inicio=2026-10&fecha_fin=2026-10');

        $respuesta->assertOk()->assertSee('Reporte mensual · Octubre 2026', false);
        $this->assertStringContainsString('$4,000.00', $this->tarjeta($respuesta, 'Total de ingresos'));
        $this->assertStringContainsString('$1,000.00', $this->tarjeta($respuesta, 'Total de gastos'));
        $this->assertStringContainsString('25.0%', $this->tarjeta($respuesta, 'Gastos sobre ingresos'));
    }

    public function test_con_un_solo_mes_no_muestra_la_evolucion(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=mensual&fecha_inicio=2026-10&fecha_fin=2026-10');

        $respuesta->assertDontSee('graficoEvolucionReporte');
        $this->grafico($respuesta, 'graficoComparacionReporte');
    }

    public function test_los_graficos_comparan_ingresos_y_gastos_y_distribuyen_los_gastos(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte();

        $comparacion = $this->grafico($respuesta, 'graficoComparacionReporte');
        $this->assertSame(['label' => 'Ingresos', 'data' => [9000], 'color' => 'success'], $comparacion['datasets'][0]);
        $this->assertSame(['label' => 'Gastos', 'data' => [3000], 'color' => 'danger'], $comparacion['datasets'][1]);

        $distribucion = $this->grafico($respuesta, 'graficoDistribucionReporte');
        $this->assertSame('doughnut', $distribucion['type']);
        $this->assertSame(['Insumos', 'Alquiler'], $distribucion['labels']);
    }

    public function test_filtra_por_categoria(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=mensual&categoria_id=insumos');

        $respuesta->assertOk()
            ->assertSee('Categoría: Insumos')
            ->assertSee('<option value="insumos" selected>Insumos</option>', false)
            ->assertSee('Insumos del 2026-10-15')
            ->assertDontSee('Alquiler del 2026-11-05');
        $this->assertStringContainsString('$1,500.00', $this->tarjeta($respuesta, 'Total de gastos'));
    }

    public function test_sin_ingresos_el_porcentaje_de_gastos_no_se_calcula(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=mensual&categoria_id=insumos');

        $tarjeta = $this->tarjeta($respuesta, 'Gastos sobre ingresos');
        $this->assertStringContainsString('No disponible', $tarjeta);
        $this->assertStringContainsString('Sin ingresos en el periodo', $tarjeta);
        $this->assertStringNotContainsString('%</span>', $tarjeta);
    }

    public function test_cambiar_de_tipo_conserva_la_categoria(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?categoria_id=insumos');

        $respuesta->assertSee('href="'.e(route('reportes.index', ['tipo' => 'diario', 'categoria_id' => 'insumos'])).'"', false)
            ->assertSee('href="'.e(route('reportes.index', ['tipo' => 'anual', 'categoria_id' => 'insumos'])).'"', false);
    }

    public function test_limpiar_filtros_vuelve_al_tipo_actual_sin_filtros(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=diario&fecha_inicio=2026-11-02&fecha_fin=2026-11-05&categoria_id=insumos');

        $respuesta->assertSee('<a href="'.route('reportes.index', ['tipo' => 'diario']).'" class="btn btn-outline-secondary" title="Limpiar filtros"', false);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function filtrosInvalidos(): array
    {
        return [
            'fechas invertidas' => ['diario', '?tipo=diario&fecha_inicio=2026-11-10&fecha_fin=2026-11-01', 'fecha_fin', 'El campo fecha final debe ser una fecha posterior o igual a fecha inicial.'],
            'mes con formato inválido' => ['mensual', '?tipo=mensual&fecha_inicio=11/2026&fecha_fin=2026-11', 'fecha_inicio', 'El campo mes inicial debe usar el formato AAAA-MM (por ejemplo, 2026-11).'],
            'fecha con formato inválido' => ['diario', '?tipo=diario&fecha_inicio=01/11/2026&fecha_fin=2026-11-05', 'fecha_inicio', 'El campo fecha inicial debe ser una fecha con el formato AAAA-MM-DD (por ejemplo, 2026-11-18).'],
            'año que no es un número' => ['anual', '?tipo=anual&fecha_inicio=dos+mil&fecha_fin=2026', 'fecha_inicio', 'El campo año inicial debe ser un año de cuatro cifras (por ejemplo, 2026).'],
            'año fuera de rango' => ['anual', '?tipo=anual&fecha_inicio=1999&fecha_fin=2026', 'fecha_inicio', 'El campo año inicial debe estar entre 2000 y 2100.'],
            'años invertidos' => ['anual', '?tipo=anual&fecha_inicio=2026&fecha_fin=2024', 'fecha_fin', 'El campo año final debe ser mayor o igual que 2026.'],
            'rango incompleto' => ['mensual', '?tipo=mensual&fecha_inicio=2026-01', 'fecha_fin', 'El campo mes final es obligatorio cuando se indica mes inicial.'],
            'rango diario excesivo' => ['diario', '?tipo=diario&fecha_inicio=2026-01-01&fecha_fin=2026-06-30', 'fecha_fin', 'El reporte diario no puede abarcar más de 93 días.'],
            'rango anual excesivo' => ['anual', '?tipo=anual&fecha_inicio=2010&fecha_fin=2026', 'fecha_fin', 'El reporte anual no puede abarcar más de 10 años.'],
            'categoría inexistente' => ['mensual', '?tipo=mensual&categoria_id=no-existe', 'categoria_id', 'El valor seleccionado en categoría no es válido.'],
        ];
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_rechaza_filtros_invalidos_y_vuelve_al_mismo_tipo(string $tipo, string $consulta, string $campo, string $mensaje): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte($consulta);

        $respuesta->assertRedirect(route('reportes.index', ['tipo' => $tipo]))
            ->assertSessionHasErrors([$campo => $mensaje]);
    }

    public function test_rechaza_un_tipo_de_reporte_desconocido(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=semanal');

        $respuesta->assertRedirect(route('reportes.index'))
            ->assertSessionHasErrors(['tipo' => 'El valor seleccionado en tipo de reporte no es válido.']);
    }

    public function test_tras_un_error_muestra_el_mensaje_y_lo_escrito_por_el_usuario(): void
    {
        $this->conMovimientos();

        $respuesta = $this->actingAs(User::factory()->create())
            ->followingRedirects()
            ->get('/reportes?tipo=diario&fecha_inicio=2026-11-10&fecha_fin=2026-11-01');

        $respuesta->assertOk()
            ->assertSee('El campo fecha final debe ser una fecha posterior o igual a fecha inicial.')
            ->assertSee('value="2026-11-10"', false);
    }

    public function test_sin_movimientos_muestra_estados_vacios(): void
    {
        $this->conMovimientos([]);

        $respuesta = $this->verReporte();

        $respuesta->assertOk()
            ->assertSee('No hay ingresos ni gastos en este periodo. Prueba con otro periodo o categoría.')
            ->assertSee('No hay gastos en este periodo. Prueba con otro periodo o categoría.')
            ->assertSee('No hay movimientos que coincidan con los filtros. Prueba con otro periodo o categoría, o usa «Limpiar filtros».')
            ->assertDontSee('Mostrando');
        $this->assertStringContainsString('$0.00', $this->tarjeta($respuesta, 'Resultado financiero'));
        $this->assertStringContainsString('No disponible', $this->tarjeta($respuesta, 'Gastos sobre ingresos'));
    }

    public function test_el_detalle_esta_paginado_y_en_orden_cronologico(): void
    {
        $movimientos = [];
        for ($dia = 1; $dia <= 20; $dia++) {
            $movimientos[] = $this->movimiento(sprintf('2026-11-%02d', min($dia, 18)), TipoMovimiento::Gasto, $dia, 'Insumos', "Compra {$dia}");
        }
        $this->conMovimientos($movimientos);

        $respuesta = $this->verReporte('?tipo=diario');

        $respuesta->assertSeeText('Mostrando 1 a 15 de 20 movimientos')
            ->assertSeeInOrder(['Detalle de movimientos', '01/11/2026', 'Compra 1', '02/11/2026', 'Compra 2'])
            ->assertSee('href="'.e(route('reportes.index', ['tipo' => 'diario', 'page' => 2])).'"', false)
            ->assertDontSee('-$16.00');
    }

    public function test_el_contenido_de_los_movimientos_se_escapa(): void
    {
        $this->conMovimientos([$this->movimiento('2026-11-02', TipoMovimiento::Gasto, 10, 'Insumos', '<script>alert(1)</script>')]);

        $respuesta = $this->verReporte();

        $respuesta->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_la_exportacion_pdf_reutiliza_los_filtros_y_excel_aparece_deshabilitado(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte('?tipo=diario&fecha_inicio=2026-11-02&fecha_fin=2026-11-05&categoria_id=insumos');

        $respuesta->assertSee('href="'.e(route('reportes.pdf', [
            'tipo' => 'diario', 'fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-11-05', 'categoria_id' => 'insumos',
        ])).'"', false);
        $this->assertMatchesRegularExpression('/<button type="button" class="btn btn-outline-secondary" disabled aria-disabled="true"[^>]*>\s*<i class="bi bi-file-earmark-spreadsheet"/', $respuesta->getContent());
    }

    public function test_generar_y_exportar_muestran_un_indicador_de_carga(): void
    {
        $this->conMovimientos();

        $respuesta = $this->verReporte();

        $respuesta->assertSee('class="btn btn-outline-primary" data-loading-text="Generando PDF…"', false);
        $this->assertMatchesRegularExpression('/<button type="submit" class="btn btn-primary flex-grow-1"\s+data-loading-text="Generando reporte…"/', $respuesta->getContent());
    }

    public function test_descarga_el_reporte_en_pdf(): void
    {
        $this->conMovimientos();

        $respuesta = $this->actingAs(User::factory()->create())->get('/reportes/pdf');

        $respuesta->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('reporte-mensual-2026-06-a-2026-11.pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    /**
     * Texto de las páginas del PDF: descomprime los flujos de contenido de DomPDF.
     */
    private function contenidoDelPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $flujos);

        return implode("\n", array_map(fn (string $flujo) => @gzuncompress($flujo) ?: $flujo, $flujos[1]));
    }

    public function test_cada_pagina_del_pdf_muestra_su_numero_y_el_total_de_paginas(): void
    {
        $movimientos = [];
        for ($indice = 0; $indice < 120; $indice++) {
            $movimientos[] = $this->movimiento(
                CarbonImmutable::parse('2026-06-01')->addDays($indice)->toDateString(),
                TipoMovimiento::Gasto,
                100 + $indice,
                'Insumos',
            );
        }
        $this->conMovimientos($movimientos);

        $respuesta = $this->actingAs(User::factory()->create())->get('/reportes/pdf');

        $pdf = $respuesta->assertOk()->getContent();
        $totalDePaginas = preg_match_all('#/Type\s*/Page[^s]#', $pdf);
        $contenido = $this->contenidoDelPdf($pdf);
        $enPdf = fn (string $texto) => mb_convert_encoding($texto, 'UTF-16BE', 'UTF-8');

        $this->assertGreaterThanOrEqual(3, $totalDePaginas);
        for ($pagina = 1; $pagina <= $totalDePaginas; $pagina++) {
            $this->assertSame(1, substr_count($contenido, $enPdf("Página {$pagina} de {$totalDePaginas}")), "Falta la numeración de la página {$pagina}.");
        }
        $this->assertStringNotContainsString($enPdf(' de 0'), $contenido);
    }

    public function test_el_nombre_del_pdf_refleja_los_filtros(): void
    {
        $this->conMovimientos();

        $respuesta = $this->actingAs(User::factory()->create())->get('/reportes/pdf?tipo=diario&fecha_inicio=2026-11-02&fecha_fin=2026-11-05&categoria_id=insumos');

        $respuesta->assertOk()->assertDownload('reporte-diario-2026-11-02-a-2026-11-05-insumos.pdf');
    }

    public function test_el_pdf_no_se_genera_con_filtros_invalidos(): void
    {
        $this->conMovimientos();

        $respuesta = $this->actingAs(User::factory()->create())->get('/reportes/pdf?tipo=anual&fecha_inicio=2026&fecha_fin=2020');

        $respuesta->assertRedirect(route('reportes.index', ['tipo' => 'anual']))
            ->assertSessionHasErrors('fecha_fin');
    }

    public function test_la_plantilla_pdf_contiene_los_indicadores_la_distribucion_y_el_detalle(): void
    {
        $servicio = new ReporteFinanciero(new MovimientosEnMemoria($this->movimientosConocidos()));
        $filtros = new FiltrosReporte(TipoReporte::Mensual, new Periodo('2026-10-01', '2026-11-30'));

        $vista = $this->view('reportes.pdf', ['reporte' => $servicio->generar($filtros), 'generadoEl' => now()]);

        $vista->assertSee('Reporte financiero mensual')
            ->assertSee('Periodo:')
            ->assertSee('1 oct 2026 – 30 nov 2026')
            ->assertSee('Categoría:')
            ->assertSee('Todas')
            ->assertSeeInOrder(['Total de ingresos', '$9,000.00', 'Total de gastos', '$3,000.00', 'Resultado financiero', '$6,000.00', 'Gastos sobre ingresos', '33.3%'])
            ->assertSeeInOrder(['Evolución mensual', 'Oct 2026', 'Nov 2026'])
            ->assertSeeInOrder(['Distribución de gastos por categoría', 'Insumos', '$1,500.00', '50.0%'])
            ->assertSeeInOrder(['Detalle de movimientos (5)', '03/10/2026', 'Salario del 2026-10-03', '$4,000.00'])
            ->assertSee('Generado el 18/11/2026 10:00')
            ->assertDontSee('Datos de demostración');
    }
}
