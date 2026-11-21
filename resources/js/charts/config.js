/**
 * Construcción de configuraciones Chart.js de Finanzy. Funciones puras: no usan el DOM
 * ni importan Chart.js, por eso se prueban con `node --test`.
 *
 * Especificación que llega desde Blade (atributo data-chart del <canvas>):
 * {
 *   type: 'bar' | 'line' | 'doughnut',
 *   labels: string[],
 *   datasets: [{ label: string, data: (number|null)[], color?: string }],
 *   format: 'currency' | 'number' | 'percent',   // por defecto 'currency'
 *   stacked: boolean                              // solo barras y líneas
 * }
 */

import { formatPercent, getFormatter } from './format.js';

export const SUPPORTED_TYPES = ['bar', 'line', 'doughnut'];

/**
 * Paleta categórica en orden fijo (tonos de la escala de Bootstrap 5.3).
 * Validada con el verificador de la guía de visualización: banda de luminosidad,
 * croma, separación CVD (ΔE mínimo 12.9 entre vecinos) y visión normal (ΔE 18.0).
 * Amarillo y cian quedan por debajo de 3:1 de contraste: por eso los gráficos
 * siempre llevan leyenda/tooltips y una tabla accesible.
 */
export const PALETTE = ['#0d6efd', '#198754', '#cc9a06', '#dc3545', '#0aa2c0', '#6610f2', '#ca6510', '#d63384'];

/** Colores con significado, para series como ingresos (success) o gastos (danger). */
export const SEMANTIC_COLORS = {
    primary: '#0d6efd',
    success: '#198754',
    danger: '#dc3545',
    warning: '#cc9a06',
    info: '#0aa2c0',
    indigo: '#6610f2',
    orange: '#ca6510',
    pink: '#d63384',
    secondary: '#6c757d',
};

/** Ancho (px) por debajo del cual la leyenda pasa abajo. */
export const COMPACT_WIDTH = 480;

/**
 * Color de la serie: nombre semántico, hexadecimal o, si no se indica, el de la paleta
 * según su posición (nunca se generan tonos nuevos: a partir del 9.º se repite el gris).
 */
export function resolveColor(color, index = 0) {
    if (color && SEMANTIC_COLORS[color]) {
        return SEMANTIC_COLORS[color];
    }

    if (typeof color === 'string' && /^#[0-9a-f]{6}$/i.test(color)) {
        return color;
    }

    return PALETTE[index] ?? SEMANTIC_COLORS.secondary;
}

export function withAlpha(hex, alpha) {
    const value = Number.parseInt(hex.slice(1), 16);

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}

/**
 * Sin datos que dibujar: sin etiquetas, sin series o con todos los valores vacíos o en cero.
 * Debe coincidir con la regla del componente Blade chart-card.
 */
export function isEmptySpec(spec) {
    if (!spec || !Array.isArray(spec.labels) || spec.labels.length === 0) {
        return true;
    }

    const values = (spec.datasets ?? []).flatMap((dataset) => dataset.data ?? []);

    return values.every((value) => value === null || value === undefined || Number(value) === 0);
}

/**
 * Leyenda de la dona con el porcentaje de cada sector sobre el total: "Planilla (35.2%)".
 * Conserva el índice para que al pulsar un elemento se oculte o muestre su sector.
 */
export function doughnutLegendLabels(chart, theme) {
    const dataset = chart.data.datasets[0] ?? { data: [], backgroundColor: [] };
    const values = dataset.data.map((value) => Number(value) || 0);
    const total = values.reduce((sum, value) => sum + value, 0);

    return chart.data.labels.map((label, index) => ({
        text: `${label} (${formatPercent(total === 0 ? 0 : (values[index] / total) * 100)})`,
        fillStyle: dataset.backgroundColor[index],
        strokeStyle: dataset.backgroundColor[index],
        fontColor: theme.textColor,
        lineWidth: 0,
        pointStyle: 'rectRounded',
        hidden: typeof chart.getDataVisibility === 'function' ? !chart.getDataVisibility(index) : false,
        index,
    }));
}

function legendPosition(type, width) {
    if (width < COMPACT_WIDTH) {
        return 'bottom';
    }

    return type === 'doughnut' ? 'right' : 'top';
}

function buildDatasets(spec, theme) {
    if (spec.type === 'doughnut') {
        return spec.datasets.map((dataset) => ({
            label: dataset.label,
            data: dataset.data,
            backgroundColor: spec.labels.map((_, index) => resolveColor(dataset.colors?.[index], index)),
            borderColor: theme.surfaceColor,
            borderWidth: 2,
            hoverOffset: 6,
        }));
    }

    return spec.datasets.map((dataset, index) => {
        const color = resolveColor(dataset.color, index);

        if (spec.type === 'line') {
            return {
                label: dataset.label,
                data: dataset.data,
                borderColor: color,
                backgroundColor: withAlpha(color, 0.12),
                borderWidth: 2,
                tension: 0.3,
                fill: spec.datasets.length === 1,
                spanGaps: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: color,
                pointBorderColor: theme.surfaceColor,
                pointBorderWidth: 2,
            };
        }

        return {
            label: dataset.label,
            data: dataset.data,
            backgroundColor: color,
            hoverBackgroundColor: withAlpha(color, 0.85),
            borderColor: theme.surfaceColor,
            borderWidth: { top: 0, right: 1, bottom: 0, left: 1 },
            borderRadius: 4,
            borderSkipped: 'start',
            maxBarThickness: 36,
        };
    });
}

function buildScales(spec, theme, format) {
    if (spec.type === 'doughnut') {
        return {};
    }

    const stacked = Boolean(spec.stacked);
    const formatAxis = getFormatter(format, { compact: true });

    return {
        x: {
            stacked,
            grid: { display: false },
            border: { display: false },
            ticks: { color: theme.mutedColor, maxRotation: 0, autoSkip: true },
        },
        y: {
            stacked,
            beginAtZero: true,
            grid: { color: theme.gridColor },
            border: { display: false },
            ticks: { color: theme.mutedColor, maxTicksLimit: 6, callback: (value) => formatAxis(value) },
        },
    };
}

function buildTooltipLabel(spec, format) {
    const formatValue = getFormatter(format);

    if (spec.type === 'doughnut') {
        return (context) => {
            const values = context.dataset.data.map((value) => Number(value) || 0);
            const total = values.reduce((sum, value) => sum + value, 0);
            const share = total === 0 ? 0 : (Number(context.raw) / total) * 100;

            return ` ${context.label}: ${formatValue(context.raw)} (${share.toFixed(1)}%)`;
        };
    }

    return (context) => ` ${context.dataset.label}: ${formatValue(context.raw)}`;
}

/**
 * Configuración completa de Chart.js para una especificación.
 *
 * @param {object} spec  especificación (ver cabecera del archivo)
 * @param {{ surfaceColor: string, textColor: string, mutedColor: string, gridColor: string }} theme
 * @param {{ width?: number }} context ancho inicial del contenedor (para la leyenda)
 */
export function buildChartConfig(spec, theme, { width = 1024 } = {}) {
    if (!SUPPORTED_TYPES.includes(spec.type)) {
        throw new Error(`Tipo de gráfico no soportado: "${spec.type}". Use: ${SUPPORTED_TYPES.join(', ')}.`);
    }

    const format = spec.format ?? 'currency';
    const showLegend = spec.type === 'doughnut' || spec.datasets.length > 1;

    return {
        type: spec.type,
        data: {
            labels: spec.labels,
            datasets: buildDatasets(spec, theme),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: spec.type === 'doughnut' ? '62%' : undefined,
            interaction: spec.type === 'doughnut' ? { mode: 'nearest', intersect: true } : { mode: 'index', intersect: false },
            scales: buildScales(spec, theme, format),
            plugins: {
                legend: {
                    display: showLegend,
                    position: legendPosition(spec.type, width),
                    labels: {
                        color: theme.textColor,
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        padding: 16,
                        ...(spec.type === 'doughnut' ? { generateLabels: (chart) => doughnutLegendLabels(chart, theme) } : {}),
                    },
                },
                tooltip: {
                    callbacks: { label: buildTooltipLabel(spec, format) },
                },
            },
            onResize: (chart, size) => {
                const position = legendPosition(spec.type, size.width);

                if (chart.options.plugins.legend.position !== position) {
                    chart.options.plugins.legend.position = position;
                    chart.update('none');
                }
            },
        },
    };
}
