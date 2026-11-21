<?php

namespace App\Services\Finanzas;

enum TipoMovimiento: string
{
    case Ingreso = 'ingreso';
    case Gasto = 'gasto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Gasto => 'Gasto',
        };
    }
}
