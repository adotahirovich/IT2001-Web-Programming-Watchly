# AGENTS.md

## Project
Watchly is a web application for browsing and managing watches and jewelry.

Roles: admin, customer.

## Technology Stack
- Frontend: HTML, CSS, JavaScript, Bootstrap 5
- Backend: PHP, FlightPHP, PDO, MySQL
- Local server: XAMPP

## Folder Structure
- frontend/: assets/, css/, js/, views/
- backend/: config/, dao/, logs/, middleware/, routes/, services/
- docs/: project documentation

## Database
- MySQL database name: watchly.
- The six tables are roles, users, products, carts, orders, and reviews.
- The schema and sample data are in backend/database.sql.
- Keep table and column names consistent with that SQL file and docs/erd.mmd.

## Frontend Rules
- Keep frontend code inside frontend/.
- Use HTML, CSS, JavaScript and Bootstrap 5.
- Keep files in their designated folders.

## Backend Rules
- Keep backend code inside backend/.
- Follow the route -> service -> DAO -> database structure.
- Only services may call DAO classes.
- Use PDO prepared statements for SQL queries.
- DAO classes extend the shared backend/dao/BaseDao.php implementation.
- EntityDao.php contains the allow-list mapping entity names to database tables and writable columns.
- DAO constructors receive the PDO connection; credentials are loaded by backend/config/bootstrap.php from the ignored config.php file.
- Use PascalCase class and file names for DAO classes, such as ProductDao.php.
- Return JSON with appropriate HTTP status codes.

## General Rules
- Do not add libraries without permission.
- Do not change the database schema without permission.
- Never commit passwords or API keys.
- Keep backend/config/config.php and backend/vendor/ out of Git.
- Make small changes and explain them.
- Do not modify unrelated files.
- Ensure the student can understand and explain all code.