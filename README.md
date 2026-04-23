# Romar Dormitory Management System

## Overview

Romar is a comprehensive PHP web application for managing dormitory operations. Features include:

- Admin dashboard
- User profiles and management
- Ticket system with notifications
- Asset tracking and borrowing
- Room booking and maintenance requests
- Reports and knowledge base
- Security logging and backups

Built with PHP, MySQL, HTML/CSS/JS, and Font Awesome. Includes GitHub Actions for quality gates, security monitoring, and deployment.

## Quick Setup (XAMPP)

1. Clone repo: `git clone [https://github.com/prijit-sport/Romar.git](https://github.com/prijit-sport/Romar.git)`
2. Start XAMPP Apache + MySQL
3. Import `database/schema_mysql.sql` to MySQL (db: romar_dormitory)
4. Copy `.env.example` to `.env` and configure DB creds
5. Run `php database/migrate.php` for migrations
6. Access [http://localhost/Romar](http://localhost/Romar)

## Production Deployment

See `.github/workflows/deploy.yml` and `docs/DEPLOY_CHECKLIST.md`.

## Key Directories

- `admin/`: Admin panels
- `api/`: JSON APIs
- `assets/`: JS/CSS
- `includes/`: Shared PHP functions
- `modules/`: Core features
- `tests/`: PHP tests and load testing

## Scripts

- `scripts/ops/db_backup.php`: Database backup
- `scripts/ops/deploy_preflight.php`: Pre-deploy checks

## License

MIT
