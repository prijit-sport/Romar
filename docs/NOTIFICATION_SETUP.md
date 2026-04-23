# Notification & Validation Checklist

This guide summarizes the remaining work to bring the Romar notification pipeline and UI checks fully online.

## 1. Notification pipeline (email + Slack)

1. **Set environment values** (prefer `.env` or your deployment system). PHPMailerLite is bundled so you only need SMTP credentials (Gmail App Password is recommended):
   - `ROMAR_MAIL_HOST`, `ROMAR_MAIL_PORT`, `ROMAR_MAIL_USERNAME`, `ROMAR_MAIL_PASSWORD`, `ROMAR_MAIL_FROM`, `ROMAR_MAIL_FROM_NAME`
   - `ROMAR_NOTIFICATION_EMAIL_TO` (comma/semicolon separated extras)
   - `ROMAR_NOTIFICATION_SLACK_WEBHOOK` (optional Slack webhook URL)
   - For Gmail/App Password: use `smtp.gmail.com`, port `587`, and the 16-char App Password instead of your Gmail login.
2. **Check PHP `mail()` support** – ensure `php.ini` is configured for SMTP or local sendmail and that the `sendmail_path` / `SMTP` values match your provider; the notification helper uses `mail()` plus HTTP fallback for Slack.
3. **Trigger a notification** by creating or updating a ticket through the UI; audit `logs/` or the inbox to confirm recipients receive the message and the Slack channel posts the payload defined in `buildTicketNotificationPayload()`.
4. If `mail()` is blocked, configure a real SMTP client (e.g., PHPMailer, or a queue that pushes jobs) and replace `sendNotificationEmail()` to use it. The helper already collects recipients and Slack payloads in a reusable format.

## 2. Web UI verification

1. **Ticket creation screen**
   - Go to `/modules/tickets.php` and open the “Create ticket” modal.
   - Confirm the “Asset” dropdown lists rows that exist in `assets` (this is provided by the `SELECT asset_id...` query).
   - Submit a ticket and observe the success flash; check `logs/` for notification attempts if the asset selection succeeded.
2. **User management modal**
   - Try adding a user with invalid email or weak password – you should see validation errors produced by `validate_batch()` and `validate_password()`.
   - Edit a user and verify only allowed values pass, duplicate username/email is rejected, and the optional password field only submits when `validate_password()` returns true.

## 3. Automated checks

- Run `C:\xampp\php\php.exe tests/validation_ticket_helpers_test.php` to confirm the helper suite still passes; it exercises `validate_batch()`, `validate_password()`, and `calculateSLA()` without spinning up the full application.
- Re-run `php -l` on the modified files (`includes/functions_notification.php`, `modules/tickets.php`, etc.) after any further changes.

## 4. Next enhancements (optional)

- Consider wiring `sendNotificationSlack()` into a queue or background worker if Slack/API latency becomes an issue.
- Add CI coverage that runs the new `tests/validation_ticket_helpers_test.php` script so future changes keep the validator/ticket helper behavior intact.
