# Dynamic Workflow & Approval Engine

A configurable Workflow & Approval Engine, built entirely in **pure PHP** -
no framework, no Composer dependencies. Organizations define workflows and
their approval steps entirely through the API; nothing about a specific
business process (Leave Request, Purchase Request, Expense Claim, ...) is
hardcoded in the application code.

## At a glance

- **Dynamic, data-driven engine** - steps, approver assignment, conditional
  routing, and single-vs-parallel approval are all defined at runtime via
  the API, not hardcoded per workflow.
- **Conditional routing** - each step can carry AND-combined conditions
  (`amount > 10000`, `country in [...]`, ...) evaluated against the
  request's own data; unmatched steps are skipped automatically.
- **Parallel approval** - a step can require a single approval or every
  assigned approver's sign-off (`approval_type: "all"`).
- **Delegation** - approvers can hand off their pending approvals to
  another authorized user for a date range, fully traceable.
- **Full audit trail** - every state transition is logged and readable via
  the API and the web UI.
- **Zero install friction** - SQLite by default (nothing to install beyond
  PHP itself); MySQL and PostgreSQL are both fully supported for real
  deployments.
- **Two independently useful pieces**: a REST API (the actual deliverable)
  and an optional server-rendered PHP UI on top of it.

```
workflow-approval-engine/
  backend/     The REST API + workflow engine (the actual challenge deliverable)
  frontend/    A server-rendered PHP web UI that consumes the backend's API
```

## Which one do I need?

- **Grading the challenge itself?** Everything the challenge asks for -
  source code, migrations, API docs, schema, tests - lives in
  **`backend/`**. Start with [`backend/README.md`](backend/README.md).
- **Want to click through the system in a browser** instead of using
  curl/Postman? Run `backend/` first, then `frontend/` on a different
  port - see [`frontend/README.md`](frontend/README.md).

## Live deployment

The backend API is deployed and reachable right now - no local setup
required to try it:

```
https://workflow-backend-virm.onrender.com/api
```

```bash
curl https://workflow-backend-virm.onrender.com/api/health

curl -X POST https://workflow-backend-virm.onrender.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@itec.rw","password":"AdminPass123"}'
```

The Postman collection and OpenAPI spec (below) are both pre-configured
to point at this URL by default. It runs on Render's free tier, backed by
a managed PostgreSQL database, so the first request after a period of
inactivity may take a few seconds to wake up.

## Quick start (both pieces, from scratch)

```bash
# 1. Backend API
cd backend
cp .env.example .env
php database/migrate.php
php database/seed.php
php tests/run.php          # optional sanity check - expect 26/26 passing
php -S localhost:8000 public/index.php &

# 2. Frontend (in a second terminal)
cd ../frontend
cp .env.example .env       # already points at http://localhost:8000/api by default
php -S localhost:8081 -t public
```

Open **http://localhost:8081/login.php** and log in with a seeded account
(printed by `backend/database/seed.php`, e.g. `admin@itec.rw` / `AdminPass123`).

## API documentation

| Resource | Where |
|---|---|
| Human-readable endpoint reference | [`backend/docs/API.md`](backend/docs/API.md) |
| OpenAPI 3.0 spec | [`backend/docs/openapi.yaml`](backend/docs/openapi.yaml) - paste into [editor.swagger.io](https://editor.swagger.io) or open with any OpenAPI-aware IDE plugin for an interactive Swagger UI |
| Postman collection | [`backend/docs/postman_collection.json`](backend/docs/postman_collection.json) - import into Postman; running **Login** auto-saves the JWT into `{{token}}` for every other request |
| Schema / ER diagram | [`backend/docs/ER_DIAGRAM.md`](backend/docs/ER_DIAGRAM.md) |

## Why two separate PHP apps instead of one?

- **Separation of concerns matches the challenge's own ask**: requirement
  #10 explicitly asks for a RESTful API as the deliverable. Keeping the
  UI as a separate consumer of that API (rather than baking HTML output
  into the same codebase) means the API is a real, independently
  testable, independently deployable product - not an implementation
  detail of a web app.
- **The frontend can be deleted entirely** and the backend still fully
  satisfies every functional requirement in the challenge on its own,
  via curl/Postman/any HTTP client. The frontend is a convenience layer
  on top, not a dependency.
- **Different scaling/security postures.** The API can sit behind a
  gateway, rate limiter, or be called by a mobile app / another service
  tomorrow, independent of whatever renders HTML today.

See [`backend/README.md`](backend/README.md) for the engine's architecture,
assumptions, and trade-offs; see [`frontend/README.md`](frontend/README.md)
for how its pages are built from a small set of shared, reusable render
components rather than duplicated markup.
