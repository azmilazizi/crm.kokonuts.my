# Contributing

Thank you for improving Kokonuts CRM! This guide explains how to get a local development environment running and how to use the new tooling that keeps the codebase consistent.

## Prerequisites

- PHP 8.1 or newer with the `mbstring`, `intl`, `zip`, `gd`, `mysqli`, and `imap` extensions
- Composer 2.x
- Node.js 18.x (or newer) and npm
- MySQL-compatible database server

## Getting Started

1. **Install PHP dependencies** (ensure the `imap` extension is enabled in your CLI configuration)
   ```bash
   composer install
   ```
   If Composer reports that the lock file is out of sync, run `composer update --no-progress --no-interaction` once to download the
   QA tooling packages defined in `require-dev`.
2. **Create your environment file**
   ```bash
   cp .env.example .env
   ```
   Update the database credentials and application settings in `.env` to match your local environment. Values left empty fall back to the defaults defined in `application/config/app-config.php`.
3. **Install frontend tooling (optional for backend-only changes)**
   ```bash
   npm install
   ```

## Quality Tooling

Run these commands from the repository root.

| Purpose | Command |
| --- | --- |
| PHP style check | `vendor/bin/php-cs-fixer fix --diff --dry-run` |
| PHP code sniffing | `vendor/bin/phpcs` |
| Static analysis | `vendor/bin/phpstan analyse --no-progress --memory-limit=512M` |
| Rector dry run | `vendor/bin/rector process --dry-run` |
| JS/CSS lint | `npm run lint` |
| JS/CSS format check | `npm run format` |
| Auto-format JS/CSS | `npm run format:write` |

> **Tip:** Run `composer format` to apply PHP-CS-Fixer rules automatically.

## Database

The CRM expects a MySQL database. Configure the credentials in `.env` or leave the default values intact to fall back to the existing configuration constants.

## Commit Guidelines

- Keep commits focused and descriptive.
- Run the tooling above before submitting a pull request.
- Ensure CI passes locally when possible.
- Need to publish your branch? Provide your repository URL to `tools/git/push-with-remote.sh` to automatically create or update
  the `origin` remote and push in one step:
  ```bash
  GIT_REMOTE_URL=git@github.com:your-org/your-repo.git tools/git/push-with-remote.sh my-feature-branch
  ```

Happy building!
