/**
 * Pruebas de las validaciones reutilizables del lado del cliente.
 *
 *     docker compose run --rm frontend node --test tests/js/validaciones.test.js
 */

import assert from 'node:assert/strict';
import { describe, test } from 'node:test';
import { describirCampo, etiquetaDe, validarValor } from '../../resources/js/ui/validaciones.js';

globalThis.CSS ??= { escape: (valor) => valor };

describe('validarValor: campos obligatorios', () => {
    test('un campo obligatorio vacío o con espacios no es válido', () => {
        assert.equal(validarValor({ valor: '', etiqueta: 'Concepto', requerido: true }), 'El campo «Concepto» es obligatorio.');
        assert.equal(validarValor({ valor: '   ', etiqueta: 'Concepto', requerido: true }), 'El campo «Concepto» es obligatorio.');
    });

    test('un campo opcional vacío es válido', () => {
        assert.equal(validarValor({ valor: '', etiqueta: 'Notas' }), null);
    });

    test('obligatorio cuando el campo relacionado tiene valor', () => {
        const requeridoCon = { valor: '2026-11-01', etiqueta: 'Desde' };

        assert.equal(validarValor({ valor: '', etiqueta: 'Hasta', tipo: 'date', requeridoCon }), 'Completa «Hasta» cuando indicas «Desde».');
        assert.equal(validarValor({ valor: '', etiqueta: 'Hasta', tipo: 'date', requeridoCon: { valor: '', etiqueta: 'Desde' } }), null);
    });
});

describe('validarValor: montos', () => {
    test('un monto positivo con decimales es válido', () => {
        assert.equal(validarValor({ valor: '1250.75', etiqueta: 'Monto', tipo: 'number', montoPositivo: true }), null);
    });

    test('cero y negativos no son montos válidos', () => {
        assert.equal(validarValor({ valor: '0', etiqueta: 'Monto', tipo: 'number', montoPositivo: true }), 'El monto de «Monto» debe ser mayor que cero.');
        assert.equal(validarValor({ valor: '-5', etiqueta: 'Monto', tipo: 'number', montoPositivo: true }), 'El monto de «Monto» debe ser mayor que cero.');
    });

    test('un texto no numérico no es un monto', () => {
        assert.equal(validarValor({ valor: '12,50', etiqueta: 'Monto', tipo: 'number', montoPositivo: true }), 'Escribe un número válido en «Monto».');
        assert.equal(validarValor({ valor: 'abc', etiqueta: 'Monto', montoPositivo: true }), 'Escribe un número válido en «Monto».');
    });

    test('respeta min y max de los campos numéricos', () => {
        assert.equal(validarValor({ valor: '1999', etiqueta: 'Año inicial', tipo: 'number', min: '2000', max: '2100' }), '«Año inicial» debe ser mayor o igual que 2000.');
        assert.equal(validarValor({ valor: '2101', etiqueta: 'Año inicial', tipo: 'number', min: '2000', max: '2100' }), '«Año inicial» debe ser menor o igual que 2100.');
        assert.equal(validarValor({ valor: '2026', etiqueta: 'Año inicial', tipo: 'number', min: '2000', max: '2100' }), null);
    });
});

describe('validarValor: fechas', () => {
    test('acepta fechas reales y rechaza las inexistentes o mal escritas', () => {
        assert.equal(validarValor({ valor: '2026-02-28', etiqueta: 'Fecha', tipo: 'date' }), null);
        assert.equal(validarValor({ valor: '2028-02-29', etiqueta: 'Fecha', tipo: 'date' }), null);
        assert.equal(validarValor({ valor: '2026-02-30', etiqueta: 'Fecha', tipo: 'date' }), 'Selecciona una fecha válida en «Fecha».');
        assert.equal(validarValor({ valor: '18/11/2026', etiqueta: 'Fecha', tipo: 'date' }), 'Selecciona una fecha válida en «Fecha».');
    });

    test('valida los meses AAAA-MM', () => {
        assert.equal(validarValor({ valor: '2026-11', etiqueta: 'Mes inicial', tipo: 'month' }), null);
        assert.equal(validarValor({ valor: '2026-13', etiqueta: 'Mes inicial', tipo: 'month' }), 'Usa el formato AAAA-MM en «Mes inicial» (por ejemplo, 2026-11).');
        assert.equal(validarValor({ valor: '11/2026', etiqueta: 'Mes inicial', tipo: 'month' }), 'Usa el formato AAAA-MM en «Mes inicial» (por ejemplo, 2026-11).');
    });
});

describe('validarValor: rangos', () => {
    test('la fecha final no puede ser anterior a la inicial', () => {
        const desde = { valor: '2026-11-10', etiqueta: 'Desde' };

        assert.equal(validarValor({ valor: '2026-11-01', etiqueta: 'Hasta', tipo: 'date', desde }), '«Hasta» no puede ser anterior a «Desde».');
        assert.equal(validarValor({ valor: '2026-11-10', etiqueta: 'Hasta', tipo: 'date', desde }), null);
        assert.equal(validarValor({ valor: '2026-12-01', etiqueta: 'Hasta', tipo: 'date', desde }), null);
    });

    test('el rango funciona con meses y con años numéricos', () => {
        assert.equal(validarValor({ valor: '2026-03', etiqueta: 'Mes final', tipo: 'month', desde: { valor: '2026-11', etiqueta: 'Mes inicial' } }), '«Mes final» no puede ser anterior a «Mes inicial».');
        assert.equal(validarValor({ valor: '2024', etiqueta: 'Año final', tipo: 'number', desde: { valor: '2026', etiqueta: 'Año inicial' } }), '«Año final» no puede ser anterior a «Año inicial».');
        assert.equal(validarValor({ valor: '2030', etiqueta: 'Año final', tipo: 'number', desde: { valor: '999', etiqueta: 'Año inicial' } }), null);
    });

    test('sin fecha inicial no se comprueba el rango', () => {
        assert.equal(validarValor({ valor: '2026-11-01', etiqueta: 'Hasta', tipo: 'date', desde: { valor: '', etiqueta: 'Desde' } }), null);
    });
});

describe('describirCampo', () => {
    function campo({ id, name = id, type = 'text', value = '', atributos = {} }) {
        return {
            id,
            name,
            value,
            getAttribute: (nombre) => (nombre === 'type' ? type : atributos[nombre] ?? null),
            hasAttribute: (nombre) => nombre in atributos,
        };
    }

    function formulario(campos, etiquetas) {
        return {
            querySelector(selector) {
                const porId = /^#(.+)$/.exec(selector);
                const porEtiqueta = /^label\[for="(.+)"\]$/.exec(selector);
                const porNombre = /^\[name="(.+)"\]$/.exec(selector);

                if (porId) return campos.find((c) => c.id === porId[1]) ?? null;
                if (porNombre) return campos.find((c) => c.name === porNombre[1]) ?? null;
                if (porEtiqueta) return etiquetas[porEtiqueta[1]] ? { textContent: etiquetas[porEtiqueta[1]] } : null;

                return null;
            },
        };
    }

    test('lee el tipo, las reglas y los campos relacionados de los atributos', () => {
        const inicio = campo({ id: 'fecha_inicio', type: 'date', value: '2026-11-10' });
        const fin = campo({ id: 'fecha_fin', type: 'date', value: '2026-11-01', atributos: { 'data-requerido-con': 'fecha_inicio', 'data-fecha-desde': 'fecha_inicio' } });
        const form = formulario([inicio, fin], { fecha_inicio: 'Desde', fecha_fin: 'Hasta' });

        const descripcion = describirCampo(fin, form);

        assert.equal(descripcion.tipo, 'date');
        assert.equal(descripcion.etiqueta, 'Hasta');
        assert.deepEqual(descripcion.desde, { valor: '2026-11-10', etiqueta: 'Desde' });
        assert.equal(validarValor(descripcion), '«Hasta» no puede ser anterior a «Desde».');
    });

    test('quita el asterisco de obligatorio de la etiqueta', () => {
        const monto = campo({ id: 'monto', type: 'number', atributos: { required: '', 'data-monto-positivo': '' } });
        const form = formulario([monto], { monto: '\n  Monto*\n ' });

        assert.equal(etiquetaDe(monto, form), 'Monto');
        assert.equal(validarValor(describirCampo(monto, form)), 'El campo «Monto» es obligatorio.');
    });
});
