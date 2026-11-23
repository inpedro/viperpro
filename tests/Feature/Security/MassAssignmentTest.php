<?php

namespace Tests\Feature\Security;

use App\Models\User;

class MassAssignmentTest extends SecurityTestCase
{
    public function test_privileged_user_fields_are_not_mass_assignable(): void
    {
        $user = $this->makeUser();
        $user->update([
            'role_id' => 0, 'is_admin' => 1, 'banned' => 0, 'is_demo_agent' => 1,
            'affiliate_revenue_share' => 99, 'affiliate_cpa' => 1000, 'inviter' => 1, 'name' => 'Renamed',
        ]);

        $fresh = $user->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertNotEquals(0, (int) $fresh->role_id);
        $this->assertSame(0, (int) $fresh->is_demo_agent);
        $this->assertNotEquals(99, (int) $fresh->affiliate_revenue_share);
        $this->assertNull($fresh->inviter);
    }

    public function test_registration_ignores_privileged_input_but_sets_affiliate_defaults(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'P', 'email' => 'reg@example.test', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
            'phone' => '+15550100', 'term_a' => 1, 'agreement' => 1,
            'role_id' => 0, 'is_admin' => 1, 'affiliate_cpa' => 5000,
        ])->assertOk();

        $user = User::where('email', 'reg@example.test')->first();
        $this->assertNotEquals(0, (int) $user->role_id);
        $this->assertEquals(10, (float) $user->affiliate_cpa);      // from settings, not the request
        $this->assertSame(20, (int) $user->affiliate_revenue_share);
    }
}
