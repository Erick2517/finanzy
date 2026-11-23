<?php

namespace Tests\Feature\Componentes;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    private const RUTA = 'http://localhost/usuarios';

    private function paginador(int $total, int $pagina = 1, int $porPagina = 10): LengthAwarePaginator
    {
        $desde = ($pagina - 1) * $porPagina + 1;
        $hasta = min($total, $pagina * $porPagina);
        $elementos = $total > 0 ? range($desde, $hasta) : [];

        return new LengthAwarePaginator($elementos, $total, $porPagina, $pagina, ['path' => self::RUTA]);
    }

    public function test_muestra_el_rango_y_el_total_de_resultados(): void
    {
        $vista = $this->blade('<x-ui.pagination :paginator="$p" />', ['p' => $this->paginador(35)]);

        $vista->assertSeeText('Mostrando 1 a 10 de 35 resultados');
    }

    public function test_el_sustantivo_del_resumen_es_configurable(): void
    {
        $vista = $this->blade('<x-ui.pagination :paginator="$p" label="usuarios" />', ['p' => $this->paginador(35, 4)]);

        $vista->assertSeeText('Mostrando 31 a 35 de 35 usuarios');
    }

    public function test_muestra_enlaces_de_pagina_en_espanol_sin_el_resumen_en_ingles(): void
    {
        $vista = $this->blade('<x-ui.pagination :paginator="$p" />', ['p' => $this->paginador(35, 2)]);

        $vista->assertSee('<li class="page-item active" aria-current="page"><span class="page-link">2</span></li>', false)
            ->assertSee('href="'.self::RUTA.'?page=3"', false)
            ->assertSee('aria-label="Página anterior"', false)
            ->assertSee('aria-label="Página siguiente"', false)
            ->assertDontSee('Showing')
            ->assertDontSee('Previous')
            ->assertDontSee('Next');
    }

    public function test_con_una_sola_pagina_muestra_el_resumen_sin_enlaces(): void
    {
        $vista = $this->blade('<x-ui.pagination :paginator="$p" />', ['p' => $this->paginador(3)]);

        $vista->assertSeeText('Mostrando 1 a 3 de 3 resultados')
            ->assertDontSee('aria-label="Paginación"', false);
    }

    public function test_sin_registros_no_muestra_nada(): void
    {
        $vista = $this->blade('<x-ui.pagination :paginator="$p" />', ['p' => $this->paginador(0)]);

        $this->assertSame('', trim((string) $vista));
    }

    public function test_es_compatible_con_la_paginacion_simple_sin_total(): void
    {
        $paginador = new Paginator(range(11, 21), 10, 2, ['path' => self::RUTA]);

        $vista = $this->blade('<x-ui.pagination :paginator="$p" />', ['p' => $paginador]);

        $vista->assertSeeText('Mostrando 11 a 20 resultados')
            ->assertSee('href="'.self::RUTA.'?page=1"', false)
            ->assertSee('href="'.self::RUTA.'?page=3"', false);
    }
}
