<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarMovimientoRequest;
use App\Models\Categoria;
use App\Models\MovimientoFinanciero;
use App\Services\Finanzas\TipoMovimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Registro provisional de movimientos financieros (ingresos y gastos) mientras no
 * existan los módulos definitivos de ingresos y gastos. Lo registrado alimenta el
 * panel financiero y los reportes (MovimientosFinancierosEloquent).
 */
class MovimientoController extends Controller
{
    public function create(Request $request): View
    {
        $categorias = Categoria::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Categoria $categoria) => [
                $categoria->id => sprintf('%s (%s)', $categoria->nombre, TipoMovimiento::from($categoria->tipo)->etiqueta()),
            ]);

        return view('movimientos.create', ['categorias' => $categorias]);
    }

    public function store(RegistrarMovimientoRequest $request): RedirectResponse
    {
        MovimientoFinanciero::create($request->validated() + ['user_id' => $request->user()->id]);

        return to_route('movimientos.create')->with('success', 'Movimiento registrado. Ya se refleja en el panel y en los reportes.');
    }
}
