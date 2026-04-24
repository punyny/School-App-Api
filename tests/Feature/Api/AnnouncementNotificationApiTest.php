<?php

namespace Tests\Feature\Api;

use App\Events\RealtimeNotificationBroadcasted;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_student_target_announcement_and_student_can_view_it(): void
    {
        $this->seed();
        Event::fake([RealtimeNotificationBroadcasted::class]);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        Sanctum::actingAs($admin);
        $create = $this->postJson('/api/announcements', [
            'title' => 'School Event',
            'content' => 'Sports day this Friday.',
            'date' => '2026-03-13',
            'target_role' => 'student',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'School Event')
            ->assertJsonPath('data.target_role', 'student');
        Event::assertDispatched(RealtimeNotificationBroadcasted::class);

        Sanctum::actingAs($student);
        $list = $this->getJson('/api/announcements');
        $list->assertOk()
            ->assertJsonFragment(['title' => 'School Event']);

        Sanctum::actingAs($teacher);
        $teacherList = $this->getJson('/api/announcements');
        $teacherList->assertOk()
            ->assertJsonMissing(['title' => 'School Event']);
    }

    public function test_admin_can_send_announcement_to_specific_teacher(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $student = User::query()->where('email', 'student@example.com')->firstOrFail();

        Sanctum::actingAs($admin);
        $create = $this->postJson('/api/announcements', [
            'title' => 'Teacher Only Notice',
            'content' => 'Please prepare annual event agenda.',
            'target_user_id' => $teacher->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.target_user_id', $teacher->id);

        Sanctum::actingAs($teacher);
        $teacherList = $this->getJson('/api/announcements');
        $teacherList->assertOk()
            ->assertJsonFragment(['title' => 'Teacher Only Notice']);

        Sanctum::actingAs($student);
        $studentList = $this->getJson('/api/announcements');
        $studentList->assertOk()
            ->assertJsonMissing(['title' => 'Teacher Only Notice']);
    }

    public function test_student_can_mark_own_notification_as_read(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = User::query()->where('email', 'student@example.com')->firstOrFail();

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/notifications', [
            'user_id' => $student->id,
            'title' => 'Notice',
            'content' => 'Please check your profile.',
        ]);

        $created->assertCreated();
        $notificationId = $created->json('data.id');
        $this->assertNotNull($notificationId);

        Sanctum::actingAs($student);
        $updated = $this->patchJson("/api/notifications/{$notificationId}/read-status", [
            'read_status' => true,
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.read_status', true);
    }

    public function test_teacher_cannot_create_notifications(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $student = User::query()->where('email', 'student@example.com')->firstOrFail();

        Sanctum::actingAs($teacher);

        $this->postJson('/api/notifications', [
            'user_id' => $student->id,
            'title' => 'Student notification',
            'content' => 'Should be blocked for teacher role.',
        ])->assertForbidden();
    }

    public function test_admin_can_delete_school_announcement(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $class = \App\Models\SchoolClass::query()->where('school_id', $admin->school_id)->firstOrFail();

        $announcement = Announcement::query()->create([
            'title' => 'Class Notice',
            'content' => 'Only class update.',
            'date' => now()->toDateString(),
            'school_id' => $admin->school_id,
            'class_id' => $class->id,
        ]);

        Sanctum::actingAs($admin);
        $delete = $this->deleteJson('/api/announcements/'.$announcement->id);

        $delete->assertOk();
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_teacher_cannot_delete_global_announcement(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $announcement = Announcement::query()->create([
            'title' => 'Global Notice',
            'content' => 'Whole school update.',
            'date' => now()->toDateString(),
            'school_id' => $teacher->school_id,
            'class_id' => null,
        ]);

        Sanctum::actingAs($teacher);
        $delete = $this->deleteJson('/api/announcements/'.$announcement->id);

        $delete->assertForbidden();
        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
    }

    public function test_admin_can_update_notification_for_own_school_user(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = User::query()->where('email', 'student@example.com')->firstOrFail();

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/notifications', [
            'user_id' => $student->id,
            'title' => 'Original title',
            'content' => 'Original content',
        ]);

        $created->assertCreated();
        $notificationId = (int) $created->json('data.id');

        $updated = $this->putJson('/api/notifications/'.$notificationId, [
            'title' => 'Updated title',
            'content' => 'Updated content',
            'read_status' => true,
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.read_status', true);
    }

    public function test_admin_can_create_announcement_with_attachment_media(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        Sanctum::actingAs($admin);
        $create = $this->post('/api/announcements', [
            'title' => 'Attachment Notice',
            'content' => 'See attached schedule.',
            'attachments' => [
                UploadedFile::fake()->create('schedule.pdf', 120, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Attachment Notice');

        $announcement = Announcement::query()->where('title', 'Attachment Notice')->firstOrFail();
        $this->assertDatabaseHas('media', [
            'mediable_type' => Announcement::class,
            'mediable_id' => $announcement->id,
            'category' => 'attachment',
        ]);
        $this->assertNotEmpty($announcement->fresh()->file_attachments);
    }
}
