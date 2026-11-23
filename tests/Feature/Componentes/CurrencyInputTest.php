<?php

namespace Tests\Feature\Componentes;

use Tests\TestCase;

class CurrencyInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withViewErrors([]);
    }

    public function test_por_defecto_exige_un_monto_positivo_en_el_cliente(): void
    {
        $vista = $this->blade('<x-form.currency-input name="monto" label="Monto" required />');

        $vista->assertSee('data-monto-positivo', false)
            ->assertSee('required', false);
    }

    public function test_la_regla_de_monto_positivo_se_puede_desactivar(): void
    {
        $vista = $this->blade('<x-form.currency-input name="ajuste" label="Ajuste" :positive="false" />');

        $vista->assertDontSee('data-monto-positivo', false);
    }
}
