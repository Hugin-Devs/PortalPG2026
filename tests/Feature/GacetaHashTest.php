<?php

namespace Tests\Feature;

use App\Models\Gaceta;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GacetaHashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_uploading_a_gaceta_stores_its_sha256_hash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->create('gaceta-1.pdf', 50, 'application/pdf');

        $this->actingAs($admin)->post('/admin/gacetas', [
            'file' => $file,
            'numero' => '1',
            'fecha' => '2026-01-15',
        ])->assertRedirect('/admin/gacetas');

        $gaceta = Gaceta::latest()->first();
        $this->assertNotNull($gaceta->file_hash);
        $this->assertSame(
            hash_file('sha256', Storage::disk('public')->path($gaceta->path)),
            $gaceta->file_hash
        );
    }

    public function test_updating_a_gaceta_recomputes_its_hash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::disk('public')->put('gacetas/original.pdf', 'contenido-original');
        $gaceta = Gaceta::create([
            'numero' => '1',
            'fecha' => '2026-01-15',
            'path' => 'gacetas/original.pdf',
            'original_name' => 'original.pdf',
            'mime_type' => 'application/pdf',
            'size' => 18,
            'file_hash' => hash('sha256', 'contenido-original'),
            'user_id' => $admin->id,
        ]);

        $replacement = UploadedFile::fake()->create('gaceta-nueva.pdf', 60, 'application/pdf');

        $this->actingAs($admin)->put("/admin/gacetas/{$gaceta->id}", [
            'file' => $replacement,
            'numero' => '2',
            'fecha' => '2026-02-01',
        ])->assertRedirect('/admin/gacetas');

        $gaceta->refresh();
        $this->assertNotNull($gaceta->file_hash);
        $this->assertSame('2', $gaceta->numero);
        $this->assertSame(
            hash_file('sha256', Storage::disk('public')->path($gaceta->path)),
            $gaceta->file_hash
        );
        $this->assertNotSame(hash('sha256', 'contenido-original'), $gaceta->file_hash);
    }
}
