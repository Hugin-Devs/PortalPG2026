<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminVideoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_index_renders_video_list(): void
    {
        Video::create(['title' => 'Existente', 'type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->actingAs($this->superadmin())->get('/admin/videos')
            ->assertOk()
            ->assertSee('Existente');
    }

    public function test_store_youtube_video_with_audit(): void
    {
        $this->actingAs($this->superadmin())->post('/admin/videos', [
            'title' => 'Video YT',
            'type' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertRedirect();

        $video = Video::where('title', 'Video YT')->first();
        $this->assertNotNull($video);
        $this->assertSame('youtube', $video->type);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $video->url);
        $this->assertNull($video->path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'video.created', 'entity_id' => $video->id]);
    }

    public function test_store_file_video_uploads_and_uses_safe_extension(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin())->post('/admin/videos', [
            'title' => 'Video archivo',
            'type' => 'file',
            'file' => UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'),
        ])->assertRedirect();

        $video = Video::where('title', 'Video archivo')->first();
        $this->assertNotNull($video);
        $this->assertStringStartsWith('videos/', $video->path);
        $this->assertStringEndsWith('.mp4', $video->path);
        Storage::disk('public')->assertExists($video->path);
        $this->assertNull($video->url);
    }

    public function test_store_rejects_invalid_youtube_url(): void
    {
        $this->actingAs($this->superadmin())->post('/admin/videos', [
            'title' => 'Malo',
            'type' => 'youtube',
            'url' => 'https://example.com/no-es-youtube',
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseCount('videos', 0);
    }

    public function test_store_requires_url_for_youtube_and_file_for_file(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/admin/videos', [
            'title' => 'Sin URL', 'type' => 'youtube',
        ])->assertSessionHasErrors('url');

        $this->actingAs($admin)->post('/admin/videos', [
            'title' => 'Sin archivo', 'type' => 'file',
        ])->assertSessionHasErrors('file');

        $this->actingAs($admin)->post('/admin/videos', [
            'type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('videos', 0);
    }

    public function test_destacar_sets_featured_and_clears_others(): void
    {
        $a = Video::create(['title' => 'A', 'type' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'is_featured' => true]);
        $b = Video::create(['title' => 'B', 'type' => 'youtube', 'url' => 'https://youtu.be/abcdefghijk']);

        $this->actingAs($this->superadmin())->post('/admin/videos/destacar', [
            'video_id' => $b->id,
        ])->assertRedirect();

        $this->assertFalse($a->refresh()->is_featured);
        $this->assertTrue($b->refresh()->is_featured);
        $this->assertDatabaseHas('activity_logs', ['action' => 'video.featured', 'entity_id' => $b->id]);
    }

    public function test_destacar_empty_clears_all_and_validates(): void
    {
        $a = Video::create(['title' => 'A', 'type' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'is_featured' => true]);

        $this->actingAs($this->superadmin())->post('/admin/videos/destacar', [])->assertRedirect();
        $this->assertFalse($a->refresh()->is_featured);
        $this->assertDatabaseHas('activity_logs', ['action' => 'video.featured', 'entity_id' => null]);

        $this->actingAs($this->superadmin())->post('/admin/videos/destacar', ['video_id' => 999])
            ->assertSessionHasErrors('video_id');
    }

    public function test_destroy_removes_file_and_record(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/borrar.mp4', 'x');
        $video = Video::create(['title' => 'Borrar', 'type' => 'file', 'path' => 'videos/borrar.mp4']);

        $this->actingAs($this->superadmin())->delete("/admin/videos/{$video->id}")->assertRedirect();

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
        Storage::disk('public')->assertMissing('videos/borrar.mp4');
        $this->assertDatabaseHas('activity_logs', ['action' => 'video.deleted', 'entity_id' => $video->id]);
    }

    public function test_destroy_youtube_video_without_file(): void
    {
        $video = Video::create(['title' => 'YT', 'type' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->actingAs($this->superadmin())->delete("/admin/videos/{$video->id}")->assertRedirect();
        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_video_model_accessors(): void
    {
        $yt = new Video(['type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $yt->embed_url);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $yt->source_url);

        $short = new Video(['type' => 'youtube', 'url' => 'https://youtu.be/abcdefghijk']);
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $short->embed_url);

        $bad = new Video(['type' => 'youtube', 'url' => 'https://youtube.com/watch?v=corto']);
        $this->assertNull($bad->embed_url);
        $this->assertNull($bad->source_url);

        $file = new Video(['type' => 'file', 'path' => 'videos/x.mp4']);
        $this->assertNull($file->embed_url);
        $this->assertSame(asset('storage/videos/x.mp4'), $file->source_url);

        $none = new Video(['type' => 'file']);
        $this->assertNull($none->source_url);
    }

    public function test_authorization_requires_videos_permissions(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($editor)->get('/admin/videos')->assertForbidden();
        $this->actingAs($admin)->get('/admin/videos')->assertForbidden();
        $this->actingAs($editor)->post('/admin/videos', ['title' => 'x', 'type' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertForbidden();
    }
}
