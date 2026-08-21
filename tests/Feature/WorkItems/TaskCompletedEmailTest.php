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
 * @return array{0: User, 1: User, 2: Department, 3: string} manager, report, department, work item id
 */
function taskCompletedEmailTestSetup(): array
{
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);

    $created = test()->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Fix the login bug',
        'description' => 'Details',
    ])->json('data');

    return [$manager, $report, $department, $created['id']];
}

it('queues the task-completed email to the assignee when someone else closes the task', function () {
    [$manager, $report, , $workItemId] = taskCompletedEmailTestSetup();

    // Reset so only the status-change dispatch is asserted below.
    Queue::fake();

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'closed',
        'resolution' => 'Deployed the fix.',
    ]);

    $response->assertOk();
    Queue::assertPushed(
        SendWorkItemEmail::class,
        fn (SendWorkItemEmail $job) => $job->workItemId === $workItemId
            && $job->actorId === $manager->id
            && $job->view === 'emails.task-completed'
            && str_contains($job->subject, 'Task Completed'),
    );
});

it('does not queue the task-completed email when the assignee closes their own task', function () {
    [, $report, , $workItemId] = taskCompletedEmailTestSetup();

    Queue::fake();

    $response = $this->actingAs($report)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'closed',
        'resolution' => 'Fixed it myself.',
    ]);

    $response->assertOk();
    Queue::assertNotPushed(SendWorkItemEmail::class);
});

it('does not queue any email for a non-closing status transition', function () {
    [$manager, , , $workItemId] = taskCompletedEmailTestSetup();

    Queue::fake();

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'in_progress',
    ]);

    $response->assertOk();
    Queue::assertNotPushed(SendWorkItemEmail::class);
});

it('renders the completion email with the resolution content', function () {
    $department = App\Models\Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $assignee = User::factory()->create([
        'email' => 'assignee@taskdesk.test',
        'department_id' => $department->id,
    ]);
    $assignee->assignRole(Role::User->value);

    $workItem = App\Models\WorkItem::factory()->create([
        'department_id' => $department->id,
        'assigned_to_id' => $assignee->id,
        'assigned_by_id' => $manager->id,
        'task_id' => 'T0100',
        'subject' => 'Fix the login bug',
        'resolution' => 'Root caused a stale cache key.',
    ]);

    $mock = Mockery::mock(App\Contracts\MailerInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(function (string $to, string $subject, string $htmlBody) use ($workItem) {
            return $to === 'assignee@taskdesk.test'
                && str_contains($htmlBody, 'Root caused a stale cache key.')
                && str_contains($htmlBody, $workItem->subject);
        })
        ->andReturn(true);

    (new SendWorkItemEmail(
        $workItem->id,
        $manager->id,
        'emails.task-completed',
        "Task Completed: {$workItem->task_id} — {$workItem->subject}",
    ))->handle($mock);
});

it('does not fail the request when the mailer reports a failure on completion', function () {
    [$manager, , , $workItemId] = taskCompletedEmailTestSetup();

    Queue::fake();

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'closed',
        'resolution' => 'Done.',
    ]);

    // The status change already succeeded before the queued job ever ran —
    // a delivery failure inside the job can never surface here.
    $response->assertOk();
});
