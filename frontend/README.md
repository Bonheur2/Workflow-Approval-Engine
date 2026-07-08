# Workflow & Approval Engine - Frontend

A **pure PHP**, server-rendered web UI for the [backend](../backend) API.
No framework, no Composer dependencies, no build step - plain PHP pages
using native sessions to hold your login token, calling the backend over
cURL. A handful of small, dependency-free vanilla-JS snippets (no library,
no bundler) add progressive-enhancement touches - see "Design notes" below.

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
    header.php / footer.php  Shared page chrome: top bar (brand, notification bell with
                              unread count, user menu), left sidebar nav, flash banner
  public/                    One file per route (login.php, workflows.php, request_show.php, ...)
  public/style.css           Every page's styling - CSS variables for the palette, no preprocessor
```

Every page is thin: it fetches data from the API, then hands it to a
function in `src/Components.php` to render. Nothing about how a table or
a form looks is duplicated across pages. Concretely:

| Component (in `Components.php`) | Used by | Renders |
|---|---|---|
| `render_step_fields()` + `collect_steps_from_post()` | `workflow_new.php`, `workflow_edit_steps.php` | One workflow-step definition block (name, approver role/specific person, approval type, up to 3 conditions), and the matching form-to-API-payload parser. This was the biggest duplication before the refactor - both pages needed the exact same ~90-line block, one for a blank step and one prefilled from an existing step. |
| `render_known_fields()` + `collect_known_fields_from_post()` | `request_new.php` | Clearly-labeled value inputs for exactly the fields a workflow's steps reference in their conditions (e.g. "Days", "Amount") - the field name travels in a hidden input, so requesters fill in values only instead of guessing field names |
| `render_kv_rows()` + `collect_kv_from_post()` | `request_show.php` (resubmit) | Free-form, addable/removable field-name/value row pairs (client-side "+ Add field" / remove, see Design notes) for data not covered by a workflow's known fields |
| `render_requests_table()` | `requests.php`, `approvals.php` | The id/workflow/status/step/date table, with a centered empty-state + call-to-action when there's nothing to show |
| `render_workflows_table()` | `index.php`, `workflows.php` | The workflow list table (with an optional description column), same empty-state treatment |
| `render_approvals_table()` | `request_show.php` | The per-step approval table (approver/actor shown by name, not id) |
| `render_audit_trail()` | `request_show.php` | The audit-log table (action, user, status change, comments, when) |
| `render_empty_state()` | every list/table component | The shared centered "nothing here yet" placeholder, optionally with a button |
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
| `request_new.php` | everyone | Submit a request against any active workflow - value inputs are labeled straight from the workflow's own step conditions (e.g. "Amount", "Days"), so requesters never have to guess a field name |
| `requests.php` / `request_show.php` | everyone | List + detail view: request data, approval history, full audit trail, and (if you're an eligible approver) Approve/Reject/Return buttons, or (if you're the owner of a returned request) an edit-and-resubmit form |
| `approvals.php` | approver/admin | Your personal "awaiting my action" queue |
| `delegations.php` | approver/admin | Delegate your approval responsibilities to any other active approver/admin for a date range, or revoke one |
| `users.php` | admin | Create users, change roles, activate/deactivate accounts |
| `notifications.php` | everyone | Your notification inbox as a table, filterable to unread, each row linking straight to its request and offering mark-as-read |

## Design notes

- **Server-first, JS only as progressive enhancement.** Every action still
  works as a plain HTML form `POST`/`GET` with zero client-side state; the
  handful of vanilla-JS snippets (no library, no bundler, no build step)
  only make already-working interactions nicer:
  - closing the user menu when you click outside it (`templates/footer.php`),
  - a password show/hide toggle on the login/register forms,
  - auto-filling `request_new.php`'s field labels/values from the selected
    workflow's own step conditions (the field names are embedded as JSON
    server-side; the browser just relabels inputs, no separate API call),
  - "+ Add field" / remove buttons on the resubmit form's free-form rows
    (`request_show.php`, via `render_kv_rows()`).

  The "how many steps" workflow-editor flow is still a genuine two-step
  wizard (pick a count via a plain GET form, then fill in that many step
  blocks) rather than a JS "add row" button.
- **No session-side business logic.** This app never talks to a
  database directly - every read and write goes through the backend's
  REST API. The frontend's only job is rendering HTML and translating
  form submissions into API calls; all validation, RBAC, and workflow
  logic lives in the backend, where the challenge asks for it.
- **Auth**: the JWT returned by `POST /auth/login` is stored in the PHP
  session (server-side, not a cookie the browser can read/tamper with)
  and attached as a `Bearer` token on every subsequent API call.
- **Data entry** never exposes raw JSON, since the backend's `data` and
  `conditions` fields are intentionally free-form. Workflow conditions
  still use simple field/operator/value rows; a request's `data` is now
  auto-labeled from its workflow's own condition fields where possible
  (`render_known_fields()`), falling back to plain key/value rows
  (`render_kv_rows()`) wherever a workflow has no matching field. Either
  way, numeric-looking values are cast to numbers automatically, matching
  what `ConditionEvaluator` expects for `>`/`<` comparisons.

