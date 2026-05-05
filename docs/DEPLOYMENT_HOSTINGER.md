# Deployment on Hostinger (Other / Static + PHP)

## Important distinction

Vite stays in the repository as the local/build tool.

Vite is NOT deployed as a running server on Hostinger.

Hostinger serves only:

- static React build files
- PHP API files

## Overview

- React source is built with Vite.
- React build output goes to `/public`.
- PHP API lives in `/backend/api`.
- Apache serves static files and routes SPA fallback through `.htaccess`.

## Build locally or in CI

```bash
npm install
npm run build
```

This generates:

```
/public/index.html
/public/assets/*
```

## Upload structure to `public_html`

```
/public/*            → public_html/
/backend/*           → public_html/backend/
.htaccess            → public_html/.htaccess
```

## URLs

- App: https://yourdomain.com/
- API: https://yourdomain.com/backend/api/processes/list

## Hostinger setup

Choose:

```
Other / Static + PHP
```

Do not choose Vite as a runtime app.

## Notes

- Keep `package.json`, `vite.config.js`, and `src/*` in GitHub.
- Do not upload `node_modules`.
- Do not rely on Hostinger to run `npm run dev`.
- Ensure `.htaccess` is enabled.
- Ensure PHP is enabled for `/backend`.
