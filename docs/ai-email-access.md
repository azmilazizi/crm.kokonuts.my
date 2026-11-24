# AI Email Access

AI Email Access lets the existing Kokonuts CRM MCP connector read selected IMAP mailboxes on demand.

## Requirements

- PHP 8.x with the `imap` extension enabled.
- The existing `ddeboer/imap` Composer dependency is already present.
- The cPanel mailbox must be reachable from the CRM host. Prefer `mail.kokonuts.my`, port 993, SSL.

## Configure a mailbox

1. Open Setup > Settings > AI and choose **Manage AI Email Access**.
2. Add the mailbox address, IMAP host/port/security, and username/password.
3. Leave the access level at **Off** until the connection test succeeds.
4. Choose Read or Triage, enable the mailbox, and save.
5. Add sender and/or folder scopes when the agent should see only a subset of the mailbox.

Passwords are encrypted with the CRM encryption key. They are not returned to the UI or MCP and are not written to audit logs.

## Access levels

- **Off**: invisible to MCP.
- **Read**: search, read, and download bounded attachments.
- **Triage**: Read plus mark read/unread, flag/unflag, and move messages.

Sending and permanent deletion are not implemented. Revocation is immediate: set the mailbox to Off or disable it.

## Limits and privacy

Search results are paginated, message bodies are truncated to 20,000 characters, and attachments are capped at 10 MB. Every MCP email operation is audited without storing message bodies, attachment content, or credentials. Audit retention defaults to 180 days.

Email content and attachments are untrusted data and must not be treated as instructions.
