<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\MovimientoFinanciero;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integración de las categorías y movimientos (Kevin Ramírez) con el panel, los
 * reportes y las exportaciones (Víctor Meléndez), con datos reales de la base de datos.
 */
class IntegracionFinanzasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Categoria $salario;

    private Categoria $alquiler;

    private Categoria $insumos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');

        $this->usuario = User::factory()->create();
        $this->salario = $this->categoria($this->usuario, 'Salario', 'ingreso');
        $this->alquiler = $this->categoria($this->usuario, 'Alquiler', 'gasto');
        $this->insumos = $this->categoria($this->usuario, 'Insumos', 'gasto');

        $this->movimiento($this->salario, '2026-11-02', 5000, 'Nómina de noviembre');
        $this->movimiento($this->alquiler, '2026-11-05', 1500, 'Renta de oficina');
        $this->movimiento($this->insumos, '2026-11-06', 10, '=HYPERLINK("http://ejemplo.test")');
        $this->movimiento($this->insumos, '2026-10-10', 250.50, 'Compra de papel');
    }

    private function categoria(User $usuario, string $nombre, string $tipo): Categoria
    {
        return Categoria::create(['user_id' => $usuario->id, 'nombre' => $nombre, 'tipo' => $tipo]);
    }

    private function movimiento(Categoria $categoria, string $fecha, float $monto, string $concepto): void
    {
        MovimientoFinanciero::create([
            'user_id' => $categoria->user_id,
            'categoria_id' => $categoria->id,
            'monto' => $monto,
            'fecha' => $fecha,
            'concepto' => $concepto,
        ]);
    }

    /**
     * @return list<string>
     */
    private function lineasDelCsv(string $consulta, ?User $usuario = null): array
    {
        $respuesta = $this->actingAs($usuario ?? $this->usuario)->get('/reportes/csv'.$consulta);
        $respuesta->assertOk();

        return explode("\n", trim($respuesta->streamedContent()));
    }

    public function test_el_panel_muestra_los_datos_reales_del_usuario(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get('/dashboard');

        $respuesta->assertOk()
            ->assertDontSee('Datos de demostración.')
            ->assertSee('$5,000.00')
            ->assertSee('$1,510.00')
            ->assertSee('$3,490.00')
            ->assertSee('30.2%')
            ->assertSee('Nómina de noviembre');
    }

    public function test_un_usuario_no_ve_los_datos_de_otro_en_el_panel_ni_en_los_reportes(): void
    {
        $otro = User::factory()->create();
        $this->movimiento($this->categoria($otro, 'Honorarios', 'ingreso'), '2026-11-03', 777, 'Ingreso del otro usuario');

        $this->actingAs($otro)->get('/dashboard')
            ->assertOk()
            ->assertSee('$777.00')
            ->assertDontSee('Nómina de noviembre')
            ->assertDontSee('$5,000.00');

        $this->actingAs($otro)->get('/reportes?tipo=anual&fecha_inicio=2026&fecha_fin=2026')
            ->assertOk()
            ->assertSee('Ingreso del otro usuario')
            ->assertDontSee('Renta de oficina')
            ->assertDontSee('Alquiler');

        $csv = implode("\n", $this->lineasDelCsv('?tipo=anual&fecha_inicio=2026&fecha_fin=2026', $otro));
        $this->assertStringNotContainsString('Renta de oficina', $csv);
    }

    public function test_un_usuario_no_puede_filtrar_por_la_categoria_de_otro(): void
    {
        $otro = User::factory()->create();
        $consulta = '?categoria_id='.$this->alquiler->id;

        $this->actingAs($otro)->get('/reportes'.$consulta)->assertSessionHasErrors('categoria_id');
        $this->actingAs($otro)->get('/reportes/csv'.$consulta)->assertSessionHasErrors('categoria_id');
        $this->actingAs($otro)->get('/reportes/pdf'.$consulta)->assertSessionHasErrors('categoria_id');
    }

    public function test_los_reportes_diario_mensual_y_anual_usan_los_datos_reales(): void
    {
        $this->actingAs($this->usuario);

        $this->get('/reportes?tipo=diario&fecha_inicio=2026-11-01&fecha_fin=2026-11-30')
            ->assertOk()
            ->assertSee('Renta de oficina')
            ->assertDontSee('Compra de papel')
            ->assertSee('$1,510.00');

        $this->get('/reportes?tipo=mensual&fecha_inicio=2026-10&fecha_fin=2026-11')
            ->assertOk()
            ->assertSee('Compra de papel')
            ->assertSee('$1,760.50');

        $this->get('/reportes?tipo=anual&fecha_inicio=2026&fecha_fin=2026')
            ->assertOk()
            ->assertSee('Compra de papel')
            ->assertSee('$3,239.50');
    }

    public function test_el_reporte_filtra_por_categoria_y_por_tipo_de_movimiento(): void
    {
        $this->actingAs($this->usuario);

        $this->get('/reportes?tipo=mensual&fecha_inicio=2026-10&fecha_fin=2026-11&categoria_id='.$this->insumos->id)
            ->assertOk()
            ->assertSee('Categoría: Insumos')
            ->assertSee('Compra de papel')
            ->assertDontSee('Renta de oficina');

        $this->get('/reportes?tipo=mensual&fecha_inicio=2026-10&fecha_fin=2026-11&tipo_movimiento=gasto')
            ->assertOk()
            ->assertSee('Movimientos: Solo gastos')
            ->assertSee('Renta de oficina')
            ->assertDontSee('Nómina de noviembre');
    }

    public function test_el_csv_exporta_exactamente_lo_filtrado_en_pantalla_y_en_el_mismo_orden(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get('/reportes/csv?tipo=diario&fecha_inicio=2026-11-01&fecha_fin=2026-11-30&tipo_movimiento=gasto');

        $respuesta->assertOk()->assertDownload('reporte-diario-2026-11-01-a-2026-11-30-gasto.csv');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $respuesta->streamedContent());

        $this->assertSame([
            "\xEF\xBB\xBFFecha;Tipo;Categoría;Descripción;Monto",
            '2026-11-05;Gasto;Alquiler;"Renta de oficina";1500.00',
            '2026-11-06;Gasto;Insumos;"\'=HYPERLINK(""http://ejemplo.test"")";10.00',
        ], $this->lineasDelCsv('?tipo=diario&fecha_inicio=2026-11-01&fecha_fin=2026-11-30&tipo_movimiento=gasto'));
    }

    public function test_el_csv_respeta_el_filtro_de_categoria(): void
    {
        $lineas = $this->lineasDelCsv('?tipo=mensual&fecha_inicio=2026-10&fecha_fin=2026-11&categoria_id='.$this->insumos->id);

        $this->assertCount(3, $lineas);
        $this->assertStringContainsString('Compra de papel', $lineas[1]);
        $this->assertStringContainsString('HYPERLINK', $lineas[2]);
    }

    public function test_el_pdf_se_descarga_con_los_filtros_aplicados(): void
    {
        $this->actingAs($this->usuario)
            ->get('/reportes/pdf?tipo=mensual&fecha_inicio=2026-10&fecha_fin=2026-11&categoria_id='.$this->insumos->id.'&tipo_movimiento=gasto')
            ->assertOk()
            ->assertDownload("reporte-mensual-2026-10-a-2026-11-{$this->insumos->id}-gasto.pdf");
    }

    public function test_sin_ingresos_el_porcentaje_de_gastos_no_esta_disponible(): void
    {
        $usuario = User::factory()->create();
        $this->movimiento($this->categoria($usuario, 'Alquiler', 'gasto'), '2026-11-05', 900, 'Solo un gasto');

        $this->actingAs($usuario)->get('/dashboard')
            ->assertOk()
            ->assertSee('No disponible')
            ->assertSee('Sin ingresos en el periodo')
            ->assertSee('-$900.00');
    }

    public function test_sin_gastos_el_porcentaje_de_gastos_es_cero(): void
    {
        $usuario = User::factory()->create();
        $this->movimiento($this->categoria($usuario, 'Salario', 'ingreso'), '2026-11-02', 1200, 'Solo un ingreso');

        $this->actingAs($usuario)->get('/dashboard')
            ->assertOk()
            ->assertSee('0.0%')
            ->assertSee('No hay gastos registrados en este periodo.');
    }

    public function test_sin_movimientos_las_pantallas_y_el_csv_funcionan_vacios(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/dashboard')
            ->assertOk()
            ->assertSee('No hay movimientos registrados en este periodo.');

        $this->actingAs($usuario)->get('/reportes')
            ->assertOk()
            ->assertSee('No hay movimientos que coincidan con los filtros.');

        $this->assertSame(["\xEF\xBB\xBFFecha;Tipo;Categoría;Descripción;Monto"], $this->lineasDelCsv('', $usuario));
    }

    public function test_el_formulario_de_registro_solo_ofrece_las_categorias_del_usuario(): void
    {
        $otro = User::factory()->create();
        $this->categoria($otro, 'Categoría ajena', 'gasto');

        $this->actingAs($this->usuario)->get(route('movimientos.create'))
            ->assertOk()
            ->assertSee('Salario (Ingreso)')
            ->assertSee('Alquiler (Gasto)')
            ->assertDontSee('Categoría ajena');
    }

    public function test_sin_categorias_el_registro_pide_crear_una_primero(): void
    {
        $this->actingAs(User::factory()->create())->get(route('movimientos.create'))
            ->assertOk()
            ->assertSee('Debes crear al menos una categoría antes de registrar movimientos.')
            ->assertSee('href="'.route('categorias.index').'"', false);
    }

    public function test_un_movimiento_registrado_aparece_en_los_reportes(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('movimientos.store'), [
                'categoria_id' => $this->insumos->id,
                'monto' => '45.90',
                'fecha' => '2026-11-12',
                'concepto' => 'Tinta para impresora',
            ])
            ->assertRedirect(route('movimientos.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('movimientos_financieros', [
            'user_id' => $this->usuario->id,
            'categoria_id' => $this->insumos->id,
            'concepto' => 'Tinta para impresora',
        ]);

        $this->actingAs($this->usuario)->get('/reportes?tipo=diario&fecha_inicio=2026-11-01&fecha_fin=2026-11-30')
            ->assertSee('Tinta para impresora');
    }

    public function test_el_registro_valida_el_monto_la_fecha_y_el_concepto(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('movimientos.store'), ['categoria_id' => $this->insumos->id, 'monto' => '0', 'fecha' => 'no-es-fecha', 'concepto' => ''])
            ->assertSessionHasErrors(['monto', 'fecha', 'concepto']);

        $this->assertDatabaseCount('movimientos_financieros', 4);
    }

    public function test_los_errores_del_registro_se_muestran_en_espanol(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('movimientos.store'), ['categoria_id' => 999999, 'monto' => '0', 'fecha' => '2026-11-12', 'concepto' => 'Prueba'])
            ->assertSessionHasErrors([
                'categoria_id' => 'El valor seleccionado en categoría no es válido.',
                'monto' => 'El campo monto debe ser mayor que 0.',
            ]);

        $this->actingAs($this->usuario)
            ->post(route('movimientos.store'), ['categoria_id' => $this->insumos->id, 'monto' => '10.555', 'fecha' => '2026-11-12', 'concepto' => 'Prueba'])
            ->assertSessionHasErrors(['monto' => 'El monto puede tener como máximo dos decimales.']);
    }

    public function test_el_registro_ignora_un_usuario_enviado_en_el_formulario(): void
    {
        $otro = User::factory()->create();

        $this->actingAs($this->usuario)->post(route('movimientos.store'), [
            'user_id' => $otro->id,
            'categoria_id' => $this->insumos->id,
            'monto' => '12.00',
            'fecha' => '2026-11-12',
            'concepto' => 'Intento de suplantación',
        ]);

        $this->assertDatabaseHas('movimientos_financieros', ['concepto' => 'Intento de suplantación', 'user_id' => $this->usuario->id]);
        $this->assertDatabaseMissing('movimientos_financieros', ['user_id' => $otro->id]);
    }

    public function test_la_pagina_de_categorias_solo_lista_las_del_usuario(): void
    {
        $this->categoria(User::factory()->create(), 'Categoría ajena', 'gasto');

        $this->actingAs($this->usuario)->get(route('categorias.index'))
            ->assertOk()
            ->assertSee('Insumos')
            ->assertDontSee('Categoría ajena');
    }

    public function test_no_se_puede_cambiar_el_tipo_de_una_categoria_con_movimientos(): void
    {
        $this->actingAs($this->usuario)
            ->from(route('categorias.index'))
            ->put(route('categorias.update', $this->insumos), ['nombre' => 'Insumos', 'tipo' => 'ingreso'])
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('error', 'No se puede cambiar el tipo de una categoría con movimientos asociados.');

        $this->assertSame('gasto', $this->insumos->fresh()->tipo);
    }

    public function test_una_categoria_con_movimientos_puede_cambiar_de_nombre_y_una_sin_movimientos_de_tipo(): void
    {
        $sinMovimientos = $this->categoria($this->usuario, 'Varios', 'gasto');

        $this->actingAs($this->usuario)
            ->put(route('categorias.update', $this->insumos), ['nombre' => 'Materiales', 'tipo' => 'gasto'])
            ->assertSessionHas('success');
        $this->actingAs($this->usuario)
            ->put(route('categorias.update', $sinMovimientos), ['nombre' => 'Varios', 'tipo' => 'ingreso'])
            ->assertSessionHas('success');

        $this->assertSame('Materiales', $this->insumos->fresh()->nombre);
        $this->assertSame('ingreso', $sinMovimientos->fresh()->tipo);
    }

    public function test_el_panel_el_reporte_y_el_csv_dan_los_mismos_totales_para_el_mismo_periodo(): void
    {
        $periodo = 'fecha_inicio=2026-11-01&fecha_fin=2026-11-30';

        $this->actingAs($this->usuario)->get('/dashboard?'.$periodo)->assertSee('$5,000.00')->assertSee('$1,510.00');
        $this->actingAs($this->usuario)->get('/reportes?tipo=diario&'.$periodo)->assertSee('$5,000.00')->assertSee('$1,510.00');

        $totales = ['Ingreso' => 0.0, 'Gasto' => 0.0];
        foreach (array_slice($this->lineasDelCsv('?tipo=diario&'.$periodo), 1) as $linea) {
            $campos = str_getcsv($linea, ';');
            $totales[$campos[1]] += (float) $campos[4];
        }

        $this->assertSame(['Ingreso' => 5000.0, 'Gasto' => 1510.0], $totales);
    }

    public function test_las_nuevas_rutas_exigen_sesion(): void
    {
        $this->get(route('movimientos.create'))->assertRedirect(route('login'));
        $this->post(route('movimientos.store'))->assertRedirect(route('login'));
        $this->get(route('reportes.csv'))->assertRedirect(route('login'));
    }

    public function test_el_menu_enlaza_categorias_y_la_pagina_de_categorias_marca_su_opcion(): void
    {
        $this->actingAs($this->usuario)->get(route('categorias.index'))
            ->assertOk()
            ->assertSee('Salario')
            ->assertSee('aria-current="page"', false);
    }
}
