<?php

namespace App\Providers;

use App\Services\Finanzas\DatosFinancierosSimulados;
use App\Services\Finanzas\FuenteDatosFinancieros;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // DATOS SIMULADOS: sustituir por la implementación que consulte los modelos de
        // ingresos, gastos y categorías cuando esos módulos estén disponibles.
        $this->app->bind(FuenteDatosFinancieros::class, DatosFinancierosSimulados::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
