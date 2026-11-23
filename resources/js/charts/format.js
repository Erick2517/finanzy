/**
 * Formatos numéricos de Finanzy. Funciones puras (sin DOM ni Chart.js).
 *
 * formatMoney produce exactamente el mismo texto que el componente Blade
 * <x-ui.money>: $1,234.56 / -$1,234.56.
 */

const groupedNumber = (decimals) =>
    new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });

/**
 * Redondea "mitad hacia arriba" como PHP round() y evita el cero negativo
 * (-0.004 se muestra como 0). Se usa notación exponencial para que 10.555
 * redondee a 10.56 y no a 10.55 por el error de coma flotante de 10.555 * 100.
 */
function roundTo(value, decimals) {
    const number = Number(value) || 0;
    const rounded = Number(`${Math.round(Number(`${Math.abs(number)}e${decimals}`))}e-${decimals}`);

    return number < 0 && rounded !== 0 ? -rounded : rounded;
}

export function formatMoney(value, { decimals = 2 } = {}) {
    const rounded = roundTo(value, decimals);
    const sign = rounded < 0 ? '-' : '';

    return `${sign}$${groupedNumber(decimals).format(Math.abs(rounded))}`;
}

export function formatNumber(value, { decimals = 0 } = {}) {
    return groupedNumber(decimals).format(roundTo(value, decimals));
}

export function formatPercent(value, { decimals = 1 } = {}) {
    return `${formatNumber(value, { decimals })}%`;
}

/**
 * Devuelve el formateador para un tipo de dato: 'currency' (por defecto), 'number' o 'percent'.
 *
 * @param {'currency'|'number'|'percent'} format
 * @param {{ compact?: boolean }} options compact: sin decimales, para los ejes.
 */
export function getFormatter(format = 'currency', { compact = false } = {}) {
    switch (format) {
        case 'number':
            return (value) => formatNumber(value);
        case 'percent':
            return (value) => formatPercent(value, { decimals: compact ? 0 : 1 });
        default:
            return (value) => formatMoney(value, { decimals: compact ? 0 : 2 });
    }
}
