# GitHub Deploy TODO - Romar Project

Status: Plan approved ✅

## Deploy Steps (blackboxai/deploy-2026 branch):
- [ ] 1. Cleanup sensitive files/dirs (venv, tmp*, logs old files, database/dormitory.db)
- [ ] 2. Update .gitignore with venv/**, database/*.db, uploads/, **/*.pyc
- [ ] 3. Run preflight: php scripts/ops/deploy_preflight.php
- [ ] 4. git checkout -b blackboxai/deploy-2026
- [ ] 5. git add . & git commit -m "Cleanup + prepare for GitHub deploy"
- [ ] 6. gh repo create romar-dormitory --public --source=. --remote=origin --push
- [ ] 7. gh pr create --title "Deploy production-ready Romar" --body "Full cleanup, workflows ready. Merge to trigger release." --fill --base main
- [ ] 8. Merge PR, workflows run (deploy.yml creates release)

**Notes:** Repo public as romar-dormitory. gh CLI ready. Sensitive data excluded.

Previous cleanup complete.

