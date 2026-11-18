/**
 * Pruebas de las funciones puras del sistema de gráficos.
 * Se ejecutan con el ejecutor nativo de Node (sin dependencias):
 *
 *     docker compose run --rm frontend node --test tests/js/charts.test.js
 */

import assert from 'node:assert/strict';
import { describe, test } from 'node:test';
import { buildChartConfig, COMPACT_WIDTH, isEmptySpec, PALETTE, resolveColor, SEMANTIC_COLORS } from '../../resources/js/charts/config.js';
import { formatMoney, formatPercent, getFormatter } from '../../resources/js/charts/format.js';

const theme = { surfaceColor: '#ffffff', textColor: '#212529', mutedColor: '#6c757d', gridColor: '#dee2e6' };

const barSpec = {
    type: 'bar',
    labels: ['Ene', 'Feb'],
    datasets: [
        { label: 'Ingresos', data: [1000, 2000], color: 'success' },
        { label: 'Gastos', data: [500, 800], color: 'danger' },
    ],
    format: 'currency',
};

describe('formatMoney', () => {
    test('coincide con el componente Blade x-ui.money', () => {
        assert.equal(formatMoney(12500), '$12,500.00');
        assert.equal(formatMoney(-1234.5), '-$1,234.50');
        assert.equal(formatMoney('8430.5'), '$8,430.50');
    });

    test('redondea la mitad hacia arriba como PHP, sin error de coma flotante', () => {
        assert.equal(formatMoney(10.555), '$10.56');
        assert.equal(formatMoney(1.005), '$1.01');
    });

    test('un negativo que redondea a cero no muestra signo', () => {
        assert.equal(formatMoney(-0.004), '$0.00');
    });

    test('el formato compacto de los ejes no muestra decimales', () => {
        assert.equal(getFormatter('currency', { compact: true })(12500), '$12,500');
    });
});

describe('formatPercent', () => {
    test('muestra un decimal y el símbolo de porcentaje', () => {
        assert.equal(formatPercent(12.345), '12.3%');
    });
});

describe('resolveColor', () => {
    test('acepta nombres semánticos y hexadecimales', () => {
        assert.equal(resolveColor('success', 5), SEMANTIC_COLORS.success);
        assert.equal(resolveColor('#123abc', 0), '#123abc');
    });

    test('sin color usa la paleta en orden fijo y nunca genera tonos nuevos', () => {
        assert.equal(resolveColor(undefined, 0), PALETTE[0]);
        assert.equal(resolveColor(undefined, 7), PALETTE[7]);
        assert.equal(resolveColor(undefined, 8), SEMANTIC_COLORS.secondary);
    });

    test('un valor desconocido no se inyecta como color', () => {
        assert.equal(resolveColor('red; background: url(x)', 1), PALETTE[1]);
    });
});

describe('isEmptySpec', () => {
    test('detecta la ausencia de datos', () => {
        assert.equal(isEmptySpec({ type: 'bar', labels: [], datasets: [] }), true);
        assert.equal(isEmptySpec({ type: 'bar', labels: ['Ene'], datasets: [] }), true);
        assert.equal(isEmptySpec({ type: 'bar', labels: ['Ene', 'Feb'], datasets: [{ data: [0, null] }] }), true);
    });

    test('un solo valor distinto de cero (incluso negativo) es un dato', () => {
        assert.equal(isEmptySpec({ type: 'bar', labels: ['Ene', 'Feb'], datasets: [{ data: [0, -50] }] }), false);
    });
});

describe('buildChartConfig', () => {
    test('rechaza tipos no soportados', () => {
        assert.throws(() => buildChartConfig({ ...barSpec, type: 'radar' }, theme), /no soportado/);
    });

    test('barras: colores semánticos, extremos redondeados y escala responsive', () => {
        const config = buildChartConfig(barSpec, theme);

        assert.equal(config.data.datasets[0].backgroundColor, SEMANTIC_COLORS.success);
        assert.equal(config.data.datasets[1].backgroundColor, SEMANTIC_COLORS.danger);
        assert.equal(config.data.datasets[0].borderRadius, 4);
        assert.equal(config.options.responsive, true);
        assert.equal(config.options.maintainAspectRatio, false);
        assert.equal(config.options.scales.y.beginAtZero, true);
    });

    test('el eje y los tooltips usan formato en dólares', () => {
        const config = buildChartConfig(barSpec, theme);
        const tooltip = config.options.plugins.tooltip.callbacks.label({ dataset: { label: 'Ingresos' }, raw: 1500.5 });

        assert.equal(config.options.scales.y.ticks.callback(2000), '$2,000');
        assert.equal(tooltip, ' Ingresos: $1,500.50');
    });

    test('la leyenda aparece con dos o más series y se oculta con una sola', () => {
        const single = { ...barSpec, datasets: [barSpec.datasets[0]] };

        assert.equal(buildChartConfig(barSpec, theme).options.plugins.legend.display, true);
        assert.equal(buildChartConfig(single, theme).options.plugins.legend.display, false);
    });

    test('las barras apiladas apilan ambos ejes', () => {
        const config = buildChartConfig({ ...barSpec, stacked: true }, theme);

        assert.equal(config.options.scales.x.stacked, true);
        assert.equal(config.options.scales.y.stacked, true);
    });

    test('líneas: trazo de 2px y marcadores de al menos 8px', () => {
        const config = buildChartConfig({ ...barSpec, type: 'line' }, theme);
        const dataset = config.data.datasets[0];

        assert.equal(dataset.borderWidth, 2);
        assert.ok(dataset.pointRadius * 2 >= 8);
        assert.equal(dataset.borderColor, SEMANTIC_COLORS.success);
    });

    test('dona: un color por sector, separación del color de fondo y sin ejes', () => {
        const config = buildChartConfig({
            type: 'doughnut',
            labels: ['Salario', 'Ventas', 'Otros'],
            datasets: [{ label: 'Ingresos', data: [500, 300, 200] }],
        }, theme);

        assert.deepEqual(config.data.datasets[0].backgroundColor, PALETTE.slice(0, 3));
        assert.equal(config.data.datasets[0].borderColor, theme.surfaceColor);
        assert.deepEqual(config.options.scales, {});
        assert.equal(config.options.plugins.legend.display, true);
    });

    test('el tooltip de la dona incluye el porcentaje del total', () => {
        const config = buildChartConfig({ type: 'doughnut', labels: ['A', 'B'], datasets: [{ data: [750, 250] }] }, theme);
        const label = config.options.plugins.tooltip.callbacks.label({ label: 'A', raw: 750, dataset: { data: [750, 250] } });

        assert.equal(label, ' A: $750.00 (75.0%)');
    });

    test('la leyenda de la dona muestra el porcentaje de cada sector y su visibilidad', () => {
        const config = buildChartConfig({ type: 'doughnut', labels: ['Planilla', 'Alquiler'], datasets: [{ data: [600, 400] }] }, theme);
        const chart = {
            data: config.data,
            getDataVisibility: (index) => index !== 1,
        };

        const items = config.options.plugins.legend.labels.generateLabels(chart);

        assert.deepEqual(items.map((item) => item.text), ['Planilla (60.0%)', 'Alquiler (40.0%)']);
        assert.deepEqual(items.map((item) => item.index), [0, 1]);
        assert.deepEqual(items.map((item) => item.hidden), [false, true]);
        assert.equal(items[0].fillStyle, PALETTE[0]);
    });

    test('las barras no redefinen las etiquetas de la leyenda', () => {
        assert.equal(buildChartConfig(barSpec, theme).options.plugins.legend.labels.generateLabels, undefined);
    });

    test('la leyenda pasa abajo en contenedores estrechos', () => {
        assert.equal(buildChartConfig(barSpec, theme, { width: COMPACT_WIDTH - 1 }).options.plugins.legend.position, 'bottom');
        assert.equal(buildChartConfig(barSpec, theme, { width: 900 }).options.plugins.legend.position, 'top');
    });

    test('al redimensionar se reubica la leyenda una sola vez', () => {
        const config = buildChartConfig(barSpec, theme, { width: 900 });
        let updates = 0;
        const chart = { options: config.options, update: () => { updates++; } };

        config.options.onResize(chart, { width: 300 });
        config.options.onResize(chart, { width: 320 });

        assert.equal(chart.options.plugins.legend.position, 'bottom');
        assert.equal(updates, 1);
    });
});
