<?php

namespace App\Providers;

use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\MovimientosFinancierosEloquent;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Fuente de datos del panel y de los reportes: por defecto, los datos reales del
     * usuario autenticado (MovimientosFinancierosEloquent). DatosFinancierosSimulados
     * se conserva para pruebas y demostraciones; para usarla, sustituir aquí la clase.
     */
    public function register(): void
    {
        $this->app->bind(FuenteDatosFinancieros::class, MovimientosFinancierosEloquent::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
