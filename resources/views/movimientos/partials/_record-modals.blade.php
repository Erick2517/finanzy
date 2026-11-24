@foreach ($movimientos as $movimiento)
    @if ($movimiento->activo)
        <x-ui.modal
            :id="'modalEditar-'.$movimiento->id"
            :title="'Editar '.$etiquetaTipo"
            :action-url="route('movimientos.update', $movimiento)"
            method="PUT"
            :submit-label="'Guardar '.$etiquetaTipo">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="movimiento_id" value="{{ $movimiento->id }}">
            <x-form.select-category
                name="categoria_id"
                :categories="$categorias"
                :selected="$movimiento->categoria_id"
                :id="'categoria_id-'.$movimiento->id"
                required />
            <x-form.input name="concepto" label="Concepto" :value="$movimiento->concepto"
                          :id="'concepto-'.$movimiento->id" maxlength="255" required />
            <x-form.currency-input name="monto" label="Monto" :value="$movimiento->monto"
                                   :id="'monto-'.$movimiento->id" min="0.01" required />
            <x-form.date-picker name="fecha" label="Fecha" :value="$movimiento->fecha->toDateString()"
                                :id="'fecha-'.$movimiento->id" required />
        </x-ui.modal>

        <x-ui.modal
            :id="'modalEliminar-'.$movimiento->id"
            :title="'Desactivar '.$etiquetaTipo"
            :action-url="route('movimientos.destroy', $movimiento)"
            method="DELETE"
            submit-label="Desactivar"
            submit-type="danger"
            size="sm">
            <p class="mb-0">
                ¿Deseas desactivar <strong>{{ $movimiento->concepto }}</strong>?
                Se conservará en el historial y dejará de incluirse en los cálculos.
            </p>
        </x-ui.modal>
    @else
        <x-ui.modal
            :id="'modalActivar-'.$movimiento->id"
            :title="'Reactivar '.$etiquetaTipo"
            :action-url="route('movimientos.activar', $movimiento)"
            method="PATCH"
            submit-label="Reactivar"
            submit-type="success"
            size="sm">
            <p class="mb-0">¿Deseas reactivar <strong>{{ $movimiento->concepto }}</strong>?</p>
        </x-ui.modal>
    @endif
@endforeach
