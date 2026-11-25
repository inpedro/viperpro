<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetTest extends SecurityTestCase
{
    private ?string $captured = null;

    private function issueToken(string $email): string
    {
        $this->captured = null;
        Mail::shouldReceive('send')->andReturnUsing(function ($view, $data) {
            $this->captured = $data['token'];
        });

        $this->postJson('/api/auth/forget-password', ['email' => $email])->assertOk();
        $this->assertNotEmpty($this->captured);

        return (string) $this->captured;
    }

    public function test_token_is_long_random_and_stored_hashed(): void
    {
        $user  = $this->makeUser();
        $token = $this->issueToken($user->email);

        $this->assertSame(64, strlen($token));
        $stored = DB::table('tenant_password_resets')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
    }

    public function test_token_for_one_account_cannot_reset_another_account(): void
    {
        $attacker = $this->makeUser();
        $victim   = $this->makeUser();
        $token    = $this->issueToken($attacker->email);

        $this->postJson('/api/auth/reset-password/'.$token, [
            'email' => $victim->email, 'token' => $token,
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertStatus(400);

        $this->assertTrue(Hash::check('correct-horse-battery', $victim->fresh()->password));
    }

    public function test_token_is_single_use_and_expires(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $user  = $this->makeUser();
        $token = $this->issueToken($user->email);
        $body  = ['email' => $user->email, 'token' => $token, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];

        $this->postJson('/api/auth/reset-password/'.$token, $body)->assertOk();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->postJson('/api/auth/reset-password/'.$token, $body)->assertStatus(400);

        $token2 = $this->issueToken($user->email);
        DB::table('tenant_password_resets')->where('email', $user->email)->update(['created_at' => now()->subHours(2)]);
        $this->postJson('/api/auth/reset-password/'.$token2, array_merge($body, ['token' => $token2]))->assertStatus(400);
    }

    public function test_unknown_email_gets_the_same_response(): void
    {
        Mail::shouldReceive('send')->never();
        $res = $this->postJson('/api/auth/forget-password', ['email' => 'nobody@example.test'])->assertOk();
        $this->assertTrue($res->json('status'));
    }

    public function test_reset_endpoints_are_rate_limited(): void
    {
        Mail::shouldReceive('send')->andReturnNull();
        $user = $this->makeUser();

        $statuses = [];
        for ($i = 0; $i < 4; $i++) {
            $statuses[] = $this->postJson('/api/auth/forget-password', ['email' => $user->email])->status();
        }

        $this->assertSame([200, 200, 200, 429], $statuses);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->makeUser();
        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])->status();
        }
        $this->assertSame(429, $last);
    }
}
