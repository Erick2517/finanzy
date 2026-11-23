<?php

namespace App\Services\Finanzas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Granularidad de un reporte: define cómo se elige el periodo (fechas, meses o años),
 * su valor por defecto, el rango máximo permitido y cómo se agrupa la evolución.
 */
enum TipoReporte: string
{
    case Diario = 'diario';
    case Mensual = 'mensual';
    case Anual = 'anual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Diario => 'Diario',
            self::Mensual => 'Mensual',
            self::Anual => 'Anual',
        };
    }

    /**
     * Formato de los campos de periodo y de la clave de agrupación.
     */
    public function formato(): string
    {
        return match ($this) {
            self::Diario => 'Y-m-d',
            self::Mensual => 'Y-m',
            self::Anual => 'Y',
        };
    }

    /**
     * Tipo de campo que usa el formulario: date, month o year (numérico).
     */
    public function tipoDeCampo(): string
    {
        return match ($this) {
            self::Diario => 'date',
            self::Mensual => 'month',
            self::Anual => 'year',
        };
    }

    /**
     * Unidad del periodo, en singular y plural (para etiquetas y mensajes).
     *
     * @return array{0: string, 1: string}
     */
    public function unidad(): array
    {
        return match ($this) {
            self::Diario => ['fecha', 'días'],
            self::Mensual => ['mes', 'meses'],
            self::Anual => ['año', 'años'],
        };
    }

    /**
     * Máximo de días, meses o años que puede abarcar el reporte.
     */
    public function maximoDeGrupos(): int
    {
        return match ($this) {
            self::Diario => 93,
            self::Mensual => 36,
            self::Anual => 10,
        };
    }

    /**
     * Días, meses o años que abarca el periodo.
     */
    public function cantidadDeGrupos(Periodo $periodo): int
    {
        return match ($this) {
            self::Diario => $periodo->cantidadDeDias(),
            self::Mensual => $periodo->cantidadDeMeses(),
            self::Anual => $periodo->fin->year - $periodo->inicio->year + 1,
        };
    }

    public function periodoPorDefecto(?CarbonInterface $hoy = null): Periodo
    {
        $hoy = CarbonImmutable::parse($hoy ?? now());

        return match ($this) {
            self::Diario => new Periodo($hoy->startOfMonth(), $hoy),
            self::Mensual => Periodo::ultimosMeses(6, $hoy),
            self::Anual => new Periodo($hoy->subYears(2)->startOfYear(), $hoy->endOfYear()),
        };
    }

    /**
     * Periodo a partir de los valores del formulario (ya validados con formato()).
     */
    public function periodoEntre(string $inicio, string $fin): Periodo
    {
        $desde = CarbonImmutable::createFromFormat('!'.$this->formato(), $inicio);
        $hasta = CarbonImmutable::createFromFormat('!'.$this->formato(), $fin);

        return match ($this) {
            self::Diario => new Periodo($desde, $hasta),
            self::Mensual => new Periodo($desde->startOfMonth(), $hasta->endOfMonth()),
            self::Anual => new Periodo($desde->startOfYear(), $hasta->endOfYear()),
        };
    }

    /**
     * Valor de un campo del formulario para una fecha.
     */
    public function valorDeCampo(CarbonInterface $fecha): string
    {
        return $fecha->format($this->formato());
    }

    /**
     * Grupos (días, meses o años) que abarca el periodo, con su clave y su etiqueta.
     *
     * @return list<array{clave: string, etiqueta: string}>
     */
    public function grupos(Periodo $periodo): array
    {
        $grupos = [];
        $paso = match ($this) {
            self::Diario => fn (CarbonImmutable $fecha) => $fecha->addDay(),
            self::Mensual => fn (CarbonImmutable $fecha) => $fecha->addMonthNoOverflow(),
            self::Anual => fn (CarbonImmutable $fecha) => $fecha->addYear(),
        };

        $fecha = match ($this) {
            self::Diario => $periodo->inicio,
            self::Mensual => $periodo->inicio->startOfMonth(),
            self::Anual => $periodo->inicio->startOfYear(),
        };

        for (; $fecha->lessThanOrEqualTo($periodo->fin); $fecha = $paso($fecha)) {
            $grupos[] = ['clave' => $fecha->format($this->formato()), 'etiqueta' => $this->etiquetaDeGrupo($fecha)];
        }

        return $grupos;
    }

    private function etiquetaDeGrupo(CarbonImmutable $fecha): string
    {
        return match ($this) {
            self::Diario => $fecha->format('d/m'),
            self::Mensual => ucfirst(str_replace('.', '', $fecha->locale('es')->translatedFormat('M Y'))),
            self::Anual => $fecha->format('Y'),
        };
    }
}
