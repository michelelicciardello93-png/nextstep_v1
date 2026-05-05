# NextStep v1

NextStep is a role-based decision workflow platform for support and customer-care teams.

Admins build process flows, checklists, evaluations, and outcomes. Agents run processes and produce logged results. Outcomes can trigger manual or automatic external actions such as webhooks or Power Automate flows.

## Architecture

- Frontend: React + Vite (build only)
- Backend: PHP 8
- Database: MySQL
- Deployment: Hostinger (Other / Static + PHP)

## Structure

```
/public          → React build output (served to users)
/backend/api     → PHP API endpoints
/backend/lib     → backend logic
```

## Core rule

PHP handles data and actions. React handles all UI.
No autoload scripts. No DOM patching. No mixed PHP-rendered pages.

## Deployment

See `/docs/DEPLOYMENT_HOSTINGER.md`
