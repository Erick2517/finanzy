<x-ui.modal id="modalRegistro" :title="'Nuevo '.$etiquetaTipo"
            :action-url="route('movimientos.store')" :submit-label="'Registrar '.$etiquetaTipo">
    <input type="hidden" name="tipo" value="{{ $tipo }}">
    <x-form.select-category name="categoria_id" :categories="$categorias" required />
    <x-form.input name="concepto" label="Concepto" placeholder="Ej. Pago de proveedor" maxlength="255" required />
    <x-form.currency-input name="monto" label="Monto" min="0.01" placeholder="0.00" required />
    <x-form.date-picker name="fecha" label="Fecha" required />
</x-ui.modal>
