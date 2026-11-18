/**
 * Tema visual de los gráficos a partir de las variables CSS de Bootstrap, para que
 * tipografía y colores sigan la hoja de estilos de Finanzy.
 */

function cssVariable(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value || fallback;
}

export function readTheme() {
    return {
        fontFamily: cssVariable('--bs-body-font-family', 'system-ui, sans-serif'),
        surfaceColor: cssVariable('--bs-body-bg', '#ffffff'),
        textColor: cssVariable('--bs-body-color', '#212529'),
        mutedColor: cssVariable('--bs-secondary-color', 'rgba(33, 37, 41, 0.75)'),
        gridColor: cssVariable('--bs-border-color-translucent', 'rgba(0, 0, 0, 0.175)'),
    };
}

/**
 * Valores por defecto globales de Chart.js (también afectan a gráficos creados a mano,
 * como los de /demo, sin cambiar sus datos ni sus colores).
 */
export function applyChartDefaults(Chart, theme) {
    Chart.defaults.font.family = theme.fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = theme.mutedColor;
    Chart.defaults.borderColor = theme.gridColor;

    Object.assign(Chart.defaults.plugins.tooltip, {
        backgroundColor: 'rgba(33, 37, 41, 0.92)',
        titleColor: '#ffffff',
        bodyColor: '#ffffff',
        padding: 10,
        cornerRadius: 6,
        boxPadding: 4,
        usePointStyle: true,
    });

    Chart.defaults.plugins.legend.labels.boxWidth = 12;
}
