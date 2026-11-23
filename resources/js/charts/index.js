/**
 * Sistema de gráficos de Finanzy.
 *
 * Uso desde Blade (sin JavaScript propio en la vista):
 *
 *     <x-financial.chart-card chart-id="ingresosGastos" type="bar"
 *         :labels="$meses" :datasets="$series" format="currency" />
 *
 * El componente escribe la especificación en el atributo data-chart del <canvas>
 * y este módulo crea los gráficos al cargar la página.
 *
 * API global (window.FinanzyCharts), para contenido que se reemplaza dinámicamente:
 *   init(root)          crea o recrea los gráficos dentro de root (idempotente)
 *   update(canvas, spec) redibuja un gráfico con datos nuevos (o muestra el estado vacío)
 *   destroy(root)       destruye los gráficos dentro de root
 */

import Chart from 'chart.js/auto';
import { buildChartConfig, isEmptySpec } from './config.js';
import { applyChartDefaults, readTheme } from './theme.js';

const SELECTOR = 'canvas[data-chart]';

let theme = null;

function currentTheme() {
    if (!theme) {
        theme = readTheme();
        applyChartDefaults(Chart, theme);
    }

    return theme;
}

function resolveCanvas(target) {
    return typeof target === 'string' ? document.getElementById(target) : target;
}

function parseSpec(canvas) {
    try {
        return JSON.parse(canvas.dataset.chart);
    } catch (error) {
        console.error(`[FinanzyCharts] data-chart inválido en #${canvas.id}`, error);

        return null;
    }
}

function toggleEmptyState(canvas, isEmpty) {
    canvas.hidden = isEmpty;

    const emptyState = canvas.parentElement?.querySelector('[data-chart-empty]');

    if (emptyState) {
        emptyState.hidden = !isEmpty;
    }
}

/** Destruye los gráficos gestionados cuyo <canvas> ya no está en el documento. */
function destroyDetached() {
    Object.values(Chart.instances).forEach((chart) => {
        if (chart.canvas?.hasAttribute('data-chart') && !chart.canvas.isConnected) {
            chart.destroy();
        }
    });
}

function render(canvas, spec) {
    Chart.getChart(canvas)?.destroy();

    const isEmpty = !spec || isEmptySpec(spec);
    toggleEmptyState(canvas, isEmpty);

    if (isEmpty) {
        return null;
    }

    const width = canvas.parentElement?.clientWidth ?? 1024;

    return new Chart(canvas, buildChartConfig(spec, currentTheme(), { width }));
}

export function initCharts(root = document) {
    currentTheme();
    destroyDetached();

    return [...root.querySelectorAll(SELECTOR)].map((canvas) => render(canvas, parseSpec(canvas)));
}

export function updateChart(target, spec) {
    const canvas = resolveCanvas(target);

    if (!canvas) {
        console.error('[FinanzyCharts] No se encontró el gráfico', target);

        return null;
    }

    canvas.dataset.chart = JSON.stringify(spec);

    return render(canvas, spec);
}

export function destroyCharts(root = document) {
    root.querySelectorAll(SELECTOR).forEach((canvas) => Chart.getChart(canvas)?.destroy());
}

export const FinanzyCharts = { init: initCharts, update: updateChart, destroy: destroyCharts };

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initCharts());
} else {
    initCharts();
}
