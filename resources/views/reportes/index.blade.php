
<x-layout.app title="Reportes" activeRoute="reportes">

    <x-layout.page-header
        title="Reportes financieros"
        subtitle="Consulta y exporta tus movimientos"
    />

    <!-- ========================================= -->
    <!-- MENSAJES -->
    <!-- ========================================= -->

    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif


    <!-- ========================================= -->
    <!-- RESUMEN FINANCIERO -->
    <!-- ========================================= -->

    <div class="row g-3 mb-4">

        @foreach ([
            'ingresos' => 'Ingresos',
            'gastos' => 'Gastos',
            'balance' => 'Balance'
        ] as $key => $label)

            <div class="col-md-4">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <div class="text-muted mb-2">
                            {{ $label }}
                        </div>

                        <div class="fs-3 fw-bold {{ $key === 'gastos' ? 'text-danger' : ($key === 'ingresos' ? 'text-success' : '') }}">
                            $ {{ number_format($resumen[$key] ?? 0, 2) }}
                        </div>

                    </div>

                </div>

            </div>

        @endforeach

    </div>


    <!-- ========================================= -->
    <!-- FILTROS Y EXPORTACIÓN -->
    <!-- ========================================= -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <h5 class="mb-3">
                Filtros y exportación
            </h5>

            <form
                method="GET"
                action="{{ route('reportes.index') }}"
                class="row g-3 align-items-end"
            >

                <!-- FECHA INICIAL -->
                <div class="col-md-2">

                    <label for="desde" class="form-label">
                        Desde
                    </label>

                    <input
                        type="date"
                        id="desde"
                        name="desde"
                        value="{{ $filtros['desde'] ?? '' }}"
                        class="form-control"
                    >

                </div>


                <!-- FECHA FINAL -->
                <div class="col-md-2">

                    <label for="hasta" class="form-label">
                        Hasta
                    </label>

                    <input
                        type="date"
                        id="hasta"
                        name="hasta"
                        value="{{ $filtros['hasta'] ?? '' }}"
                        class="form-control"
                    >

                </div>


                <!-- TIPO DE MOVIMIENTO -->
                <div class="col-md-2">

                    <label for="tipo" class="form-label">
                        Tipo
                    </label>

                    <select
                        id="tipo"
                        name="tipo"
                        class="form-select"
                    >

                        <option value="">
                            Todos
                        </option>

                        <option
                            value="ingreso"
                            @selected(($filtros['tipo'] ?? '') === 'ingreso')
                        >
                            Ingreso
                        </option>

                        <option
                            value="gasto"
                            @selected(($filtros['tipo'] ?? '') === 'gasto')
                        >
                            Gasto
                        </option>

                    </select>

                </div>


                <!-- CATEGORÍA -->
                <div class="col-md-3">

                    <label for="categoria_id" class="form-label">
                        Categoría
                    </label>

                    <select
                        id="categoria_id"
                        name="categoria_id"
                        class="form-select"
                    >

                        <option value="">
                            Todas
                        </option>

                        @foreach ($categorias as $c)

                            <option
                                value="{{ $c->id }}"
                                @selected(($filtros['categoria_id'] ?? '') == $c->id)
                            >
                                {{ $c->nombre }} ({{ $c->tipo }})
                            </option>

                        @endforeach

                    </select>

                </div>


                <!-- BOTONES DE FILTROS -->
                <div class="col-md-3">

                    <div class="d-flex gap-2 flex-wrap">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="bi bi-funnel"></i>
                            Aplicar filtros
                        </button>

                        <a
                            href="{{ route('reportes.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            Limpiar
                        </a>

                    </div>

                </div>

            </form>


            <!-- ========================================= -->
            <!-- EXPORTACIÓN DE REPORTES -->
            <!-- ========================================= -->

            <div class="mt-4 d-flex gap-2 flex-wrap">

                <!-- DESCARGAR PDF -->
                <a
                    href="{{ route('reportes.pdf', request()->only(['desde', 'hasta', 'tipo', 'categoria_id'])) }}"
                    class="btn btn-outline-danger"
                >
                    <i class="bi bi-file-pdf"></i>
                    Descargar PDF
                </a>

                <!-- EXPORTAR CSV -->
                <a
                    href="{{ route('reportes.csv', request()->only(['desde', 'hasta', 'tipo', 'categoria_id'])) }}"
                    class="btn btn-outline-success"
                >
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    Exportar CSV (Excel)
                </a>

            </div>

        </div>

    </div>


    <!-- ========================================= -->
    <!-- REGISTRO DE MOVIMIENTOS -->
    <!-- ========================================= -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <h5 class="mb-2">
                Registrar movimiento para reportes
            </h5>

            <p class="text-muted small mb-3">
                Registro independiente y provisional hasta integrar
                los módulos de ingresos y gastos del equipo.
            </p>

            <form
                method="POST"
                action="{{ route('reportes.movimientos.store') }}"
                class="row g-3 align-items-end"
            >

                @csrf


                <!-- SELECCIONAR CATEGORÍA -->
                <div class="col-md-3">

                    <label for="registro_categoria" class="form-label">
                        Categoría
                    </label>

                    <select
                        id="registro_categoria"
                        name="categoria_id"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Seleccionar
                        </option>

                        @foreach ($categorias as $c)

                            <option
                                value="{{ $c->id }}"
                                @selected(old('categoria_id') == $c->id)
                            >
                                {{ $c->nombre }} ({{ $c->tipo }})
                            </option>

                        @endforeach

                    </select>

                </div>


                <!-- MONTO -->
                <div class="col-md-2">

                    <label for="monto" class="form-label">
                        Monto ($)
                    </label>

                    <input
                        type="number"
                        id="monto"
                        name="monto"
                        min="0.01"
                        step="0.01"
                        value="{{ old('monto') }}"
                        class="form-control"
                        placeholder="0.00"
                        required
                    >

                </div>


                <!-- FECHA -->
                <div class="col-md-2">

                    <label for="fecha" class="form-label">
                        Fecha
                    </label>

                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        value="{{ old('fecha', date('Y-m-d')) }}"
                        class="form-control"
                        required
                    >

                </div>


                <!-- CONCEPTO -->
                <div class="col-md-3">

                    <label for="concepto" class="form-label">
                        Concepto
                    </label>

                    <input
                        type="text"
                        id="concepto"
                        name="concepto"
                        value="{{ old('concepto') }}"
                        class="form-control"
                        maxlength="255"
                        placeholder="Ej. Pago de supermercado"
                        required
                    >

                </div>


                <!-- BOTÓN REGISTRAR -->
                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                        @disabled($categorias->isEmpty())
                    >
                        <i class="bi bi-plus-circle"></i>
                        Registrar
                    </button>

                </div>

            </form>

            @if ($categorias->isEmpty())

                <div class="alert alert-warning mt-3 mb-0">
                    Debes crear al menos una categoría antes de registrar movimientos.
                </div>

            @endif

        </div>

    </div>


    <!-- ========================================= -->
    <!-- DETALLE DE MOVIMIENTOS -->
    <!-- ========================================= -->

    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <h5 class="mb-3">
                Detalle de movimientos
            </h5>

            <div class="table-responsive">

                <table class="table table-striped align-middle">

                    <!-- ENCABEZADOS -->
                    <thead>

                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Concepto</th>
                            <th class="text-end">Monto</th>
                        </tr>

                    </thead>


                    <!-- REGISTROS -->
                    <tbody>

                        @forelse ($movimientos as $m)

                            <tr>

                                <!-- FECHA -->
                                <td>
                                    {{ $m->fecha->format('d/m/Y') }}
                                </td>


                                <!-- TIPO -->
                                <td>

                                    <span
                                        class="badge {{ $m->categoria->tipo === 'ingreso' ? 'bg-success' : 'bg-danger' }}"
                                    >
                                        {{ ucfirst($m->categoria->tipo) }}
                                    </span>

                                </td>


                                <!-- CATEGORÍA -->
                                <td>
                                    {{ $m->categoria->nombre }}
                                </td>


                                <!-- CONCEPTO -->
                                <td>
                                    {{ $m->concepto }}
                                </td>


                                <!-- MONTO -->
                                <td class="text-end">

                                    <span class="fw-semibold {{ $m->categoria->tipo === 'ingreso' ? 'text-success' : 'text-danger' }}">
                                        $ {{ number_format($m->monto, 2) }}
                                    </span>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="5"
                                    class="text-center text-muted py-4"
                                >
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    No hay movimientos para los filtros seleccionados.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            <!-- ========================================= -->
            <!-- PAGINACIÓN -->
            <!-- ========================================= -->

            <div class="mt-3">
                {{ $movimientos->links() }}
            </div>

        </div>

    </div>

</x-layout.app>
