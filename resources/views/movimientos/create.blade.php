{{--
    Registro provisional de movimientos (formulario original de Kevin Ramírez, movido
    desde la pantalla de reportes). Lo registrado alimenta el panel y los reportes.
--}}
<x-layout.app title="Registrar movimiento" activeRoute="movimientos">
    <x-layout.page-header title="Registrar movimiento" subtitle="Registra un ingreso o un gasto en una de tus categorías.">
        <a href="{{ route('reportes.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> Ver reportes
        </a>
    </x-layout.page-header>

    <div class="row">
        <div class="col-12 col-lg-8 col-xxl-6">
            <x-ui.alert type="info" :dismissible="false" class="mb-4">
                Registro provisional hasta que estén disponibles los módulos de ingresos y gastos.
                El tipo del movimiento (ingreso o gasto) es el de la categoría elegida.
            </x-ui.alert>

            @if ($categorias->isEmpty())
                <x-ui.alert type="warning" :dismissible="false" class="mb-4">
                    Debes crear al menos una categoría antes de registrar movimientos.
                    <a href="{{ route('categorias.index') }}" class="alert-link">Ir a Categorías</a>
                </x-ui.alert>
            @endif

            <form method="POST" action="{{ route('movimientos.store') }}" class="card card-body border-0 shadow-sm" data-validar novalidate>
                @csrf

                <x-form.select-category name="categoria_id" :categories="$categorias" required />

                <div class="row">
                    <div class="col-12 col-sm-6">
                        <x-form.currency-input name="monto" label="Monto" min="0.01" required placeholder="0.00" />
                    </div>
                    <div class="col-12 col-sm-6">
                        <x-form.date-picker name="fecha" label="Fecha" required />
                    </div>
                </div>

                <x-form.input name="concepto" label="Concepto" maxlength="255" placeholder="Ej. Pago de supermercado" required />

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" data-loading-text="Registrando…" @disabled($categorias->isEmpty())>
                        <i class="bi bi-plus-circle" aria-hidden="true"></i> Registrar movimiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layout.app>
