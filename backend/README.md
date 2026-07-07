# Dynamic Workflow & Approval Engine

A configurable Workflow & Approval Engine, built in **pure PHP** - no
framework, no Composer dependencies. Organizations define workflows and
approval steps entirely through the API; nothing about a specific process
(Leave Request, Purchase Request, Expense Claim, ...) is hardcoded in the
application code.

## Why zero dependencies

The challenge asks for "your preferred PHP framework" but this submission
deliberately uses none, to make "runs successfully following the README"
as close to guaranteed as possible: there's no Composer/Packagist
resolution step that can fail in a restricted network, no framework
version drift, nothing to `composer install`. Everything - routing,
JWT auth, validation, the ORM-lite data layer - is ~1,500 lines of
plain PHP you can read top to bottom in `src/`.

## Requirements

- PHP 8.1+ with the `pdo_sqlite` extension (bundled with most PHP
  installs) - or `pdo_mysql` if you prefer MySQL (see below).
- No Composer, no Node, no external services required.

## Quick start (SQLite - zero config)

```bash
cp .env.example .env
php database/migrate.php     # creates storage/database.sqlite + schema
php database/seed.php        # creates an admin, 3 approvers, 1 requester, and a demo workflow
php -S localhost:8000 public/index.php
```

The app is now running at `http://localhost:8000`. Try:
```bash
curl http://localhost:8000/api/health

curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@itec.rw","password":"AdminPass123"}'
```
(Seeded accounts and passwords are printed by `database/seed.php` and
also listed at the bottom of this file.)

Full endpoint reference: [`docs/API.md`](docs/API.md).
Schema reference: [`docs/ER_DIAGRAM.md`](docs/ER_DIAGRAM.md).

## Running the tests

```bash
php tests/run.php
```

No PHPUnit/Composer dependency: `tests/run.php` is a ~60-line
dependency-free runner (`tests/TestCase.php`) that discovers and executes
every `Tests\*Test` class, using a fresh **in-memory** SQLite database per
test method for full isolation. It currently covers:
- `ConditionEvaluatorTest` - the conditional-routing operators (`>`, `=`, `in`, `contains`, AND-combination, fail-closed on missing fields).
- `JWTTest` - encode/decode round trip, expiry, tampering, malformed tokens.
- `ValidatorTest` - the request-validation helper.
- `WorkflowEngineTest` - the full engine: conditional step skipping, parallel (`all`) approval, rejection, return-for-modification + resubmit, delegation, unauthorized-actor rejection, inactive-workflow guard, and the workflow-edit-doesn't-affect-in-flight-requests guarantee.

## Switching to MySQL

```bash
# in .env
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_DATABASE=workflow_engine
DB_USERNAME=root
DB_PASSWORD=yourpassword
```
```bash
mysql -u root -p -e "CREATE DATABASE workflow_engine"
php database/migrate.php
php database/seed.php
```

## Switching to PostgreSQL

```bash
# in .env
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=workflow_engine
DB_USERNAME=postgres
DB_PASSWORD=yourpassword
DB_SSLMODE=prefer   # use "require" for most managed hosts (e.g. Render)
```
```bash
createdb workflow_engine
php database/migrate.php
php database/seed.php
```

On Render specifically: create a "PostgreSQL" instance from the dashboard,
then copy its Host/Port/Database/Username/Password (from that instance's
"Connect" tab) into the **backend web service's** environment variables
(`DB_DRIVER=pgsql`, `DB_SSLMODE=require`, plus the connection fields above)
and redeploy. Unlike the default sqlite setup, this survives redeploys -
sqlite's file lives inside the web service's own (ephemeral) container disk.

## Architecture

```
public/index.php         Front controller: bootstraps env, defines every route + middleware
src/Core/                 Router, Request/Response, PDO wrapper, JWT, Validator, Logger, Env, Autoloader
src/Middleware/           AuthMiddleware (JWT verification), RoleMiddleware (RBAC)
src/Models/                Thin data-access layer (one class per table, parameterized SQL only)
src/Services/
  ConditionEvaluator.php   Evaluates a step's routing conditions against a request's data
  WorkflowEngine.php       The engine: submit / approve / reject / return / resubmit,
                            step activation, parallel-approval completion, delegation resolution
  AuditService.php         Thin wrapper around the append-only audit log
  NotificationService.php  In-app notification generation for every required event
src/Controllers/           One controller per resource, thin - they validate input and delegate
                            all business logic to WorkflowEngine / models
database/migrations/       SQL schema (sqlite/ and mysql/ variants)
database/seed.php          Demo users + a "Purchase Request" workflow with conditional + parallel steps
tests/                     Dependency-free test runner + test suite
docs/                      API.md, ER_DIAGRAM.md
```

**Design principle:** controllers never contain workflow logic. Every
decision about *what happens next* to a request lives in
`WorkflowEngine` and reads entirely from data (the step definitions +
the request's JSON payload), which is what makes the engine reusable
across arbitrary business processes without code changes.

### How the dynamic engine actually decides things

1. **Submission** freezes a snapshot of the workflow's current steps onto
   the request (`requests.workflow_snapshot`) and evaluates from step 1.
2. For each step, in order, `ConditionEvaluator::evaluate()` checks its
   JSON `conditions` (e.g. `amount > 10000`) against the request's `data`.
   The first step whose conditions pass (or which has no conditions) is
   **activated**: an approval row is created for every eligible approver
   (either everyone with `approver_role`, or one specific
   `approver_user_id`), and each gets a notification.
3. An approve/reject/return action resolves which `request_approvals`
   row the acting user is entitled to touch (their own assignment, or an
   active delegation from the assignee).
4. **Single**-type steps complete on the first `approved` row (remaining
   pending rows are marked `skipped`). **All**-type steps (parallel
   approval) only complete once every assigned approver has approved.
5. On step completion, the engine walks forward looking for the next
   *reachable* step and activates it; if none remain, the request is
   marked `approved`. A `reject` at any point immediately closes the
   request as `rejected`. A `return` sends it back to the requester as
   `returned`, who can edit and resubmit (restarting evaluation from
   step 1 against the new data).

## Assumptions

- **Roles are exactly** `admin` (System Administrator), `approver`,
  `requester`, matching the challenge's three named roles. A user's role
  is a single fixed value, not a per-workflow assignment.
- **Public registration always creates a `requester`.** Admins provision
  approvers/admins via `POST /users` or `PATCH /users/{id}/role`, so
  nobody can self-elevate through the public endpoint.
- **A step's conditions are AND-combined**, evaluated with a fail-closed
  policy: a condition referencing a field the request's `data` doesn't
  supply evaluates to false rather than silently passing.
- **`approval_type: "all"`** means every user eligible for that step (all
  users with the given role, at the time the step activates) must
  approve; **any single rejection at that step rejects the whole
  request** rather than waiting out the others.
- **`approval_type: "single"`** means the step completes on the first
  approval; other pending approvers for that step are marked `skipped`
  (visible in `request_approvals`, so it's still fully auditable).
- **A workflow is editable at any time**; requests already in progress
  are never affected because each one carries its own frozen step
  snapshot from submission time. This directly satisfies "*A workflow
  should remain editable without affecting requests that are already in
  progress.*"
- **"Return for modification"** sends the request back to `returned`;
  the requester edits the `data` payload and calls `resubmit`, which
  restarts step evaluation from the beginning against the new data
  (rather than resuming mid-flow), since the new data may change which
  steps are even reachable.
- **Delegation** is time-boxed (`start_date`/`end_date`, inclusive) and
  only to users with role `approver` or `admin`. A delegate's action is
  recorded with `acted_by` set to the delegate while `approver_id` keeps
  the original assignee, so delegated approvals remain fully traceable.
- **Notifications** are an in-app inbox (`GET /notifications`), not
  email/SMS - the challenge explicitly leaves the delivery mechanism to
  our discretion. Wiring in an email/SMS provider would mean adding a
  single adapter call inside `NotificationService`; the event triggers
  and audit trail already exist.
- **JWT over sessions:** since this is a stateless REST API (the
  spec calls for "RESTful APIs" specifically), auth is a signed,
  short-lived (default 1h) HS256 bearer token rather than cookie/session
  state.

## Trade-offs and limitations

- **No framework** means no request-scoped DI container, no built-in ORM,
  no auto-generated OpenAPI spec - `docs/API.md` is hand-written instead.
  This is a deliberate trade against "runs successfully with zero
  install friction."
- **JWT has no revocation list.** Deactivating a user (`is_active=0`)
  blocks new logins, but a token issued before deactivation remains
  valid until it expires (default TTL: 1 hour). A production system
  would add a token blacklist or move to shorter TTLs + refresh tokens.
- **The custom test runner is not PHPUnit.** It's intentionally minimal
  (no mocking, no data providers) to stay dependency-free; the coverage
  is aimed at the engine's decision logic (the highest-risk code) rather
  than exhaustive controller-level coverage.
- **SQLite is the default** for a friction-free `README`-only setup; the
  MySQL migration is provided and switching is a two-line `.env` change,
  but SQLite's concurrency model is weaker than MySQL's under heavy
  concurrent writes - fine for evaluation, not for production scale.
- **Delegation resolution scans all pending requests** in
  `GET /api/approvals` rather than using a dedicated indexed query; for
  a demo dataset this is instant, but a high-volume deployment would
  want a materialized "effective approver" view.

## Seeded demo accounts

Created by `php database/seed.php`:

| Email | Password | Role |
|---|---|---|
| admin@itec.rw | AdminPass123 | admin |
| finance@itec.rw | ApproverPass123 | approver |
| legal@itec.rw | ApproverPass123 | approver |
| ceo@itec.rw | ApproverPass123 | approver |
| requester@itec.rw | RequesterPass123 | requester |

Plus a demo **"Purchase Request"** workflow:
1. Finance Review (always required)
2. Legal Review (only if `amount > 10000`)
3. Executive Approval - **all** of Finance + Legal + CEO must approve (only if `amount > 50000`)

Try submitting requests with different `amount` values to see the
conditional routing and parallel-approval logic in action (see
`docs/API.md` for the exact request bodies).
