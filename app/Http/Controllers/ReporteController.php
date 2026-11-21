<?php
namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\MovimientoFinanciero;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReporteController extends Controller {
    private function filtros(Request $request): array {
        return $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'tipo' => ['nullable', Rule::in(['ingreso', 'gasto'])],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('user_id', $request->user()->id)],
        ]);
    }
    private function consulta(Request $request, array $filtros) {
        return MovimientoFinanciero::query()->where('user_id', $request->user()->id)->with('categoria')
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $v) => $q->where('categoria_id', $v))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->whereHas('categoria', fn ($c) => $c->where('tipo', $v)));
    }
    private function resumen($movimientos): array {
        $ingresos = $movimientos->filter(fn ($m) => $m->categoria->tipo === 'ingreso')->sum('monto');
        $gastos = $movimientos->filter(fn ($m) => $m->categoria->tipo === 'gasto')->sum('monto');
        return ['ingresos' => $ingresos, 'gastos' => $gastos, 'balance' => $ingresos - $gastos];
    }
    public function index(Request $request) {
        $filtros = $this->filtros($request);
        $movimientos = $this->consulta($request, $filtros)->orderByDesc('fecha')->orderByDesc('id')->paginate(15)->withQueryString();
        $resumen = $this->resumen($this->consulta($request, $filtros)->get());
        $categorias = Categoria::where('user_id', $request->user()->id)->orderBy('nombre')->get();
        return view('reportes.index', compact('movimientos', 'resumen', 'categorias', 'filtros'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('user_id', $request->user()->id)],
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'fecha' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:255'],
        ]);
        MovimientoFinanciero::create($data + ['user_id' => $request->user()->id]);
        return redirect()->route('reportes.index')->with('success', 'Movimiento registrado.');
    }
    public function pdf(Request $request) {
        $filtros = $this->filtros($request);
        $movimientos = $this->consulta($request, $filtros)->orderByDesc('fecha')->get();
        $resumen = $this->resumen($movimientos);
        return Pdf::loadView('reportes.pdf', compact('movimientos', 'resumen', 'filtros'))->setPaper('a4', 'landscape')->download('finanzy-reporte.pdf');
    }
    public function csv(Request $request) {
        $filtros = $this->filtros($request);
        $consulta = $this->consulta($request, $filtros)->orderByDesc('fecha');
        return response()->streamDownload(function () use ($consulta) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha', 'Tipo', 'Categoría', 'Concepto', 'Monto'], ';');
            foreach ($consulta->cursor() as $movimiento) {
                fputcsv($salida, [$movimiento->fecha->format('Y-m-d'), $movimiento->categoria->tipo, $movimiento->categoria->nombre, $movimiento->concepto, $movimiento->monto], ';');
            }
            fclose($salida);
        }, 'finanzy-reporte.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
