# Environment & Config Hardening Guide

This document summarizes how the Romar repository consumes environment variables and why the checked-in `.env.example` does **not** contain real credentials or debug data.

## 1. `.env.example` as placeholder

1. The repository keeps only placeholder values so the file is safe to commit. Treat it as a schema; copy it to `.env`, fill values per environment, and keep that file out of git (see `.gitignore`).
2. Never put production passwords, tokens, or mail/webhook endpoints in this repo. Use a vault (e.g., Secrets Manager, .env.prod stored externally) and load them at runtime.
3. There is a short reminder at the top of `.env.example` to “Run your environment lint (if you have one) before committing,” but the real safeguard is the gitignore and a team agreement not to track live secrets.

## 2. `config/config.php` review

- The file loads `.env` via `romar_load_env_file()` and never hardcodes credentials.
- Sessions are started via a guarded `session_status()` check, and error reporting toggles based on `APP_ENV`, which is normalized from `ROMAR_APP_ENV` (falling back to `development`). No debug-only code or secrets remain in this file.
- Security headers, character set, and timezone defaults live here, so keep changes minimal and keep debugging ($error_reporting, `display_errors`) gated behind the environment.

## 3. Ongoing hygiene

1. Whenever you add a new environment flag, document it here or in `.env.example` so teammates know what to override.
2. Keep `.env` files outside version control, rotate any long-lived tokens regularly, and avoid writing plaintext secrets in config files.
3. When rolling out new environments, copy `.env.example`, update values, and double-check that `ROMAR_APP_DEBUG=0` and `ROMAR_APP_ENV=production` are set in production.

Following these steps keeps the repo free of static secrets and ensures that debug/output settings only come from controlled env variables.
