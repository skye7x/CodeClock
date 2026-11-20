# CodeClock

A small coding time tracker built with plain PHP, MySQL, HTML, CSS and vanilla JS.
Start/stop a timer per project, link a public GitHub repo, sync its commits and see
which commits happened during which session.

## Features
- Register / login (sessions, bcrypt, CSRF protection, login throttling)
- Projects with an optional public GitHub repo
- Start/stop timer (time is set by the server, survives page refresh)
- Sessions list with commit counts per session
- GitHub commit sync (button + cron script)
- Stats: time per project, commits per hour, last 7/30 days

## Folder layout
```
codeclock-app/        private code - NOT web accessible
  config/             config.php (ignored by git) + config.example.php
  lib/                db, helpers, auth, github, sync, stats
  views/              header + footer
  sql/                001_schema.sql
  cron/               sync_commits.php
  logs/
public_html/codeclock/  public pages, api/ and assets/
```
Locally and on the server the two folders sit next to each other, so no paths change.

## Local setup (XAMPP)
1. Put the repo in `C:\xampp\htdocs\CodeClock`.
2. Create a MySQL database and import `codeclock-app/sql/001_schema.sql` in phpMyAdmin.
3. Copy `codeclock-app/config/config.example.php` to `config.php` and fill in your DB details.
4. Open `http://localhost/CodeClock/public_html/codeclock/`.

## Deploy (Hostido / cPanel)
1. Upload `codeclock-app/` next to `public_html/` (NOT inside it).
2. Upload the contents of `public_html/codeclock/` to `public_html/codeclock/`.
3. Create `config.php` on the server by hand with `APP_ENV` set to `prod`.
4. Import the schema in phpMyAdmin.
5. Cron job (every 30 min): `/usr/local/bin/php /home/USER/codeclock-app/cron/sync_commits.php`

## Known limitations
See the open issues.
