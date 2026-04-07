# Assets structure

CSS and JS are organized by **Customer** and **Admin**.

## CSS
- **`css/customer/`** – Customer-facing pages (dashboard, home, flavors, login, register, chat, messages, favorite, orders, landing).
- **`css/Admin/`** – Admin panel (layout, notification, app, flavor, gallon, ingredients, order, dashboard).

Use in Blade: `asset('assets/css/customer/filename.css')` or `asset('assets/css/Admin/filename.css')`.

## JS
- **`js/customer/`** – Page-specific or shared scripts for customer views.
- **`js/admin/`** – Page-specific or shared scripts for admin views.

Use in Blade: `asset('assets/js/customer/filename.js')` or `asset('assets/js/admin/filename.js')`.

To move inline `<script>` code into these files, pass any server data (e.g. routes, CSRF token) via a small inline config, e.g. `window.APP_CONFIG = { ... };`, then load the external script.
