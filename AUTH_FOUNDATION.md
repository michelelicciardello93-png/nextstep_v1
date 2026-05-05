# SaaS Authentication Foundation (Super Admin + Workspaces)

This document defines the **first implementation slice** for your multi-tenant SaaS:

1. Database setup for users, workspaces, roles, memberships, and sessions.
2. Bootstrap flow to create the first `superadmin` user.
3. Creation of the default workspace.
4. Authentication and post-login routing flow.

## 1) Core concepts

- **User**: Identity that can authenticate once and belong to one or many workspaces.
- **Workspace**: Tenant boundary for most app data.
- **Role**:
  - `superadmin` (global, all workspaces)
  - `workspace_owner`
  - `workspace_admin`
  - `workspace_member`
- **Membership**: user ↔ workspace mapping with role.
- **Session**: persisted login session (or token metadata).

## 2) Recommended login behavior

- If user has `superadmin`: send to global admin console.
- Else if user has only one workspace: send directly to that workspace dashboard.
- Else: send to workspace picker.

## 3) Security defaults

- Password hashing: Argon2id (preferred) or bcrypt with strong cost.
- Unique emails, case-insensitive normalization.
- Rotate refresh tokens and support session revocation.
- Record `last_login_at` and IP/user-agent for security auditing.
- Rate limit login endpoint.

## 4) Bootstrap order

1. Run DB migration (`db/schema.sql`).
2. Run bootstrap script (`scripts/bootstrap_superadmin.sql`) with your first admin email.
3. Verify you can login as superadmin.
4. Build auth API endpoints.
5. Add route guards / middleware.

