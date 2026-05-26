# Nanofin

**A lightweight download interface for your Jellyfin server.**

Browse movies and TV shows from your Jellyfin library, and download them directly — no Jellyfin account needed, no streaming, no transcoding.

---

## Features

### Library
- Poster grid of movies and TV shows with sort, filter, and search
- Detail page per movie (poster, metadata, download button)
- Detail page per TV show with collapsible seasons and per-episode downloads
- Reverse sort (↑/↓), skeleton loading, and smooth card entrance animations

### Access control
- **Public mode** — library open to everyone, no login required
- **Private mode** — login required; admin panel always protected
- Role-based access: `admin` (full access) and `user` (library only)
- Per-user content restriction: movies, shows, or both

### Authentication
- Two-phase login: enter email or username → password or magic link
- **Magic link sign-in** — passwordless login via a single-use email link
- **Self-service password reset** — user requests a new password by email
- **Force password change** — users created by an admin must change their password on first login
- Login rate limiting (5 failures / IP / 15 min)

### Admin panel
- Dashboard with Jellyfin status, user count, and recent downloads
- Settings: Jellyfin connection, SMTP (with live verification), locale, grid size, and more
- User management: create, edit, delete; invite by email; reset passwords
- Download logs with per-user filtering
- One-click global session revocation

### Emails (optional)
- Invitation, password reset, magic link, and SMTP test emails
- Fully translated (EN/FR), locale-aware per recipient
- SMTP connection verified at save time — green ✓ / red ✗ with error details

### Deployment
- Runs on shared Apache hosting (FTP upload + `composer install`)
- No Node / npm required on the server — built assets are committed
- Subfolder deployment supported (`domain.com/nanofin`)
- Automatic database migrations on every boot

---

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 + Slim 4 |
| Database | SQLite (via PDO) |
| Templates | Twig |
| CSS | Tailwind CSS v4 |
| JS | Alpine.js v3 |
| Emails | PHPMailer |
| i18n | Custom Translator (no dependency) |
| Asset build | Vite + npm (dev only) |

---

## Installation

See **[INSTALL.md](INSTALL.md)** for the full setup guide covering shared hosting,
VPS, subfolder installs, and local development.

**Quick start:**

```bash
git clone https://github.com/lehenandmartin/Nanofin.git
cd Nanofin
composer install --no-dev
cp .env.example .env
# Visit the site — the setup wizard runs automatically
```

---

## Requirements

- PHP 8.2+ with `pdo_sqlite`, `mbstring`, `openssl`
- Apache with `mod_rewrite`
- A Jellyfin server with an API key
- *(Optional)* An SMTP server for email features

---

## About

This project was built with the assistance of [Claude](https://claude.ai) (Anthropic),
under the author's direction and review.

---

## License

[MIT](LICENSE) — © 2025 Martin Le Hénand
