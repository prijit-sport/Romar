# Windows Ticket Notifications

This helper documents how to push Romar IT ticket events directly into the
Windows Action Center using the new `scripts/windows-ticket-notifier.ps1` watcher.
The script logs into Romar, polls `api/getnotifications.php`, shows each unread
notification as a toast (via BurntToast), then marks it as read.

## Prerequisites

- A Windows 10/11 workstation with PowerShell 5.1 or newer.
- Access to the Romar installation (default `http://192.168.2.99/Romar`).
- The [BurntToast](https://www.powershellgallery.com/packages/BurntToast) module,
  which the script installs if missing.
- A user account that has `admin` or `it_support` role so that notifications
  are generated for ticket events.

## Running the notifier

1. Open PowerShell and optionally install BurntToast manually the first time:
   ```powershell
   Install-Module BurntToast -Scope CurrentUser
   ```
2. Run the watcher script interactively:
   ```powershell
   powershell -ExecutionPolicy Bypass -NoProfile -File .\scripts\windows-ticket-notifier.ps1 `
       -BaseUrl 'http://192.168.2.99/Romar' `
       -PollIntervalSeconds 45
   ```
   The script prompts for the Romar username (unless you pass `-Username`) and
   password (unless you pass `-Password` or `-Credential`). Provide credentials
   for an admin/IT support user so that notifications are created on ticket events.

3. Approve toast notifications when Windows asks: go to **Settings → System →
   Notifications & actions** and allow the PowerShell/BurntToast app to show
   notifications so they appear in the notification shade like the screenshot you
   shared.

4. Every poll cycle, the script will show a toast for each unread ticket
   notification, then call `api/marknotificationread.php` so the same ticket is
   not repeated.

### Advanced options

- Supply `-MarkAllRead` if you prefer the script to mark every active
  notification as read after each batch instead of one-by-one.
- Use `-Username`/`-Password` when scripting the watcher so that you can run it
  from Task Scheduler without interactive prompts. **Keep passwords protected.**
- Inject a Windows scheduled task that runs the same PowerShell command at logon:
  - Program: `powershell.exe`
  - Arguments: `-ExecutionPolicy Bypass -NoProfile -File "C:\xampp\htdocs\Romar\scripts\windows-ticket-notifier.ps1" -Username itwatch -Password 'secret'`
  - Trigger: At logon (or every 5 minutes if you want redundancy).

## Tips

- If the notifier loses its session (because the web session expires), it will
  automatically re-login and keep polling.
- The script runs forever; stop it with `Ctrl+C` or by ending the PowerShell
  process in Task Manager.
- You can wrap it with `nssm` or `PowerShell Scheduled Task` if you want it
  to start automatically whenever Windows boots.
