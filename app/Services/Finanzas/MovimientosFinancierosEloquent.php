<?php

namespace App\Services\Finanzas;

use App\Models\Categoria;
use App\Models\MovimientoFinanciero;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Str;

/**
 * Datos financieros reales: los movimientos y categorías que cada usuario registra
 * (tablas movimientos_financieros y categorias).
 *
 * Solo consulta los registros del usuario autenticado; sin sesión no devuelve nada.
 * El tipo (ingreso o gasto) de cada movimiento es el de su categoría. Los totales y
 * agrupaciones se delegan en MovimientosEnMemoria para no duplicar los cálculos.
 */
class MovimientosFinancierosEloquent implements FuenteDatosFinancieros
{
    /** Los movimientos registrados no tienen flujo de aprobación: todos están confirmados. */
    public const ESTADO = 'Confirmado';

    /** @var array<string, MovimientosEnMemoria> */
    private array $consultas = [];

    public function __construct(private Guard $auth) {}

    public function esSimulada(): bool
    {
        return false;
    }

    public function totalIngresos(Periodo $periodo): float
    {
        return $this->delPeriodo($periodo)->totalIngresos($periodo);
    }

    public function totalGastos(Periodo $periodo): float
    {
        return $this->delPeriodo($periodo)->totalGastos($periodo);
    }

    public function totalesPorMes(Periodo $periodo): array
    {
        return $this->delPeriodo($periodo)->totalesPorMes($periodo);
    }

    public function gastosPorCategoria(Periodo $periodo): array
    {
        return $this->delPeriodo($periodo)->gastosPorCategoria($periodo);
    }

    public function movimientosRecientes(Periodo $periodo, int $limite): array
    {
        return $this->delPeriodo($periodo)->movimientosRecientes($periodo, $limite);
    }

    /**
     * Categorías del usuario: id => nombre. Si un nombre se repite (p. ej. «Otros» de
     * ingreso y de gasto), se añade el tipo para distinguirlas.
     */
    public function categorias(): array
    {
        $usuario = $this->auth->id();

        if ($usuario === null) {
            return [];
        }

        $categorias = Categoria::query()->where('user_id', $usuario)->get(['id', 'nombre', 'tipo']);
        $repetidos = $categorias->countBy(fn (Categoria $categoria) => Str::lower($categoria->nombre))->filter(fn (int $veces) => $veces > 1);

        return $categorias
            ->mapWithKeys(fn (Categoria $categoria) => [
                (string) $categoria->id => $repetidos->has(Str::lower($categoria->nombre))
                    ? sprintf('%s (%s)', $categoria->nombre, TipoMovimiento::from($categoria->tipo)->etiqueta())
                    : $categoria->nombre,
            ])
            ->sortBy(fn (string $nombre) => Str::lower(Str::ascii($nombre)))
            ->all();
    }

    public function movimientos(Periodo $periodo, ?string $categoria = null): array
    {
        return $this->delPeriodo($periodo, $categoria)->movimientos($periodo);
    }

    /**
     * Movimientos del usuario en el periodo (y de una categoría, si se indica), cargados
     * una sola vez por petición aunque el panel pida varios indicadores.
     */
    private function delPeriodo(Periodo $periodo, ?string $categoria = null): MovimientosEnMemoria
    {
        $clave = implode('|', [$periodo->inicio->toDateString(), $periodo->fin->toDateString(), $categoria]);

        return $this->consultas[$clave] ??= new MovimientosEnMemoria($this->consultar($periodo, $categoria));
    }

    /**
     * @return list<Movimiento>
     */
    private function consultar(Periodo $periodo, ?string $categoria): array
    {
        $usuario = $this->auth->id();

        if ($usuario === null) {
            return [];
        }

        // Límite superior exclusivo (día siguiente): incluye el último día aunque la
        // base de datos guarde la fecha con hora (SQLite).
        return MovimientoFinanciero::query()
            ->where('user_id', $usuario)
            ->where('fecha', '>=', $periodo->inicio->toDateString())
            ->where('fecha', '<', $periodo->fin->addDay()->toDateString())
            ->when($categoria, fn ($consulta, string $id) => $consulta->where('categoria_id', $id))
            ->with('categoria:id,nombre,tipo')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MovimientoFinanciero $movimiento) => new Movimiento(
                $movimiento->fecha->toImmutable()->startOfDay(),
                $movimiento->concepto,
                $movimiento->categoria->nombre,
                TipoMovimiento::from($movimiento->categoria->tipo),
                (float) $movimiento->monto,
                self::ESTADO,
            ))
            ->all();
    }
}
