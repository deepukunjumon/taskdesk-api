@extends('emails.layout')

@section('title', 'Task Completed')

@section('content')
    <h1 style="font-size: 18px; margin: 0 0 16px; color: #111827;">Your task has been marked complete</h1>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px;">
        <tr>
            <td style="padding: 6px 0; color: #6b7280; width: 140px;">Work ID</td>
            <td style="padding: 6px 0;">{{ $workItem->task_id }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Subject</td>
            <td style="padding: 6px 0;">{{ $workItem->subject }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Completed By</td>
            <td style="padding: 6px 0;">{{ $completedBy->name }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #6b7280;">Completed On</td>
            <td style="padding: 6px 0;">
                {{ $workItem->end_time ? $workItem->end_time->format('d M Y, h:i A') : 'Not set' }}
            </td>
        </tr>
        @if ($workItem->resolution)
            <tr>
                <td style="padding: 6px 0; color: #6b7280; vertical-align: top;">Resolution</td>
                <td style="padding: 6px 0;">{{ $workItem->resolution }}</td>
            </tr>
        @endif
    </table>

    <p style="margin: 24px 0 0;">
        <a href="{{ $taskUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600;">
            View Task
        </a>
    </p>
@endsection
