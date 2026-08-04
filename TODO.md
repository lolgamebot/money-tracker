# Money Tracker - Restructure & Version Control TODO

## File Management
- [x] Analyze project structure and identify duplicates
- [x] Confirm plan with user

## Restructure (match habittracker layout)
- [x] Move app PHP files into `public/` (dashboard, login, register, logout, add, edit, delete, categories, recurring, charts, profile)
- [x] Move `process_recurring.php` into `public/` (page-specific action script)
- [x] Move `database/schema.sql` → root `database.sql`
- [x] Remove empty `database/` directory
- [x] Remove empty `public/assets/` and downstream duplicates
- [x] Create root `index.php` that redirects to `public/login.php` (external, separated from public)
- [x] Update all `require` statements to `../config/db.php` and `../includes/helpers.php`
- [x] PHP syntax check passed for all files

## Version Control
- [x] Update `.gitignore` to `config/db.php` + `.analysis_tmp/` (matches habittracker)
- [x] Verified `config/db.php` untracked & gitignored (credentials safe)
- [x] Update `README.md` with new structure + InfinityFree deployment instructions
- [x] Commit the restructure cleanly
- [ ] (Optional) Push to origin

## Database Safety
- [x] Verify `database.sql` is reference-only (not run against live DB)
- [x] Preserve `config/db.php` credentials
- [x] Existing DB schema (accounts, categories, expenses) stays compatible with live data
