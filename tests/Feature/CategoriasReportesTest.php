<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriasReportesTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitado_no_puede_acceder_a_modulos(): void
    {
        $this->get('/categorias')->assertRedirect('/login');
        $this->get('/reportes')->assertRedirect('/login');
    }

    public function test_usuario_crea_y_consulta_su_categoria(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('categorias.store'), ['nombre' => 'Salario', 'tipo' => 'ingreso'])->assertRedirect(route('categorias.index'));
        $this->assertDatabaseHas('categorias', ['user_id' => $user->id, 'nombre' => 'Salario']);
        $this->actingAs($user)->get(route('categorias.index'))->assertSee('Salario');
    }

    public function test_usuario_no_puede_modificar_categoria_ajena(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $categoria = Categoria::create(['user_id' => $owner->id, 'nombre' => 'Privada', 'tipo' => 'gasto']);
        $this->actingAs($other)->put(route('categorias.update', $categoria), ['nombre' => 'Cambiada', 'tipo' => 'gasto'])->assertForbidden();
        $this->actingAs($other)->delete(route('categorias.destroy', $categoria))->assertForbidden();
    }

    public function test_reportes_exportan_csv_y_pdf(): void
    {
        // El nombre del archivo refleja los filtros: por defecto, reporte mensual de los últimos 6 meses.
        $this->travelTo('2026-11-20 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('reportes.csv'))->assertOk()->assertDownload('reporte-mensual-2026-06-a-2026-11.csv');
        $this->actingAs($user)->get(route('reportes.pdf'))->assertOk()->assertDownload('reporte-mensual-2026-06-a-2026-11.pdf');
    }

    public function test_permite_registrar_movimiento_con_categoria_de_otro_usuario(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $categoria = Categoria::create(['user_id' => $owner->id, 'nombre' => 'Privada', 'tipo' => 'gasto']);

        $this->actingAs($other)->post(route('movimientos.store'), [
            'tipo' => 'gasto',
            'categoria_id' => $categoria->id,
            'monto' => 15,
            'fecha' => '2026-11-12',
            'concepto' => 'Prueba',
        ])->assertRedirect(route('gastos.index'));

        $this->assertDatabaseHas('movimientos_financieros', [
            'user_id' => $other->id,
            'categoria_id' => $categoria->id,
            'concepto' => 'Prueba',
            'activo' => true,
        ]);
    }

    public function test_no_permite_categoria_de_tipo_distinto_al_movimiento(): void
    {
        $user = User::factory()->create();
        $categoria = Categoria::create(['user_id' => $user->id, 'nombre' => 'Salario', 'tipo' => 'ingreso']);

        $this->actingAs($user)->post(route('movimientos.store'), [
            'tipo' => 'gasto',
            'categoria_id' => $categoria->id,
            'monto' => 15,
            'fecha' => '2026-11-12',
            'concepto' => 'Prueba',
        ])->assertSessionHasErrors('categoria_id');

        $this->assertDatabaseCount('movimientos_financieros', 0);
    }
}
