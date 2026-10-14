<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\OrgMemberController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RrssController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\EnteController;
use App\Http\Controllers\Public\GacetaController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsController as PublicNewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/noticias', [PublicNewsController::class, 'index'])->name('news.index');
Route::get('/noticias/{slug}', [PublicNewsController::class, 'show'])->name('news.show');
Route::get('/categoria/{slug}', [PublicNewsController::class, 'category'])->name('news.category');
Route::get('/entes', [EnteController::class, 'index'])->name('entes.index');
Route::get('/entes/{slug}', [EnteController::class, 'show'])->name('entes.show');
Route::redirect('/servicios', '/entes');
Route::get('/sobre-nosotros', [AboutController::class, 'index'])->name('about.index');
Route::get('/contacto', [ContactController::class, 'index'])->name('contact.index');
Route::post('/contacto', [ContactController::class, 'store'])->name('contact.store')->middleware('throttle:contact');
Route::get('/gacetas', [GacetaController::class, 'index'])->name('gacetas.index');
Route::get('/buscar', [SearchController::class, 'index'])->name('search')->middleware('throttle:search');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/pagina/{slug}', [PageController::class, 'show'])->name('page.show');

Route::prefix('admin')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth', 'password.changed'])->group(function () {
        Route::get('/cambiar-contrasena', [AuthController::class, 'showChangePassword'])->name('password.change');
        Route::post('/cambiar-contrasena', [AuthController::class, 'changePassword'])->name('password.change.update');
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

        // Admin - Noticias
        Route::get('/noticias', [NewsController::class, 'index'])->name('admin.news.index')->middleware('permission:news.view');
        Route::get('/noticias/crear', [NewsController::class, 'create'])->name('admin.news.create')->middleware('permission:news.create');
        Route::post('/noticias', [NewsController::class, 'store'])->name('admin.news.store')->middleware('permission:news.create');
        Route::post('/noticias/publicar', [NewsController::class, 'bulkPublish'])->name('admin.news.bulkPublish')->middleware('permission:news.approve');
        Route::post('/noticias/{post}/aprobar', [NewsController::class, 'approve'])->name('admin.news.approve')->middleware('permission:news.approve');
        Route::get('/noticias/{post}/editar', [NewsController::class, 'edit'])->name('admin.news.edit')->middleware('permission:news.edit');
        Route::put('/noticias/{post}', [NewsController::class, 'update'])->name('admin.news.update')->middleware('permission:news.edit');
        // PUT también: los botones ↑/↓ usan formaction+formmethod sobre el form de
        // edición (que lleva _method=PUT, spoofing a PUT en el navegador).
        Route::match(['post', 'put'], '/noticias/{post}/media/{media}/move', [NewsController::class, 'move'])->name('admin.news.media.move')->middleware('permission:news.edit');
        Route::delete('/noticias/{post}', [NewsController::class, 'destroy'])->name('admin.news.destroy')->middleware('permission:news.delete');

        // Admin - Categorías
        Route::get('/categorias', [CategoryController::class, 'index'])->name('admin.categories.index')->middleware('permission:categories.view');
        Route::get('/categorias/crear', [CategoryController::class, 'create'])->name('admin.categories.create')->middleware('permission:categories.create');
        Route::post('/categorias', [CategoryController::class, 'store'])->name('admin.categories.store')->middleware('permission:categories.create');
        Route::get('/categorias/{category}/editar', [CategoryController::class, 'edit'])->name('admin.categories.edit')->middleware('permission:categories.edit');
        Route::put('/categorias/{category}', [CategoryController::class, 'update'])->name('admin.categories.update')->middleware('permission:categories.edit');
        Route::delete('/categorias/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy')->middleware('permission:categories.delete');

        // Admin - Entes Afiliados
        Route::get('/entes', [PartnerController::class, 'index'])->name('admin.partners.index')->middleware('permission:partners.view');
        Route::get('/entes/crear', [PartnerController::class, 'create'])->name('admin.partners.create')->middleware('permission:partners.create');
        Route::post('/entes', [PartnerController::class, 'store'])->name('admin.partners.store')->middleware('permission:partners.create');
        Route::get('/entes/{partner}/editar', [PartnerController::class, 'edit'])->name('admin.partners.edit')->middleware('permission:partners.edit');
        Route::put('/entes/{partner}', [PartnerController::class, 'update'])->name('admin.partners.update')->middleware('permission:partners.edit');
        Route::delete('/entes/{partner}', [PartnerController::class, 'destroy'])->name('admin.partners.destroy')->middleware('permission:partners.delete');

        // Admin - Usuarios
        Route::get('/usuarios', [UserController::class, 'index'])->name('admin.users.index')->middleware('permission:users.view');
        Route::get('/usuarios/crear', [UserController::class, 'create'])->name('admin.users.create')->middleware('permission:users.create');
        Route::post('/usuarios', [UserController::class, 'store'])->name('admin.users.store')->middleware('permission:users.create');
        Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])->name('admin.users.edit')->middleware('permission:users.edit');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('admin.users.update')->middleware('permission:users.edit');
        Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy')->middleware('permission:users.delete');

        // Admin - Medios
        Route::get('/medios', [MediaController::class, 'index'])->name('admin.media.index')->middleware('permission:media.view');
        Route::post('/medios', [MediaController::class, 'store'])->name('admin.media.store')->middleware('permission:media.upload');
        Route::delete('/medios/{media}', [MediaController::class, 'destroy'])->name('admin.media.destroy')->middleware('permission:media.delete');

        // Admin - Videos
        Route::get('/videos', [VideoController::class, 'index'])->name('admin.videos.index')->middleware('permission:videos.view');
        Route::post('/videos', [VideoController::class, 'store'])->name('admin.videos.store')->middleware('permission:videos.upload');
        Route::post('/videos/destacar', [VideoController::class, 'destacar'])->name('admin.videos.destacar')->middleware('permission:videos.manage');
        Route::delete('/videos/{video}', [VideoController::class, 'destroy'])->name('admin.videos.destroy')->middleware('permission:videos.delete');

        // Admin - Gacetas
        Route::get('/gacetas', [App\Http\Controllers\Admin\GacetaController::class, 'index'])->name('admin.gacetas.index')->middleware('permission:gacetas.view');
        Route::post('/gacetas', [App\Http\Controllers\Admin\GacetaController::class, 'store'])->name('admin.gacetas.store')->middleware('permission:gacetas.create');
        Route::get('/gacetas/{gaceta}/editar', [App\Http\Controllers\Admin\GacetaController::class, 'edit'])->name('admin.gacetas.edit')->middleware('permission:gacetas.edit');
        Route::put('/gacetas/{gaceta}', [App\Http\Controllers\Admin\GacetaController::class, 'update'])->name('admin.gacetas.update')->middleware('permission:gacetas.edit');
        Route::delete('/gacetas/{gaceta}', [App\Http\Controllers\Admin\GacetaController::class, 'destroy'])->name('admin.gacetas.destroy')->middleware('permission:gacetas.delete');

        // Admin - Sobre Nosotros
        Route::get('/nosotros', [App\Http\Controllers\Admin\AboutController::class, 'index'])->name('admin.about.index')->middleware('permission:about.edit');
        Route::put('/nosotros', [App\Http\Controllers\Admin\AboutController::class, 'update'])->name('admin.about.update')->middleware('permission:about.edit');

        // Admin - Contacto
        Route::get('/contacto', [App\Http\Controllers\Admin\ContactController::class, 'editInfo'])->name('admin.contact.edit')->middleware('permission:contact.edit');
        Route::put('/contacto', [App\Http\Controllers\Admin\ContactController::class, 'updateInfo'])->name('admin.contact.update')->middleware('permission:contact.edit');
        Route::get('/contacto/mensajes', [App\Http\Controllers\Admin\ContactController::class, 'messages'])->name('admin.contact.messages')->middleware('permission:contact.view');
        Route::get('/contacto/mensajes/{contact}', [App\Http\Controllers\Admin\ContactController::class, 'showMessage'])->name('admin.contact.message.show')->middleware('permission:contact.view');
        Route::delete('/contacto/mensajes/{contact}', [App\Http\Controllers\Admin\ContactController::class, 'destroyMessage'])->name('admin.contact.message.destroy')->middleware('permission:contact.delete');

        // Admin - Estructura Organizativa
        Route::get('/estructura', [OrgMemberController::class, 'index'])->name('admin.org-members.index')->middleware('permission:org.view');
        Route::get('/estructura/crear', [OrgMemberController::class, 'create'])->name('admin.org-members.create')->middleware('permission:org.create');
        Route::post('/estructura', [OrgMemberController::class, 'store'])->name('admin.org-members.store')->middleware('permission:org.create');
        Route::get('/estructura/{orgMember}/editar', [OrgMemberController::class, 'edit'])->name('admin.org-members.edit')->middleware('permission:org.edit');
        Route::put('/estructura/{orgMember}', [OrgMemberController::class, 'update'])->name('admin.org-members.update')->middleware('permission:org.edit');
        Route::delete('/estructura/{orgMember}', [OrgMemberController::class, 'destroy'])->name('admin.org-members.destroy')->middleware('permission:org.delete');

        // Admin - Roles y Permisos
        Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index')->middleware('permission:roles.manage');
        Route::post('/roles', [RoleController::class, 'update'])->name('admin.roles.update')->middleware('permission:roles.manage');

        // Admin - Redes Sociales
        Route::get('/rrss', [RrssController::class, 'index'])->name('admin.rrss.index')->middleware('permission:rrss.manage');
        Route::put('/rrss', [RrssController::class, 'update'])->name('admin.rrss.update')->middleware('permission:rrss.manage');

        // Admin - Configuración General (solo superadmin)
        Route::get('/configuracion', [SettingsController::class, 'index'])->name('admin.settings.index')->middleware('permission:settings.manage');
        Route::put('/configuracion', [SettingsController::class, 'update'])->name('admin.settings.update')->middleware('permission:settings.manage');

        // Admin - Auditoría (G6)
        Route::get('/auditoria', [AuditController::class, 'index'])->name('admin.audit.index')->middleware('permission:audit.view');
    });
});
