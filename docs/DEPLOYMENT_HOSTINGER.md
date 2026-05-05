# Deployment on Hostinger (Other / Static + PHP)

## Overview

- React app is built to `/public`
- PHP API lives in `/backend/api`
- Apache serves static files and routes SPA via `.htaccess`

## Build

```bash
npm install
npm run build
```

This generates:

```
/public/index.html
/public/assets/*
```

## Upload structure (to `public_html`)

```
/public/*            → public_html/
/backend/*           → public_html/backend/
.htaccess            → public_html/.htaccess
```

## URLs

- App: https://yourdomain.com/
- API: https://yourdomain.com/backend/api/processes/list

## Notes

- Do NOT deploy as Vite runtime.
- Choose "Other" in Hostinger.
- Ensure `.htaccess` is enabled.
- Ensure PHP is enabled for `/backend`.
