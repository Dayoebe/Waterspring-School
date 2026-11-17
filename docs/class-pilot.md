# ClassPilot AI

ClassPilot is an optional server-side AI integration inside the Watersprings dashboard. It supports Gemini or Groq and provides separate teacher, student, and family experiences while reusing the school's classes, subjects, teacher assignments, student enrolments, parent links, published curricula, and result-publication rules.

## Setup

1. Set `CLASS_PILOT_ENABLED=true`, choose `CLASS_PILOT_PROVIDER=gemini` or `groq`, and add the matching provider key to the server `.env`. Keep it server-side.
2. Configure a model available to that provider account. Optionally change request limits, timeouts, or retention settings documented in `.env.example`.
3. Run `php artisan migrate --no-interaction`.
4. Run `php artisan permissions:sync-matrix --no-interaction` so existing roles receive the new scoped permissions.
5. Run `npm run build` and clear/rebuild application caches for the deployment environment.

When disabled or missing the selected provider key, the workspace deliberately shows a setup-required state and does not fabricate content. Automated tests should mock `App\Services\ClassPilot\Contracts\AiProvider`; they never require live provider access.

The configured Gemini default in this repository is `gemini-3.8-flash`. Model availability is provider/account specific; override it when the configured account does not expose that model.

### Groq

Set `CLASS_PILOT_PROVIDER=groq`, add `GROQ_API_KEY`, and optionally override `GROQ_MODEL`. The default Groq model is `openai/gpt-oss-20b`. ClassPilot uses Groq's OpenAI-compatible chat-completions endpoint with JSON output. Only one provider key is selected and sent for each request.

## Data and safety boundaries

- Generated teacher material is stored as a draft. Approval is explicit and does not publish an assignment, exam, or answer key.
- Student practice attempts are separate from official results and never update marks.
- Family requests resolve the selected child through the existing parent relationship and accept only approved results whose term has been published.
- Prompts contain bounded curriculum excerpts and the minimum selected record data. Logs contain identifiers and error classes, not prompt or response bodies.
- `CLASS_PILOT_RETENTION_DAYS` records the intended retention policy, but the repository does not currently include a ClassPilot pruning command. Do not claim automatic deletion until a reviewed cleanup job is implemented and scheduled.
- AI output is educational guidance, not an official school statement, human teacher, or counsellor.

ClassPilot access is controlled by `access classpilot` plus the role-specific teacher, student, or family permission. Teacher approvals are audit logged. Provider credentials, prompts, responses, and unpublished school data must never be moved into browser-side JavaScript.
