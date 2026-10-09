<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Media;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    private function imageFile(string $clientName): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/fixtures/pixel.png'),
            $clientName,
            'image/png',
            null,
            true
        );
    }

    public function test_uploads_helper_uses_content_not_client_name(): void
    {
        $file = $this->imageFile('shell.php');

        $this->assertSame('png', Uploads::safeExtension($file, ['png']));
    }

    public function test_uploads_helper_falls_back_to_bin_for_unknown_content(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 1, 'text/plain');

        $this->assertSame('bin', Uploads::safeExtension($file, ['png']));
    }

    public function test_media_upload_renamed_to_html_is_stored_as_safe_extension(): void
    {
        $file = $this->imageFile('malicious.html');

        $this->actingAs($this->superadmin)->post('/admin/medios', [
            'files' => [$file],
        ])->assertRedirect('/admin/medios');

        $media = Media::latest()->first();
        $this->assertStringEndsWith('.png', $media->filename);
        $this->assertStringNotContainsString('.html', $media->filename);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_news_featured_image_renamed_to_html_keeps_safe_extension(): void
    {
        $file = $this->imageFile('featured.html');
        $category = Category::factory()->create();

        $this->actingAs($this->superadmin)->post('/admin/noticias', [
            'title' => 'Noticia',
            'category_id' => $category->id,
            'excerpt' => 'Resumen',
            'content' => '<p>Contenido</p>',
            'featured_image_file' => $file,
        ])->assertRedirect('/admin/noticias');

        $post = Post::latest()->first();
        $this->assertStringEndsWith('.png', $post->featured_image);
        $this->assertStringNotContainsString('.html', $post->featured_image);
        Storage::disk('public')->assertExists($post->featured_image);
    }

    public function test_partner_logo_renamed_to_html_keeps_safe_extension(): void
    {
        $file = $this->imageFile('logo.html');

        $this->actingAs($this->superadmin)->post('/admin/entes', [
            'name' => 'Ente',
            'description' => 'Desc',
            'logo' => $file,
        ])->assertRedirect('/admin/entes');

        $partner = Partner::latest()->first();
        $this->assertStringEndsWith('.png', $partner->logo);
        $this->assertStringNotContainsString('.html', $partner->logo);
        Storage::disk('public')->assertExists($partner->logo);
    }

    public function test_settings_logo_renamed_to_html_keeps_safe_extension(): void
    {
        $file = $this->imageFile('logo.html');

        $this->actingAs($this->superadmin)->put('/admin/configuracion', [
            'site_name' => 'Portal',
            'logo' => $file,
        ])->assertRedirect('/admin/configuracion');

        $stored = Setting::where('key', 'site_logo')->value('value');
        $this->assertStringEndsWith('.png', $stored);
        $this->assertStringNotContainsString('.html', $stored);
        Storage::disk('public')->assertExists($stored);
    }

    public function test_text_file_renamed_as_image_is_rejected_by_mimes_rule(): void
    {
        $file = UploadedFile::fake()->create('fake.png', 1, 'text/plain');

        $this->actingAs($this->superadmin)->post('/admin/medios', [
            'files' => [$file],
        ])->assertSessionHasErrors('files.0');

        $this->assertNull(Media::latest()->first());
    }
}
