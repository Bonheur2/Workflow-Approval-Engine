# Workflow & Approval Engine - Frontend

A **pure PHP**, server-rendered web UI for the [backend](../backend) API.
No JavaScript, no framework, no Composer dependencies - just plain PHP
pages using native sessions to hold your login token, calling the
backend over cURL.

## Requirements

- PHP 8.1+ with the **`curl`** extension enabled (this is different from
  the backend, which only needs `pdo_sqlite`). Check with:
  ```bash
  php -m | grep curl
  ```
  If it's missing: on Windows, open your `php.ini` and uncomment
  `;extension=curl` (remove the leading `;`), the same way you enabled
  `pdo_sqlite` for the backend. On Ubuntu/Debian: `sudo apt install php-curl`.
- The backend API already running (see `../backend/README.md`).

## Setup

```bash
cp .env.example .env
```
By default `.env` points at `http://localhost:8000/api` - edit it if your
backend runs somewhere else.

## Run it

```bash
php -S localhost:8081 -t public
```

Open **http://localhost:8081/login.php** in your browser and log in with
one of the backend's seeded accounts (see `../backend/README.md`), for
example:
```
admin@itec.rw / AdminPass123
```

## Architecture: shared components, not copy-pasted pages

```
frontend/
  config.php              Points at the backend API base URL
  src/
    ApiClient.php           ~50-line cURL wrapper - every API call goes through this
    Session.php              Auth/session helpers (log_in, require_role, flash messages, e())
    Components.php           Reusable render functions - see below
    bootstrap.php             Included at the top of every page: loads the three files above
  templates/
    header.php / footer.php  Shared page chrome (nav, flash banner)
  public/                    One file per route (login.php, workflows.php, request_show.php, ...)
```

Every page is thin: it fetches data from the API, then hands it to a
function in `src/Components.php` to render. Nothing about how a table or
a form looks is duplicated across pages. Concretely:

| Component (in `Components.php`) | Used by | Renders |
|---|---|---|
| `render_step_fields()` + `collect_steps_from_post()` | `workflow_new.php`, `workflow_edit_steps.php` | One workflow-step definition block (name, approver role/specific person, approval type, up to 3 conditions), and the matching form-to-API-payload parser. This was the biggest duplication before the refactor - both pages needed the exact same ~90-line block, one for a blank step and one prefilled from an existing step. |
| `render_kv_rows()` + `collect_kv_from_post()` | `request_new.php`, `request_show.php` (resubmit) | The field-name/value row pairs used to build a request's free-form `data` payload, blank or prefilled from an existing request |
| `render_requests_table()` | `requests.php`, `approvals.php` | The id/workflow/status/step/date table |
| `render_workflows_table()` | `index.php`, `workflows.php` | The workflow list table (with an optional description column) |
| `render_approvals_table()` | `request_show.php` | The per-step approval history table |
| `render_audit_trail()` | `request_show.php` | The audit-log list |
| `render_badge()` | everywhere a status appears | A single consistent colored pill for pending/approved/rejected/returned/active/inactive, with an optional custom label (e.g. "revoked" instead of "inactive") |
| `flash_result()` | any action handler | The standard "did the API call succeed → flash success, else flash the error message" pattern |

If you need to change what a status pill looks like, or add a field to
every step-definition form, there is exactly **one function** to edit -
not N page files.

## What's included

| Page | Who can see it | What it does |
|---|---|---|
| `login.php` / `register.php` / `logout.php` | everyone | Auth (register always creates a `requester`) |
| `index.php` | everyone | Dashboard: request count, approval queue count, unread notifications, active workflows |
| `workflows.php` / `workflow_show.php` | everyone | Browse workflows and their step definitions |
| `workflow_new.php` | admin | Two-step wizard: pick how many steps, then define each one |
| `workflow_edit.php` / `workflow_edit_steps.php` | admin | Edit a workflow's name/description/status, or replace its entire step list (prefilled from the current definition) |
| `request_new.php` | everyone | Submit a request against any active workflow, entering data as simple field/value rows |
| `requests.php` / `request_show.php` | everyone | List + detail view: request data, approval history, full audit trail, and (if you're an eligible approver) Approve/Reject/Return buttons, or (if you're the owner of a returned request) an edit-and-resubmit form |
| `approvals.php` | approver/admin | Your personal "awaiting my action" queue |
| `delegations.php` | approver/admin | Delegate your approval responsibilities to another approver/admin for a date range, or revoke one |
| `users.php` | admin | Create users, change roles, activate/deactivate accounts |
| `notifications.php` | everyone | Your notification inbox, filterable to unread, with mark-as-read |

## Design notes

- **No JavaScript at all**, per request - every interaction (including
  role changes and the step-count wizard) is a plain HTML form `POST`/`GET`.
  The "how many steps" flow is a genuine two-step wizard (pick a count,
  then fill in that many step blocks) rather than a JS "add row" button.
- **No session-side business logic.** This app never talks to a
  database directly - every read and write goes through the backend's
  REST API. The frontend's only job is rendering HTML and translating
  form submissions into API calls; all validation, RBAC, and workflow
  logic lives in the backend, where the challenge asks for it.
- **Auth**: the JWT returned by `POST /auth/login` is stored in the PHP
  session (server-side, not a cookie the browser can read/tamper with)
  and attached as a `Bearer` token on every subsequent API call.
- **Data entry for requests/workflow conditions** uses simple key/value
  rows instead of a raw JSON textarea, since the backend's `data` and
  `conditions` fields are intentionally free-form - this keeps the UI
  usable for non-technical users while still round-tripping through the
  exact same JSON the API expects (numeric-looking values are cast to
  numbers automatically, matching what `ConditionEvaluator` expects for
  `>`/`<` comparisons).

