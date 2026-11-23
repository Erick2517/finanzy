<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerarReporteRequest;
use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\Movimiento;
use App\Services\Finanzas\ReporteFinanciero;
use App\Services\Finanzas\TipoReporte;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Reportes financieros diarios, mensuales y anuales, en pantalla y en PDF.
 * Ambas salidas (y el CSV de ExportarReporteController) usan los mismos filtros y el
 * mismo cálculo (ReporteFinanciero).
 */
class ReporteController extends Controller
{
    public const MOVIMIENTOS_POR_PAGINA = 15;

    public function index(GenerarReporteRequest $request, ReporteFinanciero $reportes, FuenteDatosFinancieros $fuente): View
    {
        $reporte = $reportes->generar($request->filtros($fuente));

        return view('reportes.index', [
            'reporte' => $reporte,
            'movimientos' => $this->paginar($reporte['movimientos'], $request),
            'categorias' => $fuente->categorias(),
            'tipos' => TipoReporte::cases(),
        ]);
    }

    public function pdf(GenerarReporteRequest $request, ReporteFinanciero $reportes, FuenteDatosFinancieros $fuente): Response
    {
        $filtros = $request->filtros($fuente);

        $documento = Pdf::loadView('reportes.pdf', [
            'reporte' => $reportes->generar($filtros),
            'generadoEl' => now(),
        ])->setPaper('a4');

        $documento->render();
        $this->numerarPaginas($documento->getDomPDF());

        return $documento->download($filtros->nombreDeArchivo('pdf'));
    }

    /**
     * Escribe «Página X de Y» en el pie de cada página, alineado a la derecha.
     *
     * DomPDF no implementa counter(pages) en CSS (siempre da 0), así que el total se
     * obtiene con un page_script tras el renderizado, cuando ya se conoce el número de
     * páginas. Las medidas reproducen el pie de reportes/pdf.blade.php: margen derecho
     * de 16 mm, texto de 8 px (6 pt) en gris #6c757d con DejaVu Sans y, en vertical,
     * la línea del pie (margen inferior 20 mm − bottom 12 mm + alto 8 mm = 16 mm del borde).
     */
    private function numerarPaginas(Dompdf $dompdf): void
    {
        $milimetro = 72 / 25.4;
        $margenDerecho = 16 * $milimetro;
        $distanciaAlBorde = 16 * $milimetro;
        $altoDelTexto = 6;

        $dompdf->getCanvas()->page_script(function (int $pagina, int $totalDePaginas, Canvas $lienzo, FontMetrics $metricas) use ($margenDerecho, $altoDelTexto, $distanciaAlBorde) {
            $texto = "Página {$pagina} de {$totalDePaginas}";
            $fuente = $metricas->getFont('DejaVu Sans');
            $ancho = $metricas->getTextWidth($texto, $fuente, $altoDelTexto);

            $lienzo->text(
                $lienzo->get_width() - $margenDerecho - $ancho,
                $lienzo->get_height() - $distanciaAlBorde,
                $texto,
                $fuente,
                $altoDelTexto,
                [108 / 255, 117 / 255, 125 / 255],
            );
        });
    }

    /**
     * @param  list<Movimiento>  $movimientos
     * @return LengthAwarePaginator<int, Movimiento>
     */
    private function paginar(array $movimientos, GenerarReporteRequest $request): LengthAwarePaginator
    {
        $pagina = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            array_slice($movimientos, ($pagina - 1) * self::MOVIMIENTOS_POR_PAGINA, self::MOVIMIENTOS_POR_PAGINA),
            count($movimientos),
            self::MOVIMIENTOS_POR_PAGINA,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
