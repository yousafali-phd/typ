# Typing Test System

Web-based typing test platform built with PHP, MySQL, and Vue.js. The project includes a candidate portal, an admin dashboard, and a center incharge portal for controlled lab-based assessments.

## Overview

This application is designed for center-wise typing exams with session-password login, live attempt tracking, candidate management, and result reporting.

Main modules:

- Candidate Portal: `index.html`
- Admin Dashboard: `admin/index.html`
- Center Incharge Portal: `center/index.html`
- Backend APIs: `api_backend/`

## Key Features

- Session-based candidate login with center validation
- Live candidate tracking during active tests
- Automated test result calculation and saving
- Admin analytics, candidate management, and result export
- Center incharge access with restricted center-specific visibility
- CSV import support for candidate bulk uploads
- Lab session password generation and expiry
- Responsive admin sidebar with mobile menu support

## Requirements

- Windows + WAMP or another PHP/MySQL stack
- PHP 7.4+ or newer
- MySQL / MariaDB
- Modern browser with JavaScript enabled

## Project Structure

```text
typing/
├── index.html
├── admin/
├── center/
├── api_backend/
├── css/
├── js/
├── img/
├── fonts/
├── production/
├── setup_database.sql
├── update_database.sql
├── quick_test_data.sql
├── reset_users.sql
├── verify_login.html
└── test_login.php
```

## Installation

1. Copy the project folder into your web server directory, for example `C:\wamp64\www\typing`.
2. Start Apache and MySQL from WAMP.
3. Create a database named `typing_april_12`.
4. Import the schema using `setup_database.sql`.
5. Apply `update_database.sql` if your local database needs the latest columns and fixes.
6. Optionally import `quick_test_data.sql` to load sample centers, candidates, and sessions.

## Configuration

Database connection settings are managed in `api_backend/mysqli.php`.

Typical local setup:

- Host: `localhost`
- Database: `typing_april_12`
- Username: `root`
- Password: empty for default WAMP installs

If you deploy to another environment, update the same connection file and mirror the production copies under `production/`.

## Usage

### Candidate Portal

Open the root page in a browser and log in with:

- CNIC
- Roll number
- Lab session password

### Admin Dashboard

Open `admin/` to manage:

- Candidates
- Centers
- Paragraphs
- Type codes
- Lab sessions
- Results
- Center incharges
- Analytics and settings

### Center Incharge Portal

Open `center/` to view only center-assigned candidates, sessions, and results.

## Helpful Utilities

- `verify_login.html` for browser-based login and API checks
- `test_login.php` for server-side diagnostics
- `reset_users.sql` for clearing test status during retest cycles

## Deployment Notes

- Keep root and `production/` files in sync when making runtime changes.
- Use the `production/` copies as the deployable bundle.
- Confirm the database schema matches the current PHP API expectations before pushing live.

### Production deployment

- Ensure your webserver prefers `index.php` over `index.html` so PHP wrappers run (Apache: `DirectoryIndex index.php index.html`).
- The app uses PHP wrapper files (`index.php`) to enforce portal feature flags (`app_settings`). If `index.html` is served directly, disabling portals from Admin will not take effect.
- Gate denials are logged to `api_backend/portal_gate_denied.log` (created automatically when a portal is blocked). Check this file on production for quick diagnostics when a portal is disabled.
- Disabled candidate and center incharge portals now show a friendly `Exam Expired` page instead of an HTTP 404 error.
- The Admin dashboard includes a Sync Production tool that packages the deployable bundle into `production/production_release_YYYYmmddHHMMSS.zip` after collecting the base path and database credentials.
- The production bundle includes `production/index.php` and `production/.htaccess` so the gate runs before `index.html` is served.

## Support

If login, session, or result issues appear, start with the diagnostic pages above and check the API/backend connection in `api_backend/mysqli.php`.
