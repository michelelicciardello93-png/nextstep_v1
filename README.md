# NextStep V1

NextStep is a workspace-based process runner and operations platform built for Hostinger deployment with a browser-native React frontend and PHP/MySQL backend.

## Component 1–2 foundation

This branch implements the minimum stable authentication foundation:

- workspace, user, session, auth-code, audit-log, and system-setting tables
- first-launch setup where the first account becomes the active `superadmin`
- password hashing with PHP `password_hash`
- hashed session tokens stored in the database and exposed to the browser only as an HTTP-only cookie
- auth-code registration for later users
- role-aware dashboard navigation for `superadmin`, `admin`, `supervisor`, and `agent`
- basic user management and invite-code creation endpoints for admins/superadmins

## Project structure

```txt
index.html                  Browser entry point
.htaccess                   Hostinger/Apache API + SPA routing
src/main.js                 Browser-native React app, no Node build step
src/app/api.js              Shared frontend API client
src/styles.css              App styles
backend/api/index.php       PHP API router
backend/lib/*.php           DB, auth, and response helpers
backend/migrations/*.sql    MySQL schema migrations
```

## Why there is no Node build setup

This project is intentionally a plain static frontend plus PHP backend. Hostinger can serve the app directly as files, so there is no Node project file, framework config file, or build command required for deployment.

The frontend uses browser-native ES modules and an import map for React. Upload the repository files to Hostinger, import the database migration, configure PHP database credentials, and open the site.

## Backend configuration

The PHP backend reads database settings from environment variables when available:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`

If environment variables are not available on your Hostinger plan, edit `backend/lib/db.php` with your database credentials before deploying.

## Database setup

Import the migration in your MySQL database before opening the app:

```sql
SOURCE backend/migrations/001_auth_foundation.sql;
```

Then visit the app. If no users exist, NextStep shows the first setup screen and creates the first workspace plus superadmin account.
