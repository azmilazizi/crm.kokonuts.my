# Kokonuts CRM

Modern tooling has been added to streamline local development and continuous integration.

## Developer Setup

1. Install PHP dependencies:
   ```bash
   composer install
   ```
2. Copy the environment template and adjust as needed:
   ```bash
   cp .env.example .env
   ```
3. (Optional) Install frontend tooling:
   ```bash
   npm install
   ```

4. (Optional) Configure a Git remote and push your branch in one step:
   ```bash
   GIT_REMOTE_URL=git@github.com:your-org/your-repo.git \
     tools/git/push-with-remote.sh stabilize/cleanup-initial
   ```
   The helper script adds (or updates) the `origin` remote automatically before pushing. Override the remote name with
   `GIT_REMOTE_NAME` if your workflow uses a different alias.

## Quality Commands

- PHP CS Fixer (dry run): `vendor/bin/php-cs-fixer fix --diff --dry-run`
- PHP_CodeSniffer: `vendor/bin/phpcs`
- PHPStan: `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`
- Rector dry run: `vendor/bin/rector process --dry-run`
- JS/CSS lint: `npm run lint`
- JS/CSS format check: `npm run format`

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed contribution guidelines.
