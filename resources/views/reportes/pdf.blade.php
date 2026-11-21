
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <title>Finanzy - Reporte financiero</title>

    <style>
        /* CONFIGURACIÓN GENERAL */
        @page {
            margin: 30px;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #20332f;
            margin: 0;
            padding: 0;
        }

        /* ENCABEZADO */
        h1 {
            color: #167b69;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .informacion {
            font-size: 10px;
            color: #666666;
            margin-bottom: 20px;
        }

        /* RESUMEN FINANCIERO */
        .resumen {
            width: 100%;
            margin-bottom: 20px;
        }

        .resumen td {
            padding: 12px;
            border: 1px solid #dddddd;
            text-align: center;
            width: 33.33%;
        }

        .resumen .titulo {
            font-size: 11px;
            color: #666666;
        }

        .resumen .monto {
            font-size: 17px;
            font-weight: bold;
            margin-top: 5px;
        }

        .ingreso {
            color: #198754;
        }

        .gasto {
            color: #dc3545;
        }

        .balance {
            color: #167b69;
        }

        /* TABLA DE MOVIMIENTOS */
        .tabla-movimientos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabla-movimientos th,
        .tabla-movimientos td {
            padding: 8px;
            border-bottom: 1px solid #dddddd;
            text-align: left;
        }

        .tabla-movimientos th {
            background-color: #e9f3f0;
            color: #20332f;
            font-weight: bold;
        }

        .tabla-movimientos tr {
            page-break-inside: avoid;
        }

        .right {
            text-align: right !important;
        }

        .sin-datos {
            text-align: center !important;
            color: #777777;
            padding: 20px;
        }

        /* PIE DE PÁGINA */
        .footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #dddddd;
            font-size: 9px;
            color: #777777;
            text-align: center;
        }
    </style>

</head>

<body>

    <!-- ========================================= -->
    <!-- ENCABEZADO DEL REPORTE -->
    <!-- ========================================= -->

    <h1>
        Finanzy | Reporte financiero
    </h1>

    <div class="informacion">

        <strong>Generado:</strong>
        {{ now()->format('d/m/Y H:i') }}

        <br>

        <strong>Período:</strong>
        {{ $filtros['desde'] ?? 'Inicio' }}
        a
        {{ $filtros['hasta'] ?? 'Actualidad' }}

        <br>

        <strong>Tipo:</strong>
        {{ ucfirst($filtros['tipo'] ?? 'Todos') }}

    </div>


    <!-- ========================================= -->
    <!-- RESUMEN FINANCIERO -->
    <!-- ========================================= -->

    <table class="resumen">

        <tr>

            <!-- INGRESOS -->
            <td>

                <div class="titulo">
                    Total de ingresos
                </div>

                <div class="monto ingreso">
                    $ {{ number_format($resumen['ingresos'] ?? 0, 2) }}
                </div>

            </td>


            <!-- GASTOS -->
            <td>

                <div class="titulo">
                    Total de gastos
                </div>

                <div class="monto gasto">
                    $ {{ number_format($resumen['gastos'] ?? 0, 2) }}
                </div>

            </td>


            <!-- BALANCE -->
            <td>

                <div class="titulo">
                    Balance general
                </div>

                <div class="monto balance">
                    $ {{ number_format($resumen['balance'] ?? 0, 2) }}
                </div>

            </td>

        </tr>

    </table>


    <!-- ========================================= -->
    <!-- DETALLE DE MOVIMIENTOS -->
    <!-- ========================================= -->

    <h3>
        Detalle de movimientos financieros
    </h3>

    <table class="tabla-movimientos">

        <!-- ENCABEZADOS -->
        <thead>

            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Categoría</th>
                <th>Concepto</th>
                <th class="right">Monto ($)</th>
            </tr>

        </thead>


        <!-- REGISTROS -->
        <tbody>

            @forelse ($movimientos as $m)

                <tr>

                    <!-- FECHA -->
                    <td>
                        {{ $m->fecha->format('d/m/Y') }}
                    </td>


                    <!-- TIPO -->
                    <td>
                        {{ ucfirst($m->categoria->tipo) }}
                    </td>


                    <!-- CATEGORÍA -->
                    <td>
                        {{ $m->categoria->nombre }}
                    </td>


                    <!-- CONCEPTO -->
                    <td>
                        {{ $m->concepto }}
                    </td>


                    <!-- MONTO -->
                    <td class="right">

                        @if ($m->categoria->tipo === 'ingreso')

                            <span class="ingreso">
                                $ {{ number_format($m->monto, 2) }}
                            </span>

                        @else

                            <span class="gasto">
                                $ {{ number_format($m->monto, 2) }}
                            </span>

                        @endif

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="5" class="sin-datos">
                        No hay movimientos financieros
                        para los filtros seleccionados.
                    </td>
                </tr>

            @endforelse

        </tbody>

    </table>


    <!-- ========================================= -->
    <!-- PIE DE PÁGINA -->
    <!-- ========================================= -->

    <div class="footer">

        Finanzy - Sistema de Gestión Financiera

        <br>

        Reporte generado automáticamente el
        {{ now()->format('d/m/Y H:i') }}

    </div>

</body>

</html>
