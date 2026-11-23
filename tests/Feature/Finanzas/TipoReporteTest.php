<?php

namespace Tests\Feature\Finanzas;

use App\Services\Finanzas\Periodo;
use App\Services\Finanzas\TipoReporte;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TipoReporteTest extends TestCase
{
    private function rango(Periodo $periodo): string
    {
        return $periodo->inicio->toDateString().' / '.$periodo->fin->toDateString();
    }

    /**
     * @return array<string, array{0: TipoReporte, 1: string}>
     */
    public static function periodosPorDefecto(): array
    {
        return [
            'diario: del día 1 del mes a hoy' => [TipoReporte::Diario, '2026-11-01 / 2026-11-18'],
            'mensual: últimos seis meses' => [TipoReporte::Mensual, '2026-06-01 / 2026-11-30'],
            'anual: el año actual y los dos anteriores' => [TipoReporte::Anual, '2024-01-01 / 2026-12-31'],
        ];
    }

    #[DataProvider('periodosPorDefecto')]
    public function test_cada_tipo_tiene_un_periodo_por_defecto(TipoReporte $tipo, string $esperado): void
    {
        $periodo = $tipo->periodoPorDefecto(CarbonImmutable::parse('2026-11-18 10:00:00'));

        $this->assertSame($esperado, $this->rango($periodo));
    }

    /**
     * @return array<string, array{0: TipoReporte, 1: string, 2: string, 3: string}>
     */
    public static function periodosDelFormulario(): array
    {
        return [
            'diario: fechas exactas' => [TipoReporte::Diario, '2026-11-03', '2026-11-09', '2026-11-03 / 2026-11-09'],
            'mensual: meses completos' => [TipoReporte::Mensual, '2026-02', '2026-03', '2026-02-01 / 2026-03-31'],
            'anual: años completos' => [TipoReporte::Anual, '2024', '2025', '2024-01-01 / 2025-12-31'],
        ];
    }

    #[DataProvider('periodosDelFormulario')]
    public function test_interpreta_los_campos_del_formulario_segun_el_tipo(TipoReporte $tipo, string $inicio, string $fin, string $esperado): void
    {
        $this->assertSame($esperado, $this->rango($tipo->periodoEntre($inicio, $fin)));
    }

    public function test_un_mes_del_formulario_no_se_desborda_aunque_hoy_sea_31(): void
    {
        $this->travelTo('2026-03-31 10:00:00');

        $periodo = TipoReporte::Mensual->periodoEntre('2026-02', '2026-02');

        $this->assertSame('2026-02-01 / 2026-02-28', $this->rango($periodo));
    }

    public function test_agrupa_por_dia_con_etiquetas_cortas(): void
    {
        $grupos = TipoReporte::Diario->grupos(new Periodo('2026-11-30', '2026-12-02'));

        $this->assertSame([
            ['clave' => '2026-11-30', 'etiqueta' => '30/11'],
            ['clave' => '2026-12-01', 'etiqueta' => '01/12'],
            ['clave' => '2026-12-02', 'etiqueta' => '02/12'],
        ], $grupos);
    }

    public function test_agrupa_por_mes_cruzando_el_cambio_de_anio(): void
    {
        $grupos = TipoReporte::Mensual->grupos(new Periodo('2026-12-01', '2027-01-31'));

        $this->assertSame([
            ['clave' => '2026-12', 'etiqueta' => 'Dic 2026'],
            ['clave' => '2027-01', 'etiqueta' => 'Ene 2027'],
        ], $grupos);
    }

    public function test_agrupa_por_anio(): void
    {
        $grupos = TipoReporte::Anual->grupos(new Periodo('2024-01-01', '2025-12-31'));

        $this->assertSame([['clave' => '2024', 'etiqueta' => '2024'], ['clave' => '2025', 'etiqueta' => '2025']], $grupos);
    }

    /**
     * @return array<string, array{0: TipoReporte, 1: string, 2: string, 3: int}>
     */
    public static function cantidades(): array
    {
        return [
            'días' => [TipoReporte::Diario, '2026-11-01', '2026-11-30', 30],
            'meses' => [TipoReporte::Mensual, '2026-01-01', '2026-12-31', 12],
            'años' => [TipoReporte::Anual, '2020-01-01', '2026-12-31', 7],
        ];
    }

    #[DataProvider('cantidades')]
    public function test_cuenta_los_grupos_del_periodo(TipoReporte $tipo, string $inicio, string $fin, int $esperado): void
    {
        $this->assertSame($esperado, $tipo->cantidadDeGrupos(new Periodo($inicio, $fin)));
    }
}
