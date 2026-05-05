# NextStep v1

NextStep is a role-based decision workflow platform for support and customer-care teams.

Admins build process flows, checklists, evaluations, and outcomes. Agents run processes and produce logged results. Outcomes can trigger manual or automatic external actions such as webhooks or Power Automate flows.

## Architecture

- Frontend: React + Vite
- Backend: PHP 8
- Database: MySQL
- Hosting target: Hostinger-compatible PHP hosting

## Core rule

PHP handles data and actions. React handles all UI. No autoload scripts. No DOM patching. No mixed PHP-rendered pages.
