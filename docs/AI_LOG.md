# AI Usage Log

## Milestone 1 — Setup, Static Frontend, ERD

### Tools and tasks
- **ChatGPT:** Used for basic guidance with project structure, folder organization, and simple frontend planning.
- **ChatGPT:** Used to compare the project with the Milestone 1 and full project requirements, review whether it was ready to submit, and improve this AI usage log.
- **GitHub Copilot:** Used for assistance with basic project setup and code suggestions.
- AI was used as a helper, while the project structure and implementation were reviewed and adjusted manually.

### Example prompts
- "Check if my project structur and frontend are okay for Milestone 1."
- "Compare my project with the project requirements and tell me if it is ready to submit."
- "Help me write the AI usage log in simple language."

### Where the AI was wrong
1. Some AI suggestions about organizing the files did not fit exactly with the folders already in the project. I noticed this while checking where the frontend pages and backend files were kept, so I kept the existing folders instead of adding unnecessary new ones.
2. At first, the AI review only focused on the Milestone 1 requirements that I had shared. After I shared the full project requirements, I checked the advice again and separated what is needed for Milestone 1 from features that are planned for later milestones. This helped avoid treating unfinished future features as missing from the current submission.

## Milestone 2 — Database and DAO Layer

### Tools and tasks
- **GitHub Copilot Backend agent (model not recorded):** Used for explanations about the Milestone 2 database/DAO requirements, suggestions while working on some DAO code, and help preparing a command-line CRUD test. The student imported the database, ran the test, and reviewed and simplified the code with AI assistance.

### Example requests
- Asked for a simple explanation of who creates/imports the database and how to check the database connection.
- Asked how to run and manually verify the DAO CRUD test.

### CRUD test
- `C:\xampp\php\php.exe C:\xampp\htdocs\IT2001-Web-Programming-Watchly\backend\test_dao.php`

### Where the AI was wrong or needed correction
1. The first DAO implementation was more complicated than needed for this project. The code was reviewed and simplified while retaining the required CRUD functionality.
2. The DAO test was initially run by AI without a runnable test script for the student. A `test_dao.php` script was added, then run manually by both AI and the student; test rows are rolled back.