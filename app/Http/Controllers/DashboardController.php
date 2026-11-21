<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltrarDashboardRequest;
use App\Services\Finanzas\Periodo;
use App\Services\Finanzas\ResumenFinanciero;
use Illuminate\View\View;

/**
 * Panel financiero: indicadores, evolución mensual, distribución de gastos y
 * movimientos recientes del periodo seleccionado.
 */
class DashboardController extends Controller
{
    public function __invoke(FiltrarDashboardRequest $request, ResumenFinanciero $resumen): View
    {
        return view('dashboard', [
            'resumen' => $resumen->generar($request->periodo()),
            'periodosPredefinidos' => Periodo::predefinidos(),
        ]);
    }
}
