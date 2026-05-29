<h1 align="center">Nanofin</h1>
<h3 align="center">A lightweight download interface for your Jellyfin server.</h3>

<p align="center">
  <img src="https://img.shields.io/github/v/release/lehenandmartin/Nanofin" alt="Latest release">
  <img src="https://img.shields.io/github/license/lehenandmartin/Nanofin" alt="License">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/github/actions/workflow/status/lehenandmartin/Nanofin/release.yml?label=build" alt="Build">
</p>

<p align="center">
<img width="1920" height="1220" alt="Library" src="https://github.com/user-attachments/assets/6cf4c5e5-203a-4a8f-83c0-f095c6e2a3a1" />
</p>

---

Browse movies and TV shows from your Jellyfin library, and download them directly — no Jellyfin account needed, no streaming, no transcoding.

### Library
- Poster grid of movies and TV shows with sort, filter, and search
- Detail page per movie (poster, metadata, download button)
- Detail page per TV show with collapsible seasons and per-episode downloads

### Access control
- Private mode — login required; admin panel always protected
- Public mode — library open to everyone, no login required
- Role-based access: `admin` (full access) and `user` (library only)
- Per-user content restriction: movies, shows, or both

### Authentication
- Two-phase login: enter email or username → password or magic link
- Magic link sign-in — passwordless login via a single-use email link
- Login rate limiting (5 failures / IP / 15 min)

### Admin panel
- User management: create, edit, delete; invite by email; reset passwords
- Settings: Jellyfin connection, SMTP, grid size, and more
- Download logs with per-user filtering
- Discord webhook notifications — post a message to a channel on each download

### Deployment
- Runs on shared Apache hosting (FTP upload)
- No Node / npm required on the server
- Subfolder deployment supported (`domain.com/nanofin`)

## Screenshots

<p align="center">
<img width="1920" height="991" alt="Movie" src="https://github.com/user-attachments/assets/eef006cf-33eb-4c7d-939e-7eeb0dfd1ced" />
<img width="1920" height="1220" alt="TV Show" src="https://github.com/user-attachments/assets/9fe5e342-5fc9-4ef4-bae9-2af4e887a5f8" />
</p>

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 + Slim 4 |
| Database | SQLite (via PDO) |
| Templates | Twig |
| CSS | Tailwind CSS v4 |
| JS | Alpine.js v3 |
| Emails | PHPMailer |
| Asset build | Vite + npm (dev only) |

## Installation

See **[INSTALL.md](INSTALL.md)** for the full setup guide covering shared hosting,
VPS, subfolder installs, and local development.

### Quick start

```bash
git clone https://github.com/lehenandmartin/Nanofin.git
cd Nanofin
composer install --no-dev
cp .env.example .env
# Visit the site — the setup wizard runs automatically
```

## Requirements

- PHP 8.2+ with `pdo_sqlite`, `mbstring`, `openssl`
- Apache with `mod_rewrite`
- A Jellyfin server with an API key
- *(Optional)* An SMTP server for email features

## About

This project was built with the assistance of [Claude](https://claude.ai), under the author's direction and review.

## Disclaimer

Nanofin is intended for personal, private use only — to access content from your own Jellyfin server, on files you own or have the right to use. It is not designed or intended to facilitate the sharing of copyrighted material without authorization. The author takes no responsibility for any misuse.

## License

[MIT](LICENSE) — © 2025 Martin Le Hénand
