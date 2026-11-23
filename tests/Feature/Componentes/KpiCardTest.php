<?php

namespace Tests\Feature\Componentes;

use Illuminate\Testing\TestView;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KpiCardTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $datos
     */
    private function tarjeta(string $atributos, array $datos = []): TestView
    {
        return $this->blade('<x-financial.kpi-card title="Balance" '.$atributos.' />', $datos);
    }

    public function test_un_monto_negativo_se_muestra_con_signo_y_en_rojo(): void
    {
        $vista = $this->tarjeta(':amount="$monto"', ['monto' => -250]);

        $this->assertMatchesRegularExpression('/<span class="[^"]*\btext-danger\b[^"]*">-\$250\.00<\/span>/', (string) $vista);
    }

    public function test_un_monto_positivo_no_se_muestra_en_rojo(): void
    {
        $vista = $this->tarjeta(':amount="$monto"', ['monto' => 250]);

        $vista->assertSee('$250.00')
            ->assertDontSee('text-danger');
    }

    public function test_una_variacion_positiva_muestra_flecha_de_aumento(): void
    {
        $vista = $this->tarjeta(':amount="100" :percentage="8.5"');

        $vista->assertSee('+8.5%')
            ->assertSee('bi-arrow-up-right')
            ->assertSee('text-success')
            ->assertDontSee('bi-arrow-down-right');
    }

    public function test_una_variacion_negativa_muestra_flecha_de_disminucion(): void
    {
        $vista = $this->tarjeta(':amount="100" :percentage="-3.2"');

        $vista->assertSee('-3.2%')
            ->assertSee('bi-arrow-down-right')
            ->assertSee('text-danger')
            ->assertDontSee('bi-arrow-up-right');
    }

    /**
     * @return array<string, array{0: float|int}>
     */
    public static function variacionesNeutras(): array
    {
        return [
            'cero exacto' => [0],
            'positiva que redondea a cero' => [0.04],
            'negativa que redondea a cero' => [-0.04],
        ];
    }

    #[DataProvider('variacionesNeutras')]
    public function test_una_variacion_de_cero_se_muestra_neutra_sin_flecha(float|int $variacion): void
    {
        $vista = $this->tarjeta(':amount="100" :percentage="$variacion"', ['variacion' => $variacion]);

        $vista->assertSee('0.0%')
            ->assertSee('Sin variación')
            ->assertSee('text-secondary')
            ->assertDontSee('bi-arrow-up-right')
            ->assertDontSee('bi-arrow-down-right')
            ->assertDontSee('+0.0%')
            ->assertDontSee('-0.0%');
    }

    public function test_con_tendencia_invertida_un_aumento_es_desfavorable(): void
    {
        $vista = $this->tarjeta(':amount="100" :percentage="12" invert-trend');

        $vista->assertSee('+12.0%')
            ->assertSee('bi-arrow-up-right')
            ->assertSee('small text-danger', false)
            ->assertSee('(desfavorable)')
            ->assertDontSee('text-success');
    }

    public function test_con_tendencia_invertida_una_disminucion_es_favorable(): void
    {
        $vista = $this->tarjeta(':amount="100" :percentage="-12" invert-trend');

        $vista->assertSee('-12.0%')
            ->assertSee('bi-arrow-down-right')
            ->assertSee('small text-success', false)
            ->assertSee('(favorable)')
            ->assertDontSee('text-danger');
    }

    public function test_con_tendencia_invertida_una_variacion_de_cero_sigue_siendo_neutra(): void
    {
        $vista = $this->tarjeta(':amount="100" :percentage="0" invert-trend');

        $vista->assertSee('Sin variación')
            ->assertSee('text-secondary')
            ->assertDontSee('favorable');
    }

    public function test_un_indicador_porcentual_muestra_el_valor_con_un_decimal_y_sin_simbolo_de_dolar(): void
    {
        $vista = $this->tarjeta(':amount="33.333" format="percent"');

        $vista->assertSee('33.3%')
            ->assertDontSee('$');
    }

    public function test_un_valor_no_calculable_muestra_el_texto_alternativo(): void
    {
        $vista = $this->tarjeta(':amount="null" format="percent" empty-text="No aplica"');

        $vista->assertSee('No aplica')
            ->assertDontSee('%')
            ->assertDontSee('$');
    }

    public function test_muestra_una_nota_bajo_el_valor(): void
    {
        $vista = $this->tarjeta(':amount="100" caption="Ingresos − gastos"');

        $vista->assertSeeInOrder(['$100.00', 'Ingresos − gastos']);
    }

    public function test_el_texto_de_comparacion_es_configurable(): void
    {
        $vista = $this->tarjeta(':amount="100" :percentage="5" comparison-label="vs. mes anterior"');

        $vista->assertSee('vs. mes anterior')
            ->assertDontSee('vs. periodo anterior');
    }

    public function test_sin_variacion_no_se_muestra_el_texto_de_comparacion(): void
    {
        $vista = $this->tarjeta(':amount="100"');

        $vista->assertDontSee('vs. periodo anterior')
            ->assertDontSee('%');
    }
}
