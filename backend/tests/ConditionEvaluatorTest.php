<?php

namespace Tests;

use App\Services\ConditionEvaluator;

class ConditionEvaluatorTest extends TestCase
{
    public function testEmptyConditionsAlwaysPass(): void
    {
        $this->assertTrue(ConditionEvaluator::evaluate([], ['amount' => 5]));
    }

    public function testGreaterThanNumeric(): void
    {
        $conditions = [['field' => 'amount', 'operator' => '>', 'value' => 10000]];
        $this->assertTrue(ConditionEvaluator::evaluate($conditions, ['amount' => 15000]));
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['amount' => 5000]));
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['amount' => 10000]), 'boundary should not pass for strict >');
    }

    public function testEqualsStringCaseInsensitive(): void
    {
        $conditions = [['field' => 'department', 'operator' => '=', 'value' => 'Finance']];
        $this->assertTrue(ConditionEvaluator::evaluate($conditions, ['department' => 'finance']));
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['department' => 'Legal']));
    }

    public function testMultipleConditionsAreAndCombined(): void
    {
        $conditions = [
            ['field' => 'amount', 'operator' => '>', 'value' => 10000],
            ['field' => 'department', 'operator' => '=', 'value' => 'Finance'],
        ];
        $this->assertTrue(ConditionEvaluator::evaluate($conditions, ['amount' => 20000, 'department' => 'Finance']));
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['amount' => 20000, 'department' => 'Legal']));
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['amount' => 5000, 'department' => 'Finance']));
    }

    public function testMissingFieldFailsClosed(): void
    {
        $conditions = [['field' => 'country', 'operator' => '=', 'value' => 'Rwanda']];
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['amount' => 100]));
    }

    public function testInOperator(): void
    {
        $conditions = [['field' => 'level', 'operator' => 'in', 'value' => ['Manager', 'Director']]];
        $this->assertTrue(ConditionEvaluator::evaluate($conditions, ['level' => 'Manager']));
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['level' => 'Staff']));
    }

    public function testContainsOperator(): void
    {
        $conditions = [['field' => 'item', 'operator' => 'contains', 'value' => 'server']];
        $this->assertTrue(ConditionEvaluator::evaluate($conditions, ['item' => 'New Server Rack']));
        $this->assertFalse(ConditionEvaluator::evaluate($conditions, ['item' => 'Office Chair']));
    }
}
