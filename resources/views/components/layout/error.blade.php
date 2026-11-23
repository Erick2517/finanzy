{{--
    Página de error de Finanzy (resources/views/errors/*).

    $code:    código HTTP que se muestra.
    $title:   título breve.
    $message: explicación para el usuario.

    La acción depende de la sesión: «Volver al panel» o «Iniciar sesión». En una dirección
    inexistente Laravel no llega a cargar la sesión (no hay ruta ni grupo web), así que se
    ofrece «Ir al inicio», que lleva al panel o al inicio de sesión según corresponda.
    Las comprobaciones se protegen con rescue() para que la propia página de error no falle
    si, por ejemplo, la base de datos no está disponible.
--}}
@props(['code', 'title', 'message'])

@php
    $sesionDisponible = rescue(fn () => request()->hasSession(), false, false);
    $conSesion = $sesionDisponible && rescue(fn () => auth()->check(), false, false);
@endphp

<x-layout.auth :title="$title">
    <div class="text-center">
        <p class="display-4 fw-bold text-primary mb-2">{{ $code }}</p>
        <p class="text-muted mb-4">{{ $message }}</p>

        @if ($conSesion)
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="bi bi-speedometer2" aria-hidden="true"></i> Volver al panel
            </a>
        @elseif ($sesionDisponible)
            <a href="{{ route('login') }}" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Iniciar sesión
            </a>
        @else
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="bi bi-house" aria-hidden="true"></i> Ir al inicio
            </a>
        @endif
    </div>
</x-layout.auth>
