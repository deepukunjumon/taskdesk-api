@extends('emails.layout')

@section('title', 'Task Assigned')

@section('content')
    <p style="margin: 0 0 8px; color: #111827;">Dear {{ $assignedTo }},</p>
    <p style="margin: 0 0 24px; font-size: 14px; color: #6b7280;">
    <p style="margin: 0 0 8px; color: #111827;">A new task has been assigned to you</p>
    <p style="margin: 0 0 24px; font-size: 14px; color: #6b7280;">
        {{ $assignedBy->name }} assigned you <strong style="color: #111827;">{{ $workItem->subject }}</strong>.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px; border-top: 1px solid #eef1f5;">
        <tr>
            <td style="padding: 12px 0 6px; color: #6b7280; width: 140px;">Work ID</td>
            <td style="padding: 12px 0 6px; font-weight: 600;">{{ $workItem->task_id }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Subject</td>
            <td style="padding: 6px 0;">{{ $workItem->subject }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Priority</td>
            <td style="padding: 6px 0;">
                <span style="display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize; background-color: #ecfdf5; color: #1F7A5C;">
                    {{ $workItem->priority->value }}
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Assigned By</td>
            <td style="padding: 6px 0;">{{ $assignedBy->name }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0 12px; color: #6b7280;">Due (SLA)</td>
            <td style="padding: 6px 0 12px;">
                {{ $workItem->sla_due_at ? $workItem->sla_due_at->format('d M Y, h:i A') : 'Not set' }}
            </td>
        </tr>
    </table>

    <p style="margin: 28px 0 0;">
        <a href="{{ $taskUrl }}" style="display: inline-block; background-color: #11597a; color: #ffffff; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-size: 14px; font-weight: 600;">
            View Task
        </a>
    </p>
@endsection
