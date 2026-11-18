<?php

namespace Tests\Feature\Componentes;

use Illuminate\Testing\TestView;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChartCardTest extends TestCase
{
    /** El bloque del estado vacío está oculto (el gráfico es visible). */
    private const VACIO_OCULTO = '/data-chart-empty\s+hidden\s*>/';

    public function test_por_defecto_muestra_el_lienzo_del_grafico(): void
    {
        $vista = $this->blade('<x-financial.chart-card title="Ingresos" chart-id="graficoIngresos" />');

        $vista->assertSee('<canvas id="graficoIngresos"', false)
            ->assertDontSee('No hay datos para mostrar');
    }

    public function test_sin_datos_muestra_el_estado_vacio_en_lugar_del_grafico(): void
    {
        $vista = $this->blade('<x-financial.chart-card title="Ingresos" chart-id="graficoIngresos" :empty="true" />');

        $vista->assertSee('No hay datos para mostrar en este periodo.')
            ->assertDontSee('<canvas', false);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function graficoGestionado(string $atributos, array $datos): TestView
    {
        return $this->blade('<x-financial.chart-card title="Ingresos vs. gastos" chart-id="ingresosGastos" '.$atributos.' />', $datos);
    }

    /**
     * Decodifica la especificación escrita en el atributo data-chart del <canvas>.
     *
     * @return array<string, mixed>
     */
    private function especificacion(TestView $vista): array
    {
        preg_match('/data-chart="([^"]*)"/', (string) $vista, $coincidencia);

        return json_decode(html_entity_decode($coincidencia[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_el_modo_gestionado_entrega_la_especificacion_al_sistema_de_graficos(): void
    {
        $vista = $this->graficoGestionado('type="bar" :labels="$meses" :datasets="$series" :stacked="true"', [
            'meses' => collect(['Ene', 'Feb']),
            'series' => [['label' => 'Ingresos', 'data' => ['1000.50', 2000], 'color' => 'success']],
        ]);

        $this->assertSame([
            'type' => 'bar',
            'labels' => ['Ene', 'Feb'],
            'datasets' => [['label' => 'Ingresos', 'data' => [1000.5, 2000], 'color' => 'success']],
            'format' => 'currency',
            'stacked' => true,
        ], $this->especificacion($vista));
        $this->assertMatchesRegularExpression(self::VACIO_OCULTO, (string) $vista);
    }

    /**
     * @return array<string, array{0: array<int, string>, 1: array<int, array<string, mixed>>}>
     */
    public static function datosVacios(): array
    {
        return [
            'sin etiquetas' => [[], [['label' => 'Ingresos', 'data' => []]]],
            'sin series' => [['Ene'], []],
            'todos los valores en cero o nulos' => [['Ene', 'Feb'], [['label' => 'Ingresos', 'data' => [0, null]]]],
        ];
    }

    /**
     * @param  array<int, string>  $etiquetas
     * @param  array<int, array<string, mixed>>  $series
     */
    #[DataProvider('datosVacios')]
    public function test_el_modo_gestionado_sin_datos_muestra_el_estado_vacio(array $etiquetas, array $series): void
    {
        $vista = $this->graficoGestionado('type="line" :labels="$etiquetas" :datasets="$series"', [
            'etiquetas' => $etiquetas,
            'series' => $series,
        ]);

        $this->assertMatchesRegularExpression('/<canvas id="ingresosGastos"[^>]*\shidden\s*><\/canvas>/', (string) $vista);
        $this->assertDoesNotMatchRegularExpression(self::VACIO_OCULTO, (string) $vista);
        $vista
            ->assertSee('No hay datos para mostrar en este periodo.')
            ->assertDontSee('<table', false);
    }

    public function test_un_valor_negativo_cuenta_como_dato(): void
    {
        $vista = $this->graficoGestionado('type="bar" :labels="$meses" :datasets="$series"', [
            'meses' => ['Ene'],
            'series' => [['label' => 'Balance', 'data' => [-150]]],
        ]);

        $this->assertMatchesRegularExpression(self::VACIO_OCULTO, (string) $vista);
    }

    public function test_el_modo_gestionado_incluye_una_tabla_accesible_con_formato_monetario(): void
    {
        $vista = $this->graficoGestionado('type="bar" :labels="$meses" :datasets="$series"', [
            'meses' => ['Ene', 'Feb'],
            'series' => [
                ['label' => 'Ingresos', 'data' => [1250.5, null]],
                ['label' => 'Gastos', 'data' => [-80, 300]],
            ],
        ]);

        $vista->assertSee('<div class="visually-hidden">', false)
            ->assertSeeInOrder(['Concepto', 'Ingresos', 'Gastos', 'Ene', '$1,250.50', '-$80.00', 'Feb', '—', '$300.00'], false);
    }

    public function test_la_tabla_accesible_respeta_el_formato_de_porcentaje(): void
    {
        $vista = $this->graficoGestionado('type="doughnut" format="percent" :labels="$categorias" :datasets="$series"', [
            'categorias' => ['Salario'],
            'series' => [['label' => 'Participación', 'data' => [62.45]]],
        ]);

        $vista->assertSee('62.5%')
            ->assertDontSee('$62');
    }

    public function test_las_etiquetas_se_escapan_en_el_atributo_y_en_la_tabla(): void
    {
        $vista = $this->graficoGestionado('type="bar" :labels="$meses" :datasets="$series"', [
            'meses' => ['<script>alert(1)</script>'],
            'series' => [['label' => '"><img src=x onerror=alert(1)>', 'data' => [10]]],
        ]);

        $vista->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false);
        $this->assertSame('<script>alert(1)</script>', $this->especificacion($vista)['labels'][0]);
    }

    public function test_el_mensaje_del_estado_vacio_es_configurable(): void
    {
        $vista = $this->blade('<x-financial.chart-card chart-id="graficoGastos" :empty="true" empty-message="Aún no hay gastos registrados." />');

        $vista->assertSee('Aún no hay gastos registrados.')
            ->assertDontSee('No hay datos para mostrar en este periodo.');
    }
}
