<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_action_creates_audit_log_record(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/announcements', [
            'title' => 'Audit test announcement',
            'content' => 'Audit log should capture this action.',
            'date' => '2026-03-09',
        ]);

        $create->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'actor_role' => 'admin',
            'method' => 'POST',
            'endpoint' => '/api/announcements',
            'status_code' => 201,
        ]);

        $list = $this->getJson('/api/audit-logs');
        $list->assertOk()->assertJsonFragment([
            'actor_id' => $admin->id,
            'endpoint' => '/api/announcements',
        ]);
    }

    public function test_admin_only_sees_logs_in_own_school_scope(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $otherSchool = School::query()->create([
            'name' => 'Other School',
            'location' => 'Other City',
            'config_details' => [],
        ]);

        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'school_id' => $admin->school_id,
            'actor_name' => $admin->name,
            'actor_role' => 'admin',
            'method' => 'POST',
            'endpoint' => '/api/homeworks',
            'action' => 'POST /api/homeworks',
            'resource_type' => 'homeworks',
            'resource_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'request_payload' => ['class_id' => 1],
            'status_code' => 201,
        ]);

        AuditLog::query()->create([
            'actor_id' => $admin->id,
            'school_id' => $otherSchool->id,
            'actor_name' => $admin->name,
            'actor_role' => 'admin',
            'method' => 'DELETE',
            'endpoint' => '/api/users/99',
            'action' => 'DELETE /api/users/99',
            'resource_type' => 'users',
            'resource_id' => 99,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'request_payload' => ['id' => 99],
            'status_code' => 200,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/audit-logs');

        $response->assertOk();
        $returnedSchoolIds = collect($response->json('data'))->pluck('school_id')->filter()->unique()->all();

        $this->assertContains((int) $admin->school_id, $returnedSchoolIds);
        $this->assertNotContains((int) $otherSchool->id, $returnedSchoolIds);
    }

    public function test_teacher_cannot_access_audit_logs_api(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        Sanctum::actingAs($teacher);

        $response = $this->getJson('/api/audit-logs');
        $response->assertForbidden();
    }
}

