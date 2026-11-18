{{--
    Plantilla PDF del reporte (DomPDF). Usa los mismos datos que la pantalla
    (ReporteFinanciero) con estilos en línea: DomPDF no carga Vite/Bootstrap ni
    ejecuta JavaScript, por eso los gráficos se presentan como tablas.
--}}
@php
    $filtros = $reporte['filtros'];
    $tipo = $filtros->tipo;
    $esIngreso = fn ($movimiento) => $movimiento->tipo === \App\Services\Finanzas\TipoMovimiento::Ingreso;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte financiero {{ mb_strtolower($tipo->etiqueta()) }} | Finanzy</title>
    <style>
        @page { margin: 28mm 16mm 20mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #212529; }
        header { position: fixed; top: -20mm; left: 0; right: 0; height: 16mm; border-bottom: 2px solid #0d6efd; }
        header .marca { font-size: 16px; font-weight: bold; color: #0d6efd; }
        header .subtitulo { color: #6c757d; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; color: #6c757d; font-size: 8px; border-top: 1px solid #dee2e6; padding-top: 2mm; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 16px 0 6px; color: #0d6efd; }
        .aviso { background: #cff4fc; border: 1px solid #9eeaf9; padding: 6px 8px; margin: 8px 0; }
        .filtros td { padding: 2px 8px 2px 0; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th { background: #f1f3f5; text-align: left; font-weight: bold; padding: 5px 6px; border-bottom: 1px solid #ced4da; }
        table.datos td { padding: 4px 6px; border-bottom: 1px solid #e9ecef; }
        table.datos tr.total td { font-weight: bold; border-top: 1px solid #adb5bd; }
        .indicadores td { width: 25%; padding: 8px; border: 1px solid #dee2e6; vertical-align: top; }
        .indicadores .etiqueta { color: #6c757d; font-size: 8px; text-transform: uppercase; }
        .indicadores .valor { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .num { text-align: right; white-space: nowrap; }
        .positivo { color: #198754; }
        .negativo { color: #dc3545; }
        .vacio { color: #6c757d; font-style: italic; padding: 8px 0; }
    </style>
</head>
<body>
    <header>
        <div class="marca">Finanzy</div>
        <div class="subtitulo">Sistema de gestión financiera</div>
    </header>

    {{-- «Página X de Y» lo añade ReporteController::numerarPaginas() a la derecha de este pie. --}}
    <footer>
        <span>Generado el {{ $generadoEl->format('d/m/Y H:i') }}</span>
    </footer>

    <h1>Reporte financiero {{ mb_strtolower($tipo->etiqueta()) }}</h1>
    <table class="filtros">
        <tr><td><strong>Periodo:</strong></td><td>{{ ucfirst($filtros->periodo->etiqueta()) }}</td></tr>
        <tr><td><strong>Categoría:</strong></td><td>{{ $filtros->nombreCategoria ?? 'Todas' }}</td></tr>
    </table>

    @if ($reporte['esSimulado'])
        <div class="aviso"><strong>Datos de demostración.</strong> Las cifras de este reporte son simuladas.</div>
    @endif

    <h2>Indicadores</h2>
    <table class="indicadores" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td><div class="etiqueta">Total de ingresos</div><div class="valor positivo"><x-ui.money :amount="$reporte['ingresos']" /></div></td>
            <td><div class="etiqueta">Total de gastos</div><div class="valor negativo"><x-ui.money :amount="$reporte['gastos']" /></div></td>
            <td>
                <div class="etiqueta">Resultado financiero</div>
                <div @class(['valor', 'negativo' => $reporte['resultado'] < 0])><x-ui.money :amount="$reporte['resultado']" /></div>
            </td>
            <td>
                <div class="etiqueta">Gastos sobre ingresos</div>
                <div class="valor">{{ $reporte['porcentajeGastos'] === null ? 'No disponible' : number_format($reporte['porcentajeGastos'], 1) . '%' }}</div>
            </td>
        </tr>
    </table>

    @if ($reporte['evolucion'])
        <h2>Evolución {{ ['diario' => 'diaria', 'mensual' => 'mensual', 'anual' => 'anual'][$tipo->value] }}</h2>
        <table class="datos">
            <thead><tr><th>{{ ucfirst($tipo->unidad()[0]) }}</th><th class="num">Ingresos</th><th class="num">Gastos</th><th class="num">Resultado</th></tr></thead>
            <tbody>
                @foreach ($reporte['evolucion']['etiquetas'] as $indice => $etiqueta)
                    <tr>
                        <td>{{ $etiqueta }}</td>
                        <td class="num"><x-ui.money :amount="$reporte['evolucion']['ingresos'][$indice]" /></td>
                        <td class="num"><x-ui.money :amount="$reporte['evolucion']['gastos'][$indice]" /></td>
                        <td @class(['num', 'negativo' => $reporte['evolucion']['resultados'][$indice] < 0])><x-ui.money :amount="$reporte['evolucion']['resultados'][$indice]" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Distribución de gastos por categoría</h2>
    @if (empty($reporte['distribucionGastos']['etiquetas']))
        <p class="vacio">No hay gastos en este periodo.</p>
    @else
        <table class="datos">
            <thead><tr><th>Categoría</th><th class="num">Monto</th><th class="num">Porcentaje</th></tr></thead>
            <tbody>
                @foreach ($reporte['distribucionGastos']['etiquetas'] as $indice => $categoria)
                    <tr>
                        <td>{{ $categoria }}</td>
                        <td class="num"><x-ui.money :amount="$reporte['distribucionGastos']['valores'][$indice]" /></td>
                        <td class="num">{{ number_format($reporte['distribucionGastos']['porcentajes'][$indice], 1) }}%</td>
                    </tr>
                @endforeach
                <tr class="total"><td>Total</td><td class="num"><x-ui.money :amount="$reporte['gastos']" /></td><td class="num">100.0%</td></tr>
            </tbody>
        </table>
    @endif

    <h2>Detalle de movimientos ({{ count($reporte['movimientos']) }})</h2>
    @if (empty($reporte['movimientos']))
        <p class="vacio">No hay movimientos que coincidan con los filtros del reporte.</p>
    @else
        <table class="datos">
            <thead><tr><th>Fecha</th><th>Descripción</th><th>Categoría</th><th>Tipo</th><th class="num">Monto</th></tr></thead>
            <tbody>
                @foreach ($reporte['movimientos'] as $movimiento)
                    <tr>
                        <td>{{ $movimiento->fecha->format('d/m/Y') }}</td>
                        <td>{{ $movimiento->descripcion }}</td>
                        <td>{{ $movimiento->categoria }}</td>
                        <td>{{ $movimiento->tipo->etiqueta() }}</td>
                        <td @class(['num', 'positivo' => $esIngreso($movimiento), 'negativo' => ! $esIngreso($movimiento)])>
                            <x-ui.money :amount="$esIngreso($movimiento) ? $movimiento->monto : -$movimiento->monto" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
