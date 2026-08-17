<?php

use App\Jobs\SendPasswordResetOtp;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Clear the cache and fake the queue before each test to ensure a clean state.
    Cache::flush();
    Queue::fake();
});

/**
 * Helper function to request a password reset OTP for a given email and capture the OTP from the queued job.
 *
 * @param string $email
 * @return string
 */
function forgotPasswordTestRequestOtp(string $email): string
{
    test()->postJson('/api/forgot-password', ['email' => $email])->assertOk();

    $otp = null;
    Queue::assertPushed(SendPasswordResetOtp::class, function (SendPasswordResetOtp $job) use ($email, &$otp) {
        if ($job->email !== $email) {
            return false;
        }
        $otp = $job->otp;

        return true;
    });

    expect($otp)->not->toBeNull();

    return $otp;
}

// ---------------------------------------------------------------------------
// Account existence — deliberately disclosed (user-enumeration tradeoff
// accepted as a product decision; see PasswordResetService::requestOtp()).
// ---------------------------------------------------------------------------

it('succeeds for an existing email', function () {
    User::factory()->create(['email' => 'exists@taskdesk.test']);

    $response = $this->postJson('/api/forgot-password', ['email' => 'exists@taskdesk.test']);

    $response->assertOk()->assertJsonPath('success', true);
});

it('rejects a non-existing email with a validation error, and does not dispatch an OTP email', function () {
    $response = $this->postJson('/api/forgot-password', ['email' => 'nobody-here@taskdesk.test']);

    $response->assertStatus(422)->assertJsonValidationErrors('email');

    Queue::assertNotPushed(SendPasswordResetOtp::class);
    expect(PasswordResetOtp::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

it('completes the full request -> verify -> reset flow with a correct OTP', function () {
    $user = User::factory()->create(['email' => 'flow@taskdesk.test', 'password' => 'old-password-123']);

    $otp = forgotPasswordTestRequestOtp('flow@taskdesk.test');

    $verify = $this->postJson('/api/verify-otp', ['email' => 'flow@taskdesk.test', 'otp' => $otp]);
    $verify->assertOk();
    $resetToken = $verify->json('data.reset_token');
    expect($resetToken)->toBeString()->not->toBeEmpty();

    $reset = $this->postJson('/api/reset-password', [
        'reset_token' => $resetToken,
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ]);
    $reset->assertOk();

    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue();

    $login = $this->postJson('/api/login', ['email' => 'flow@taskdesk.test', 'password' => 'new-password-456']);
    $login->assertOk();
});

// ---------------------------------------------------------------------------
// Expiry
// ---------------------------------------------------------------------------

it('rejects an expired OTP even when the value is correct', function () {
    User::factory()->create(['email' => 'expired@taskdesk.test']);
    $otp = forgotPasswordTestRequestOtp('expired@taskdesk.test');

    PasswordResetOtp::where('email', 'expired@taskdesk.test')->update(['expires_at' => now()->subMinute()]);

    $response = $this->postJson('/api/verify-otp', ['email' => 'expired@taskdesk.test', 'otp' => $otp]);

    $response->assertStatus(422)->assertJsonPath('error_code', 'expired');
});

// ---------------------------------------------------------------------------
// Attempt limit
// ---------------------------------------------------------------------------

it('locks out further attempts after the 6th wrong OTP for that request (5 allowed)', function () {
    User::factory()->create(['email' => 'lockout@taskdesk.test']);
    forgotPasswordTestRequestOtp('lockout@taskdesk.test');

    for ($i = 1; $i <= 4; $i++) {
        $response = $this->postJson('/api/verify-otp', ['email' => 'lockout@taskdesk.test', 'otp' => '000000']);
        $response->assertStatus(422)->assertJsonPath('error_code', 'invalid');
    }

    // 5th wrong attempt trips the lockout.
    $fifth = $this->postJson('/api/verify-otp', ['email' => 'lockout@taskdesk.test', 'otp' => '000000']);
    $fifth->assertStatus(422)->assertJsonPath('error_code', 'locked_out');

    // 6th — locked out even before checking the code, regardless of value.
    $sixth = $this->postJson('/api/verify-otp', ['email' => 'lockout@taskdesk.test', 'otp' => '111111']);
    $sixth->assertStatus(422)->assertJsonPath('error_code', 'locked_out');
});

it('does not lock out a correct OTP submitted within the attempt limit', function () {
    User::factory()->create(['email' => 'withinlimit@taskdesk.test']);
    $otp = forgotPasswordTestRequestOtp('withinlimit@taskdesk.test');

    // 4 wrong attempts, then the correct one on the 5th — should still succeed.
    for ($i = 1; $i <= 4; $i++) {
        $this->postJson('/api/verify-otp', ['email' => 'withinlimit@taskdesk.test', 'otp' => '000000'])
            ->assertStatus(422);
    }

    $response = $this->postJson('/api/verify-otp', ['email' => 'withinlimit@taskdesk.test', 'otp' => $otp]);
    $response->assertOk();
});

// ---------------------------------------------------------------------------
// Invalidating prior OTPs on a new request
// ---------------------------------------------------------------------------

it('invalidates a prior unconsumed OTP when a new one is requested', function () {
    User::factory()->create(['email' => 'renew@taskdesk.test']);

    $firstOtp = forgotPasswordTestRequestOtp('renew@taskdesk.test');
    $secondOtp = forgotPasswordTestRequestOtp('renew@taskdesk.test');

    expect($firstOtp)->not->toBe($secondOtp);

    $usingFirst = $this->postJson('/api/verify-otp', ['email' => 'renew@taskdesk.test', 'otp' => $firstOtp]);
    $usingFirst->assertStatus(422)->assertJsonPath('error_code', 'invalid');

    $usingSecond = $this->postJson('/api/verify-otp', ['email' => 'renew@taskdesk.test', 'otp' => $secondOtp]);
    $usingSecond->assertOk();
});

// ---------------------------------------------------------------------------
// One-time use
// ---------------------------------------------------------------------------

it('rejects reusing an already-consumed OTP', function () {
    User::factory()->create(['email' => 'reuse@taskdesk.test']);
    $otp = forgotPasswordTestRequestOtp('reuse@taskdesk.test');

    $this->postJson('/api/verify-otp', ['email' => 'reuse@taskdesk.test', 'otp' => $otp])->assertOk();

    $reused = $this->postJson('/api/verify-otp', ['email' => 'reuse@taskdesk.test', 'otp' => $otp]);
    $reused->assertStatus(422)->assertJsonPath('error_code', 'invalid');
});

it('rejects reusing an already-consumed reset token', function () {
    User::factory()->create(['email' => 'reusetoken@taskdesk.test']);
    $otp = forgotPasswordTestRequestOtp('reusetoken@taskdesk.test');

    $verify = $this->postJson('/api/verify-otp', ['email' => 'reusetoken@taskdesk.test', 'otp' => $otp]);
    $resetToken = $verify->json('data.reset_token');

    $first = $this->postJson('/api/reset-password', [
        'reset_token' => $resetToken,
        'password' => 'first-password-123',
        'password_confirmation' => 'first-password-123',
    ]);
    $first->assertOk();

    $second = $this->postJson('/api/reset-password', [
        'reset_token' => $resetToken,
        'password' => 'second-password-456',
        'password_confirmation' => 'second-password-456',
    ]);
    $second->assertStatus(422)->assertJsonValidationErrors('reset_token');
});

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

it('rejects an OTP that is not exactly 6 digits', function () {
    User::factory()->create(['email' => 'badformat@taskdesk.test']);

    $response = $this->postJson('/api/verify-otp', ['email' => 'badformat@taskdesk.test', 'otp' => '12345']);

    $response->assertStatus(422)->assertJsonValidationErrors('otp');
});

it('rejects an invalid email format on the forgot-password request', function () {
    $response = $this->postJson('/api/forgot-password', ['email' => 'not-an-email']);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});
