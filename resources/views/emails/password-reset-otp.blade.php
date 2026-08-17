@extends('emails.layout')

@section('title', 'Password Reset Code')

@section('content')
    <p style="margin: 0 0 8px; color: #111827;">Reset your TaskDesk password</p>
    <p style="margin: 0 0 24px; font-size: 14px; color: #6b7280;">
        Use the code below to continue resetting your password. It expires in 10 minutes.
        If you didn't request this, you can safely ignore this email.
    </p>

    <div style="text-align: center; margin: 0 0 24px;">
        <span style="display: inline-block; padding: 12px 28px; border-radius: 8px; background-color: #f3f4f6; font-size: 28px; font-weight: 700; letter-spacing: 0.3em; color: #111827;">
            {{ $otp }}
        </span>
    </div>

    <p style="margin: 0; font-size: 13px; color: #9ca3af;">
        For your security, this code can only be used a few times before you'll need to request a new one.
    </p>
@endsection
