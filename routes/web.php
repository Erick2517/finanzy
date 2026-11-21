<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ReporteController;
use App\Http\Requests\StoreDemoRequest;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/demo');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:Administrador'])->group(function () {
    Route::resource('usuarios', UserController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [UserController::class, 'toggleStatus'])->name('usuarios.toggle-status');

    Route::get('/bitacora', [ActivityLogController::class, 'index'])->name('bitacora.index');
});

Route::redirect('/home', '/dashboard')->name('home');

Route::get('/demo', function () {
    return view('demo');
})->name('demo');

Route::post('/demo', function (StoreDemoRequest $request) {
    return redirect('/demo')->with('success', 'Registro de prueba validado correctamente (no se guardó en base de datos).');
})->name('demo.store');

// Módulos financieros: cada usuario accede exclusivamente a sus datos.
Route::middleware('auth')->group(function () {
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::post('/reportes/movimientos', [ReporteController::class, 'store'])->name('reportes.movimientos.store');
    Route::get('/reportes/pdf', [ReporteController::class, 'pdf'])->name('reportes.pdf');
    Route::get('/reportes/csv', [ReporteController::class, 'csv'])->name('reportes.csv');
});
