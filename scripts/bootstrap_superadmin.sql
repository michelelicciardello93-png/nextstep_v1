-- Usage:
--   1) Replace placeholders below.
--   2) Run this file once after schema migration.
-- Notes:
--   - password_hash must be generated in app/service layer using Argon2id or bcrypt.

begin;

-- Replace values before running:
-- :admin_email       => e.g. founder@yourcompany.com
-- :password_hash     => secure hash from auth service
-- :default_ws_slug   => e.g. default
-- :default_ws_name   => e.g. Default Workspace

with inserted_user as (
  insert into users (email, password_hash, display_name, is_superadmin)
  values ('admin@example.com', 'REPLACE_WITH_SECURE_HASH', 'Super Admin', true)
  on conflict (email) do update set
    is_superadmin = true,
    updated_at = now()
  returning id
),
inserted_workspace as (
  insert into workspaces (slug, name, is_default, created_by_user_id)
  values ('default', 'Default Workspace', true, (select id from inserted_user))
  on conflict (slug) do update set
    is_default = true,
    updated_at = now()
  returning id
)
insert into memberships (user_id, workspace_id, role)
values (
  (select id from inserted_user),
  (select id from inserted_workspace),
  'workspace_owner'
)
on conflict (user_id, workspace_id) do nothing;

commit;
