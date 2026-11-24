<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use App\Services\Finanzas\FuenteDatosFinancieros;
use App\Services\Finanzas\MovimientosFinancierosEloquent;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Fuente de datos del panel y de los reportes: por defecto, los datos compartidos
     * de la empresa (MovimientosFinancierosEloquent). DatosFinancierosSimulados
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
        //fuerza https en render
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
