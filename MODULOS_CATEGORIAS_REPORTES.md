# Finanzy — categorías, reportes y pruebas

## Instalación

1. Conserva tu `.env` local y configura la base de datos.
2. Ejecuta `composer install` si no tienes dependencias.
3. Ejecuta `php artisan migrate`.
4. Ejecuta `npm install` y `npm run build` si todavía no compilaste los recursos.
5. Ejecuta `php artisan serve`.
6. Abre `/categorias` y `/reportes` con un usuario autenticado.
7. Ejecuta `php artisan test --filter=CategoriasReportesTest` para comprobar las pruebas.

## Integración

El ZIP original no contiene todavía módulos funcionales de ingresos/gastos. Por eso se añadió `movimientos_financieros` como fuente provisional para los reportes, con un formulario de registro dentro de Reportes. Cuando el equipo integre sus módulos definitivos, se debe adaptar `ReporteController` para consultar esas tablas y evitar registros duplicados. El CSV abre en Excel; no es un archivo XLSX nativo.

Los registros se aíslan por usuario. No se han ejecutado pruebas automatizadas en este entorno: deben ejecutarse con Composer, dependencias y base de datos configurados.
