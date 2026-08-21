<?php

use App\Enums\Role;
use App\Jobs\SendWorkItemEmail;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Queue::fake();
});

/**
 * @return array{0: User, 1: User, 2: Department}
 */
function taskAssignedEmailTestManager(): array
{
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);

    return [$manager, $report, $department];
}

// ---------------------------------------------------------------------------
// Queued on create
// ---------------------------------------------------------------------------

it('queues the task-assigned email when creating a task assigned to someone else', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();

    $response = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Do the thing',
        'description' => 'Details',
    ]);

    $response->assertStatus(201);
    $workItemId = $response->json('data.id');

    Queue::assertPushed(
        SendWorkItemEmail::class,
        fn (SendWorkItemEmail $job) => $job->workItemId === $workItemId
            && $job->actorId === $manager->id
            && $job->view === 'emails.task-assigned',
    );
});

it('does not queue the task-assigned email on self-assignment at creation', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($user)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $user->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Self task',
        'description' => 'Details',
    ]);

    $response->assertStatus(201);
    Queue::assertNotPushed(SendWorkItemEmail::class);
});

// ---------------------------------------------------------------------------
// Queued on reassign
// ---------------------------------------------------------------------------

it('queues the task-assigned email when reassigning a task to someone else', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();
    $otherReport = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $otherReport->assignRole(Role::User->value);

    $created = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Reassign me',
        'description' => 'Details',
    ])->json('data');

    // Reset so only the reassign-time dispatch is asserted below.
    Queue::fake();

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$created['id']}/reassign", [
        'assigned_to_id' => $otherReport->id,
    ]);

    $response->assertOk();
    Queue::assertPushed(
        SendWorkItemEmail::class,
        fn (SendWorkItemEmail $job) => $job->workItemId === $created['id']
            && $job->actorId === $manager->id
            && $job->view === 'emails.task-assigned',
    );
});

it('does not queue the task-assigned email when reassigning a task to the actor themself', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();

    $created = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Reassign to self',
        'description' => 'Details',
    ])->json('data');

    Queue::fake();

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$created['id']}/reassign", [
        'assigned_to_id' => $manager->id,
    ]);

    $response->assertOk();
    Queue::assertNotPushed(SendWorkItemEmail::class);
});

// ---------------------------------------------------------------------------
// Job internals — mocked mailer, never hits the real Pepipost API
// ---------------------------------------------------------------------------

it('calls MailerInterface::send with the correct recipient and content', function () {
    $department = Department::factory()->create();
    $assignedBy = User::factory()->create();
    $assignedBy->assignRole(Role::User->value);
    $assignee = User::factory()->create([
        'email' => 'assignee@taskdesk.test',
        'department_id' => $department->id,
    ]);
    $assignee->assignRole(Role::User->value);

    $workItem = App\Models\WorkItem::factory()->create([
        'department_id' => $department->id,
        'assigned_to_id' => $assignee->id,
        'assigned_by_id' => $assignedBy->id,
        'task_id' => 'T0099',
        'subject' => 'Investigate the outage',
    ]);

    $mock = Mockery::mock(App\Contracts\MailerInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(function (string $to, string $subject, string $htmlBody) use ($workItem) {
            return $to === 'assignee@taskdesk.test'
                && str_contains($subject, $workItem->task_id)
                && str_contains($htmlBody, $workItem->task_id)
                && str_contains($htmlBody, $workItem->subject);
        })
        ->andReturn(true);

    (new SendWorkItemEmail(
        $workItem->id,
        $assignedBy->id,
        'emails.task-assigned',
        "Task Assigned: {$workItem->task_id} — {$workItem->subject}",
    ))->handle($mock);
});

it('throws when the mailer reports failure, so the queue retries rather than silently dropping it', function () {
    $department = Department::factory()->create();
    $assignedBy = User::factory()->create();
    $assignedBy->assignRole(Role::User->value);
    $assignee = User::factory()->create(['department_id' => $department->id]);
    $assignee->assignRole(Role::User->value);

    $workItem = App\Models\WorkItem::factory()->create([
        'department_id' => $department->id,
        'assigned_to_id' => $assignee->id,
        'assigned_by_id' => $assignedBy->id,
    ]);

    $mock = Mockery::mock(App\Contracts\MailerInterface::class);
    $mock->shouldReceive('send')->once()->andReturn(false);

    $job = new SendWorkItemEmail(
        $workItem->id,
        $assignedBy->id,
        'emails.task-assigned',
        "Task Assigned: {$workItem->task_id} — {$workItem->subject}",
    );

    expect(fn () => $job->handle($mock))->toThrow(RuntimeException::class);
});

it('does nothing when the work item no longer exists by the time the job runs', function () {
    $mock = Mockery::mock(App\Contracts\MailerInterface::class);
    $mock->shouldNotReceive('send');

    $job = new SendWorkItemEmail(
        '00000000-0000-0000-0000-000000000000',
        'also-missing',
        'emails.task-assigned',
        'Task Assigned: T0000 — Missing',
    );

    $job->handle($mock);
})->throwsNoExceptions();
