/**
 * Validaciones reutilizables del lado del cliente (Finanzy).
 *
 * Laravel (FormRequest) sigue siendo la validación definitiva: esto solo evita enviar
 * formularios con errores evidentes y muestra los mensajes antes, en español.
 *
 * Uso en cualquier módulo — marcar el formulario y usar atributos estándar:
 *
 *   <form method="POST" action="…" data-validar novalidate>
 *       <input id="concepto" name="concepto" required>                         obligatorio
 *       <input id="monto" name="monto" type="number" step="0.01"
 *              data-monto-positivo required>                                   número > 0
 *       <input id="fecha" name="fecha" type="date" required>                   fecha válida
 *       <input id="fecha_inicio" name="fecha_inicio" type="date"
 *              data-requerido-con="fecha_fin">                                 obligatorio si el otro tiene valor
 *       <input id="fecha_fin" name="fecha_fin" type="date"
 *              data-requerido-con="fecha_inicio" data-fecha-desde="fecha_inicio">  no anterior al inicio
 *   </form>
 *
 * También se respetan min y max en los campos numéricos, y type="month" (AAAA-MM).
 * Los nombres de data-requerido-con y data-fecha-desde son el id (o el name) del otro campo.
 * El texto de los mensajes usa la etiqueta <label for="…"> del campo.
 */

const FORMATO_FECHA = /^(\d{4})-(\d{2})-(\d{2})$/;
const FORMATO_MES = /^(\d{4})-(\d{2})$/;

function estaVacio(valor) {
    return valor === null || valor === undefined || String(valor).trim() === '';
}

function esFechaReal(valor) {
    const partes = FORMATO_FECHA.exec(valor);

    if (!partes) {
        return false;
    }

    const [anio, mes, dia] = partes.slice(1).map(Number);
    const fecha = new Date(Date.UTC(anio, mes - 1, dia));

    return fecha.getUTCFullYear() === anio && fecha.getUTCMonth() === mes - 1 && fecha.getUTCDate() === dia;
}

function esMesReal(valor) {
    const partes = FORMATO_MES.exec(valor);

    return Boolean(partes) && Number(partes[2]) >= 1 && Number(partes[2]) <= 12;
}

function esNumero(valor) {
    return /^-?\d+(\.\d+)?$/.test(String(valor).trim());
}

/**
 * Compara dos valores del mismo tipo de campo: números como números y fechas o meses
 * ISO (AAAA-MM-DD, AAAA-MM) como texto, que en ese formato conserva el orden.
 */
function esAnterior(valor, referencia, tipo) {
    return tipo === 'number' ? Number(valor) < Number(referencia) : String(valor) < String(referencia);
}

/**
 * Regla pura: devuelve el mensaje de error de un campo o null si es válido.
 *
 * @param {{
 *   valor: string, etiqueta: string, tipo?: 'text'|'number'|'date'|'month',
 *   requerido?: boolean, min?: string|null, max?: string|null, montoPositivo?: boolean,
 *   requeridoCon?: {valor: string, etiqueta: string}|null,
 *   desde?: {valor: string, etiqueta: string}|null
 * }} campo
 */
export function validarValor(campo) {
    const { valor, etiqueta, tipo = 'text', requerido = false, min = null, max = null, montoPositivo = false, requeridoCon = null, desde = null } = campo;

    if (estaVacio(valor)) {
        if (requerido) {
            return `El campo «${etiqueta}» es obligatorio.`;
        }

        if (requeridoCon && !estaVacio(requeridoCon.valor)) {
            return `Completa «${etiqueta}» cuando indicas «${requeridoCon.etiqueta}».`;
        }

        return null;
    }

    if (tipo === 'number' || montoPositivo) {
        if (!esNumero(valor)) {
            return `Escribe un número válido en «${etiqueta}».`;
        }

        if (montoPositivo && Number(valor) <= 0) {
            return `El monto de «${etiqueta}» debe ser mayor que cero.`;
        }

        if (!estaVacio(min) && Number(valor) < Number(min)) {
            return `«${etiqueta}» debe ser mayor o igual que ${min}.`;
        }

        if (!estaVacio(max) && Number(valor) > Number(max)) {
            return `«${etiqueta}» debe ser menor o igual que ${max}.`;
        }
    }

    if (tipo === 'date' && !esFechaReal(valor)) {
        return `Selecciona una fecha válida en «${etiqueta}».`;
    }

    if (tipo === 'month' && !esMesReal(valor)) {
        return `Usa el formato AAAA-MM en «${etiqueta}» (por ejemplo, 2026-11).`;
    }

    if (desde && !estaVacio(desde.valor) && esAnterior(valor, desde.valor, tipo)) {
        return `«${etiqueta}» no puede ser anterior a «${desde.etiqueta}».`;
    }

    return null;
}

/* ------------------------------------------------------------------ */
/* Conexión con el DOM                                                 */
/* ------------------------------------------------------------------ */

const SELECTOR_CAMPOS = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea';

function buscarCampo(formulario, referencia) {
    return formulario.querySelector(`#${CSS.escape(referencia)}`) ?? formulario.querySelector(`[name="${CSS.escape(referencia)}"]`);
}

export function etiquetaDe(campo, formulario) {
    const etiqueta = campo.id ? formulario.querySelector(`label[for="${CSS.escape(campo.id)}"]`) : null;
    const texto = etiqueta?.textContent ?? campo.getAttribute('aria-label') ?? campo.name ?? '';

    return texto.replace(/\*/g, '').replace(/\s+/g, ' ').trim();
}

function tipoDe(campo) {
    const tipo = (campo.getAttribute('type') ?? '').toLowerCase();

    return ['number', 'date', 'month'].includes(tipo) ? tipo : 'text';
}

function relacion(campo, formulario, atributo) {
    const referencia = campo.getAttribute(atributo);
    const otro = referencia ? buscarCampo(formulario, referencia) : null;

    return otro ? { valor: otro.value, etiqueta: etiquetaDe(otro, formulario) } : null;
}

export function describirCampo(campo, formulario) {
    return {
        valor: campo.value,
        etiqueta: etiquetaDe(campo, formulario),
        tipo: tipoDe(campo),
        requerido: campo.hasAttribute('required'),
        min: campo.getAttribute('min'),
        max: campo.getAttribute('max'),
        montoPositivo: campo.hasAttribute('data-monto-positivo'),
        requeridoCon: relacion(campo, formulario, 'data-requerido-con'),
        desde: relacion(campo, formulario, 'data-fecha-desde'),
    };
}

/** Contenedor tras el que va el mensaje (en un .input-group, tras el grupo). */
function anclaDelMensaje(campo) {
    return campo.closest('.input-group') ?? campo;
}

function quitarMensajesDelServidor(campo) {
    anclaDelMensaje(campo).parentElement
        ?.querySelectorAll(':scope > .invalid-feedback:not([data-validacion-cliente])')
        .forEach((mensaje) => mensaje.remove());
}

export function mostrarError(campo, mensaje) {
    quitarMensajesDelServidor(campo);

    const id = `${campo.id || campo.name}-error-cliente`;
    let elemento = campo.ownerDocument.getElementById(id);

    if (!elemento) {
        elemento = campo.ownerDocument.createElement('div');
        elemento.id = id;
        elemento.className = 'invalid-feedback d-block';
        elemento.setAttribute('data-validacion-cliente', '');
        anclaDelMensaje(campo).insertAdjacentElement('afterend', elemento);
    }

    elemento.textContent = mensaje;
    campo.classList.add('is-invalid');
    campo.setAttribute('aria-invalid', 'true');
    campo.setAttribute('aria-describedby', id);
}

export function limpiarError(campo) {
    campo.ownerDocument.getElementById(`${campo.id || campo.name}-error-cliente`)?.remove();
    quitarMensajesDelServidor(campo);
    campo.classList.remove('is-invalid');
    campo.closest('.input-group')?.classList.remove('is-invalid');
    campo.removeAttribute('aria-invalid');
    campo.removeAttribute('aria-describedby');
}

/**
 * Valida todos los campos del formulario, muestra u oculta los mensajes y devuelve
 * los campos con error (en orden).
 */
export function validarFormulario(formulario) {
    const conError = [];

    formulario.querySelectorAll(SELECTOR_CAMPOS).forEach((campo) => {
        if (campo.disabled) {
            return;
        }

        const mensaje = validarValor(describirCampo(campo, formulario));

        if (mensaje) {
            mostrarError(campo, mensaje);
            conError.push(campo);
        } else if (campo.classList.contains('is-invalid')) {
            limpiarError(campo);
        }
    });

    return conError;
}

export function registrarValidaciones(documento = document) {
    // Fase de captura: se valida antes que cualquier otro manejador de envío
    // (p. ej. el indicador de carga de estado-carga.js).
    documento.addEventListener('submit', (evento) => {
        const formulario = evento.target;

        if (!formulario.matches?.('form[data-validar]')) {
            return;
        }

        const conError = validarFormulario(formulario);

        if (conError.length > 0) {
            evento.preventDefault();
            evento.stopPropagation();
            conError[0].focus();
        }
    }, true);

    // Al corregir un campo marcado, se vuelve a comprobar ese formulario.
    const revalidar = (evento) => {
        const campo = evento.target;
        const formulario = campo.closest?.('form[data-validar]');

        if (formulario && campo.classList?.contains('is-invalid')) {
            validarFormulario(formulario);
        }
    };

    documento.addEventListener('input', revalidar);
    documento.addEventListener('change', revalidar);
}
