<?php

namespace Tests\Feature\Componentes;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{0: float|int|string, 1: string}>
     */
    public static function montos(): array
    {
        return [
            'positivo con miles' => [12500, '$12,500.00'],
            'negativo con signo antes del símbolo' => [-1234.5, '-$1,234.50'],
            'decimales redondeados' => [10.555, '$10.56'],
            'cadena numérica' => ['8430.5', '$8,430.50'],
            'negativo que redondea a cero' => [-0.004, '$0.00'],
        ];
    }

    #[DataProvider('montos')]
    public function test_formatea_el_monto_en_dolares(float|int|string $monto, string $esperado): void
    {
        $vista = $this->blade('<x-ui.money :amount="$monto" />', ['monto' => $monto]);

        $vista->assertSee('>'.$esperado.'</span>', false);
    }
}
