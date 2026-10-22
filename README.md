# Kokonuts CRM

Modern tooling has been added to streamline local development and continuous integration.

## Developer Setup

1. Install PHP dependencies (ensure the `imap` extension is enabled in your PHP CLI):
   ```bash
   composer install
   ```
   If Composer reports that the lock file is out of date, run `composer update --no-progress --no-interaction` once to hydrate the
   developer tooling packages.
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

## Deployment

The cPanel Git deployment pipeline defined in `.cpanel.yml` now runs `tools/deploy/sync-env.sh` after every pull.
The script keeps the production `.env` file in sync by setting `APP_BASE_URL` to `https://crm.kokonuts.my/` without
overwriting other keys. If `.env` does not exist, it is generated from `.env.example` with production-safe defaults.
Update `tools/deploy/sync-env.sh` if you need to change the domain or add more automated environment values.

## Quality Commands

- PHP CS Fixer (dry run): `vendor/bin/php-cs-fixer fix --diff --dry-run`
- PHP_CodeSniffer: `vendor/bin/phpcs`
- PHPStan: `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`
- Rector dry run: `vendor/bin/rector process --dry-run`
- JS/CSS lint: `npm run lint`
- JS/CSS format check: `npm run format`

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed contribution guidelines.

## ChatGPT MCP connector

The existing read-only MCP endpoint supports ChatGPT and Claude over Streamable HTTP.
It exposes business reporting tools and read-only SQL access to CRM, POS, inventory,
purchasing, accounting and HR data. It uses a shared secret URL, with broad access
rather than individual staff permissions. Do not publish or log the connector URL.

1. Deploy the changes to your HTTPS CRM installation.
2. In **Setup > Settings > AI**, generate a connector link and **Save Settings**.
3. In ChatGPT on the web, open **Plugins > + > Add custom MCP server**. Name it
   **Kokonuts CRM**, paste the complete connector URL, and choose **No authentication**
   (the URL itself contains the access secret).
4. Create and install the plugin. Select **@Kokonuts CRM** in a conversation and try
   “Show sales and profit for this month.” Workspace permissions may restrict adding
   custom servers.

See the [official ChatGPT setup instructions](https://developers.openai.com/api/docs/guides/custom-mcp-server).
No OpenAI API key is needed for this server. Rotate or disable the link in AI settings
and save to revoke access for every client using it. Claude can continue using the
same URL. Per-user OAuth authorization is not implemented by this connector.

Run the database-free regression checks with `php tools/test-mcp.php`. These checks
verify protocol behavior and CSRF configuration; they do not replace connecting the
deployed server from ChatGPT and checking real reporting results.
