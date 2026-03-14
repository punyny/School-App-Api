<?php

namespace Tests\Feature\Api;

use App\Events\RealtimeNotificationBroadcasted;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_incident_and_parent_can_acknowledge(): void
    {
        $this->seed();
        Event::fake([RealtimeNotificationBroadcasted::class]);

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();

        Sanctum::actingAs($teacher);
        $create = $this->postJson('/api/incident-reports', [
            'student_id' => $student->id,
            'description' => 'Fighting in class',
            'type' => 'Discipline',
            'date' => '2026-03-11',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.acknowledged', false);
        Event::assertDispatched(RealtimeNotificationBroadcasted::class);

        $incidentId = $create->json('data.id');
        $this->assertNotNull($incidentId);

        Sanctum::actingAs($parent);
        $ack = $this->patchJson("/api/incident-reports/{$incidentId}/acknowledgment", [
            'acknowledged' => true,
        ]);

        $ack->assertOk()
            ->assertJsonPath('data.acknowledged', true);
    }

    public function test_parent_cannot_create_incident_report(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $token = $parent->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/incident-reports', [
            'student_id' => $student->id,
            'description' => 'Parent test create',
            'type' => 'Other',
        ]);

        $response->assertForbidden();
    }
}
