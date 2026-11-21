{{--
    Tarjeta de gráfico.

    Modo gestionado (recomendado): al indicar $type, el sistema de gráficos de Finanzy
    (resources/js/charts) dibuja el gráfico; la vista no necesita JavaScript propio.
        $type:     'bar' | 'line' | 'doughnut'
        $labels:   etiquetas del eje X o de los sectores
        $datasets: [['label' => 'Ingresos', 'data' => [...], 'color' => 'success'], ...]
                   color: primary, success, danger, warning, info, indigo, orange, pink,
                   secondary o un hexadecimal. En la dona, 'colors' asigna un color por sector.
        $format:   'currency' (dólares), 'number' o 'percent'
        $stacked:  barras o líneas apiladas
    Si no hay etiquetas o todos los valores son nulos o cero, se muestra el estado vacío.

    Modo manual (compatibilidad): sin $type solo se dibuja el <canvas> con id $chartId,
    y la vista crea el gráfico con window.Chart.

    $empty:        fuerza el estado vacío.
    $emptyMessage: texto del estado vacío.
--}}
@props([
    'title' => null,
    'chartId',
    'height' => '300px',
    'empty' => false,
    'emptyMessage' => 'No hay datos para mostrar en este periodo.',
    'type' => null,
    'labels' => [],
    'datasets' => [],
    'format' => 'currency',
    'stacked' => false,
])

@php
    $gestionado = $type !== null;

    $etiquetas = collect($labels)->map(fn ($etiqueta) => (string) $etiqueta)->values()->all();

    $series = collect($datasets)->map(fn (array $serie) => array_filter([
        'label' => (string) ($serie['label'] ?? ''),
        'data' => collect($serie['data'] ?? [])->map(fn ($valor) => $valor === null ? null : (float) $valor)->values()->all(),
        'color' => $serie['color'] ?? null,
        'colors' => $serie['colors'] ?? null,
    ], fn ($valor) => $valor !== null))->values()->all();

    $sinValores = collect($series)->pluck('data')->flatten()
        ->every(fn ($valor) => $valor === null || (float) $valor === 0.0);

    $sinDatos = $empty || ($gestionado && ($etiquetas === [] || $sinValores));

    $especificacion = [
        'type' => $type,
        'labels' => $etiquetas,
        'datasets' => $series,
        'format' => $format,
        'stacked' => (bool) $stacked,
    ];
@endphp

<div class="card h-100 border-0 shadow-sm">
    @if ($title)
        <div class="card-header bg-white fw-semibold">{{ $title }}</div>
    @endif

    <div class="card-body">
        <div class="position-relative w-100" style="height: {{ $height }};">
            @if ($gestionado)
                <canvas id="{{ $chartId }}" role="img" aria-label="{{ $title ?? 'Gráfico financiero' }}"
                        data-chart="{{ json_encode($especificacion, JSON_UNESCAPED_UNICODE) }}"
                        @if ($sinDatos) hidden @endif></canvas>

                {{-- El sistema de gráficos alterna este bloque con el <canvas> --}}
                <div class="h-100" data-chart-empty @unless ($sinDatos) hidden @endunless>
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted">
                        <i class="bi bi-bar-chart fs-1 mb-2" aria-hidden="true"></i>
                        <p class="mb-0 small">{{ $emptyMessage }}</p>
                    </div>
                </div>

                @unless ($sinDatos)
                    {{-- Alternativa accesible: los mismos datos en una tabla para lectores de pantalla.
                         La clase va en un contenedor: una <table> ignora el ancho de 1 px de
                         .visually-hidden y provocaría desplazamiento horizontal en móvil. --}}
                    <div class="visually-hidden">
                    <table>
                        <caption>{{ $title ?? 'Datos del gráfico' }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Concepto</th>
                                @foreach ($series as $serie)
                                    <th scope="col">{{ $serie['label'] !== '' ? $serie['label'] : 'Valor' }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($etiquetas as $indice => $etiqueta)
                                <tr>
                                    <th scope="row">{{ $etiqueta }}</th>
                                    @foreach ($series as $serie)
                                        @php($valor = $serie['data'][$indice] ?? null)
                                        <td>
                                            @if ($valor === null)
                                                —
                                            @elseif ($format === 'percent')
                                                {{ number_format($valor, 1) }}%
                                            @elseif ($format === 'number')
                                                {{ number_format($valor) }}
                                            @else
                                                <x-ui.money :amount="$valor" />
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endunless
            @elseif ($empty)
                <div class="h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted">
                    <i class="bi bi-bar-chart fs-1 mb-2" aria-hidden="true"></i>
                    <p class="mb-0 small">{{ $emptyMessage }}</p>
                </div>
            @else
                <canvas id="{{ $chartId }}" role="img" aria-label="{{ $title ?? 'Gráfico financiero' }}"></canvas>
            @endif
        </div>
    </div>
</div>
