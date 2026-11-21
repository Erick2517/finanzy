<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerarReporteRequest;
use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\Movimiento;
use App\Services\Finanzas\ReporteFinanciero;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación del reporte a CSV (se abre en Excel). Usa los mismos filtros y el mismo
 * cálculo que la pantalla y el PDF, así que exporta exactamente los movimientos que el
 * usuario está viendo, en el mismo orden cronológico.
 */
class ExportarReporteController extends Controller
{
    /** Excel en español espera el punto y coma como separador. */
    private const SEPARADOR = ';';

    public function csv(GenerarReporteRequest $request, ReporteFinanciero $reportes, FuenteDatosFinancieros $fuente): StreamedResponse
    {
        $filtros = $request->filtros($fuente);
        $movimientos = $reportes->generar($filtros)['movimientos'];

        return response()->streamDownload(function () use ($movimientos) {
            $salida = fopen('php://output', 'w');

            // BOM: Excel reconoce así la codificación UTF-8 (tildes y eñes).
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha', 'Tipo', 'Categoría', 'Descripción', 'Monto'], self::SEPARADOR);

            foreach ($movimientos as $movimiento) {
                fputcsv($salida, $this->fila($movimiento), self::SEPARADOR);
            }

            fclose($salida);
        }, $filtros->nombreDeArchivo('csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string>
     */
    private function fila(Movimiento $movimiento): array
    {
        return [
            $movimiento->fecha->format('Y-m-d'),
            $movimiento->tipo->etiqueta(),
            self::textoSeguro($movimiento->categoria),
            self::textoSeguro($movimiento->descripcion),
            number_format($movimiento->monto, 2, '.', ''),
        ];
    }

    /**
     * Evita la inyección de fórmulas: Excel ejecuta como fórmula el texto que empieza
     * por =, +, -, @, tabulador o retorno de carro. Con un apóstrofo delante se muestra
     * como texto.
     */
    private static function textoSeguro(string $texto): string
    {
        return preg_match('/^[=+\-@\t\r]/', $texto) === 1 ? "'".$texto : $texto;
    }
}
