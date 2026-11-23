{{--
    Barra de filtros por periodo y categoría (formulario GET).

    $categories:       arreglo o colección id => nombre (ej. Category::pluck('nombre', 'id')).
                       Si está vacía, el selector de categoría no se muestra.
    $actionUrl:        URL a la que se envía el formulario. Por defecto, la página actual.
    $clearUrl:         destino del botón "Limpiar filtros". Por defecto, $actionUrl sin parámetros.
    $movementTypes:    arreglo valor => etiqueta (ej. ['ingreso' => 'Solo ingresos']) para el
                       campo tipo_movimiento. Si está vacío, el selector no se muestra.
    $startDate, $endDate, $selectedCategory, $selectedMovementType: valores iniciales.
    $showPeriods:      muestra los accesos rápidos "Este mes", "Mes anterior" y "Año actual"
                       (solo con $inputType 'date').
    $inputType:        campos de periodo: 'date' (fechas), 'month' (AAAA-MM) o 'year' (AAAA).
    $startLabel, $endLabel, $submitLabel: textos de los campos y del botón principal.
    $loadingText:      si se indica, el botón principal muestra este texto con un indicador de
                       carga al enviar y no admite un segundo envío (resources/js/ui/estado-carga.js).
    $slot:             campos adicionales del formulario (p. ej. <input type="hidden">).

    Valor de cada campo: entrada anterior (tras un error de validación) > parámetro de la URL
    > valor inicial recibido. Así se conservan los filtros al enviar el formulario.
--}}
@props([
    'categories' => [],
    'actionUrl' => null,
    'clearUrl' => null,
    'startDate' => null,
    'endDate' => null,
    'selectedCategory' => null,
    'movementTypes' => [],
    'selectedMovementType' => null,
    'showPeriods' => true,
    'inputType' => 'date',
    'startLabel' => 'Desde',
    'endLabel' => 'Hasta',
    'submitLabel' => 'Filtrar',
    'loadingText' => null,
])

@php
    $atributosDeCampo = match ($inputType) {
        'month' => ['type' => 'month', 'placeholder' => 'AAAA-MM'],
        'year' => ['type' => 'number', 'min' => '2000', 'max' => '2100', 'step' => '1', 'inputmode' => 'numeric', 'placeholder' => 'AAAA'],
        default => ['type' => 'date'],
    };
    $atributosDeCampo = new \Illuminate\View\ComponentAttributeBag($atributosDeCampo);

    $showPeriods = $showPeriods && $inputType === 'date';

    $destino = $actionUrl ?? url()->current();
    $destinoLimpio = $clearUrl ?? strtok($destino, '?');

    $valor = fn (string $campo, $inicial) => old($campo, request()->query($campo, $inicial));

    $fechaInicio = $valor('fecha_inicio', $startDate);
    $fechaFin = $valor('fecha_fin', $endDate);
    $categoria = $valor('categoria_id', $selectedCategory);

    $tipoMovimiento = $valor('tipo_movimiento', $selectedMovementType);

    $hayCategorias = collect($categories)->isNotEmpty();
    $hayTipos = collect($movementTypes)->isNotEmpty();

    // Ancho de cada columna según los selectores opcionales que se muestran.
    $columnas = match ((int) $hayCategorias + (int) $hayTipos) {
        0 => ['fecha' => 'col-md-4', 'selector' => '', 'acciones' => 'col-md-4'],
        1 => ['fecha' => 'col-md-3', 'selector' => 'col-md-3', 'acciones' => 'col-md-3'],
        default => ['fecha' => 'col-md-6 col-xl-2', 'selector' => 'col-md-6 col-xl-3', 'tipo' => 'col-md-6 col-xl-2', 'acciones' => 'col-md-6 col-xl-3'],
    };
    $columnas['tipo'] ??= $columnas['selector'];

    $hoy = now();
    $mesAnterior = $hoy->copy()->subMonthNoOverflow();

    $periodos = [
        'Este mes' => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()],
        'Mes anterior' => [$mesAnterior->copy()->startOfMonth(), $mesAnterior->copy()->endOfMonth()],
        'Año actual' => [$hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()],
    ];

    $periodos = collect($periodos)->map(function (array $rango) use ($destinoLimpio, $hayCategorias, $categoria, $fechaInicio, $fechaFin) {
        [$inicio, $fin] = [$rango[0]->toDateString(), $rango[1]->toDateString()];

        $parametros = array_filter([
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'categoria_id' => $hayCategorias ? $categoria : null,
        ], fn ($valor) => filled($valor));

        return [
            'url' => $destinoLimpio . '?' . http_build_query($parametros),
            'activo' => $fechaInicio === $inicio && $fechaFin === $fin,
        ];
    });
@endphp

{{-- data-validar: validaciones del cliente (resources/js/ui/validaciones.js); el servidor valida igualmente --}}
<form action="{{ $destino }}" method="GET" class="card card-body border-0 shadow-sm mb-4" data-validar novalidate>
    {{ $slot }}

    <div class="row g-3 align-items-start">
        <div class="col-12 col-sm-6 {{ $columnas['fecha'] }}">
            <label for="fecha_inicio" class="form-label fw-semibold small">{{ $startLabel }}</label>
            <input {{ $atributosDeCampo }} id="fecha_inicio" name="fecha_inicio" value="{{ $fechaInicio }}"
                   data-requerido-con="fecha_fin"
                   @class(['form-control', 'is-invalid' => $errors->has('fecha_inicio')])
                   @error('fecha_inicio') aria-describedby="fecha_inicio-error" aria-invalid="true" @enderror>
            @error('fecha_inicio')
                <div id="fecha_inicio-error" class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12 col-sm-6 {{ $columnas['fecha'] }}">
            <label for="fecha_fin" class="form-label fw-semibold small">{{ $endLabel }}</label>
            <input {{ $atributosDeCampo }} id="fecha_fin" name="fecha_fin" value="{{ $fechaFin }}"
                   data-requerido-con="fecha_inicio" data-fecha-desde="fecha_inicio"
                   @class(['form-control', 'is-invalid' => $errors->has('fecha_fin')])
                   @error('fecha_fin') aria-describedby="fecha_fin-error" aria-invalid="true" @enderror>
            @error('fecha_fin')
                <div id="fecha_fin-error" class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        @if ($hayCategorias)
            <div class="col-12 {{ $columnas['selector'] }}">
                <label for="categoria_id" class="form-label fw-semibold small">Categoría</label>
                <select id="categoria_id" name="categoria_id"
                        @class(['form-select', 'is-invalid' => $errors->has('categoria_id')])
                        @error('categoria_id') aria-describedby="categoria_id-error" aria-invalid="true" @enderror>
                    <option value="">Todas las categorías</option>
                    @foreach ($categories as $id => $nombre)
                        <option value="{{ $id }}" @selected((string) $id === (string) $categoria)>{{ $nombre }}</option>
                    @endforeach
                </select>
                @error('categoria_id')
                    <div id="categoria_id-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        @if ($hayTipos)
            <div class="col-12 {{ $columnas['tipo'] }}">
                <label for="tipo_movimiento" class="form-label fw-semibold small">Movimientos</label>
                <select id="tipo_movimiento" name="tipo_movimiento"
                        @class(['form-select', 'is-invalid' => $errors->has('tipo_movimiento')])
                        @error('tipo_movimiento') aria-describedby="tipo_movimiento-error" aria-invalid="true" @enderror>
                    <option value="">Ingresos y gastos</option>
                    @foreach ($movementTypes as $valorTipo => $etiquetaTipo)
                        <option value="{{ $valorTipo }}" @selected((string) $valorTipo === (string) $tipoMovimiento)>{{ $etiquetaTipo }}</option>
                    @endforeach
                </select>
                @error('tipo_movimiento')
                    <div id="tipo_movimiento-error" class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="col-12 {{ $columnas['acciones'] }}">
            {{-- Etiqueta invisible: alinea los botones con los campos aunque estos muestren errores --}}
            <span class="form-label small d-none d-md-block invisible" aria-hidden="true">Acciones</span>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" @if ($loadingText) data-loading-text="{{ $loadingText }}" @endif>
                    <i class="bi bi-funnel" aria-hidden="true"></i> {{ $submitLabel }}
                </button>
                <a href="{{ $destinoLimpio }}" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros">
                    <i class="bi bi-eraser" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    @if ($showPeriods)
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
            <span class="small text-muted fw-semibold">Periodo:</span>
            @foreach ($periodos as $etiqueta => $periodo)
                <a href="{{ $periodo['url'] }}"
                   @class(['btn btn-sm', 'btn-primary' => $periodo['activo'], 'btn-outline-primary' => ! $periodo['activo']])
                   @if ($periodo['activo']) aria-current="true" @endif>
                    {{ $etiqueta }}
                </a>
            @endforeach
        </div>
    @endif
</form>
