# Romar UI/UX Formal Unification Plan (Approved)
Status: ✅ Plan Approved - Implementation Started

## Plan Summary
- **Goal**: Unified formal navy corporate theme across all pages (reduce green/blue inconsistencies)
- **Core files**: `includes/styles.css` (new global), `includes/header.php` (link + base theme)
- **Theme**: Navy sidebar (`#1e40af → #1e3a8a`), gold accents (`#d97706`), subtle shadows

## Implementation Steps (Sequential)

### Phase 1: Core Infrastructure ✅ COMPLETE
- [x] Create `TODO.md` (tracking)
- [x] Step 1: Create `includes/styles.css` (global formal styles)
- [x] Step 2: Update `includes/header.php` (navy theme + link CSS, removed 200+ inline lines)

### Phase 2: Priority Modules ✅ COMPLETE
- [x] Step 3: `modules/assets.php` (removed 500+ lines inline CSS → navy theme)
- [x] Step 4: `modules/tickets.php` (removed 600+ lines inline CSS → navy theme)
- [x] Step 5: `modules/dashboard.php` (removed 800+ lines inline CSS → navy theme)

**Progress: 60% (Phase 2 complete, Phase 3 admin pages next)**

### Phase 2: Priority Modules (Inconsistent Green)
- [ ] Step 3: `modules/assets.php` (remove inline CSS → use new classes)
- [ ] Step 4: `modules/tickets.php` (green → navy)
- [ ] Step 5: `modules/dashboard.php` (green → navy)

### Phase 3: Admin Pages
- [ ] Step 6: Sample `admin/announcements.php`, `admin/settings.php` → unify
- [ ] Step 7: Remaining admin/* (users-management, meeting-rooms, etc.)

### Phase 4: Other Modules + Validation
- [ ] Step 8: `modules/Knowledgebase.php`, `reports.php`, `ticket_view.php`
- [ ] Step 9: Test all pages + responsive
- [ ] Step 10: Final search_files verify no inline gradients/shadows left → attempt_completion

## Next Action
Proceed to Step 1: Create `includes/styles.css`

**Progress: 5% (Infrastructure)**
