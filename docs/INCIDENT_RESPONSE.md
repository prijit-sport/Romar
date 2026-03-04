# Incident Response (Security Alerts)

## Owner
- Primary owner (`ROMAR_INCIDENT_OWNER`): set to on-call person or rotation alias.
- Backup owner: set as secondary recipient in webhook/email channel.

## Trigger Sources
- `tests/security_alert_check.php`
- `logs/security.log`

## Alert Channels
- Webhook: set `ROMAR_ALERT_WEBHOOK_URL`
- Channel format: `ROMAR_ALERT_CHANNEL=slack|teams|generic`
- Email: set `ROMAR_ALERT_EMAIL_TO`

## Severity Guide
- High:
  - `login_failed` exceeds threshold repeatedly from same source
  - `access_denied` spikes from privileged routes
- Medium:
  - `rate_limit_blocked` gradual increase
- Low:
  - transient threshold crossing with quick recovery

## First 15 Minutes
1. Acknowledge incident in alert channel.
2. Validate counts from `tests/security_alert_check.php`.
3. Inspect recent events in `logs/security.log`.
4. Identify source IP/user/request pattern.
5. Apply temporary mitigation (WAF/IP block/rate limit tuning).

## Escalation
1. Escalate to backup owner if no acknowledgment in 10 minutes.
2. Escalate to platform/security lead if high severity persists > 30 minutes.
3. Create post-incident action items and update thresholds if needed.
