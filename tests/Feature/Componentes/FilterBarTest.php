<?php

namespace Tests\Feature\Componentes;

use Illuminate\Http\Request;
use Illuminate\Testing\TestView;
use Tests\TestCase;

class FilterBarTest extends TestCase
{
    private const CATEGORIAS = [1 => 'Salario', 2 => 'Ventas'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withViewErrors([]);
    }

    /**
     * Simula la petición GET actual con sus parámetros de consulta.
     *
     * @param  array<string, string>  $consulta
     */
    private function peticion(array $consulta = []): Request
    {
        $peticion = Request::create('/panel', 'GET', $consulta);
        $this->app->instance('request', $peticion);

        return $peticion;
    }

    private function barra(string $atributos = ''): TestView
    {
        return $this->blade(
            '<x-financial.filter-bar action-url="/panel" :categories="$categorias" '.$atributos.' />',
            ['categorias' => self::CATEGORIAS],
        );
    }

    public function test_el_boton_limpiar_lleva_a_la_url_del_formulario_sin_filtros(): void
    {
        $this->peticion(['fecha_inicio' => '2026-01-05']);

        $vista = $this->barra();

        $vista->assertSee('<a href="/panel" class="btn btn-outline-secondary" title="Limpiar filtros"', false);
    }

    public function test_el_boton_limpiar_acepta_una_url_propia(): void
    {
        $vista = $this->barra('clear-url="/panel/inicio"');

        $vista->assertSee('<a href="/panel/inicio" class="btn btn-outline-secondary" title="Limpiar filtros"', false);
    }

    public function test_conserva_los_filtros_enviados_en_la_url(): void
    {
        $this->peticion(['fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-20', 'categoria_id' => '2']);

        $vista = $this->barra('start-date="2026-11-01"');

        $vista->assertSee('value="2026-01-05"', false)
            ->assertSee('value="2026-01-20"', false)
            ->assertSee('<option value="2" selected>Ventas</option>', false)
            ->assertDontSee('value="2026-11-01"', false);
    }

    public function test_usa_los_valores_iniciales_si_no_hay_filtros_en_la_url(): void
    {
        $this->peticion();

        $vista = $this->barra('start-date="2026-11-01" end-date="2026-11-30" selected-category="1"');

        $vista->assertSee('value="2026-11-01"', false)
            ->assertSee('value="2026-11-30"', false)
            ->assertSee('<option value="1" selected>Salario</option>', false);
    }

    public function test_tras_un_error_de_validacion_muestra_lo_que_escribio_el_usuario(): void
    {
        $peticion = $this->peticion(['fecha_inicio' => '2026-01-05']);
        $sesion = $this->app['session.store'];
        $sesion->flashInput(['fecha_inicio' => '2026-02-10']);
        $peticion->setLaravelSession($sesion);

        $vista = $this->barra();

        $vista->assertSee('value="2026-02-10"', false)
            ->assertDontSee('value="2026-01-05"', false);
    }

    public function test_muestra_el_error_de_validacion_junto_al_campo(): void
    {
        $this->peticion();
        $this->withViewErrors(['fecha_fin' => 'La fecha final no puede ser anterior a la inicial.']);

        $vista = $this->barra();

        $vista->assertSee('<div id="fecha_fin-error" class="invalid-feedback">La fecha final no puede ser anterior a la inicial.</div>', false)
            ->assertSee('aria-describedby="fecha_fin-error" aria-invalid="true"', false);
        $this->assertSame(1, substr_count((string) $vista, 'is-invalid'));
    }

    public function test_ofrece_periodos_predefinidos_conservando_la_categoria(): void
    {
        $this->travelTo('2026-03-15 10:00:00');
        $this->peticion(['categoria_id' => '2']);

        $vista = $this->barra();

        $vista->assertSeeInOrder([
            'Periodo:',
            '/panel?fecha_inicio=2026-03-01&fecha_fin=2026-03-31&categoria_id=2', 'Este mes',
            '/panel?fecha_inicio=2026-02-01&fecha_fin=2026-02-28&categoria_id=2', 'Mes anterior',
            '/panel?fecha_inicio=2026-01-01&fecha_fin=2026-12-31&categoria_id=2', 'Año actual',
        ]);
    }

    public function test_el_mes_anterior_no_se_desborda_al_final_de_mes(): void
    {
        $this->travelTo('2026-03-31 10:00:00');
        $this->peticion();

        $vista = $this->barra();

        $vista->assertSee('/panel?fecha_inicio=2026-02-01&fecha_fin=2026-02-28');
    }

    public function test_marca_el_periodo_predefinido_que_coincide_con_los_filtros(): void
    {
        $this->travelTo('2026-03-15 10:00:00');
        $this->peticion(['fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-03-31']);

        $vista = $this->barra();

        $this->assertMatchesRegularExpression('/class="btn btn-sm btn-primary"\s+aria-current="true"\s*>\s*Este mes/', (string) $vista);
        $this->assertSame(1, substr_count((string) $vista, 'aria-current="true"'));
    }

    public function test_los_periodos_predefinidos_se_pueden_ocultar(): void
    {
        $this->peticion();

        $vista = $this->barra(':show-periods="false"');

        $vista->assertDontSee('Periodo:')
            ->assertDontSee('Este mes');
    }

    public function test_los_campos_de_periodo_pueden_ser_meses(): void
    {
        $this->peticion();

        $vista = $this->barra('input-type="month" start-date="2026-06" end-date="2026-11"');

        $vista->assertSee('<input type="month" placeholder="AAAA-MM" id="fecha_inicio" name="fecha_inicio" value="2026-06"', false)
            ->assertSee('<input type="month" placeholder="AAAA-MM" id="fecha_fin" name="fecha_fin" value="2026-11"', false);
    }

    public function test_los_campos_de_periodo_pueden_ser_anios(): void
    {
        $this->peticion();

        $vista = $this->barra('input-type="year" start-date="2024" end-date="2026"');

        $vista->assertSee('<input type="number" min="2000" max="2100" step="1" inputmode="numeric" placeholder="AAAA" id="fecha_inicio" name="fecha_inicio" value="2024"', false);
    }

    public function test_los_periodos_predefinidos_solo_se_ofrecen_con_fechas(): void
    {
        $this->peticion();

        $vista = $this->barra('input-type="month"');

        $vista->assertDontSee('Este mes');
    }

    public function test_las_etiquetas_el_boton_y_los_campos_adicionales_son_configurables(): void
    {
        $this->peticion();

        $vista = $this->blade(
            '<x-financial.filter-bar action-url="/panel" start-label="Mes inicial" end-label="Mes final" submit-label="Generar reporte"><input type="hidden" name="tipo" value="mensual"></x-financial.filter-bar>',
        );

        $vista->assertSeeInOrder(['<input type="hidden" name="tipo" value="mensual">', 'Mes inicial', 'Mes final', 'Generar reporte'], false)
            ->assertDontSee('Filtrar');
    }

    public function test_el_boton_principal_admite_un_texto_de_carga(): void
    {
        $this->peticion();

        $conCarga = $this->barra('loading-text="Generando reporte…"');
        $sinCarga = $this->barra();

        $conCarga->assertSee('data-loading-text="Generando reporte…"', false);
        $sinCarga->assertDontSee('data-loading-text', false);
    }

    public function test_activa_las_validaciones_del_cliente_para_el_rango_de_fechas(): void
    {
        $this->peticion();

        $vista = $this->barra();

        $vista->assertSee('data-validar novalidate', false);
        $this->assertMatchesRegularExpression('/name="fecha_inicio" value=""\s+data-requerido-con="fecha_fin"/', (string) $vista);
        $this->assertMatchesRegularExpression('/name="fecha_fin" value=""\s+data-requerido-con="fecha_inicio" data-fecha-desde="fecha_inicio"/', (string) $vista);
    }

    public function test_sin_categorias_no_muestra_el_selector_de_categoria(): void
    {
        $this->peticion();

        $vista = $this->blade('<x-financial.filter-bar action-url="/panel" />');

        $vista->assertDontSee('name="categoria_id"', false)
            ->assertSee('name="fecha_inicio"', false);
    }
}
