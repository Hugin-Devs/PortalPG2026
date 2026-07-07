<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\Public\ContactController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/noticias', [NewsController::class, 'index'])->name('news.index');
Route::get('/noticias/{slug}', [NewsController::class, 'show'])->name('news.show');
Route::get('/categoria/{slug}', [NewsController::class, 'category'])->name('news.category');
Route::get('/servicios', [ServiceController::class, 'index'])->name('services.index');
Route::get('/servicios/{slug}', [ServiceController::class, 'show'])->name('services.show');
Route::get('/contacto', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contacto', [ContactController::class, 'store'])->name('contact.store');
Route::get('/pagina/{slug}', [PageController::class, 'show'])->name('page.show');

// Rutas Admin
Route::prefix('admin')->group(function () {
    Route::get('/login', [App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('login'); // Nombre 'login' para que el middleware lo encuentre
    Route::post('/login', [App\Http\Controllers\Admin\AuthController::class, 'login']);
    
    Route::middleware('auth')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index']);
        Route::post('/logout', [App\Http\Controllers\Admin\AuthController::class, 'logout']);
        Route::get('/noticias', [App\Http\Controllers\Admin\NewsController::class, 'index']);
        Route::get('/noticias/crear', [App\Http\Controllers\Admin\NewsController::class, 'create']);
        Route::post('/noticias', [App\Http\Controllers\Admin\NewsController::class, 'store']);
    });
});

