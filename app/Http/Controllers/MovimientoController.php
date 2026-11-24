<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarMovimientoRequest;
use App\Models\Categoria;
use App\Models\MovimientoFinanciero;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD de movimientos financieros compartidos por los usuarios de la empresa.
 */
class MovimientoController extends Controller
{
    public function ingresos(Request $request): View
    {
        return $this->index($request, 'ingreso');
    }

    public function gastos(Request $request): View
    {
        return $this->index($request, 'gasto');
    }

    public function create(Request $request): RedirectResponse
    {
        $ruta = $request->query('tipo') === 'gasto' ? 'gastos.index' : 'ingresos.index';

        return to_route($ruta);
    }

    public function store(GuardarMovimientoRequest $request): RedirectResponse
    {
        $datos = $request->safe()->only(['categoria_id', 'monto', 'fecha', 'concepto']);
        $tipo = $request->validated('tipo');

        DB::transaction(function () use ($datos, $request, $tipo): void {
            $movimiento = MovimientoFinanciero::create($datos + ['user_id' => $request->user()->id]);

            AuditLogger::log(
                'crear',
                $this->moduloDeTipo($tipo),
                "Registró movimiento #{$movimiento->id}: {$this->detalleMovimiento($movimiento)}.",
            );
        });

        return to_route($this->rutaDeTipo($tipo))
            ->with('success', ucfirst($tipo).' registrado correctamente.');
    }

    public function update(GuardarMovimientoRequest $request, MovimientoFinanciero $movimiento): RedirectResponse
    {
        abort_unless($movimiento->activo, 404);
        $tipo = $request->validated('tipo');

        DB::transaction(function () use ($movimiento, $request, $tipo): void {
            $detalleAnterior = $this->detalleMovimiento($movimiento);
            $movimiento->update($request->safe()->only(['categoria_id', 'monto', 'fecha', 'concepto']));
            $movimiento->unsetRelation('categoria');
            $detalleActual = $this->detalleMovimiento($movimiento);

            AuditLogger::log(
                'editar',
                $this->moduloDeTipo($tipo),
                "Editó movimiento #{$movimiento->id}. Antes: {$detalleAnterior}. Después: {$detalleActual}.",
            );
        });

        return to_route($this->rutaDeTipo($tipo))
            ->with('success', 'Movimiento actualizado correctamente.');
    }

    public function destroy(MovimientoFinanciero $movimiento): RedirectResponse
    {
        abort_unless($movimiento->activo, 404);
        $tipo = $movimiento->categoria->tipo;
        $detalle = $this->detalleMovimiento($movimiento);

        DB::transaction(function () use ($movimiento, $tipo, $detalle): void {
            $movimiento->update(['activo' => false]);

            AuditLogger::log(
                'desactivar',
                $this->moduloDeTipo($tipo),
                "Desactivó movimiento #{$movimiento->id}: {$detalle}.",
            );
        });

        return to_route($this->rutaDeTipo($tipo))
            ->with('success', 'Movimiento desactivado; su historial se conservó.');
    }

    public function activar(MovimientoFinanciero $movimiento): RedirectResponse
    {
        abort_if($movimiento->activo, 404);
        $tipo = $movimiento->categoria->tipo;
        $detalle = $this->detalleMovimiento($movimiento);

        DB::transaction(function () use ($movimiento, $tipo, $detalle): void {
            $movimiento->update(['activo' => true]);

            AuditLogger::log(
                'activar',
                $this->moduloDeTipo($tipo),
                "Reactivó movimiento #{$movimiento->id}: {$detalle}.",
            );
        });

        return to_route($this->rutaDeTipo($tipo))
            ->with('success', 'Movimiento reactivado correctamente.');
    }

    private function index(Request $request, string $tipo): View
    {
        $fechaHastaRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('fecha_desde')) {
            $fechaHastaRules[] = 'after_or_equal:fecha_desde';
        }

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('tipo', $tipo)],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => $fechaHastaRules,
            'estado' => ['nullable', Rule::in(['activos', 'inactivos', 'todos'])],
        ]);
        $estado = $filtros['estado'] ?? 'activos';

        $consulta = MovimientoFinanciero::query()
            ->with(['categoria', 'usuario'])
            ->whereHas('categoria', fn (Builder $consulta) => $consulta->where('tipo', $tipo));

        if ($estado !== 'todos') {
            $consulta->where('activo', $estado === 'activos');
        }

        $consulta
            ->when($filtros['q'] ?? null, fn (Builder $consulta, string $busqueda) => $consulta->where('concepto', 'like', '%'.$busqueda.'%'))
            ->when($filtros['categoria_id'] ?? null, fn (Builder $consulta, int $categoriaId) => $consulta->where('categoria_id', $categoriaId))
            ->when($filtros['fecha_desde'] ?? null, fn (Builder $consulta, string $fecha) => $consulta->whereDate('fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn (Builder $consulta, string $fecha) => $consulta->whereDate('fecha', '<=', $fecha));

        $categorias = Categoria::query()
            ->where('tipo', $tipo)
            ->orderBy('nombre')
            ->pluck('nombre', 'id');

        return view('movimientos.index', [
            'movimientos' => $consulta->orderByDesc('fecha')->orderByDesc('id')->paginate(15)->withQueryString(),
            'categorias' => $categorias,
            'filtros' => $filtros + ['estado' => $estado],
            'tipo' => $tipo,
            'etiquetaTipo' => ucfirst($tipo),
            'titulo' => $tipo === 'ingreso' ? 'Ingresos' : 'Gastos',
            'rutaIndex' => $this->rutaDeTipo($tipo),
        ]);
    }

    private function rutaDeTipo(string $tipo): string
    {
        return $tipo === 'ingreso' ? 'ingresos.index' : 'gastos.index';
    }

    private function moduloDeTipo(string $tipo): string
    {
        return $tipo === 'ingreso' ? 'ingresos' : 'gastos';
    }

    private function detalleMovimiento(MovimientoFinanciero $movimiento): string
    {
        $movimiento->loadMissing('categoria');

        return "concepto «{$movimiento->concepto}», categoría «{$movimiento->categoria->nombre}», monto {$movimiento->monto}, fecha {$movimiento->fecha->toDateString()}";
    }
}
