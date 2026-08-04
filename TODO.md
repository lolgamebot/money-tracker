# Money Tracker - Restructure & Version Control TODO

## File Management
- [x] Analyze project structure and identify duplicates
- [x] Confirm plan with user

## Restructure
- [x] Move `db.php` → `config/db.php` (keep credentials)
- [x] Move `db.example.php` → `config/db.example.php` (already exists)
- [x] Move `helpers.php` → `includes/helpers.php` (already exists)
- [x] Move `process_recurring.php` → `includes/process_recurring.php` (already exists)
- [x] Move `database.sql` → `database/schema.sql` (already exists)
- [x] Remove duplicate root files (helpers.php, process_recurring.php, db.example.php, database.sql)
- [x] Remove empty root `style.css` and `public/` folder
- [x] Update all `require` statements in PHP files to new paths

## Version Control
- [x] Update `.gitignore` to ignore `config/db.php`
- [x] Untrack `config/db.php` from git (`git rm --cached`)
- [x] Add `README.md` with setup + InfinityFree deployment instructions
- [ ] Commit the restructure cleanly

## Database Safety
- [x] Verify `schema.sql` is reference-only (not run against live DB)
- [x] Preserve `config/db.php` credentials
