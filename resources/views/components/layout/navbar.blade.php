@props(['user' => null])

@php
    $rolNombre = null;

    if ($user) {
        $rolNombre = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->first()
            : ($user->role ?? null);
    }

    $etiquetasRol = [
        'admin'         => 'Administrador',
        'administrador' => 'Administrador',
        'Administrador' => 'Administrador',
        'empleado'      => 'Empleado',
        'Empleado'      => 'Empleado',
    ];

    $rolEtiqueta = $etiquetasRol[$rolNombre] ?? 'Sin rol';
    $rolColor = match ($rolEtiqueta) {
        'Administrador' => 'primary',
        'Empleado' => 'success',
        default => 'secondary',
    };

    $urlInicio = $user ? route('dashboard') : route('login');
@endphp

<nav class="navbar navbar-dark bg-dark px-3 shadow-sm" aria-label="Barra superior">
    <div class="d-flex align-items-center gap-2">
        <button type="button"
                class="btn btn-outline-light btn-sm d-lg-none"
                data-bs-toggle="offcanvas"
                data-bs-target="#sidebarMenu"
                aria-controls="sidebarMenu"
                aria-label="Abrir menú de navegación">
            <i class="bi bi-list fs-5" aria-hidden="true"></i>
        </button>

        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="{{ $urlInicio }}">
            <i class="bi bi-wallet2"></i> Finanzy
        </a>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
        @if ($user)
            <span class="text-white fw-semibold d-none d-md-inline">{{ $user->name }}</span>
            <x-ui.badge :type="$rolColor">{{ $rolEtiqueta }}</x-ui.badge>

            <form action="{{ route('logout') }}" method="POST" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm" aria-label="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Cerrar sesión</span>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
            </a>
            <a href="{{ route('register') }}" class="btn btn-light btn-sm">
                <i class="bi bi-person-plus"></i> Registrarse
            </a>
        @endif
    </div>
</nav>
