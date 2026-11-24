<x-layout.app :title="$titulo" :active-route="$tipo === 'ingreso' ? 'ingresos' : 'gastos'">
    <x-layout.page-header :title="$titulo" subtitle="Administra los movimientos compartidos de la empresa.">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegistro" @disabled($categorias->isEmpty())>
            <i class="bi bi-plus-circle" aria-hidden="true"></i> Agregar {{ strtolower($etiquetaTipo) }}
        </button>
    </x-layout.page-header>

    @if ($categorias->isEmpty())
        <x-ui.alert type="warning" :dismissible="false" class="mb-4">
            No hay categorías de {{ strtolower($titulo) }} disponibles.
            <a href="{{ route('categorias.index') }}" class="alert-link">Ir a Categorías</a>
        </x-ui.alert>
    @endif

    @include('movimientos.partials._filters', [
        'categorias' => $categorias,
        'filtros' => $filtros,
        'rutaIndex' => $rutaIndex,
    ])

    @include('movimientos.partials._table', [
        'movimientos' => $movimientos,
        'titulo' => $titulo,
    ])

    <x-ui.pagination :paginator="$movimientos" label="movimientos" />

    @include('movimientos.partials._record-modals', [
        'categorias' => $categorias,
        'etiquetaTipo' => $etiquetaTipo,
        'movimientos' => $movimientos,
        'tipo' => $tipo,
    ])

    @include('movimientos.partials._create-modal', [
        'categorias' => $categorias,
        'etiquetaTipo' => $etiquetaTipo,
        'tipo' => $tipo,
    ])

    @include('movimientos.partials._validation-script', ['tipo' => $tipo])
</x-layout.app>
