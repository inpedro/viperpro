<?php

namespace Tests\Feature\Security;

use App\Core\Security\SignedToken;

class GameSessionTokenTest extends SecurityTestCase
{
    public function test_round_trip(): void
    {
        $claims = \Helper::DecToken(\Helper::MakeToken(['id' => 7, 'game' => 'fortune']));
        $this->assertTrue($claims['status']);
        $this->assertSame(7, $claims['id']);
        $this->assertSame('fortune', $claims['game']);
    }

    public function test_forged_or_tampered_tokens_are_rejected(): void
    {
        $token = \Helper::MakeToken(['id' => 7, 'game' => 'fortune']);
        [$payload, $sig] = explode('.', $token);

        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $claims['id'] = 1; // try to become user 1
        $forged = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=').'.'.$sig;

        $this->assertFalse(\Helper::DecToken($forged)['status']);
        $this->assertFalse(\Helper::DecToken($payload.'.AAAA')['status']);
        $this->assertFalse(\Helper::DecToken('garbage')['status']);
    }

    public function test_legacy_substitution_cipher_tokens_are_rejected(): void
    {
        $legacy = \Helper::Encode('{"status": true,"id": "1","game": "fortune"}');
        $this->assertFalse(\Helper::DecToken($legacy)['status']);
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $token = SignedToken::issue(['id' => 7], 1);
        $this->travel(5)->seconds();
        $this->assertFalse(\Helper::DecToken($token)['status']);
    }

    public function test_game_endpoint_rejects_forged_token(): void
    {
        $res = $this->postJson('/api/vgames/not-a-token/spin');
        $this->assertSame('', (string) $res->getContent()); // no session, no spin
    }
}
