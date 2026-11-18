{{--
    Resumen y enlaces de paginación.

    $paginator: resultado de ->paginate() (muestra el total) o de ->simplePaginate().
    $label:     sustantivo del resumen: "Mostrando 1 a 10 de 35 {label}".
    No se muestra nada si la página no tiene registros.
--}}
@props(['paginator', 'label' => 'resultados'])

@php
    $conTotal = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
@endphp

@if ($paginator->isNotEmpty())
    <div {{ $attributes->class(['d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3']) }}>
        <span class="small text-muted">
            Mostrando {{ $paginator->firstItem() }} a {{ $paginator->lastItem() }}@if ($conTotal) de {{ $paginator->total() }}@endif {{ $label }}
        </span>

        {{ $paginator->links('pagination::finanzy') }}
    </div>
@endif
