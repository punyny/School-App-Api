<?php

namespace Tests\Feature\Api;

use App\Events\RealtimeNotificationBroadcasted;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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

    public function test_teacher_create_incident_sends_telegram_private_notification_to_parent(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 612]], 200),
        ]);

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $parent->forceFill(['telegram_chat_id' => '7469476859'])->save();
        $student = $parent->children()->with(['user', 'class.school'])->firstOrFail();

        Sanctum::actingAs($teacher);
        $response = $this->postJson('/api/incident-reports', [
            'student_id' => $student->id,
            'description' => 'Late to class with no uniform',
            'type' => 'Discipline',
            'date' => '2026-04-20',
        ]);

        $response->assertCreated();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($student): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains($text, 'របាយការណ៍បញ្ហាសិស្ស')
                && str_contains($text, 'សិស្ស : '.(string) ($student->user?->name ?? ''))
                && str_contains($text, 'ថ្នាក់ : ')
                && str_contains($text, 'សាលា : ')
                && str_contains($text, 'ពិពណ៌នា : Late to class with no uniform');
        });
    }
}
