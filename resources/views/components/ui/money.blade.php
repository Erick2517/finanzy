{{--
    Formato monetario único de Finanzy (dólares): $1,234.56 / -$1,234.56.
    Se redondea a dos decimales antes de decidir el signo para no mostrar "-$0.00".
--}}
@props(['amount' => 0])

@php
    $valor = round((float) $amount, 2);
    $formateado = ($valor < 0 ? '-' : '') . '$' . number_format(abs($valor), 2, '.', ',');
@endphp

<span {{ $attributes->class(['text-nowrap']) }}>{{ $formateado }}</span>
