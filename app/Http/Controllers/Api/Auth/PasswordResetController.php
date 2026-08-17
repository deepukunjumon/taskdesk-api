<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\OtpVerificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResets,
    ) {}

    /**
     * Handle a password reset request by sending an OTP to the user's email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResets->requestOtp($request->validated('email'), $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email, a verification code has been sent.',
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $resetToken = $this->passwordResets->verifyOtp(
                $request->validated('email'),
                $request->validated('otp'),
                $request->ip(),
            );
        } catch (OtpVerificationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ['reset_token' => $resetToken],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResets->resetPassword(
            $request->validated('reset_token'),
            $request->validated('password'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset. You can now log in.',
        ]);
    }
}
