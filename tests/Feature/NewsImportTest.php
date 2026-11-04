<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsMediaFixtures;
use Tests\TestCase;

class NewsImportTest extends TestCase
{
    use BuildsMediaFixtures, RefreshDatabase;

    private Category $category;

    private User $superadmin;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(PermissionSeeder::class);
        $this->category = Category::factory()->create();
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->editor = User::factory()->create(['role' => 'editor']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'source_url' => 'https://www.instagram.com/p/AbC123xYz/',
            'title' => 'Noticia importada desde IG',
            'category_id' => $this->category->id,
            'excerpt' => 'Extracto de prueba',
            'content' => '<p>Contenido importado</p>',
        ], $overrides);
    }

    private function assertRedirectedToEdit(TestResponse $response): void
    {
        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/editar', $location);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/noticias/importar')->assertRedirect('/admin/login');
        $this->post('/admin/noticias/importar', $this->validPayload())->assertRedirect('/admin/login');
    }

    public function test_editor_is_forbidden_from_importing(): void
    {
        $this->actingAs($this->editor)->get('/admin/noticias/importar')->assertForbidden();
        $this->actingAs($this->editor)
            ->post('/admin/noticias/importar', $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, Post::count());
    }

    public function test_superadmin_can_open_import_form_with_bookmarklet(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/admin/noticias/importar')
            ->assertOk()
            ->assertSee('Importar desde Instagram')
            ->assertSee('bookmarklet', false)
            ->assertSee('Portal IG');
    }

    public function test_admin_role_can_import(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->post('/admin/noticias/importar', $this->validPayload());
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame('instagram', $post->source);
        $this->assertSame('https://www.instagram.com/p/AbC123xYz/', $post->source_url);
    }

    public function test_import_always_creates_draft_even_if_status_tampered(): void
    {
        $when = now()->subDays(2)->startOfMinute();

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'status' => 'published',
                'published_at' => $when->format('Y-m-d\TH:i'),
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        // status forzado a draft; published_at SÍ se guarda (fecha de IG).
        $this->assertSame('draft', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertEquals($when->toDateTimeString(), $post->published_at->toDateTimeString());
    }

    public function test_import_without_published_at_keeps_null(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload());
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);
    }

    public function test_approve_preserves_imported_published_at(): void
    {
        $when = now()->subDays(3)->startOfMinute();

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'published_at' => $when->format('Y-m-d\TH:i'),
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertSame('draft', $post->status);

        $this->actingAs($this->superadmin)
            ->post("/admin/noticias/{$post->id}/aprobar")
            ->assertRedirect('/admin/noticias')
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertEquals($when->toDateTimeString(), $post->published_at->toDateTimeString());
    }

    public function test_bulk_publish_preserves_existing_published_at(): void
    {
        $when = now()->subDays(2)->startOfMinute();

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'source_url' => 'https://www.instagram.com/p/BulkDate1/',
                'published_at' => $when->format('Y-m-d\TH:i'),
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();

        $this->actingAs($this->superadmin)
            ->post('/admin/noticias/publicar', ['posts' => [$post->id]])
            ->assertRedirect('/admin/noticias')
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertEquals($when->toDateTimeString(), $post->published_at->toDateTimeString());
    }

    public function test_invalid_instagram_url_is_rejected(): void
    {
        $this->actingAs($this->superadmin)
            ->from('/admin/noticias/importar')
            ->post('/admin/noticias/importar', $this->validPayload([
                'source_url' => 'https://evil.com/p/AbC123',
            ]))
            ->assertRedirect('/admin/noticias/importar')
            ->assertSessionHasErrors('source_url');

        $this->assertSame(0, Post::count());
    }

    public function test_duplicate_source_url_is_rejected_with_warning(): void
    {
        $canonical = 'https://www.instagram.com/p/AbC123xYz/';

        Post::create([
            'title' => 'Ya importada',
            'slug' => 'ya-importada-'.uniqid(),
            'excerpt' => 'x',
            'content' => '<p>x</p>',
            'category_id' => $this->category->id,
            'user_id' => $this->superadmin->id,
            'status' => 'draft',
            'source' => 'instagram',
            'source_url' => $canonical,
        ]);

        $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload())
            ->assertRedirect('/admin/noticias')
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'ya fue importada'));

        $this->assertSame(1, Post::count());
    }

    public function test_missing_category_is_rejected(): void
    {
        $this->actingAs($this->superadmin)
            ->from('/admin/noticias/importar')
            ->post('/admin/noticias/importar', $this->validPayload(['category_id' => 999999]))
            ->assertRedirect('/admin/noticias/importar')
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Post::count());
    }

    public function test_empty_excerpt_is_derived_from_content(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'excerpt' => '',
                'content' => '<p>Primera línea del cuerpo de la noticia que viene de Instagram</p>',
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNotSame('', $post->excerpt);
        $this->assertLessThanOrEqual(1000, strlen($post->excerpt));
        $this->assertStringContainsString('Primera línea', $post->excerpt);
    }

    public function test_content_is_sanitized_and_import_is_audited(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'content' => '<p onclick="evil()">Hola 😊</p><script>alert(1)</script>',
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertStringNotContainsString('<script>', $post->content);
        $this->assertStringNotContainsString('onclick', $post->content);
        // LATIN9: emoji eliminado por StoresLatin9.
        $this->assertStringNotContainsString('😊', $post->content);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'news.imported',
            'entity_type' => Post::class,
            'entity_id' => $post->id,
        ]);
    }

    public function test_featured_url_outside_allowlist_is_rejected(): void
    {
        $this->actingAs($this->superadmin)
            ->from('/admin/noticias/importar')
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode(['https://scontent.cdninstagram.com/v/t51/ok.jpg']),
                'featured_url' => 'https://evil.com/steal.jpg',
            ]))
            ->assertRedirect('/admin/noticias/importar')
            ->assertSessionHasErrors('featured_url');

        $this->assertSame(0, Post::count());
    }

    public function test_featured_url_not_in_image_urls_is_rejected(): void
    {
        $this->actingAs($this->superadmin)
            ->from('/admin/noticias/importar')
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode(['https://scontent.cdninstagram.com/v/t51/ok.jpg']),
                'featured_url' => 'https://scontent.cdninstagram.com/v/t51/other.jpg',
            ]))
            ->assertRedirect('/admin/noticias/importar')
            ->assertSessionHasErrors('featured_url');

        $this->assertSame(0, Post::count());
    }

    public function test_selected_featured_url_is_downloaded_and_removed_from_gallery(): void
    {
        $png = $this->png(4, 4);
        $urls = [
            'https://scontent.cdninstagram.com/v/t51/portada.jpg',
            'https://scontent.cdninstagram.com/v/t51/gal1.jpg',
        ];

        Http::fake([
            'scontent.cdninstagram.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode($urls),
                'featured_url' => $urls[0],
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNotNull($post->featured_image);
        Storage::disk('public')->assertExists($post->featured_image);

        $gallery = PostMedia::where('post_id', $post->id)->where('type', 'image')->get();
        $this->assertCount(1, $gallery);

        Http::assertSent(fn (Request $request) => $request->url() === $urls[0]);
        Http::assertSent(fn (Request $request) => $request->url() === $urls[1]);
    }

    public function test_no_featured_selection_puts_all_images_in_gallery(): void
    {
        $png = $this->png(4, 4);
        $urls = [
            'https://scontent.cdninstagram.com/v/t51/c1.jpg',
            'https://scontent.cdninstagram.com/v/t51/c2.jpg',
        ];

        Http::fake([
            'scontent.cdninstagram.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode($urls),
                'featured_url' => '',
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNull($post->featured_image);

        $gallery = PostMedia::where('post_id', $post->id)->where('type', 'image')->get();
        $this->assertCount(2, $gallery);
        Http::assertSentCount(2);
    }

    public function test_failed_featured_url_download_keeps_image_in_gallery(): void
    {
        Http::fake([
            'scontent.cdninstagram.com/*' => Http::response('nope', 500),
        ]);

        $urls = ['https://scontent.cdninstagram.com/v/t51/portada.jpg'];

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode($urls),
                'featured_url' => $urls[0],
            ]));
        $response->assertSessionHas('warning', fn ($m) => str_contains($m, 'No se pudo descargar la imagen elegida como portada'));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNull($post->featured_image);
        // La URL fallida vuelve a galería y también falla allí → 0 imágenes.
        $this->assertSame(0, PostMedia::where('post_id', $post->id)->where('type', 'image')->count());
    }

    public function test_server_side_extract_failure_shows_warning_and_requires_manual_fields(): void
    {
        Http::fake([
            'www.instagram.com/*' => Http::response('<html><body>Login</body></html>', 200),
        ]);

        // Sin título ni contenido: fetchMeta no extrae nada → validación falla.
        $this->actingAs($this->superadmin)
            ->from('/admin/noticias/importar')
            ->post('/admin/noticias/importar', [
                'source_url' => 'https://www.instagram.com/p/AbC123xYz/',
                'category_id' => $this->category->id,
            ])
            ->assertRedirect('/admin/noticias/importar')
            ->assertSessionHasErrors(['title', 'content']);

        $this->assertSame(0, Post::count());
    }

    public function test_server_side_extract_fills_fields_from_og_tags(): void
    {
        $html = <<<'HTML'
<html><head>
<meta property="og:title" content="Gobernación inaugura obra" />
<meta property="og:description" content="Texto del caption de Instagram" />
<meta property="og:image" content="https://scontent.cdninstagram.com/v/t51/og.jpg" />
<meta property="article:published_time" content="2026-10-20T15:30:00+00:00" />
</head><body>
<time datetime="2026-10-20T15:30:00.000Z">Oct 20, 2026</time>
</body></html>
HTML;

        Http::fake([
            'www.instagram.com/*' => Http::response($html, 200),
            'scontent.cdninstagram.com/*' => Http::response($this->png(4, 4), 200, ['Content-Type' => 'image/png']),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', [
                'source_url' => 'https://www.instagram.com/p/AbC123xYz/',
                'category_id' => $this->category->id,
            ]);
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNotNull($post);
        $this->assertSame('Gobernación inaugura obra', $post->title);
        $this->assertStringContainsString('Texto del caption', $post->content);
        // og:image → image_urls/galería; NO hero automático.
        $this->assertNull($post->featured_image);
        $this->assertSame(1, PostMedia::where('post_id', $post->id)->where('type', 'image')->count());
        // Fecha de IG extraída del <time datetime> (UTC → Caracas al guardar).
        $this->assertNotNull($post->published_at);
        $this->assertSame('2026-10-20', $post->published_at->format('Y-m-d'));
    }

    public function test_import_endpoint_is_throttled(): void
    {
        config(['security.rate_limits.import' => 2]);

        $this->actingAs($this->superadmin);

        $r1 = $this->post('/admin/noticias/importar', $this->validPayload([
            'source_url' => 'https://www.instagram.com/p/Throttle1/',
        ]));
        $this->assertRedirectedToEdit($r1);

        $r2 = $this->post('/admin/noticias/importar', $this->validPayload([
            'source_url' => 'https://www.instagram.com/p/Throttle2/',
        ]));
        $this->assertRedirectedToEdit($r2);

        $this->post('/admin/noticias/importar', $this->validPayload([
            'source_url' => 'https://www.instagram.com/p/Throttle3/',
        ]))->assertStatus(429);
    }

    public function test_index_shows_import_link_for_admins_only(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/admin/noticias')
            ->assertOk()
            ->assertSee('Importar IG');

        $this->actingAs($this->editor)
            ->get('/admin/noticias')
            ->assertOk()
            ->assertDontSee('Importar IG');
    }

    public function test_bookmarklet_page_includes_hash_prefill_script(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/admin/noticias/importar');

        $response->assertOk();
        $response->assertSee('#ig=', false);
        $response->assertSee('fillFromHash', false);
        $response->assertSee('image_urls', false);
        $response->assertSee('featuredPick', false);
        $response->assertSee('featured_url', false);
        $response->assertSee('carouselCount', false);
        $response->assertSee('published_at', false);
        // Híbrido: about:blank en el gesto del clic + overlay de estado.
        $response->assertSee('about:blank', false);
        $response->assertSee('Portal IG: recopilando', false);
        // Sin hero automático en el form.
        $response->assertDontSee('name="image_url"', false);
        // Regresión: comentarios // dentro del heredoc JS rompen el bookmarklet
        // al minificar (une todo en una línea → // comenta el resto → no pasa nada).
        $response->assertDontSee('// P1:', false);
        $response->assertDontSee('// P2:', false);
    }

    public function test_carousel_without_selection_puts_all_in_gallery(): void
    {
        $png = $this->png(4, 4);
        $urls = [
            'https://scontent.cdninstagram.com/v/t51/c1.jpg',
            'https://scontent.cdninstagram.com/v/t51/c2.jpg',
            'https://scontent.cdninstagram.com/v/t51/c3.jpg',
        ];

        Http::fake([
            'scontent.cdninstagram.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode($urls),
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNull($post->featured_image);

        $gallery = PostMedia::where('post_id', $post->id)->where('type', 'image')->orderBy('position')->get();
        $this->assertCount(3, $gallery);
        $this->assertSame([0, 1, 2], $gallery->pluck('position')->all());
        foreach ($gallery as $item) {
            Storage::disk('public')->assertExists($item->path);
        }

        Http::assertSentCount(3);
    }

    public function test_carousel_image_url_outside_allowlist_is_rejected(): void
    {
        $this->actingAs($this->superadmin)
            ->from('/admin/noticias/importar')
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode([
                    'https://scontent.cdninstagram.com/v/t51/ok.jpg',
                    'https://evil.com/steal.jpg',
                ]),
            ]))
            ->assertRedirect('/admin/noticias/importar')
            ->assertSessionHasErrors('image_urls.1');

        $this->assertSame(0, Post::count());
    }

    public function test_carousel_more_than_max_urls_is_rejected(): void
    {
        $urls = [];
        for ($i = 0; $i < 12; $i++) {
            $urls[] = "https://scontent.cdninstagram.com/v/t51/c{$i}.jpg";
        }

        $this->actingAs($this->superadmin)
            ->from('/admin/noticias/importar')
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode($urls),
            ]))
            ->assertRedirect('/admin/noticias/importar')
            ->assertSessionHasErrors('image_urls');

        $this->assertSame(0, Post::count());
    }

    public function test_partial_carousel_download_failure_keeps_rest_with_warning(): void
    {
        $png = $this->png(4, 4);
        $urls = [
            'https://scontent.cdninstagram.com/v/t51/ok1.jpg',
            'https://scontent.cdninstagram.com/v/t51/fail.jpg',
            'https://scontent.cdninstagram.com/v/t51/ok2.jpg',
            'https://scontent.cdninstagram.com/v/t51/ok3.jpg',
        ];

        Http::fake([
            'scontent.cdninstagram.com/v/t51/ok*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            'scontent.cdninstagram.com/v/t51/fail*' => Http::response('nope', 500),
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode($urls),
                'featured_url' => $urls[0],
            ]));
        $response->assertSessionHas('warning', fn ($m) => str_contains($m, 'No se pudo descargar una imagen del carrusel'));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        // Portada = ok1 seleccionada; galería intenta fail+ok2+ok3 → 2 ok, 1 warning.
        $this->assertNotNull($post->featured_image);
        $this->assertSame(2, PostMedia::where('post_id', $post->id)->where('type', 'image')->count());
    }

    public function test_manual_upload_as_featured_puts_all_detected_images_in_gallery(): void
    {
        $png = $this->png(4, 4);
        $urls = [
            'https://scontent.cdninstagram.com/v/t51/c1.jpg',
            'https://scontent.cdninstagram.com/v/t51/c2.jpg',
        ];

        Http::fake([
            'scontent.cdninstagram.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $upload = UploadedFile::fake()->image('custom.jpg', 10, 10);

        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'image_urls' => json_encode($urls),
                'featured_image_file' => $upload,
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertNotNull($post->featured_image);
        $this->assertStringContainsString('uploads/', $post->featured_image);

        // Upload manda como portada; TODAS las detectadas van a la galería.
        $gallery = PostMedia::where('post_id', $post->id)->where('type', 'image')->get();
        $this->assertCount(2, $gallery);

        Http::assertSentCount(2);
    }

    public function test_imported_post_shows_source_link_on_public_detail(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload());
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $post->update([
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $html = $this->get('/noticias/'.$post->slug)->assertOk()->getContent();
        $this->assertStringContainsString('Ver publicación original en Instagram', $html);
        $this->assertStringContainsString('https://www.instagram.com/p/AbC123xYz/', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        // Link también en el bloque de medios (C1 extra en reels/videos).
        $this->assertStringContainsString('ig-source-link', $html);
    }

    public function test_reel_import_shows_source_link_on_public_detail(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post('/admin/noticias/importar', $this->validPayload([
                'source_url' => 'https://www.instagram.com/reel/ReelAbC123/',
                'title' => 'Reel de prueba',
                'content' => '<p>Descripción del reel</p>',
            ]));
        $this->assertRedirectedToEdit($response);

        $post = Post::first();
        $this->assertSame('https://www.instagram.com/reel/ReelAbC123/', $post->source_url);
        $post->update([
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $html = $this->get('/noticias/'.$post->slug)->assertOk()->getContent();
        $this->assertStringContainsString('Ver publicación original en Instagram', $html);
        $this->assertStringContainsString('https://www.instagram.com/reel/ReelAbC123/', $html);
        $this->assertStringContainsString('ig-source-link', $html);
    }

    public function test_post_without_source_url_hides_source_link(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        $category = Category::factory()->create();

        $post = Post::create([
            'title' => 'Noticia sin fuente',
            'slug' => 'noticia-sin-fuente-'.uniqid(),
            'excerpt' => 'x',
            'content' => '<p>x</p>',
            'category_id' => $category->id,
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $html = $this->get('/noticias/'.$post->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('Ver publicación original en Instagram', $html);
        $this->assertStringNotContainsString('ig-source-link', $html);
    }

    public function test_bookmarklet_collects_carousel_image_urls(): void
    {
        $response = $this->actingAs($this->superadmin)->get('/admin/noticias/importar');

        $response->assertOk();
        // Scoping al post actual (shortcode + root article).
        $response->assertSee('shortcode', false);
        $response->assertSee('rootArticle', false);
        $response->assertSee('image_urls', false);
        $response->assertSee('slice(0,11)', false);
        // JSON: media keys primero (display_url/thumbnail_src), no profile_pic.
        $response->assertSee('indexOf(shortcode)', false);
        $response->assertSee('display_url', false);
        $response->assertSee('thumbnail_src', false);
        // Caption: h1 primero + filtro de stats (posts/seguidores/…).
        $response->assertSee('captionText', false);
        $response->assertSee('isJunkText', false);
        $response->assertSee('root.querySelector', false);
        $response->assertSee('firstLine', false);
        // Anti-avatar reforzado: -19, s640, profile_pic.
        $response->assertSee('coverImage', false);
        $response->assertSee('isNotAvatar', false);
        $response->assertSee('isLikelyAvatarUrl', false);
        $response->assertSee('t51.2885-19', false);
        $response->assertSee('640', false);
        $response->assertSee('profile_pic', false);
        $response->assertSee('poster', false);
        // Video/reel: isVideoPost + cover prioritario.
        $response->assertSee('isVideoPost', false);
        $response->assertSee('jsonMediaUrl', false);
        // Fecha de publicación.
        $response->assertSee('publishedAt', false);
        $response->assertSee('time[datetime]', false);
        $response->assertSee('article:published_time', false);
        $response->assertSee('taken_at_timestamp', false);
        $response->assertSee('published_at', false);
        // Sin hero automático: cover solo entra a image_urls.
        $response->assertDontSee('image_url:cover', false);
        // Next solo con indicadores de carrusel.
        $response->assertSee('hasCarouselIndicators', false);
        $response->assertSee('findNext', false);
        $response->assertSee('window.open', false);
    }
}
