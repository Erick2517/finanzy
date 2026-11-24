<x-ui.data-table
    :headers="['Fecha', 'Concepto', 'Categoría', 'Registrado por', 'Monto', 'Estado', 'Acciones']"
    :is-empty="$movimientos->isEmpty()"
    :empty="'No hay movimientos de '.strtolower($titulo).' para mostrar.'">
    @foreach ($movimientos as $movimiento)
        <tr>
            <td class="text-nowrap">{{ $movimiento->fecha->format('d/m/Y') }}</td>
            <td>{{ $movimiento->concepto }}</td>
            <td>{{ $movimiento->categoria->nombre }}</td>
            <td>{{ $movimiento->usuario?->name ?? 'Sin usuario' }}</td>
            <td class="text-end fw-semibold">
                <x-ui.money :amount="$movimiento->monto" />
            </td>
            <td>
                <x-ui.badge :type="$movimiento->activo ? 'success' : 'secondary'">
                    {{ $movimiento->activo ? 'Activo' : 'Inactivo' }}
                </x-ui.badge>
            </td>
            <td>
                <div class="d-flex justify-content-end gap-1">
                    @if ($movimiento->activo)
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#modalEditar-{{ $movimiento->id }}"
                                title="Editar" aria-label="Editar">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal" data-bs-target="#modalEliminar-{{ $movimiento->id }}"
                                title="Desactivar" aria-label="Desactivar">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-success"
                                data-bs-toggle="modal" data-bs-target="#modalActivar-{{ $movimiento->id }}"
                                title="Reactivar" aria-label="Reactivar">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
