<?php

namespace Tests\Feature;

use App\Models\OrgMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgMemberPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_valid_photo_path_is_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/estructura', [
            'name' => 'Miembro',
            'position' => 'Director',
            'photo' => 'images/miembro.jpg',
        ]);

        $response->assertRedirect('/admin/estructura');
        $this->assertDatabaseHas('org_members', ['photo' => 'images/miembro.jpg']);
    }

    public function test_photo_can_be_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/estructura', [
            'name' => 'Miembro',
            'position' => 'Director',
        ])->assertRedirect('/admin/estructura');
    }

    /**
     * @dataProvider dangerousPhotosProvider
     */
    public function test_dangerous_photo_paths_are_rejected(string $photo): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->from('/admin/estructura/crear')->post('/admin/estructura', [
            'name' => 'Miembro',
            'position' => 'Director',
            'photo' => $photo,
        ]);

        $response->assertRedirect('/admin/estructura/crear');
        $response->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('org_members', 0);
    }

    public static function dangerousPhotosProvider(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'protocol relative' => ['//evil.com/x.png'],
            'absolute url' => ['https://evil.com/x.png'],
            'path traversal' => ['../../etc/passwd.png'],
            'php extension' => ['images/miembro.php'],
            'double extension' => ['images/miembro.php.png'],
            'outside whitelist dir' => ['build/assets/x.png'],
            'script tag' => ['"><script>alert(1)</script>'],
        ];
    }

    public function test_update_rejects_dangerous_photo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = OrgMember::create(['name' => 'M', 'position' => 'P', 'photo' => 'images/ok.jpg']);

        $this->actingAs($admin)->from("/admin/estructura/{$member->id}/editar")
            ->put("/admin/estructura/{$member->id}", [
                'name' => 'M',
                'position' => 'P',
                'photo' => 'javascript:alert(1)',
            ])->assertSessionHasErrors('photo');
    }
}
