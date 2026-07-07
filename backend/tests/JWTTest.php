<?php

namespace Tests;

use App\Core\JWT;

class JWTTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $token = JWT::encode(['sub' => 42, 'role' => 'admin'], 3600);
        $payload = JWT::decode($token);
        $this->assertNotNull($payload);
        $this->assertEquals(42, $payload['sub']);
        $this->assertEquals('admin', $payload['role']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $token = JWT::encode(['sub' => 1], -10); // already expired
        $this->assertNull(JWT::decode($token));
    }

    public function testTamperedTokenIsRejected(): void
    {
        $token = JWT::encode(['sub' => 1, 'role' => 'requester'], 3600);
        $parts = explode('.', $token);
        // Flip the payload to claim admin without a valid signature.
        $forgedPayload = base64_encode(json_encode(['sub' => 1, 'role' => 'admin', 'exp' => time() + 3600]));
        $forged = $parts[0] . '.' . rtrim(strtr($forgedPayload, '+/', '-_'), '=') . '.' . $parts[2];
        $this->assertNull(JWT::decode($forged));
    }

    public function testMalformedTokenIsRejected(): void
    {
        $this->assertNull(JWT::decode('not-a-real-token'));
    }
}
