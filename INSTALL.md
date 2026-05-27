# Nanofin — Installation guide

## Table of contents

- [Requirements](#requirements)
- [Getting the files](#getting-the-files)
- [Option A — Shared hosting (FTP)](#option-a--shared-hosting-ftp)
- [Option B — VPS / server](#option-b--vps--server)
- [Option C — Local development](#option-c--local-development)
- [First-run setup wizard](#first-run-setup-wizard)
- [Email features (optional)](#email-features-optional)
- [Updating](#updating)
- [CLI tools](#cli-tools)
- [Troubleshooting](#troubleshooting)

## Requirements

| Component | Minimum |
|---|---|
| PHP | 8.2 or higher |
| PHP extensions | `pdo_sqlite`, `mbstring`, `openssl` |
| Web server | Apache with `mod_rewrite` enabled |
| Composer | Any recent version (needed server-side) |
| Jellyfin | Any recent version with API access |
| Node / npm | **Dev only** — not required on the server |

The setup wizard checks all requirements before you fill in any form.

## Getting the files

**Option A — Release archive (recommended)**

Download the latest `nanofin-vX.Y.Z.zip` from the
[GitHub releases page](https://github.com/lehenandmartin/Nanofin/releases) and extract it.

The archive already includes `vendor/` (PHP dependencies) and `public/assets/` (built CSS/JS).
**No `composer install` or Node/npm is required** — you can deploy straight away.

**Option B — Git clone (advanced)**

```bash
git clone https://github.com/lehenandmartin/Nanofin.git
cd Nanofin
composer install --no-dev --optimize-autoloader
```

Node/npm is only needed if you plan to modify the frontend assets.

## Option A — Shared hosting (FTP)

Download the release archive, extract it, and upload everything via FTP.
The archive already includes `vendor/` — **no Composer or SSH required**.

### 1. Upload the project

**Domain root** (`https://yourdomain.com`) — upload everything to `public_html/`:

```
public_html/
├── .htaccess          ← root-level router
├── public/
├── src/
├── templates/
├── data/
├── cache/
└── …
```

**Subfolder** (`https://yourdomain.com/nanofin`) — upload everything to `public_html/nanofin/`:

```
public_html/
├── (other site files)
└── nanofin/
    ├── .htaccess
    ├── public/
    └── …
```

### 2. Create the environment file

Copy `.env.example` to `.env`. The default content works for a domain root install:

```dotenv
APP_DEBUG=false
APP_BASE_PATH=
```

> **Never enable `APP_DEBUG=true` in production.** It exposes stack traces and internal paths.

**Subfolder only — two additional changes:**

**a) `.env`** — set `APP_BASE_PATH` to your subfolder path (no trailing slash):

```dotenv
APP_BASE_PATH=/nanofin
```

**b) `public/.htaccess`** — comment out `RewriteBase /` and uncomment the subfolder line:

```apache
# RewriteBase /
RewriteBase /nanofin/
```

### 3. Set directory permissions

`data/` and `cache/` must be writable by the web server:

```bash
chmod -R 755 data/ cache/
```

Most shared hosts already grant write access via FTP.

### 4. Open the setup wizard

Navigate to your URL — Nanofin redirects to the setup wizard automatically.

## Option B — VPS / server

### 1. Clone and install

```bash
git clone https://github.com/lehenandmartin/Nanofin.git /var/www/nanofin
cd /var/www/nanofin
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

### 2. Configure Apache

**Domain or subdomain** (`https://yourdomain.com` or `https://nanofin.yourdomain.com`) — create a virtual host pointing `DocumentRoot` at the project root:

```apache
<VirtualHost *:443>
    ServerName nanofin.yourdomain.com
    DocumentRoot /var/www/nanofin

    <Directory /var/www/nanofin>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/nanofin.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/nanofin.yourdomain.com/privkey.pem
</VirtualHost>
```

The root `.htaccess` forwards everything to `public/` internally — no need to point `DocumentRoot` at `public/`.

**Subfolder** (`https://yourdomain.com/nanofin`) — add an `Alias` inside your existing virtual host instead:

```apache
Alias /nanofin /var/www/nanofin

<Directory /var/www/nanofin>
    AllowOverride All
    Require all granted
</Directory>
```

### 3. Enable Apache modules and reload

```bash
a2enmod rewrite headers expires
systemctl reload apache2
```

### 4. Set ownership and permissions

```bash
chown -R www-data:www-data /var/www/nanofin
chmod -R 755 /var/www/nanofin
chmod -R 775 /var/www/nanofin/data /var/www/nanofin/cache
```

### 5. Configure `.env`

**Domain/subdomain** — leave `APP_BASE_PATH` empty:

```dotenv
APP_DEBUG=false
APP_BASE_PATH=
```

**Subfolder — two additional changes:**

**a) `.env`:**

```dotenv
APP_BASE_PATH=/nanofin
```

**b) `public/.htaccess`** — comment out `RewriteBase /` and uncomment the subfolder line:

```apache
# RewriteBase /
RewriteBase /nanofin/
```

### 6. Open the setup wizard

Navigate to your URL — the wizard starts automatically.

## Option C — Local development

### 1. Clone and install dependencies

```bash
git clone https://github.com/lehenandmartin/Nanofin.git
cd nanofin
composer install
npm install
```

### 2. Configure the environment

```bash
cp .env.example .env
```

Edit `.env`:

```dotenv
APP_DEBUG=true
APP_BASE_PATH=
```

### 3. Build assets

```bash
npm run dev    # watch mode — rebuilds on file changes
# or
npm run build  # one-shot build
```

> **When adding new Tailwind classes**, always rebuild assets.

### 4. Start a local PHP server

```bash
php -S localhost:8080 -t public
```

Or configure a local Apache vhost pointing at the project root with `AllowOverride All`.

### 5. Open the setup wizard

Navigate to `http://localhost:8080` — the wizard starts automatically.

## First-run setup wizard

On the very first request Nanofin detects that no admin account exists and
redirects to `/setup` automatically. The wizard:

1. **Checks system requirements** — PHP version, required extensions,
   directory writability. Fix any issues before proceeding.
2. **Creates the admin account** — username, password, and optional email.
3. **Configures Jellyfin** — server URL, API key, and site title.

After completing the wizard you are logged in and the library loads immediately.
The `/setup` route is permanently disabled once an admin account exists.

### Getting a Jellyfin API key

In Jellyfin: **Administration → Dashboard → API Keys → + (New key)**

## Email features (optional)

Nanofin includes several email-based features. All of them are **optional** and
require a working SMTP server configured in **Admin → Settings → Email (SMTP)**.

| Feature | What it does | How to enable |
|---|---|---|
| Invitation email | Sends login credentials to a newly created user | Check "Send invitation email" when creating a user |
| Password reset | Sends a new temporary password to the user's email | Click "Send password reset" in Admin → Users → Edit |
| Self-service password reset | Lets users request a reset themselves via `/forgot-password` | Enable "Allow password reset" in Admin → Settings |
| Magic link sign-in | Lets users sign in with a single-use email link (no password needed) | Enable "Allow magic link sign-in" in Admin → Settings |

### SMTP setup

1. Go to **Admin → Settings → Email (SMTP)**.
2. Fill in your SMTP provider details (host, port, username, password, from address).
3. Click **Save**. Nanofin tests the connection immediately and displays a green ✓
   if everything works, or a red ✗ with an error message if not.
4. Once the connection is verified, the "Allow password reset" and "Allow magic link
   sign-in" checkboxes become functional.

> **Note:** These features only activate when the SMTP connection has been
> successfully verified — the checkbox alone is not enough. If you reconfigure
> SMTP later, re-save the settings to re-verify.

### Supported SMTP providers

Any standard SMTP provider works: Gmail (App Password), Brevo, Mailgun,
Postmark, Amazon SES, or a self-hosted server. PHPMailer handles TLS/STARTTLS
automatically based on the port (587 → STARTTLS, 465 → SSL/TLS).

## Updating

1. Replace the project files (FTP upload or `git pull`).
2. Run `composer install --no-dev --optimize-autoloader` to update PHP dependencies.
3. **No migration step is needed.** Nanofin runs any pending database migrations
   automatically on the next request.
4. If you modified `public/.htaccess` for a subfolder install, verify your
   `RewriteBase` setting is preserved after the update.

## CLI tools

These commands require SSH access and must be run from the project root.

```bash
# Check which migrations have been applied / are pending
php migrate.php --status

# Run pending migrations manually (normally not needed — auto-runs at boot)
php migrate.php

# Full reset: drop all tables, clear caches, re-run all migrations
# WARNING: destroys all data including users and settings
php migrate.php --fresh
```

## Troubleshooting

### Blank page or 500 error

Enable debug mode temporarily:

```dotenv
APP_DEBUG=true
```

Check the PHP error log (location varies by host; often `logs/error_log`
or `/var/log/apache2/error.log`).

### 404 on all pages except the home page

`mod_rewrite` is not enabled or `AllowOverride All` is missing in your Apache
configuration. Enable `mod_rewrite` and ensure the directory directive allows
`.htaccess` overrides.

### Library is empty or Jellyfin shows "Unreachable"

- Verify the Jellyfin server URL is reachable from the server running Nanofin
  (not just from your browser).
- Check that the API key is valid: **Jellyfin → Administration → Dashboard → API Keys**.
- If Nanofin is behind a reverse proxy, ensure it forwards the correct `Host` header
  to Jellyfin.

### Subfolder install: CSS/JS not loading or all links 404

The subfolder settings are not in sync. All three must match:

| File | Expected value |
|---|---|
| `.env` | `APP_BASE_PATH=/yourpath` |
| `public/.htaccess` | `RewriteBase /yourpath/` |
| Apache config | `Alias /yourpath /path/to/nanofin` *(VPS only)* |

### Permission denied on `data/` or `cache/`

```bash
chmod -R 775 data/ cache/
chown -R www-data:www-data data/ cache/   # adjust user to your web server
```

### Emails are not sent

1. Go to **Admin → Settings → Email (SMTP)** and verify that a green ✓ appears
   next to the SMTP host and password fields after saving.
2. If you see a red ✗, the error message indicates whether the host is unreachable
   (wrong host/port, firewall) or the credentials are invalid.
3. Use the **Send a test email** button to confirm delivery to your inbox.
4. For password reset / magic link, also ensure the corresponding "Allow …"
   checkbox is enabled — both the checkbox and a verified SMTP connection are required.
