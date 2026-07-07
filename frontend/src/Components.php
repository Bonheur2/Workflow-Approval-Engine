<?php

/**
 * Reusable render "components" shared across pages.
 *
 * This is a plain-PHP app (no templating engine, no JS), so a "component"
 * here is just a function that echoes a chunk of HTML given some data.
 * Every page composes its view out of these instead of repeating markup -
 * in particular, the step-definition block and the key/value data-row
 * builder used to be duplicated across multiple pages; they now each
 * live in exactly one place.
 */

/** Sets a flash success/error message based on an ApiClient result - the standard "did the action work" pattern used after every form POST. */
function flash_result(array $result, string $successMessage, int $successStatus = 200): void
{
    if ($result['status'] === $successStatus) {
        flash_success($successMessage);
    } else {
        flash_error($result['data']['message'] ?? 'Action failed.');
    }
}

/** A colored status pill. Optionally pass $label to display different text than the class-determining $value (e.g. render_badge('inactive', 'revoked')). */
function render_badge(string $value, ?string $label = null): string
{
    $known = ['pending', 'approved', 'rejected', 'returned', 'active', 'inactive'];
    $class = in_array($value, $known, true) ? $value : 'inactive';
    return '<span class="badge badge-' . e($class) . '">' . e($label ?? $value) . '</span>';
}

/**
 * Renders one full "step definition" form block: name, approver
 * role/specific-person toggle, approval type, and up to 3 optional
 * conditions. Used identically by workflow_new.php (creating a workflow)
 * and workflow_edit_steps.php (replacing a workflow's steps) - this is
 * the single source of truth for that markup.
 *
 * @param int $index 1-based step number, used to namespace field names (step_name_1, step_name_2, ...)
 * @param array|null $existing Prefill values from an existing step (when editing), or null (when creating fresh)
 * @param array $approverUsers List of user records eligible to be a "specific person" approver
 * @param array $post The current $_POST, so a failed submission re-renders with what the admin typed
 */
function render_step_fields(int $index, ?array $existing, array $approverUsers, array $post): void
{
    $name = $post["step_name_$index"] ?? ($existing['name'] ?? '');
    $mode = $post["approver_mode_$index"] ?? (($existing['approver_user_id'] ?? null) ? 'user' : 'role');
    $role = $post["approver_role_$index"] ?? ($existing['approver_role'] ?? 'approver');
    $selectedUserId = $post["approver_user_$index"] ?? ($existing['approver_user_id'] ?? null);
    $type = $post["approval_type_$index"] ?? ($existing['approval_type'] ?? 'single');
    $existingConditions = $existing['conditions'] ?? [];
    ?>
    <div class="card step-block">
      <h2>Step <?= $index ?></h2>
      <label>Step name</label>
      <input type="text" name="step_name_<?= $index ?>" required value="<?= e($name) ?>" placeholder="e.g. Finance Review">

      <div class="row">
        <div>
          <label>Who approves?</label>
          <select name="approver_mode_<?= $index ?>">
            <option value="role" <?= $mode === 'role' ? 'selected' : '' ?>>Anyone with a role</option>
            <option value="user" <?= $mode === 'user' ? 'selected' : '' ?>>A specific person</option>
          </select>
        </div>
        <div>
          <label>Role (if "Anyone with a role")</label>
          <select name="approver_role_<?= $index ?>">
            <option value="approver" <?= $role === 'approver' ? 'selected' : '' ?>>approver</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>admin</option>
          </select>
        </div>
        <div>
          <label>Specific person (if selected above)</label>
          <select name="approver_user_<?= $index ?>">
            <option value="">-- choose --</option>
            <?php foreach ($approverUsers as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= (string) $selectedUserId === (string) $u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?> (<?= e($u['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label>Approval type</label>
      <select name="approval_type_<?= $index ?>">
        <option value="single" <?= $type === 'single' ? 'selected' : '' ?>>Single - first response wins</option>
        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All - every assigned approver must approve (parallel)</option>
      </select>

      <h3>Optional conditions (this step only applies if ALL of these match; leave blank to always apply)</h3>
      <?php for ($c = 1; $c <= 3; $c++):
          $condField = $post["cond_field_{$index}_{$c}"] ?? ($existingConditions[$c - 1]['field'] ?? '');
          $condOp = $post["cond_op_{$index}_{$c}"] ?? ($existingConditions[$c - 1]['operator'] ?? '=');
          $condValue = $post["cond_value_{$index}_{$c}"] ?? (isset($existingConditions[$c - 1]['value']) ? (string) $existingConditions[$c - 1]['value'] : '');
      ?>
        <div class="row" style="margin-bottom:6px;">
          <div><input type="text" name="cond_field_<?= $index ?>_<?= $c ?>" placeholder="field, e.g. amount" value="<?= e($condField) ?>"></div>
          <div>
            <select name="cond_op_<?= $index ?>_<?= $c ?>">
              <?php foreach (['=' => '= (equals)', '!=' => '!= (not equals)', '>' => '&gt;', '>=' => '&gt;=', '<' => '&lt;', '<=' => '&lt;=', 'contains' => 'contains'] as $op => $label): ?>
                <option value="<?= e($op) ?>" <?= $condOp === $op ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><input type="text" name="cond_value_<?= $index ?>_<?= $c ?>" placeholder="value, e.g. 10000" value="<?= e($condValue) ?>"></div>
        </div>
      <?php endfor; ?>
    </div>
    <?php
}

/**
 * Reads $count numbered step blocks (as produced by render_step_fields's
 * field names) out of $post and returns the array shape the backend API
 * expects for POST /workflows or PUT /workflows/{id}/steps.
 */
function collect_steps_from_post(array $post, int $count): array
{
    $steps = [];
    for ($i = 1; $i <= $count; $i++) {
        $approverMode = $post["approver_mode_$i"] ?? 'role';
        $conditions = [];
        for ($c = 1; $c <= 3; $c++) {
            $field = trim($post["cond_field_{$i}_{$c}"] ?? '');
            $value = trim($post["cond_value_{$i}_{$c}"] ?? '');
            if ($field !== '' && $value !== '') {
                $conditions[] = [
                    'field' => $field,
                    'operator' => $post["cond_op_{$i}_{$c}"] ?? '=',
                    'value' => is_numeric($value) ? $value + 0 : $value,
                ];
            }
        }
        $steps[] = [
            'step_order' => $i,
            'name' => trim($post["step_name_$i"] ?? "Step $i"),
            'approver_role' => $approverMode === 'role' ? ($post["approver_role_$i"] ?? null) : null,
            'approver_user_id' => $approverMode === 'user' ? (int) ($post["approver_user_$i"] ?? 0) : null,
            'approval_type' => $post["approval_type_$i"] ?? 'single',
            'conditions' => $conditions,
        ];
    }
    return $steps;
}

/**
 * Renders $rowCount blank/prefilled "field name / value" row pairs, used
 * by both the "submit a request" form and the "resubmit a returned
 * request" form to build the request's free-form data payload.
 *
 * @param array $prefill Associative array of existing data to prefill (e.g. when resubmitting)
 * @param array $post The current $_POST, so a failed submission doesn't lose what was typed
 */
function render_kv_rows(int $rowCount, array $prefill, array $post): void
{
    $prefillKeys = array_keys($prefill);
    for ($i = 1; $i <= $rowCount; $i++) {
        $key = $post["field_key_$i"] ?? ($prefillKeys[$i - 1] ?? '');
        $rawValue = $key !== '' && array_key_exists($key, $prefill) ? $prefill[$key] : '';
        $value = $post["field_value_$i"] ?? (is_scalar($rawValue) ? (string) $rawValue : '');
        ?>
        <div class="row" style="margin-bottom:6px;">
          <div><input type="text" name="field_key_<?= $i ?>" placeholder="field name, e.g. amount" value="<?= e($key) ?>"></div>
          <div><input type="text" name="field_value_<?= $i ?>" placeholder="value, e.g. 12000" value="<?= e($value) ?>"></div>
        </div>
        <?php
    }
}

/** Reads back the field_key_N / field_value_N rows produced by render_kv_rows() into an assoc array, casting numeric-looking values to numbers. */
function collect_kv_from_post(array $post, int $rowCount): array
{
    $data = [];
    for ($i = 1; $i <= $rowCount; $i++) {
        $key = trim($post["field_key_$i"] ?? '');
        $value = trim($post["field_value_$i"] ?? '');
        if ($key !== '' && $value !== '') {
            $data[$key] = is_numeric($value) ? $value + 0 : $value;
        }
    }
    return $data;
}

/** A table of requests (id, workflow, status, step, submitted date, view link). Used by requests.php and approvals.php. */
function render_requests_table(array $requests, bool $showViewLink = true): void
{
    if (empty($requests)) {
        echo '<p class="empty">No requests found.</p>';
        return;
    }
    ?>
    <table>
      <tr><th>ID</th><th>Workflow</th><th>Status</th><th>Current step</th><th>Submitted</th><?php if ($showViewLink): ?><th></th><?php endif; ?></tr>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td>#<?= (int) $r['id'] ?></td>
        <td>#<?= (int) $r['workflow_id'] ?></td>
        <td><?= render_badge($r['status']) ?></td>
        <td><?= $r['current_step_order'] !== null ? (int) $r['current_step_order'] : '-' ?></td>
        <td class="small"><?= e($r['created_at']) ?></td>
        <?php if ($showViewLink): ?>
          <td><a href="request_show.php?id=<?= (int) $r['id'] ?>" class="btn btn-secondary btn-sm">View</a></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php
}

/** A table of workflows (name, status, version, view/submit links). Used by the dashboard and workflows.php. */
function render_workflows_table(array $workflows, bool $showDescription = false): void
{
    if (empty($workflows)) {
        echo '<p class="empty">No workflows available.</p>';
        return;
    }
    ?>
    <table>
      <tr>
        <th>Name</th>
        <?php if ($showDescription): ?><th>Description</th><?php endif; ?>
        <th>Status</th><th>Version</th><th></th>
      </tr>
      <?php foreach ($workflows as $w): ?>
      <tr>
        <td><?= e($w['name']) ?></td>
        <?php if ($showDescription): ?><td class="muted small"><?= e($w['description']) ?></td><?php endif; ?>
        <td><?= render_badge($w['status']) ?></td>
        <td><?= (int) $w['version'] ?></td>
        <td>
          <a href="workflow_show.php?id=<?= (int) $w['id'] ?>" class="btn btn-secondary btn-sm">View</a>
          <?php if ($w['status'] === 'active'): ?>
            <a href="request_new.php?workflow_id=<?= (int) $w['id'] ?>" class="btn btn-primary btn-sm">Submit request</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php
}

/** The approval-history table on a request's detail page. */
function render_approvals_table(array $approvals): void
{
    if (empty($approvals)) {
        echo '<p class="empty">No approval activity yet.</p>';
        return;
    }
    ?>
    <table>
      <tr><th>Step</th><th>Approver</th><th>Status</th><th>Acted by</th><th>Comments</th><th>When</th></tr>
      <?php foreach ($approvals as $a): ?>
        <tr>
          <td><?= (int) $a['step_order'] ?></td>
          <td>User #<?= (int) $a['approver_id'] ?></td>
          <td><?= render_badge($a['status'] === 'skipped' ? 'inactive' : $a['status'], $a['status']) ?></td>
          <td><?= $a['acted_by'] ? 'User #' . (int) $a['acted_by'] . ($a['acted_by'] != $a['approver_id'] ? ' (delegate)' : '') : '-' ?></td>
          <td class="small"><?= e($a['comments']) ?></td>
          <td class="small"><?= e($a['acted_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php
}

/** The audit-trail list on a request's detail page. */
function render_audit_trail(array $entries): void
{
    if (empty($entries)) {
        echo '<p class="empty">No audit history.</p>';
        return;
    }
    ?>
    <ul class="list-plain">
      <?php foreach ($entries as $entry): ?>
        <li>
          <strong><?= e($entry['action']) ?></strong> by User #<?= (int) $entry['user_id'] ?>
          <span class="small muted">- <?= e($entry['created_at']) ?></span><br>
          <span class="small muted"><?= e($entry['previous_status']) ?> &rarr; <?= e($entry['new_status']) ?></span>
          <?php if ($entry['comments']): ?><br><span class="small"><?= e($entry['comments']) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php
}
