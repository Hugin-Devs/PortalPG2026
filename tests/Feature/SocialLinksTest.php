<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SocialSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * D14 — Redes sociales con múltiples cuentas por red (una URL por línea):
 * validación del admin, guardado normalizado y un icono por línea en el footer.
 */
class FillExampleSocialSeeder extends SocialSettingSeeder
{
    // Valor de ejemplo multi-línea para ejercitar la rama "rellenar vacío".
    public const REDES = [
        'social_facebook' => "https://www.facebook.com/ejemplo-uno\nhttps://www.facebook.com/ejemplo-dos",
    ];
}

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->superadmin = User::factory()->create(['role' => 'superadmin']);
    }

    private function putSocial(array $fields): TestResponse
    {
        return $this->actingAs($this->superadmin)->put('/admin/rrss', $fields);
    }

    private function homeHtml(): string
    {
        Cache::flush();

        return $this->get('/')->assertOk()->getContent();
    }

    private function socialValue(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    public function test_seeder_creates_seven_keys_and_is_idempotent(): void
    {
        $this->seed(SocialSettingSeeder::class);
        $this->seed(SocialSettingSeeder::class);

        $this->assertSame(7, Setting::where('key', 'like', 'social_%')->count());
        foreach (array_keys(SocialSettingSeeder::REDES) as $key) {
            $this->assertNotNull($this->socialValue($key), "falta la clave $key");
        }
    }

    public function test_seeder_never_overwrites_edited_values(): void
    {
        Setting::create(['key' => 'social_x', 'value' => 'https://x.com/editado']);

        $this->seed(SocialSettingSeeder::class);

        $this->assertSame('https://x.com/editado', $this->socialValue('social_x'));
    }

    public function test_seeder_fills_empty_values_from_its_template(): void
    {
        Setting::create(['key' => 'social_facebook', 'value' => '']);

        $this->seed(FillExampleSocialSeeder::class);

        $this->assertSame(
            "https://www.facebook.com/ejemplo-uno\nhttps://www.facebook.com/ejemplo-dos",
            $this->socialValue('social_facebook')
        );
    }

    public function test_update_stores_multiple_urls_and_footer_renders_one_icon_per_url(): void
    {
        $this->putSocial([
            'facebook' => "https://www.facebook.com/uno\r\nhttps://www.facebook.com/dos",
        ])->assertRedirect('/admin/rrss')->assertSessionHas('success');

        $this->assertSame(
            "https://www.facebook.com/uno\nhttps://www.facebook.com/dos",
            $this->socialValue('social_facebook')
        );

        $html = $this->homeHtml();
        $this->assertSame(2, substr_count($html, 'fa-facebook-f'), 'debe haber un icono por URL');
        $this->assertStringContainsString('href="https://www.facebook.com/uno"', $html);
        $this->assertStringContainsString('href="https://www.facebook.com/dos"', $html);
        $this->assertStringContainsString('title="Facebook — https://www.facebook.com/uno"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_update_rejects_any_invalid_line_and_keeps_db_intact(): void
    {
        Setting::create(['key' => 'social_facebook', 'value' => 'https://www.facebook.com/gdc']);

        $response = $this->putSocial([
            'facebook' => "https://www.facebook.com/ok\njavascript:alert(1)",
        ]);

        $response->assertSessionHasErrors('facebook');
        $this->assertStringContainsString('línea 2', (string) session('errors')->first('facebook'));
        $this->assertSame('https://www.facebook.com/gdc', $this->socialValue('social_facebook'));
    }

    public function test_footer_skips_blank_lines_and_single_account_shows_plain_label(): void
    {
        $this->putSocial(['instagram' => "  \nhttps://www.instagram.com/perfil\n\n "]);

        $html = $this->homeHtml();
        $this->assertSame(1, substr_count($html, 'fa-instagram'), 'lineas vacias no generan iconos');
        $this->assertStringContainsString('title="Instagram"', $html);
        $this->assertStringNotContainsString('fa-facebook-f', $html);
    }
}
