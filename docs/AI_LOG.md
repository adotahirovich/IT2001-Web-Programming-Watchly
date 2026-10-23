# AI Usage Log

## Milestone 1 — Setup, static frontend, ERD
- Tool: ChatGPT (OpenAI).
- Used for: planning the Watchly project structure, drafting the initial SPA, visual styling, and initial ERD/entity planning.
- Important prompt: “Help me implement the project strictly according to the supplied Web Programming project requirements, keeping the structure minimal.”
- Review case 1: AI-generated frontend can assume API routes and response shapes that must be verified against the backend. Fix: compare every `api()` call and response shape against FlightPHP routes during integration.
- Review case 2: AI-generated code may not fully cover all OpenAPI endpoints or authorization edge cases. Fix: test all endpoints in Swagger/Postman and extend the OpenAPI document before submitting M3.
- Update this log in each milestone with the actual prompts used, what was wrong, how it was detected, and how it was corrected. Do not claim a review or fix until you have actually done it.
