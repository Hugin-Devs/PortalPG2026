<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_logs_are_deleted_and_recent_ones_kept(): void
    {
        config(['security.audit_retention_days' => 30]);

        $old = ActivityLog::create([
            'action' => 'login.success',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(45),
        ]);

        $recent = ActivityLog::create([
            'action' => 'login.success',
            'ip_address' => '127.0.0.1',
        ]);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertDatabaseMissing('activity_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $recent->id]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        config(['security.audit_retention_days' => 30]);

        $old = ActivityLog::create([
            'action' => 'login.success',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(45),
        ]);

        $this->artisan('audit:prune', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', ['id' => $old->id]);
    }

    public function test_prune_is_recorded_as_audit_event(): void
    {
        config(['security.audit_retention_days' => 30]);

        ActivityLog::create([
            'action' => 'login.success',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(45),
        ]);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', ['action' => 'audit.pruned']);
    }

    public function test_days_option_overrides_config(): void
    {
        config(['security.audit_retention_days' => 365]);

        $old = ActivityLog::create([
            'action' => 'login.success',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(45),
        ]);

        $this->artisan('audit:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('activity_logs', ['id' => $old->id]);
    }
}
