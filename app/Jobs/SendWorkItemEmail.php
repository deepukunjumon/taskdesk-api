<?php

namespace App\Jobs;

use App\Contracts\MailerInterface;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Job to send an email notification related to a work item.
 */
class SendWorkItemEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $workItemId,
        public readonly string $actorId,
        public readonly string $view,
        public readonly string $subject,
    ) {}

    public function handle(MailerInterface $mailer): void
    {
        $workItem = WorkItem::with('assignedTo')->find($this->workItemId);
        $actor = User::find($this->actorId);

        if (! $workItem || ! $workItem->assignedTo || ! $actor) {
            return;
        }

        $taskUrl = rtrim(config('services.frontend.url'), '/').'/work-register';

        $data = match ($this->view) {
            'emails.task-assigned' => [
                'assignedTo' => $workItem->assignedTo->name,
                'assignedBy' => $actor,
            ],
            'emails.task-completed' => ['completedBy' => $actor],
            default => [],
        };

        $htmlBody = view($this->view, [...$data, 'workItem' => $workItem, 'taskUrl' => $taskUrl])->render();

        $sent = $mailer->send($workItem->assignedTo->email, $this->subject, $htmlBody);

        if (! $sent) {
            throw new RuntimeException("Failed to send \"{$this->view}\" email for work item {$this->workItemId}.");
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendWorkItemEmail exhausted retries without a successful send.', [
            'work_item_id' => $this->workItemId,
            'view' => $this->view,
            'error' => $exception?->getMessage(),
        ]);
    }
}
