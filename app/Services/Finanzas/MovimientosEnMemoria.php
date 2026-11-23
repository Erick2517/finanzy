<?php

namespace App\Services\Finanzas;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fuente de datos que agrega una lista de movimientos en memoria.
 * La usan los datos simulados y las pruebas automatizadas.
 */
class MovimientosEnMemoria implements FuenteDatosFinancieros
{
    /** @var Collection<int, Movimiento> */
    private Collection $movimientos;

    /**
     * @param  iterable<Movimiento>  $movimientos
     */
    public function __construct(iterable $movimientos = [])
    {
        $this->movimientos = collect($movimientos)
            ->sortByDesc(fn (Movimiento $movimiento) => $movimiento->fecha->getTimestamp())
            ->values();
    }

    public function esSimulada(): bool
    {
        return false;
    }

    public function totalIngresos(Periodo $periodo): float
    {
        return $this->sumar($this->delPeriodo($periodo, TipoMovimiento::Ingreso));
    }

    public function totalGastos(Periodo $periodo): float
    {
        return $this->sumar($this->delPeriodo($periodo, TipoMovimiento::Gasto));
    }

    public function totalesPorMes(Periodo $periodo): array
    {
        return $this->totalesAgrupados($periodo, 'Y-m');
    }

    /**
     * Totales del periodo agrupados por la fecha con el formato indicado
     * ('Y-m-d' por día, 'Y-m' por mes, 'Y' por año). Los grupos sin movimientos se omiten.
     *
     * @return array<string, array{ingresos: float, gastos: float}>
     */
    public function totalesAgrupados(Periodo $periodo, string $formato): array
    {
        return $this->delPeriodo($periodo)
            ->groupBy(fn (Movimiento $movimiento) => $movimiento->fecha->format($formato))
            ->map(fn (Collection $delGrupo) => [
                'ingresos' => $this->sumar($delGrupo->where('tipo', TipoMovimiento::Ingreso)),
                'gastos' => $this->sumar($delGrupo->where('tipo', TipoMovimiento::Gasto)),
            ])
            ->all();
    }

    public function gastosPorCategoria(Periodo $periodo): array
    {
        return $this->delPeriodo($periodo, TipoMovimiento::Gasto)
            ->groupBy('categoria')
            ->map(fn (Collection $deCategoria) => $this->sumar($deCategoria))
            ->sortDesc()
            ->all();
    }

    public function movimientosRecientes(Periodo $periodo, int $limite): array
    {
        return $this->delPeriodo($periodo)->take($limite)->values()->all();
    }

    /**
     * En memoria la categoría no tiene clave propia: se usa el nombre normalizado.
     */
    public function categorias(): array
    {
        return $this->movimientos
            ->mapWithKeys(fn (Movimiento $movimiento) => [self::claveCategoria($movimiento->categoria) => $movimiento->categoria])
            ->sortBy(fn (string $nombre) => Str::lower(Str::ascii($nombre)))
            ->all();
    }

    public function movimientos(Periodo $periodo, ?string $categoria = null): array
    {
        return $this->delPeriodo($periodo)
            ->when($categoria, fn (Collection $movimientos) => $movimientos->filter(
                fn (Movimiento $movimiento) => self::claveCategoria($movimiento->categoria) === $categoria,
            ))
            ->values()
            ->all();
    }

    public static function claveCategoria(string $nombre): string
    {
        return Str::slug($nombre);
    }

    /**
     * @return Collection<int, Movimiento>
     */
    private function delPeriodo(Periodo $periodo, ?TipoMovimiento $tipo = null): Collection
    {
        return $this->movimientos
            ->filter(fn (Movimiento $movimiento) => $periodo->contiene($movimiento->fecha))
            ->when($tipo, fn (Collection $movimientos) => $movimientos->where('tipo', $tipo));
    }

    /**
     * @param  Collection<int, Movimiento>  $movimientos
     */
    private function sumar(Collection $movimientos): float
    {
        return round($movimientos->sum('monto'), 2);
    }
}
