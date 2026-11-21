<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestView;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginasDeErrorTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon(string $rol): User
    {
        $this->seed(RoleSeeder::class);
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    }

    /**
     * Renderiza una página de error con la sesión disponible, como ocurre dentro de una ruta web.
     */
    private function paginaConSesion(string $codigo): TestView
    {
        $this->app['request']->setLaravelSession($this->app['session.store']);

        return $this->view("errors.{$codigo}");
    }

    public function test_una_direccion_inexistente_muestra_la_pagina_404_en_espanol(): void
    {
        $respuesta = $this->get('/direccion-que-no-existe');

        $respuesta->assertNotFound()
            ->assertSee('<title>Página no encontrada | Finanzy</title>', false)
            ->assertSee('La página que buscas no existe o cambió de dirección.')
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('Ir al inicio')
            ->assertDontSee('Not Found');
    }

    public function test_un_registro_inexistente_ofrece_volver_al_panel(): void
    {
        $administrador = $this->usuarioCon('Administrador');

        $respuesta = $this->actingAs($administrador)->get('/usuarios/999999/edit');

        $respuesta->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Volver al panel');
    }

    public function test_un_acceso_sin_permiso_muestra_la_pagina_403_en_espanol(): void
    {
        $empleado = $this->usuarioCon('Empleado');

        $respuesta = $this->actingAs($empleado)->get('/usuarios');

        $respuesta->assertForbidden()
            ->assertSee('<title>Acceso denegado | Finanzy</title>', false)
            ->assertSee('No tienes permiso para acceder a esta sección.')
            ->assertSee('Volver al panel')
            ->assertDontSee('User does not have the right roles');
    }

    public function test_el_mensaje_de_permisos_de_spatie_esta_en_espanol(): void
    {
        $this->assertSame('No tienes el rol necesario para acceder a esta sección.', __('User does not have the right roles.'));
        $this->assertSame('No tienes los permisos necesarios para realizar esta acción.', __('User does not have the right permissions.'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function paginas(): array
    {
        return [
            '403' => ['403', 'Acceso denegado', 'No tienes permiso'],
            '404' => ['404', 'Página no encontrada', 'no existe'],
            '419' => ['419', 'La página expiró', 'tu sesión caducó'],
            '500' => ['500', 'Error del servidor', 'Ocurrió un error inesperado'],
            '503' => ['503', 'Servicio en mantenimiento', 'está en mantenimiento'],
        ];
    }

    #[DataProvider('paginas')]
    public function test_sin_sesion_cada_pagina_de_error_ofrece_iniciar_sesion(string $codigo, string $titulo, string $mensaje): void
    {
        $vista = $this->paginaConSesion($codigo);

        $vista->assertSee("<title>{$titulo} | Finanzy</title>", false)
            ->assertSee($codigo)
            ->assertSee($mensaje)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('Iniciar sesión')
            ->assertDontSee('Volver al panel');
    }

    #[DataProvider('paginas')]
    public function test_con_sesion_cada_pagina_de_error_ofrece_volver_al_panel(string $codigo, string $titulo, string $mensaje): void
    {
        $this->actingAs(User::factory()->create());

        $vista = $this->paginaConSesion($codigo);

        $vista->assertSee($titulo)
            ->assertSee($mensaje)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('Volver al panel')
            ->assertDontSee('Iniciar sesión');
    }
}
