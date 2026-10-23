# AGENTS.md

## Project
Watchly is a web application for browsing and managing products.

Roles: admin, user.

## Technology Stack
- Frontend: HTML, CSS, JavaScript, Bootstrap 5
- Backend: PHP, FlightPHP, PDO, MySQL
- Local server: XAMPP

## Folder Structure
- frontend/: assets/, css/, js/, views/
- backend/: config/, dao/, logs/, middleware/, routes/, services/
- docs/: project documentation

## Frontend Rules
- Keep frontend code inside frontend/.
- Use HTML, CSS, JavaScript and Bootstrap 5.
- Keep files in their designated folders.

## Backend Rules
- Keep backend code inside backend/.
- Follow the route -> service -> DAO -> database structure.
- Only services may call DAO classes.
- Use PDO prepared statements for SQL queries.
- Return JSON with appropriate HTTP status codes.

## General Rules
- Do not add libraries without permission.
- Do not change the database schema without permission.
- Never commit passwords or API keys.
- Make small changes and explain them.
- Do not modify unrelated files.
- Ensure the student can understand and explain all code.