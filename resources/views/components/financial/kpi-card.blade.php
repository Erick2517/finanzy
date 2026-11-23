{{--
    $amount:          monto del indicador; los negativos se muestran como -$X en rojo.
    $percentage:      variación porcentual (opcional). Positiva, negativa o neutra (0.0%).
    $comparisonLabel: texto que acompaña a la variación.
    $invertTrend:     para indicadores en los que subir es desfavorable (p. ej. gastos):
                      un aumento se muestra en rojo y una disminución en verde.
                      La flecha siempre indica la dirección real del cambio.
    $format:          'currency' (por defecto) o 'percent' para indicadores que son un porcentaje.
    $emptyText:       texto que se muestra si $amount es null (valor no calculable).
    $caption:         nota breve bajo el valor (opcional).
--}}
@props([
    'title',
    'amount',
    'icon' => 'bi-cash-stack',
    'type' => 'primary',
    'percentage' => null,
    'comparisonLabel' => 'vs. periodo anterior',
    'invertTrend' => false,
    'format' => 'currency',
    'emptyText' => 'No disponible',
    'caption' => null,
])

@php
    $esNegativo = round((float) $amount, 2) < 0;

    // La tendencia se decide con el valor ya redondeado al decimal que se muestra.
    $variacion = $percentage !== null ? round((float) $percentage, 1) : null;

    $favorable = ['clase' => 'text-success', 'valoracion' => '(favorable)'];
    $desfavorable = ['clase' => 'text-danger', 'valoracion' => '(desfavorable)'];

    $tendencia = match (true) {
        $variacion === null => null,
        $variacion > 0 => ['icono' => 'bi-arrow-up-right', 'texto' => 'Aumento de', 'signo' => '+'] + ($invertTrend ? $desfavorable : $favorable),
        $variacion < 0 => ['icono' => 'bi-arrow-down-right', 'texto' => 'Disminución de', 'signo' => '-'] + ($invertTrend ? $favorable : $desfavorable),
        default => ['clase' => 'text-secondary', 'icono' => 'bi-dash', 'texto' => 'Sin variación:', 'signo' => '', 'valoracion' => ''],
    };
@endphp

<div class="card h-100 border-0 shadow-sm border-start border-4 border-{{ $type }}">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 bg-{{ $type }}-subtle text-{{ $type }} d-flex align-items-center justify-content-center flex-shrink-0"
             style="width: 48px; height: 48px;">
            <i class="bi {{ $icon }} fs-4" aria-hidden="true"></i>
        </div>

        <div class="flex-grow-1 min-w-0">
            <div class="text-muted small text-uppercase fw-semibold">{{ $title }}</div>
            @if ($amount === null)
                <span class="fs-5 fw-semibold d-block text-muted">{{ $emptyText }}</span>
            @elseif ($format === 'percent')
                <span class="fs-4 fw-bold d-block text-nowrap">{{ number_format((float) $amount, 1) }}%</span>
            @else
                <x-ui.money :amount="$amount" @class(['fs-4', 'fw-bold', 'd-block', 'text-danger' => $esNegativo]) />
            @endif

            @if ($caption)
                <div class="small text-muted">{{ $caption }}</div>
            @endif

            @if ($tendencia)
                <div class="small {{ $tendencia['clase'] }}">
                    <i class="bi {{ $tendencia['icono'] }}" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ $tendencia['texto'] }}</span>
                    {{ $tendencia['signo'] }}{{ number_format(abs($variacion), 1) }}%
                    @if ($tendencia['valoracion'])
                        <span class="visually-hidden">{{ $tendencia['valoracion'] }}</span>
                    @endif
                    @if ($comparisonLabel)
                        <span class="text-muted">{{ $comparisonLabel }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
