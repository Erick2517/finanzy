
<x-layout.app title="Categorías" activeRoute="categorias">

    <x-layout.page-header
        title="Categorías"
        subtitle="Organiza tus ingresos y gastos"
    />

    <div class="row g-4">

        <!-- ========================================= -->
        <!-- FORMULARIO PARA CREAR CATEGORÍAS -->
        <!-- ========================================= -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">

                    <h5 class="mb-3">Nueva categoría</h5>

                    <!-- MENSAJES DE ERROR -->
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    {{-- Los mensajes de sesión (success / error) los muestra el layout general. --}}

                    <form
                        action="{{ route('categorias.store') }}"
                        method="POST"
                    >
                        @csrf

                        <!-- NOMBRE -->
                        <div class="mb-3">
                            <label for="nombre" class="form-label">
                                Nombre
                            </label>

                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                class="form-control"
                                maxlength="100"
                                value="{{ old('nombre') }}"
                                placeholder="Ej. Alimentación"
                                required
                            >
                        </div>

                        <!-- TIPO -->
                        <div class="mb-3">
                            <label for="tipo" class="form-label">
                                Tipo
                            </label>

                            <select
                                id="tipo"
                                name="tipo"
                                class="form-select"
                                required
                            >
                                <option
                                    value="ingreso"
                                    @selected(old('tipo') === 'ingreso')
                                >
                                    Ingreso
                                </option>

                                <option
                                    value="gasto"
                                    @selected(old('tipo') === 'gasto')
                                >
                                    Gasto
                                </option>
                            </select>
                        </div>

                        <!-- DESCRIPCIÓN -->
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">
                                Descripción
                            </label>

                            <textarea
                                id="descripcion"
                                name="descripcion"
                                class="form-control"
                                maxlength="255"
                                rows="3"
                                placeholder="Descripción de la categoría"
                            >{{ old('descripcion') }}</textarea>
                        </div>

                        <!-- BOTÓN GUARDAR -->
                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-plus-lg"></i>
                            Guardar categoría
                        </button>

                    </form>

                </div>
            </div>
        </div>


        <!-- ========================================= -->
        <!-- LISTADO DE CATEGORÍAS -->
        <!-- ========================================= -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">

                    <h5 class="mb-3">Categorías registradas</h5>

                    <!-- FORMULARIO DE BÚSQUEDA -->
                    <form
                        method="GET"
                        action="{{ route('categorias.index') }}"
                        class="row g-2 mb-3"
                    >

                        <!-- BUSCAR POR NOMBRE -->
                        <div class="col-sm-6">
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                placeholder="Buscar categoría"
                                value="{{ request('q') }}"
                            >
                        </div>

                        <!-- FILTRAR POR TIPO -->
                        <div class="col-sm-4">
                            <select
                                name="tipo"
                                class="form-select"
                            >
                                <option value="">
                                    Todos los tipos
                                </option>

                                <option
                                    value="ingreso"
                                    @selected(request('tipo') === 'ingreso')
                                >
                                    Ingresos
                                </option>

                                <option
                                    value="gasto"
                                    @selected(request('tipo') === 'gasto')
                                >
                                    Gastos
                                </option>
                            </select>
                        </div>

                        <!-- BOTÓN FILTRAR -->
                        <div class="col-sm-2">
                            <button
                                type="submit"
                                class="btn btn-outline-primary w-100"
                            >
                                Filtrar
                            </button>
                        </div>

                    </form>


                    <!-- ========================================= -->
                    <!-- TABLA DE CATEGORÍAS -->
                    <!-- ========================================= -->
                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Movimientos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse ($categorias as $categoria)

                                    <!-- FILA DE CATEGORÍA -->
                                    <tr>

                                        <!-- NOMBRE Y DESCRIPCIÓN -->
                                        <td>
                                            <strong>
                                                {{ $categoria->nombre }}
                                            </strong>

                                            <div class="text-muted small">
                                                {{ $categoria->descripcion }}
                                            </div>
                                        </td>

                                        <!-- TIPO -->
                                        <td>
                                            <span
                                                class="badge {{ $categoria->tipo === 'ingreso' ? 'bg-success' : 'bg-danger' }}"
                                            >
                                                {{ ucfirst($categoria->tipo) }}
                                            </span>
                                        </td>

                                        <!-- CANTIDAD DE MOVIMIENTOS -->
                                        <td>
                                            {{ $categoria->movimientos_count }}
                                        </td>

                                        <!-- ACCIONES -->
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">

                                                <!-- EDITAR -->
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#editar-{{ $categoria->id }}"
                                                    aria-expanded="false"
                                                    aria-controls="editar-{{ $categoria->id }}"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                    Editar
                                                </button>

                                                <!-- ELIMINAR -->
                                                <form
                                                    action="{{ route('categorias.destroy', $categoria) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('¿Estás seguro de eliminar esta categoría?')"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        @disabled($categoria->movimientos_count > 0)
                                                    >
                                                        <i class="bi bi-trash"></i>
                                                        Eliminar
                                                    </button>

                                                </form>

                                            </div>
                                        </td>

                                    </tr>


                                    <!-- ========================================= -->
                                    <!-- FORMULARIO PARA EDITAR CATEGORÍA -->
                                    <!-- ========================================= -->
                                    <tr
                                        class="collapse"
                                        id="editar-{{ $categoria->id }}"
                                    >
                                        <td colspan="4">

                                            <div class="p-3 bg-body-tertiary rounded">

                                                <h6 class="mb-3">
                                                    Editar categoría:
                                                    {{ $categoria->nombre }}
                                                </h6>

                                                <form
                                                    action="{{ route('categorias.update', $categoria) }}"
                                                    method="POST"
                                                    class="row g-2 align-items-end"
                                                >
                                                    @csrf
                                                    @method('PUT')

                                                    <!-- EDITAR NOMBRE -->
                                                    <div class="col-md-4">
                                                        <label class="form-label">
                                                            Nombre
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="nombre"
                                                            class="form-control"
                                                            maxlength="100"
                                                            value="{{ $categoria->nombre }}"
                                                            required
                                                        >
                                                    </div>

                                                    <!-- EDITAR TIPO -->
                                                    <div class="col-md-3">
                                                        <label class="form-label">
                                                            Tipo
                                                        </label>

                                                        <select
                                                            name="tipo"
                                                            class="form-select"
                                                            required
                                                        >
                                                            <option
                                                                value="ingreso"
                                                                @selected($categoria->tipo === 'ingreso')
                                                            >
                                                                Ingreso
                                                            </option>

                                                            <option
                                                                value="gasto"
                                                                @selected($categoria->tipo === 'gasto')
                                                            >
                                                                Gasto
                                                            </option>
                                                        </select>
                                                    </div>

                                                    <!-- EDITAR DESCRIPCIÓN -->
                                                    <div class="col-md-3">
                                                        <label class="form-label">
                                                            Descripción
                                                        </label>

                                                        <input
                                                            type="text"
                                                            name="descripcion"
                                                            class="form-control"
                                                            maxlength="255"
                                                            value="{{ $categoria->descripcion }}"
                                                            placeholder="Descripción"
                                                        >
                                                    </div>

                                                    <!-- ACTUALIZAR -->
                                                    <div class="col-md-2">
                                                        <button
                                                            type="submit"
                                                            class="btn btn-primary w-100"
                                                        >
                                                            Actualizar
                                                        </button>
                                                    </div>

                                                </form>

                                            </div>

                                        </td>
                                    </tr>

                                @empty

                                    <!-- SIN CATEGORÍAS -->
                                    <tr>
                                        <td
                                            colspan="4"
                                            class="text-center text-muted py-4"
                                        >
                                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                            No hay categorías registradas.
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
                        {{ $categorias->links() }}
                    </div>

                </div>
            </div>
        </div>

    </div>

</x-layout.app>
