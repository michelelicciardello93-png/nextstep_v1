-- PostgreSQL schema for multi-tenant SaaS auth + RBAC

create extension if not exists citext;
create extension if not exists pgcrypto;

create table if not exists users (
  id uuid primary key default gen_random_uuid(),
  email citext not null unique,
  password_hash text not null,
  display_name text,
  is_active boolean not null default true,
  is_superadmin boolean not null default false,
  last_login_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists workspaces (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique,
  name text not null,
  is_default boolean not null default false,
  created_by_user_id uuid references users(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists memberships (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references users(id) on delete cascade,
  workspace_id uuid not null references workspaces(id) on delete cascade,
  role text not null check (role in ('workspace_owner','workspace_admin','workspace_member')),
  created_at timestamptz not null default now(),
  unique (user_id, workspace_id)
);

create table if not exists sessions (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references users(id) on delete cascade,
  refresh_token_hash text not null,
  ip_address inet,
  user_agent text,
  expires_at timestamptz not null,
  revoked_at timestamptz,
  created_at timestamptz not null default now()
);

create index if not exists idx_memberships_user_id on memberships(user_id);
create index if not exists idx_memberships_workspace_id on memberships(workspace_id);
create index if not exists idx_sessions_user_id on sessions(user_id);

-- Ensure at most one default workspace
create unique index if not exists uq_workspaces_single_default
  on workspaces ((is_default))
  where is_default = true;
