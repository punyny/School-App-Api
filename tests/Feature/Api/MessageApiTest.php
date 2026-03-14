<?php

namespace Tests\Feature\Api;

use App\Events\RealtimeNotificationBroadcasted;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_send_class_message_and_student_can_read_it(): void
    {
        $this->seed();
        Event::fake([RealtimeNotificationBroadcasted::class]);

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();

        Sanctum::actingAs($teacher);
        $send = $this->postJson('/api/messages', [
            'class_id' => $class->id,
            'content' => 'Tomorrow bring your textbook.',
        ]);

        $send->assertCreated()
            ->assertJsonPath('data.class_id', $class->id);
        Event::assertDispatched(RealtimeNotificationBroadcasted::class);

        Sanctum::actingAs($student);
        $list = $this->getJson('/api/messages');
        $list->assertOk()
            ->assertJsonFragment(['content' => 'Tomorrow bring your textbook.']);
    }

    public function test_student_can_reply_directly_to_teacher(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = $student->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/messages', [
            'receiver_id' => $teacher->id,
            'content' => 'សួស្តីគ្រូ ខ្ញុំមានសំណួរ។',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sender_id', $student->id)
            ->assertJsonPath('data.receiver_id', $teacher->id);
    }

    public function test_parent_cannot_send_message(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = $parent->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/messages', [
            'receiver_id' => $teacher->id,
            'content' => 'Parent test',
        ]);

        $response->assertForbidden();
    }

    public function test_message_is_view_only_cannot_be_updated(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $create = $this->withToken($token)->postJson('/api/messages', [
            'class_id' => $class->id,
            'content' => 'Initial content',
        ]);

        $create->assertCreated();
        $messageId = (int) $create->json('data.id');

        $update = $this->withToken($token)->putJson('/api/messages/'.$messageId, [
            'class_id' => $class->id,
            'content' => 'Updated content',
        ]);

        $update->assertStatus(405);
    }

    public function test_sender_can_see_receiver_seen_timestamp_after_reading_message(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $student = User::query()->where('email', 'student@example.com')->firstOrFail();

        Sanctum::actingAs($teacher);
        $create = $this->postJson('/api/messages', [
            'receiver_id' => $student->id,
            'content' => 'Please read this message.',
        ]);
        $create->assertCreated();
        $messageId = (int) $create->json('data.id');

        Sanctum::actingAs($student);
        $this
            ->getJson('/api/messages/'.$messageId)
            ->assertOk();

        Sanctum::actingAs($teacher);
        $show = $this->getJson('/api/messages/'.$messageId);

        $show->assertOk()
            ->assertJsonPath('data.read_meta.seen_count', 1);
        $this->assertNotNull($show->json('data.read_meta.direct_recipient_seen_at'));
    }
}
