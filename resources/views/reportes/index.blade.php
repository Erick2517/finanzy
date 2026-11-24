{{--
    Reportes financieros. Los datos llegan preparados desde ReporteController
    (App\Services\Finanzas\ReporteFinanciero); esta vista no calcula ni consulta nada.
--}}
@php
    $filtros = $reporte['filtros'];
    $tipo = $filtros->tipo;
    [$unidad] = $tipo->unidad();
    $etiquetaPeriodo = ucfirst($filtros->periodo->etiqueta());
@endphp

<x-layout.app title="Reportes financieros" activeRoute="reportes">
    <x-layout.page-header title="Reportes financieros" subtitle="Consulta ingresos, gastos y resultados por día, mes o año, y exporta el reporte.">
        <a href="{{ route('ingresos.index') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> Registrar movimiento
        </a>
        <a href="{{ route('reportes.pdf', $filtros->parametros()) }}" class="btn btn-outline-primary" data-loading-text="Generando PDF…">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Exportar PDF
        </a>
        <a href="{{ route('reportes.csv', $filtros->parametros()) }}" class="btn btn-outline-success" title="Archivo CSV compatible con Excel">
            <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Exportar CSV (Excel)
        </a>
    </x-layout.page-header>

    @if ($reporte['esSimulado'])
        <x-ui.alert type="info" :dismissible="false" class="mb-4">
            <strong>Datos de demostración.</strong>
            Las cifras de este reporte son simuladas y se sustituirán por los ingresos y gastos reales
            cuando estén disponibles los módulos correspondientes.
        </x-ui.alert>
    @endif

    <nav aria-label="Tipo de reporte" class="mb-3">
        <ul class="nav nav-pills gap-2">
            @foreach ($tipos as $opcion)
                <li class="nav-item">
                    <a @class(['nav-link', 'active' => $opcion === $tipo, 'bg-white border' => $opcion !== $tipo])
                       href="{{ route('reportes.index', array_filter(['tipo' => $opcion->value, 'categoria_id' => $filtros->categoria, 'tipo_movimiento' => $filtros->tipoMovimiento?->value])) }}"
                       @if ($opcion === $tipo) aria-current="page" @endif>
                        {{ $opcion->etiqueta() }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <x-financial.filter-bar
        :action-url="route('reportes.index')"
        :clear-url="route('reportes.index', ['tipo' => $tipo->value])"
        :categories="$categorias"
        :input-type="$tipo->tipoDeCampo()"
        :start-label="ucfirst($unidad) . ' inicial'"
        :end-label="ucfirst($unidad) . ' final'"
        :start-date="$tipo->valorDeCampo($filtros->periodo->inicio)"
        :end-date="$tipo->valorDeCampo($filtros->periodo->fin)"
        :selected-category="$filtros->categoria"
        :movement-types="['ingreso' => 'Solo ingresos', 'gasto' => 'Solo gastos']"
        :selected-movement-type="$filtros->tipoMovimiento?->value"
        :show-periods="false"
        submit-label="Generar reporte"
        loading-text="Generando reporte…">
        <input type="hidden" name="tipo" value="{{ $tipo->value }}">
    </x-financial.filter-bar>

    <p class="text-muted small mb-3">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        Reporte {{ mb_strtolower($tipo->etiqueta()) }} · {{ $etiquetaPeriodo }} ·
        Categoría: {{ $filtros->nombreCategoria ?? 'Todas' }} ·
        Movimientos: {{ $filtros->etiquetaMovimientos() }}
    </p>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Total de ingresos" :amount="$reporte['ingresos']" icon="bi-arrow-down-circle" type="success" />
        </div>
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Total de gastos" :amount="$reporte['gastos']" icon="bi-arrow-up-circle" type="danger" />
        </div>
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Resultado financiero" :amount="$reporte['resultado']" icon="bi-wallet2" type="primary"
                caption="Ingresos − gastos" />
        </div>
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Gastos sobre ingresos" :amount="$reporte['porcentajeGastos']" format="percent"
                icon="bi-percent" type="warning"
                :caption="$reporte['porcentajeGastos'] === null ? 'Sin ingresos en el periodo' : 'De los ingresos del periodo'" />
        </div>
    </div>

    @if ($reporte['evolucion'])
        <div class="mb-4">
            <x-financial.chart-card
                :title="'Evolución ' . ['diario' => 'diaria', 'mensual' => 'mensual', 'anual' => 'anual'][$tipo->value] . ' de ingresos y gastos'"
                chart-id="graficoEvolucionReporte"
                :type="$tipo === \App\Services\Finanzas\TipoReporte::Diario ? 'line' : 'bar'"
                height="300px"
                :labels="$reporte['evolucion']['etiquetas']"
                :datasets="[
                    ['label' => 'Ingresos', 'data' => $reporte['evolucion']['ingresos'], 'color' => 'success'],
                    ['label' => 'Gastos', 'data' => $reporte['evolucion']['gastos'], 'color' => 'danger'],
                ]"
                empty-message="No hay ingresos ni gastos en este periodo. Prueba con otro periodo o categoría." />
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <x-financial.chart-card
                title="Comparación de ingresos y gastos"
                chart-id="graficoComparacionReporte"
                type="bar"
                height="300px"
                :labels="[$etiquetaPeriodo]"
                :datasets="[
                    ['label' => 'Ingresos', 'data' => [$reporte['ingresos']], 'color' => 'success'],
                    ['label' => 'Gastos', 'data' => [$reporte['gastos']], 'color' => 'danger'],
                ]"
                empty-message="No hay ingresos ni gastos en este periodo. Prueba con otro periodo o categoría." />
        </div>
        <div class="col-12 col-lg-6">
            <x-financial.chart-card
                title="Distribución de gastos por categoría"
                chart-id="graficoDistribucionReporte"
                type="doughnut"
                height="300px"
                :labels="$reporte['distribucionGastos']['etiquetas']"
                :datasets="[['label' => 'Gastos', 'data' => $reporte['distribucionGastos']['valores']]]"
                empty-message="No hay gastos en este periodo. Prueba con otro periodo o categoría." />
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 mb-3">
        <h2 class="h5 fw-semibold mb-0">Detalle de movimientos</h2>
        <x-ui.badge type="secondary">{{ $movimientos->total() }}</x-ui.badge>
    </div>

    <x-ui.data-table
        :headers="['Fecha', 'Descripción', 'Categoría', 'Tipo', 'Monto']"
        :is-empty="$movimientos->isEmpty()"
        empty="No hay movimientos que coincidan con los filtros. Prueba con otro periodo o categoría, o usa «Limpiar filtros».">
        @foreach ($movimientos as $movimiento)
            @php($esIngreso = $movimiento->tipo === \App\Services\Finanzas\TipoMovimiento::Ingreso)
            <tr>
                <td class="text-nowrap">{{ $movimiento->fecha->format('d/m/Y') }}</td>
                <td>{{ $movimiento->descripcion }}</td>
                <td>{{ $movimiento->categoria }}</td>
                <td>
                    <x-ui.badge :type="$esIngreso ? 'success' : 'danger'">{{ $movimiento->tipo->etiqueta() }}</x-ui.badge>
                </td>
                <td class="text-end fw-semibold">
                    <x-ui.money :amount="$esIngreso ? $movimiento->monto : -$movimiento->monto"
                        @class(['text-success' => $esIngreso, 'text-danger' => ! $esIngreso]) />
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>

    <x-ui.pagination :paginator="$movimientos" label="movimientos" />
</x-layout.app>
