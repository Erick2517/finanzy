<form method="GET" action="{{ route($rutaIndex) }}" class="card card-body border-0 shadow-sm mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-6 col-xl-3">
            <label for="buscar" class="form-label fw-semibold small">Concepto</label>
            <input id="buscar" type="search" name="q" value="{{ $filtros['q'] ?? '' }}" class="form-control" placeholder="Buscar concepto">
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <label for="categoria_id" class="form-label fw-semibold small">Categoría</label>
            <select id="categoria_id" name="categoria_id" class="form-select">
                <option value="">Todas</option>
                @foreach ($categorias as $categoriaId => $nombre)
                    <option value="{{ $categoriaId }}" @selected((string) ($filtros['categoria_id'] ?? '') === (string) $categoriaId)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="fecha_desde" class="form-label fw-semibold small">Desde</label>
            <input id="fecha_desde" type="date" name="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}" class="form-control">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="fecha_hasta" class="form-label fw-semibold small">Hasta</label>
            <input id="fecha_hasta" type="date" name="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}" class="form-control">
        </div>
        <div class="col-6 col-md-4 col-xl-1">
            <label for="estado" class="form-label fw-semibold small">Estado</label>
            <select id="estado" name="estado" class="form-select">
                <option value="activos" @selected($filtros['estado'] === 'activos')>Activos</option>
                <option value="inactivos" @selected($filtros['estado'] === 'inactivos')>Inactivos</option>
                <option value="todos" @selected($filtros['estado'] === 'todos')>Todos</option>
            </select>
        </div>
        <div class="col-6 col-xl-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1">Filtrar</button>
            <a href="{{ route($rutaIndex) }}" class="btn btn-outline-secondary" aria-label="Limpiar filtros" title="Limpiar filtros">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</form>
