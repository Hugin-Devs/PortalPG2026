<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsController as PublicNewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\Public\ContactController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/noticias', [PublicNewsController::class, 'index'])->name('news.index');
Route::get('/noticias/{slug}', [PublicNewsController::class, 'show'])->name('news.show');
Route::get('/categoria/{slug}', [PublicNewsController::class, 'category'])->name('news.category');
Route::get('/servicios', [ServiceController::class, 'index'])->name('services.index');
Route::get('/servicios/{slug}', [ServiceController::class, 'show'])->name('services.show');
Route::get('/sobre-nosotros', [App\Http\Controllers\Public\AboutController::class, 'index'])->name('about.index');
Route::get('/contacto', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contacto', [ContactController::class, 'store'])->name('contact.store');
Route::get('/buscar', [App\Http\Controllers\Public\SearchController::class, 'index'])->name('search');
Route::get('/pagina/{slug}', [PageController::class, 'show'])->name('page.show');

Route::prefix('admin')->group(function () {
    Route::get('/login', [App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [App\Http\Controllers\Admin\AuthController::class, 'login']);

    Route::middleware('auth')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('admin.logout');

        // Admin - Noticias
        Route::get('/noticias', [App\Http\Controllers\Admin\NewsController::class, 'index'])->name('admin.news.index')->middleware('permission:news.view');
        Route::get('/noticias/crear', [App\Http\Controllers\Admin\NewsController::class, 'create'])->name('admin.news.create')->middleware('permission:news.create');
        Route::post('/noticias', [App\Http\Controllers\Admin\NewsController::class, 'store'])->name('admin.news.store')->middleware('permission:news.create');
        Route::get('/noticias/{post}/editar', [App\Http\Controllers\Admin\NewsController::class, 'edit'])->name('admin.news.edit')->middleware('permission:news.edit');
        Route::put('/noticias/{post}', [App\Http\Controllers\Admin\NewsController::class, 'update'])->name('admin.news.update')->middleware('permission:news.edit');
        Route::delete('/noticias/{post}', [App\Http\Controllers\Admin\NewsController::class, 'destroy'])->name('admin.news.destroy')->middleware('permission:news.delete');

        // Admin - Categorías
        Route::get('/categorias', [App\Http\Controllers\Admin\CategoryController::class, 'index'])->name('admin.categories.index')->middleware('permission:categories.view');
        Route::get('/categorias/crear', [App\Http\Controllers\Admin\CategoryController::class, 'create'])->name('admin.categories.create')->middleware('permission:categories.create');
        Route::post('/categorias', [App\Http\Controllers\Admin\CategoryController::class, 'store'])->name('admin.categories.store')->middleware('permission:categories.create');
        Route::get('/categorias/{category}/editar', [App\Http\Controllers\Admin\CategoryController::class, 'edit'])->name('admin.categories.edit')->middleware('permission:categories.edit');
        Route::put('/categorias/{category}', [App\Http\Controllers\Admin\CategoryController::class, 'update'])->name('admin.categories.update')->middleware('permission:categories.edit');
        Route::delete('/categorias/{category}', [App\Http\Controllers\Admin\CategoryController::class, 'destroy'])->name('admin.categories.destroy')->middleware('permission:categories.delete');

        // Admin - Servicios (comentado)
        // Route::get('/servicios', [App\Http\Controllers\Admin\ServiceController::class, 'index'])->name('admin.services.index');
        // Route::get('/servicios/crear', [App\Http\Controllers\Admin\ServiceController::class, 'create'])->name('admin.services.create');
        // Route::post('/servicios', [App\Http\Controllers\Admin\ServiceController::class, 'store'])->name('admin.services.store');
        // Route::get('/servicios/{service}/editar', [App\Http\Controllers\Admin\ServiceController::class, 'edit'])->name('admin.services.edit');
        // Route::put('/servicios/{service}', [App\Http\Controllers\Admin\ServiceController::class, 'update'])->name('admin.services.update');
        // Route::delete('/servicios/{service}', [App\Http\Controllers\Admin\ServiceController::class, 'destroy'])->name('admin.services.destroy');

        // Admin - Entes Afiliados
        Route::get('/entes', [App\Http\Controllers\Admin\PartnerController::class, 'index'])->name('admin.partners.index')->middleware('permission:partners.view');
        Route::get('/entes/crear', [App\Http\Controllers\Admin\PartnerController::class, 'create'])->name('admin.partners.create')->middleware('permission:partners.create');
        Route::post('/entes', [App\Http\Controllers\Admin\PartnerController::class, 'store'])->name('admin.partners.store')->middleware('permission:partners.create');
        Route::get('/entes/{partner}/editar', [App\Http\Controllers\Admin\PartnerController::class, 'edit'])->name('admin.partners.edit')->middleware('permission:partners.edit');
        Route::put('/entes/{partner}', [App\Http\Controllers\Admin\PartnerController::class, 'update'])->name('admin.partners.update')->middleware('permission:partners.edit');
        Route::delete('/entes/{partner}', [App\Http\Controllers\Admin\PartnerController::class, 'destroy'])->name('admin.partners.destroy')->middleware('permission:partners.delete');

        // Admin - Usuarios
        Route::get('/usuarios', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index')->middleware('permission:users.view');
        Route::get('/usuarios/crear', [App\Http\Controllers\Admin\UserController::class, 'create'])->name('admin.users.create')->middleware('permission:users.create');
        Route::post('/usuarios', [App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store')->middleware('permission:users.create');
        Route::get('/usuarios/{user}/editar', [App\Http\Controllers\Admin\UserController::class, 'edit'])->name('admin.users.edit')->middleware('permission:users.edit');
        Route::put('/usuarios/{user}', [App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update')->middleware('permission:users.edit');
        Route::delete('/usuarios/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy')->middleware('permission:users.delete');

        // Admin - Medios
        Route::get('/medios', [App\Http\Controllers\Admin\MediaController::class, 'index'])->name('admin.media.index')->middleware('permission:media.view');
        Route::post('/medios', [App\Http\Controllers\Admin\MediaController::class, 'store'])->name('admin.media.store')->middleware('permission:media.upload');
        Route::delete('/medios/{media}', [App\Http\Controllers\Admin\MediaController::class, 'destroy'])->name('admin.media.destroy')->middleware('permission:media.delete');

        // Admin - Sobre Nosotros
        Route::get('/nosotros', [App\Http\Controllers\Admin\AboutController::class, 'index'])->name('admin.about.index')->middleware('permission:about.edit');
        Route::put('/nosotros', [App\Http\Controllers\Admin\AboutController::class, 'update'])->name('admin.about.update')->middleware('permission:about.edit');

        // Admin - Estructura Organizativa
        Route::get('/estructura', [App\Http\Controllers\Admin\OrgMemberController::class, 'index'])->name('admin.org-members.index')->middleware('permission:org.view');
        Route::get('/estructura/crear', [App\Http\Controllers\Admin\OrgMemberController::class, 'create'])->name('admin.org-members.create')->middleware('permission:org.create');
        Route::post('/estructura', [App\Http\Controllers\Admin\OrgMemberController::class, 'store'])->name('admin.org-members.store')->middleware('permission:org.create');
        Route::get('/estructura/{orgMember}/editar', [App\Http\Controllers\Admin\OrgMemberController::class, 'edit'])->name('admin.org-members.edit')->middleware('permission:org.edit');
        Route::put('/estructura/{orgMember}', [App\Http\Controllers\Admin\OrgMemberController::class, 'update'])->name('admin.org-members.update')->middleware('permission:org.edit');
        Route::delete('/estructura/{orgMember}', [App\Http\Controllers\Admin\OrgMemberController::class, 'destroy'])->name('admin.org-members.destroy')->middleware('permission:org.delete');

        // Admin - Roles y Permisos
        Route::get('/roles', [App\Http\Controllers\Admin\RoleController::class, 'index'])->name('admin.roles.index')->middleware('permission:roles.manage');
        Route::post('/roles', [App\Http\Controllers\Admin\RoleController::class, 'update'])->name('admin.roles.update')->middleware('permission:roles.manage');
    });
});
