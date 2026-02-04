# Your-Database — Migration Notes

This repository underwent a frontend migration to move rendering and interactions
from PHP templates to a client-side JavaScript application. The goal was to
centralize UI logic, reduce inline scripts, and expose minimal APIs for the
client to interact with.

Summary of important changes
- Consolidated client logic: `public/js/app.js` now contains rendering,
  delegated event handlers, and API wrappers.
- Centralized API endpoints: `public/api/database.php` and
  `public/api/dashboard.php` handle AJAX actions for objects, categories,
  databases, and settings.
- Converted server templates to client containers: many `.php` and `.phtml`
  files now expose `window.csrfToken`, `window.databaseId`, and `window.userId`
  and rely on the JS app to render lists and handle submits.

Files of note
- public/js/app.js — main client application
- public/api/database.php — database-related API actions (list/create/edit/delete)
- public/api/dashboard.php — dashboard list/create
- public/database.php, public/database-ajouter.php — converted to client-driven pages
- public/database-settings.php — settings UI converted to call API actions

Testing & cleanup notes
- The app was manually smoke-tested locally (registration, login, create DB,
  add object, edit, category CRUD, delete DB). Temporary test data was removed.
- If you run into template parse errors after further edits, inspect the
  changed PHP files for unbalanced PHP tags introduced during migration.

How to run
1. Start your local PHP/Apache server (XAMPP).
2. Browse to the app and authenticate.
3. Open browser devtools and verify `window.csrfToken` and `window.databaseId` are set.

If you want I can prepare a commit and push instructions, or create a branch.
