# Watchly — Watches & Jewelry Store

Watchly is a single-store SPA for browsing watches and jewelry, registering/logging in, managing a cart, creating orders, and posting product reviews. Administrators manage products, roles, users, orders, and reviews.

## Technology
- HTML, CSS, JavaScript (SPA + Fetch/AJAX)
- PHP 8.1+ and FlightPHP
- MySQL + PDO prepared statements
- JWT authentication and role-based authorization
- OpenAPI / Swagger UI
- Frankfurter API for EUR/BAM exchange-rate information

## Entities and relationships
- Roles 1—N Users
- Users 1—N Orders
- Users 1—N Reviews
- Products 1—N Reviews
- Orders store purchased product IDs, quantities and prices in `items_json`

## Roles
- `user`: browse products, create orders, view their orders, submit reviews.
- `admin`: manage products, users, roles, orders, and reviews.

## Run locally
1. Install PHP 8.1+, Composer, and MySQL.
2. Import `backend/database.sql` into MySQL.
3. Copy `backend/config/config.example.php` to `backend/config/config.php` and set database credentials and a long random JWT secret.
4. Install PHP dependencies:
   ```bash
   cd backend
   composer install
   ```
5. Start the backend from the `backend` directory:
   ```bash
   php -S localhost:8000 index.php
   ```
6. Serve the frontend directory with a local static server (for example VS Code Live Server). If your API runs on a different URL, set `window.WATCHLY_API_BASE` before `app.js` loads in `frontend/index.html`.
7. Open the frontend and register a user. To promote that account to admin, run:
   ```sql
   UPDATE watchly.users SET role_id = 2 WHERE email = 'your-email@example.com';
   ```
   Sign out and sign in again to receive an admin JWT.

## API documentation
- Swagger UI: `http://localhost:8000/api/swagger`
- OpenAPI JSON: `http://localhost:8000/api/openapi.json`
- Health check: `http://localhost:8000/api/health`

## External API
The API requests the EUR/BAM rate from Frankfurter. The API is designed to log failures and return a user-friendly error. The initial frontend reserves this feature for the next integration pass; add a visible rate panel when wiring the external API into the UI.

## Test accounts
Create test accounts through registration. To create an admin test account, promote it using the SQL command above. Do not store real passwords in the repository.

## Deployment
Add the public deployed URL here after deploying the application. This project is not deployed by default.

## Milestones
Use the course-required branches `milestone1` through `milestone5`. Do not merge milestone branches into `main` yourself. Add `ibu-web-programming2001` as repository collaborator/reviewer as required by the course instructions.