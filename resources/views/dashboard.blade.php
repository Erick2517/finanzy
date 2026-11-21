{{--
    Panel financiero. Los datos llegan preparados desde DashboardController
    (App\Services\Finanzas\ResumenFinanciero); esta vista no calcula ni consulta nada.
--}}
@php
    $periodo = $resumen['periodo'];
    $comparacion = 'vs. ' . $resumen['periodoAnterior']->etiqueta();

    $colorEstado = ['Confirmado' => 'success', 'Pendiente' => 'warning'];
@endphp

<x-layout.app title="Panel Financiero" activeRoute="dashboard">
    <x-layout.page-header title="Panel Financiero" subtitle="Ingresos, gastos y balance de tu actividad financiera.">
        <div class="dropdown">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span class="visually-hidden">Periodo:</span> {{ ucfirst($periodo->etiqueta()) }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                @foreach ($periodosPredefinidos as $etiqueta => $predefinido)
                    @php($activo = $predefinido->esIgualA($periodo))
                    <li>
                        <a @class(['dropdown-item', 'active' => $activo])
                           href="{{ route('dashboard', ['fecha_inicio' => $predefinido->inicio->toDateString(), 'fecha_fin' => $predefinido->fin->toDateString()]) }}"
                           @if ($activo) aria-current="true" @endif>
                            {{ $etiqueta }}
                        </a>
                    </li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#filtros">Rango personalizado…</a></li>
            </ul>
        </div>
    </x-layout.page-header>

    @if ($resumen['esSimulado'])
        <x-ui.alert type="info" :dismissible="false" class="mb-4">
            <strong>Datos de demostración.</strong>
            Las cifras de este panel son simuladas y se sustituirán por los ingresos y gastos reales
            cuando estén disponibles los módulos correspondientes.
        </x-ui.alert>
    @endif

    <div id="filtros">
        <x-financial.filter-bar
            :action-url="route('dashboard')"
            :start-date="$periodo->inicio->toDateString()"
            :end-date="$periodo->fin->toDateString()"
            :show-periods="false" />
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Total de ingresos" :amount="$resumen['ingresos']['monto']"
                icon="bi-arrow-down-circle" type="success"
                :percentage="$resumen['ingresos']['variacion']" :comparison-label="$comparacion" />
        </div>
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Total de gastos" :amount="$resumen['gastos']['monto']"
                icon="bi-arrow-up-circle" type="danger"
                :percentage="$resumen['gastos']['variacion']" :comparison-label="$comparacion" invert-trend />
        </div>
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Resultado financiero" :amount="$resumen['balance']['monto']"
                icon="bi-wallet2" type="primary" caption="Ingresos − gastos"
                :percentage="$resumen['balance']['variacion']" :comparison-label="$comparacion" />
        </div>
        <div class="col-12 col-md-6 col-xxl-3">
            <x-financial.kpi-card title="Porcentaje de gastos" :amount="$resumen['porcentajeGastos']" format="percent"
                icon="bi-percent" type="warning"
                :caption="$resumen['porcentajeGastos'] === null ? 'Sin ingresos en el periodo' : 'De los ingresos del periodo'" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <x-financial.chart-card
                title="Ingresos vs. gastos (últimos 6 meses)"
                chart-id="graficoEvolucion"
                type="bar"
                height="320px"
                :labels="$resumen['evolucion']['etiquetas']"
                :datasets="[
                    ['label' => 'Ingresos', 'data' => $resumen['evolucion']['ingresos'], 'color' => 'success'],
                    ['label' => 'Gastos', 'data' => $resumen['evolucion']['gastos'], 'color' => 'danger'],
                ]"
                empty-message="No hay ingresos ni gastos en los últimos seis meses. Prueba con un periodo anterior." />
        </div>
        <div class="col-12 col-lg-4">
            <x-financial.chart-card
                title="Distribución de gastos por categoría"
                chart-id="graficoDistribucionGastos"
                type="doughnut"
                height="320px"
                :labels="$resumen['distribucionGastos']['etiquetas']"
                :datasets="[['label' => 'Gastos', 'data' => $resumen['distribucionGastos']['valores']]]"
                empty-message="No hay gastos registrados en este periodo. Prueba con otro periodo." />
        </div>
    </div>

    <h2 class="h5 fw-semibold mb-3">Movimientos recientes</h2>

    <x-ui.data-table
        :headers="['Fecha', 'Descripción', 'Categoría', 'Tipo', 'Monto', 'Estado']"
        :is-empty="empty($resumen['movimientos'])"
        empty="No hay movimientos registrados en este periodo. Elige otro periodo en el selector superior.">
        @foreach ($resumen['movimientos'] as $movimiento)
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
                <td>
                    <x-ui.badge :type="$colorEstado[$movimiento->estado] ?? 'secondary'"
                        @class(['text-dark' => $movimiento->estado === 'Pendiente'])>
                        {{ $movimiento->estado }}
                    </x-ui.badge>
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>
</x-layout.app>
