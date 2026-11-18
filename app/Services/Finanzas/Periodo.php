<?php

namespace App\Services\Finanzas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Rango de fechas cerrado (de las 00:00:00 del inicio a las 23:59:59 del fin)
 * sobre el que se calculan los indicadores financieros.
 */
final readonly class Periodo
{
    public CarbonImmutable $inicio;

    public CarbonImmutable $fin;

    public function __construct(CarbonInterface|string $inicio, CarbonInterface|string $fin)
    {
        $this->inicio = CarbonImmutable::parse($inicio)->startOfDay();
        $this->fin = CarbonImmutable::parse($fin)->endOfDay();

        if ($this->inicio->greaterThan($this->fin)) {
            throw new InvalidArgumentException('La fecha de inicio del periodo no puede ser posterior a la fecha de fin.');
        }
    }

    public static function mesActual(?CarbonInterface $hoy = null): self
    {
        $hoy = CarbonImmutable::parse($hoy ?? now());

        return new self($hoy->startOfMonth(), $hoy->endOfMonth());
    }

    public static function mesAnterior(?CarbonInterface $hoy = null): self
    {
        $mes = CarbonImmutable::parse($hoy ?? now())->subMonthNoOverflow();

        return new self($mes->startOfMonth(), $mes->endOfMonth());
    }

    public static function anioActual(?CarbonInterface $hoy = null): self
    {
        $hoy = CarbonImmutable::parse($hoy ?? now());

        return new self($hoy->startOfYear(), $hoy->endOfYear());
    }

    /**
     * Los $cantidad meses naturales que terminan en el mes de $hasta (incluido).
     */
    public static function ultimosMeses(int $cantidad, CarbonInterface $hasta): self
    {
        $hasta = CarbonImmutable::parse($hasta);

        return new self($hasta->startOfMonth()->subMonthsNoOverflow($cantidad - 1), $hasta->endOfMonth());
    }

    /**
     * Periodos de acceso rápido del panel.
     *
     * @return array<string, self>
     */
    public static function predefinidos(?CarbonInterface $hoy = null): array
    {
        return [
            'Este mes' => self::mesActual($hoy),
            'Mes anterior' => self::mesAnterior($hoy),
            'Año actual' => self::anioActual($hoy),
        ];
    }

    /**
     * Comprende meses naturales completos (del día 1 al último día del mes).
     */
    public function esDeMesesCompletos(): bool
    {
        return $this->inicio->isSameDay($this->inicio->startOfMonth())
            && $this->fin->isSameDay($this->fin->endOfMonth());
    }

    public function cantidadDeMeses(): int
    {
        return ($this->fin->year * 12 + $this->fin->month) - ($this->inicio->year * 12 + $this->inicio->month) + 1;
    }

    public function cantidadDeDias(): int
    {
        return (int) $this->inicio->diffInDays($this->fin->startOfDay()) + 1;
    }

    /**
     * Periodo inmediatamente anterior de la misma duración, para calcular variaciones.
     * Si el periodo son meses completos, el anterior son los mismos meses naturales previos
     * (noviembre → octubre, aunque tengan distinto número de días); si no, los mismos días.
     */
    public function anterior(): self
    {
        $finAnterior = $this->inicio->subDay();

        if ($this->esDeMesesCompletos()) {
            return new self($this->inicio->subMonthsNoOverflow($this->cantidadDeMeses()), $finAnterior);
        }

        return new self($finAnterior->subDays($this->cantidadDeDias() - 1), $finAnterior);
    }

    public function contiene(CarbonInterface $fecha): bool
    {
        return $fecha->betweenIncluded($this->inicio, $this->fin);
    }

    /**
     * Primer día de cada mes que toca el periodo.
     *
     * @return list<CarbonImmutable>
     */
    public function meses(): array
    {
        $meses = [];

        for ($mes = $this->inicio->startOfMonth(); $mes->lessThanOrEqualTo($this->fin); $mes = $mes->addMonthNoOverflow()) {
            $meses[] = $mes;
        }

        return $meses;
    }

    public function esIgualA(self $otro): bool
    {
        return $this->inicio->equalTo($otro->inicio) && $this->fin->equalTo($otro->fin);
    }

    /**
     * Texto legible: "noviembre 2026", "2026" o "1 nov 2026 – 15 nov 2026".
     */
    public function etiqueta(): string
    {
        if ($this->esDeMesesCompletos() && $this->cantidadDeMeses() === 1) {
            return $this->inicio->locale('es')->translatedFormat('F Y');
        }

        if ($this->esDeMesesCompletos() && $this->inicio->month === 1 && $this->cantidadDeMeses() === 12) {
            return (string) $this->inicio->year;
        }

        $formato = fn (CarbonImmutable $fecha) => str_replace('.', '', $fecha->locale('es')->translatedFormat('j M Y'));

        return $formato($this->inicio).' – '.$formato($this->fin);
    }
}
