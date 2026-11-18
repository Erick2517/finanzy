<?php

namespace Tests\Feature\Finanzas;

use App\Services\Finanzas\Periodo;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodoTest extends TestCase
{
    private function rango(Periodo $periodo): string
    {
        return $periodo->inicio->format('Y-m-d H:i:s').' / '.$periodo->fin->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function periodosAnteriores(): array
    {
        return [
            'un mes completo: el mes natural anterior' => ['2026-11-01', '2026-11-30', '2026-10-01 00:00:00 / 2026-10-31 23:59:59'],
            'marzo: febrero completo aunque sea más corto' => ['2026-03-01', '2026-03-31', '2026-02-01 00:00:00 / 2026-02-28 23:59:59'],
            'varios meses completos' => ['2026-01-01', '2026-03-31', '2025-10-01 00:00:00 / 2025-12-31 23:59:59'],
            'un año completo' => ['2026-01-01', '2026-12-31', '2025-01-01 00:00:00 / 2025-12-31 23:59:59'],
            'un rango personalizado: los mismos días previos' => ['2026-11-10', '2026-11-19', '2026-10-31 00:00:00 / 2026-11-09 23:59:59'],
        ];
    }

    #[DataProvider('periodosAnteriores')]
    public function test_calcula_el_periodo_anterior_de_igual_duracion(string $inicio, string $fin, string $esperado): void
    {
        $periodo = new Periodo($inicio, $fin);

        $this->assertSame($esperado, $this->rango($periodo->anterior()));
    }

    public function test_rechaza_un_inicio_posterior_al_fin(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Periodo('2026-11-30', '2026-11-01');
    }

    public function test_incluye_el_ultimo_segundo_del_dia_final(): void
    {
        $periodo = new Periodo('2026-11-01', '2026-11-30');

        $this->assertTrue($periodo->contiene(CarbonImmutable::parse('2026-11-30 23:59:59')));
        $this->assertFalse($periodo->contiene(CarbonImmutable::parse('2026-12-01 00:00:00')));
    }

    public function test_los_ultimos_seis_meses_terminan_en_el_mes_indicado(): void
    {
        $periodo = Periodo::ultimosMeses(6, CarbonImmutable::parse('2026-11-18'));

        $this->assertSame('2026-06-01 00:00:00 / 2026-11-30 23:59:59', $this->rango($periodo));
        $this->assertSame(
            ['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'],
            array_map(fn (CarbonImmutable $mes) => $mes->format('Y-m'), $periodo->meses()),
        );
    }

    public function test_los_periodos_predefinidos_parten_de_hoy(): void
    {
        $predefinidos = Periodo::predefinidos(CarbonImmutable::parse('2026-03-31 15:00:00'));

        $this->assertSame([
            'Este mes' => '2026-03-01 00:00:00 / 2026-03-31 23:59:59',
            'Mes anterior' => '2026-02-01 00:00:00 / 2026-02-28 23:59:59',
            'Año actual' => '2026-01-01 00:00:00 / 2026-12-31 23:59:59',
        ], array_map(fn (Periodo $periodo) => $this->rango($periodo), $predefinidos));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function etiquetas(): array
    {
        return [
            'un mes' => ['2026-11-01', '2026-11-30', 'noviembre 2026'],
            'un año' => ['2026-01-01', '2026-12-31', '2026'],
            'un rango' => ['2026-11-01', '2026-11-15', '1 nov 2026 – 15 nov 2026'],
        ];
    }

    #[DataProvider('etiquetas')]
    public function test_describe_el_periodo_en_espanol(string $inicio, string $fin, string $esperada): void
    {
        $periodo = new Periodo($inicio, $fin);

        $this->assertSame($esperada, $periodo->etiqueta());
    }
}
