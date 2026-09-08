# Dashboard design

The authenticated portal uses one shared shell in `resources/views/partials/dashboard-shell.blade.php`. The dashboard, results, and legacy app layouts all include it. Public pages and printed reports keep their own layouts.

`resources/css/dashboard.css`, imported by `app.css`, defines dashboard-only colors, typography, spacing, cards, tables, buttons, form fields, navigation, dialogs, and responsive behavior. Light and dark themes use the same CSS variables. Fields have a minimum 44px height, a 10px radius, visible focus rings, and distinct disabled and validation states.

For new dashboard pages:

- Use the dashboard layout and provide a title, optional description, icon, and breadcrumbs.
- Use `dashboard-section-heading` for a panel heading with actions.
- Use native inputs, selects, and textareas with associated labels; the shared stylesheet supplies their appearance.
- Keep semantic success, warning, and destructive colors. Use the shared accent for primary actions.
- Keep wide tables inside a scrolling container and allow filters and actions to wrap on mobile.
- Preserve permission checks and Livewire bindings when updating markup.

## Verification

The redesign was checked in Chrome at 390px and 1440px on 23 representative management and results pages. These pages had no horizontal document overflow and one main heading. Four create forms were checked at both sizes, including required-field validation without saving records. Dark mode, the mobile menu, account dropdown, and Livewire navigation were exercised without browser JavaScript errors.

Blade compilation and the production Vite build pass. A read-only route audit also exposed and fixed an array-count rendering error on student promotions and route ordering that intercepted administrator and grade-system create pages.

The browser checks used a super-administrator account and the local database. They do not establish coverage of every role, populated record detail, upload, payment, or mutation workflow.

## Finding dashboard pages

The sidebar groups tools by task: students and parents, staff and access, classes and subjects, calendar and timetables, exams and results, computer-based tests, attendance and discipline, fees and payments, messages and notices, website and media, reports and analytics, school settings, and your account. Related links sit one level below each group; only one group is expanded at a time.

Use the menu search to find a page by its name or task. Matching groups open automatically, and clearing the search restores the normal navigation. Only authorized destinations are included in both the menu and its search. The current page stays highlighted, including record detail and edit screens.

Unavailable feature placeholders and duplicate result-entry links were removed from navigation. Implemented reports now appear under Reports & Analytics. Student and teacher creation links open their forms directly.

Menu visibility was checked with both super-administrator role names, administrator, principal, teacher, student, and parent fixtures. Active links were checked on directory, create, and detail URLs. Browser checks exercise grouping, search, clearing/no-result states, and navigation to the student form on desktop and mobile.
