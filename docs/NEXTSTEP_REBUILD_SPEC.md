# NextStep Rebuild Spec

## Product Definition

NextStep is a role-based decision workflow platform for support and customer-care teams.

Admins create process flows, checklists, evaluations, and outcomes. Agents run processes and generate logged results. Outcomes can trigger manual or automatic external actions.

## Roles

- superadmin: full system control
- admin: manages processes, users, actions, locales, logs
- agent: runs published processes

## Architecture Principles

- React owns all UI.
- PHP exposes API only.
- MySQL stores process definitions, versions, runs, submissions, users, and actions.
- No autoload scripts.
- No manual DOM injection.
- No mixed PHP-rendered app pages.
- Processes are versioned so live workflows are not broken by draft edits.

## Core Entities

- users
- workspaces
- workspace_members
- locales
- processes
- process_versions
- process_nodes
- process_edges
- outcomes
- actions
- outcome_actions
- runs
- run_steps
- submissions
- audit_logs
- invitations

## Process Types

The same engine should support:

- decision_flow
- checklist
- evaluation
- form

Each process version stores structured nodes, edges, outcomes, and metadata.

## Routing Style

Backend routes use clean URLs:

```text
/api/processes/list
/api/processes/create
/api/processes/update
/api/processes/publish
/api/runs/start
/api/runs/step
/api/actions/run
```

Apache rewrite rules map these URLs to `backend/api/index.php`.

## Build Order

1. Database schema
2. API contract
3. PHP API skeleton
4. React shell
5. Process runner engine
6. Admin process builder
7. Actions/webhooks
8. Users, locales, workspaces, submissions
