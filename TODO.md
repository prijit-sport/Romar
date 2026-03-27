# Deployment TODO

Status: In progress

## Steps:

1. [ ] Update .gitignore to ignore IDE, reports, backups, fixed files
2. [ ] git add .
3. [ ] git commit -m "Deploy updates: core modules, admin panels, styles, database schema; ignore junk files"
4. [ ] Run preflight: php scripts/ops/deploy_preflight.php
5. [ ] git push
6. [ ] Create PR from blackboxai/deploy-cleanup to main on GitHub: https://github.com/prijit-sport/Romar
7. [ ] Merge PR

## Notes:
- Run `git status` to verify clean working tree after each step.
- Check docs/DEPLOY_CHECKLIST.md for server setup.

Updated: $(date)
