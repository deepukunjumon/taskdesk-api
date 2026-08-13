<?php

use App\Contracts\MailerInterface;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
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

    $noopMailer = Mockery::mock(MailerInterface::class);
    $noopMailer->shouldReceive('send')->andReturn(true);
    test()->app->instance(MailerInterface::class, $noopMailer);

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

it('sends the task-completed email to the assignee when someone else closes the task', function () {
    [$manager, $report, , $workItemId] = taskCompletedEmailTestSetup();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $to, string $subject, string $htmlBody) => $to === $report->email
            && str_contains($subject, 'Task Completed'))
        ->andReturn(true);
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'closed',
        'resolution' => 'Deployed the fix.',
    ]);

    $response->assertOk();
});

it('does not send the task-completed email when the assignee closes their own task', function () {
    [, $report, , $workItemId] = taskCompletedEmailTestSetup();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($report)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'closed',
        'resolution' => 'Fixed it myself.',
    ]);

    $response->assertOk();
});

it('does not send the task-completed email for a non-closing status transition', function () {
    [$manager, , , $workItemId] = taskCompletedEmailTestSetup();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'in_progress',
    ]);

    $response->assertOk();
});

it('sends the task-completed email with the resolution content', function () {
    [$manager, $report, , $workItemId] = taskCompletedEmailTestSetup();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(function (string $to, string $subject, string $htmlBody) use ($report) {
            return $to === $report->email
                && str_contains($htmlBody, 'Root caused a stale cache key.')
                && str_contains($htmlBody, 'Fix the login bug');
        })
        ->andReturn(true);
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'closed',
        'resolution' => 'Root caused a stale cache key.',
    ]);

    $response->assertOk();
});

it('does not fail the request when the mailer reports a failure on completion', function () {
    [$manager, , , $workItemId] = taskCompletedEmailTestSetup();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldReceive('send')->once()->andReturn(false);
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$workItemId}/status", [
        'status' => 'closed',
        'resolution' => 'Done.',
    ]);

    $response->assertOk();
});
