<?php

namespace Tests;

use App\Core\Validator;

class ValidatorTest extends TestCase
{
    public function testRequiredFailsOnMissingField(): void
    {
        $v = Validator::make([], ['name' => 'required']);
        $this->assertTrue($v->fails());
    }

    public function testEmailRuleRejectsInvalidEmail(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
        $this->assertTrue($v->fails());
    }

    public function testValidPayloadPasses(): void
    {
        $v = Validator::make(
            ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'approver'],
            ['name' => 'required|string|max:255', 'email' => 'required|email', 'role' => 'required|in:admin,approver,requester']
        );
        $this->assertFalse($v->fails());
    }

    public function testInRuleRejectsUnlistedValue(): void
    {
        $v = Validator::make(['role' => 'superuser'], ['role' => 'in:admin,approver,requester']);
        $this->assertTrue($v->fails());
    }
}
