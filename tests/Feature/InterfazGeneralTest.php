<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InterfazGeneralTest extends TestCase
{
    use RefreshDatabase;

    private const MODULOS_SIN_RUTA = ['/ingresos', '/gastos'];

    /**
     * Devuelve el href del enlace del logo (el <a> que contiene el icono de la billetera).
     */
    private function hrefDelLogo(string $html): ?string
    {
        preg_match('/<a\b[^>]*\bhref="([^"]*)"[^>]*>\s*<i class="bi bi-wallet2/', $html, $coincidencia);

        return $coincidencia[1] ?? null;
    }

    public function test_el_titulo_del_layout_autenticado_usa_el_nombre_finanzy(): void
    {
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/dashboard');

        $respuesta->assertOk()
            ->assertSee('<title>Panel Financiero | Finanzy</title>', false)
            ->assertDontSee('Laravel');
    }

    public function test_el_titulo_del_layout_de_invitados_usa_el_nombre_finanzy(): void
    {
        $respuesta = $this->get('/login');

        $respuesta->assertOk()
            ->assertSee('<title>Iniciar sesión | Finanzy</title>', false)
            ->assertDontSee('Laravel');
    }

    public function test_el_logo_lleva_al_dashboard_si_el_usuario_esta_autenticado(): void
    {
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/dashboard');

        $respuesta->assertOk();
        $this->assertSame(route('dashboard'), $this->hrefDelLogo($respuesta->getContent()));
    }

    public function test_el_logo_de_la_barra_superior_lleva_al_login_sin_usuario(): void
    {
        $vista = $this->blade('<x-layout.navbar />');

        $this->assertSame(route('login'), $this->hrefDelLogo((string) $vista));
    }

    public function test_la_raiz_lleva_al_login_a_quien_no_tiene_sesion(): void
    {
        $respuesta = $this->followingRedirects()->get('/');

        $respuesta->assertOk()->assertSee('<title>Iniciar sesión | Finanzy</title>', false);
    }

    public function test_la_raiz_lleva_al_panel_con_sesion(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())->get('/');

        $respuesta->assertRedirect(route('dashboard'));
    }

    public function test_la_pagina_demo_ya_no_es_publica(): void
    {
        $this->get('/demo')->assertRedirect('/login');
        $this->post('/demo', [])->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/demo')->assertOk();
    }

    public function test_la_barra_superior_tiene_nombre_accesible(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())->get('/dashboard');

        $respuesta->assertSee('<nav class="navbar navbar-dark bg-dark px-3 shadow-sm" aria-label="Barra superior">', false);
    }

    public function test_la_etiqueta_sin_rol_usa_un_estilo_neutral(): void
    {
        $respuesta = $this->actingAs(User::factory()->create())->get('/dashboard');

        $this->assertMatchesRegularExpression('/<span class="badge bg-secondary rounded-pill">\s*Sin rol\s*<\/span>/', $respuesta->getContent());
    }

    public function test_el_logo_de_las_pantallas_de_acceso_lleva_al_login(): void
    {
        $respuesta = $this->get('/register');

        $respuesta->assertOk();
        $this->assertSame(route('login'), $this->hrefDelLogo($respuesta->getContent()));
    }

    public function test_los_modulos_sin_ruta_se_muestran_como_proximamente_sin_enlace(): void
    {
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/dashboard');

        $respuesta->assertOk()
            ->assertSeeInOrder(['Ingresos', 'Próximamente', 'Gastos', 'Próximamente', 'Categorías']);

        foreach (self::MODULOS_SIN_RUTA as $ruta) {
            $respuesta->assertDontSee('href="'.url($ruta).'"', false);
        }
    }

    public function test_los_modulos_de_categorias_y_reportes_aparecen_enlazados_en_el_menu(): void
    {
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/dashboard');

        $respuesta->assertOk()
            ->assertSeeInOrder(['href="'.route('categorias.index').'"', 'Categorías', 'href="'.route('reportes.index').'"', 'Reportes'], false);
    }

    public function test_un_modulo_se_enlaza_automaticamente_cuando_su_ruta_existe(): void
    {
        Route::middleware('web')->get('/ingresos', fn () => 'ingresos')->name('ingresos.index');
        Route::getRoutes()->refreshNameLookups();
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/dashboard');

        $respuesta->assertOk()
            ->assertSee('href="'.url('/ingresos').'"', false)
            ->assertDontSee('href="'.url('/gastos').'"', false);
    }

    public function test_el_menu_marca_como_actual_el_modulo_de_la_pagina(): void
    {
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/dashboard');

        $respuesta->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('dashboard'), '/').'"\s+class="sidebar-link is-active"\s+aria-current="page"\s*>/',
            $respuesta->getContent(),
        );
    }

    public function test_el_boton_de_menu_movil_controla_el_sidebar_offcanvas(): void
    {
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/dashboard');

        $respuesta->assertOk()
            ->assertSee('data-bs-toggle="offcanvas"', false)
            ->assertSee('data-bs-target="#sidebarMenu"', false)
            ->assertSee('class="offcanvas-lg offcanvas-start" id="sidebarMenu"', false);
    }
}
