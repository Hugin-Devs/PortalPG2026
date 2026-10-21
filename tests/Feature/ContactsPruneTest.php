<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactsPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_contacts_are_deleted_and_recent_ones_kept(): void
    {
        config(['security.contact_retention_days' => 30]);

        $old = Contact::create([
            'name' => 'Antiguo',
            'email' => 'antiguo@example.com',
            'subject' => 'Viejo',
            'message' => 'Mensaje antiguo',
        ]);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        $recent = Contact::create([
            'name' => 'Reciente',
            'email' => 'reciente@example.com',
            'subject' => 'Nuevo',
            'message' => 'Mensaje reciente',
        ]);

        $this->artisan('contacts:prune')->assertSuccessful();

        $this->assertDatabaseMissing('contacts', ['id' => $old->id]);
        $this->assertDatabaseHas('contacts', ['id' => $recent->id]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        config(['security.contact_retention_days' => 30]);

        $old = Contact::create([
            'name' => 'Antiguo',
            'email' => 'antiguo@example.com',
            'subject' => 'Viejo',
            'message' => 'Mensaje antiguo',
        ]);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->artisan('contacts:prune', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('contacts', ['id' => $old->id]);
    }
}
