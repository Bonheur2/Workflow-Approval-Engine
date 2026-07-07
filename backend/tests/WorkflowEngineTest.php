<?php

namespace Tests;

use App\Models\Delegation;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRequest;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngine;

class WorkflowEngineTest extends TestCase
{
    private array $users = [];
    private int $workflowId;

    private function seed(): void
    {
        $this->freshDatabase();

        $adminId = User::create('Admin', 'admin@test.local', 'password123', 'admin');
        $financeId = User::create('Finance', 'finance@test.local', 'password123', 'approver');
        $legalId = User::create('Legal', 'legal@test.local', 'password123', 'approver');
        $ceoId = User::create('Ceo', 'ceo@test.local', 'password123', 'approver');
        $requesterId = User::create('Requester', 'req@test.local', 'password123', 'requester');

        $this->users = compact('adminId', 'financeId', 'legalId', 'ceoId', 'requesterId');

        $this->workflowId = Workflow::create('Purchase Request', 'Test workflow', $adminId);
        WorkflowStep::create($this->workflowId, 1, 'Finance Review', 'approver', null, 'single', []);
        WorkflowStep::create($this->workflowId, 2, 'Legal Review', 'approver', null, 'single', [
            ['field' => 'amount', 'operator' => '>', 'value' => 10000],
        ]);
        WorkflowStep::create($this->workflowId, 3, 'Executive Approval', 'approver', null, 'all', [
            ['field' => 'amount', 'operator' => '>', 'value' => 50000],
        ]);
    }

    public function testLowValueRequestSkipsConditionalSteps(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'requesterId' => $requesterId] = $this->users;

        $request = WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 500]);
        $this->assertEquals(1, $request['current_step_order']);

        $request = WorkflowEngine::approve($request['id'], $financeId, 'ok');
        $this->assertEquals('approved', $request['status'], 'Low-value request should finalize after just the finance step.');
    }

    public function testHighValueRequestRequiresAllThreeSteps(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'legalId' => $legalId, 'ceoId' => $ceoId, 'requesterId' => $requesterId] = $this->users;

        $request = WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 60000]);
        $this->assertEquals(1, $request['current_step_order']);

        $request = WorkflowEngine::approve($request['id'], $financeId, null);
        $this->assertEquals(2, $request['current_step_order'], 'Amount > 10000 should route into legal review.');

        $request = WorkflowEngine::approve($request['id'], $legalId, null);
        $this->assertEquals(3, $request['current_step_order'], 'Amount > 50000 should route into executive approval.');
        $this->assertEquals('pending', $request['status']);

        // Parallel step: must NOT complete after only one of three approvers.
        $request = WorkflowEngine::approve($request['id'], $financeId, null);
        $this->assertEquals('pending', $request['status'], 'All-type step must wait for every approver.');
        $this->assertEquals(3, $request['current_step_order']);

        $request = WorkflowEngine::approve($request['id'], $legalId, null);
        $this->assertEquals('pending', $request['status'], 'Still missing the CEO approval.');

        $request = WorkflowEngine::approve($request['id'], $ceoId, null);
        $this->assertEquals('approved', $request['status'], 'All three approvers done -> request approved.');
    }

    public function testRejectionStopsTheRequestImmediately(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'requesterId' => $requesterId] = $this->users;

        $request = WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 200]);
        $request = WorkflowEngine::reject($request['id'], $financeId, 'not justified');
        $this->assertEquals('rejected', $request['status']);
    }

    public function testReturnForModificationThenResubmit(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'requesterId' => $requesterId] = $this->users;

        $request = WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 200]);
        $request = WorkflowEngine::returnForModification($request['id'], $financeId, 'add justification');
        $this->assertEquals('returned', $request['status']);

        $request = WorkflowEngine::resubmit($request['id'], $requesterId, ['amount' => 200, 'justification' => 'urgent']);
        $this->assertEquals('pending', $request['status']);
        $this->assertEquals(1, $request['current_step_order']);

        $request = WorkflowEngine::approve($request['id'], $financeId, null);
        $this->assertEquals('approved', $request['status']);
    }

    public function testOnlyOriginalRequesterCanResubmit(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'requesterId' => $requesterId, 'legalId' => $legalId] = $this->users;

        $request = WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 200]);
        $request = WorkflowEngine::returnForModification($request['id'], $financeId, 'fix this');

        $this->assertThrows(
            fn() => WorkflowEngine::resubmit($request['id'], $legalId, ['amount' => 200]),
            'A different user must not be able to resubmit someone else\'s request.'
        );
    }

    public function testDelegateCanActOnBehalfOfApprover(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'legalId' => $legalId, 'requesterId' => $requesterId] = $this->users;

        Delegation::create($financeId, $legalId, date('Y-m-d'), date('Y-m-d'));

        $request = WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 200]);
        // Legal acts on Finance's pending approval via the active delegation.
        $request = WorkflowEngine::approve($request['id'], $legalId, 'approving as delegate');
        $this->assertEquals('approved', $request['status']);
    }

    public function testUnauthorizedUserCannotActOnRequest(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'ceoId' => $ceoId, 'requesterId' => $requesterId] = $this->users;

        // Build a workflow whose one step is pinned to a *specific* approver
        // (finance), so a different approver (ceo) - who has no assignment
        // and no delegation - must be rejected.
        $adminId = $this->users['adminId'];
        $restrictedWorkflowId = Workflow::create('Restricted', 'test', $adminId);
        WorkflowStep::create($restrictedWorkflowId, 1, 'Finance Only', null, $financeId, 'single', []);

        $request = WorkflowEngine::submit($restrictedWorkflowId, $requesterId, ['amount' => 200]);
        $this->assertThrows(fn() => WorkflowEngine::approve($request['id'], $ceoId, null));
    }

    public function testInactiveWorkflowRejectsNewSubmissions(): void
    {
        $this->seed();
        ['requesterId' => $requesterId] = $this->users;

        Workflow::update($this->workflowId, ['status' => 'inactive']);
        $this->assertThrows(fn() => WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 100]));
    }

    public function testEditingWorkflowStepsDoesNotAffectInFlightRequest(): void
    {
        $this->seed();
        ['financeId' => $financeId, 'requesterId' => $requesterId] = $this->users;

        // Start a request against the 3-step workflow.
        $request = WorkflowEngine::submit($this->workflowId, $requesterId, ['amount' => 60000]);

        // Admin now edits the live workflow down to a single step.
        WorkflowStep::deleteAllForWorkflow($this->workflowId);
        WorkflowStep::create($this->workflowId, 1, 'Solo Review', 'approver', null, 'single', []);

        // The in-flight request should still honour its original 3-step
        // snapshot, not the freshly edited definition.
        $stored = WorkflowRequest::find($request['id']);
        $snapshot = json_decode($stored['workflow_snapshot'], true);
        $this->assertEquals(3, count($snapshot), 'In-flight request must keep its original snapshot.');
    }
}
