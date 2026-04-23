

# Fix GitHub Actions Lint Errors in deploy.yml - COMPLETED ✓

- [x] Step 1: Create preliminary 'Check DB Config' step that sets 'needs-db-config' output (true if ROMAR_DB_HOST secret exists and non-empty)
- [x] Step 2: Update 4 conditional steps to use `if: steps.check-db.outputs.needs-db-config == 'true'`
- [x] Step 3: Remove `env:` DB vars from individual steps (set in check step via GITHUB_ENV)
- [x] Step 4: Verify no lint errors remain and workflow logic preserved
- [x] Step 5: Complete task

All VSCode lint errors in .github/workflows/deploy.yml fixed. Reload VSCode window to clear cache if needed (Ctrl+Shift+P > Reload Window).
