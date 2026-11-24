@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @if ($errors->any() && old('tipo') === $tipo)
                const movimientoId = @js(old('movimiento_id'));
                const modalId = movimientoId ? `modalEditar-${movimientoId}` : 'modalRegistro';
                const modal = document.getElementById(modalId);
                if (modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            @endif
        });
    </script>
@endpush
