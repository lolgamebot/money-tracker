# TODO - Convert standalone pages to popup screens

## Steps
- [x] 1. `includes/helpers.php`: Add `isAjaxRequest()`, `respondJson()`, modify `renderNav()` to use modal triggers, add `renderModalSystem()`.
- [x] 2. `public/index.php`: Load chartjs, call `renderModalSystem()`, convert Add Record/Add Bill buttons to modal triggers.
- [x] 3. `public/add.php`: Dual-mode output (full page + modal content), AJAX success JSON.
- [x] 4. `public/categories.php`: Dual-mode output, AJAX success/delete handling.
- [x] 5. `public/recurring.php`: Dual-mode output, AJAX for GET actions.
- [x] 6. `public/charts.php`: Dual-mode output.
- [x] 7. `public/profile.php`: Dual-mode output, AJAX success.
- [x] 8. `public/edit.php`: Dual-mode output, AJAX success.
- [ ] 9. Test all popups open and CRUD + charts work in-modal.
