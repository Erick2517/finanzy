@props(['modulo', 'activeRoute' => null])

@php
    $disponible = Route::has($modulo['ruta']);

    $activo = $disponible
        && $activeRoute !== null
        && str_starts_with(ltrim($activeRoute, '/'), $modulo['clave']);
@endphp

@if ($disponible)
    <a href="{{ route($modulo['ruta']) }}"
       @class(['sidebar-link', 'is-active' => $activo])
       @if ($activo) aria-current="page" @endif>
        <i class="bi {{ $modulo['icono'] }} sidebar-link__icon" aria-hidden="true"></i>
        <span>{{ $modulo['etiqueta'] }}</span>
    </a>
@else
    <span class="sidebar-link is-disabled" aria-disabled="true" title="Disponible próximamente">
        <i class="bi {{ $modulo['icono'] }} sidebar-link__icon" aria-hidden="true"></i>
        <span>{{ $modulo['etiqueta'] }}</span>
        <span class="badge rounded-pill text-bg-light border fw-normal ms-auto">Próximamente</span>
    </span>
@endif
