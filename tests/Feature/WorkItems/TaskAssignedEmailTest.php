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
// Sent on create
// ---------------------------------------------------------------------------

it('sends the task-assigned email when creating a task assigned to someone else', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $to, string $subject, string $htmlBody) => $to === $report->email)
        ->andReturn(true);
    $this->app->instance(MailerInterface::class, $mock);

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
});

it('does not send the task-assigned email on self-assignment at creation', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(Role::User->value);

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(MailerInterface::class, $mock);

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
});

// ---------------------------------------------------------------------------
// Sent on reassign
// ---------------------------------------------------------------------------

it('sends the task-assigned email when reassigning a task to someone else', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();
    $otherReport = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $otherReport->assignRole(Role::User->value);

    $noopMailer = Mockery::mock(MailerInterface::class);
    $noopMailer->shouldReceive('send')->andReturn(true);
    $this->app->instance(MailerInterface::class, $noopMailer);

    $created = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Reassign me',
        'description' => 'Details',
    ])->json('data');

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn (string $to, string $subject, string $htmlBody) => $to === $otherReport->email)
        ->andReturn(true);
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$created['id']}/reassign", [
        'assigned_to_id' => $otherReport->id,
    ]);

    $response->assertOk();
});

it('does not send the task-assigned email when reassigning a task to the actor themself', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();

    $noopMailer = Mockery::mock(MailerInterface::class);
    $noopMailer->shouldReceive('send')->andReturn(true);
    $this->app->instance(MailerInterface::class, $noopMailer);

    $created = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Reassign to self',
        'description' => 'Details',
    ])->json('data');

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldNotReceive('send');
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->patchJson("/api/work-items/{$created['id']}/reassign", [
        'assigned_to_id' => $manager->id,
    ]);

    $response->assertOk();
});

// ---------------------------------------------------------------------------
// Content and failure handling
// ---------------------------------------------------------------------------

it('sends with the correct subject and body content', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldReceive('send')
        ->once()
        ->withArgs(function (string $to, string $subject, string $htmlBody) use ($report) {
            return $to === $report->email
                && str_contains($subject, 'Investigate the outage')
                && str_contains($htmlBody, 'Investigate the outage')
                && str_contains($htmlBody, $manager->name);
        })
        ->andReturn(true);
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Investigate the outage',
        'description' => 'Details',
    ]);

    $response->assertStatus(201);
});

it('does not fail the request when the mailer reports a failure', function () {
    [$manager, $report, $department] = taskAssignedEmailTestManager();

    $mock = Mockery::mock(MailerInterface::class);
    $mock->shouldReceive('send')->once()->andReturn(false);
    $this->app->instance(MailerInterface::class, $mock);

    $response = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Should still succeed',
        'description' => 'Details',
    ]);

    // The assignment already succeeded before the mailer was ever called —
    // a delivery failure must never surface as a failed API response.
    $response->assertStatus(201);
});
