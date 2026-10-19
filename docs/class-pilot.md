# ClassPilot AI

ClassPilot is an optional, server-side Gemini integration inside the existing Watersprings dashboard. It provides separate teacher, student, and family experiences while reusing the school's classes, subjects, teacher assignments, student enrolments, parent links, published curricula, and result-publication rules.

## Setup

1. Add `GEMINI_API_KEY` to the server `.env`. Keep it server-side.
2. Optionally change `GEMINI_MODEL`, request limits, timeouts, or retention settings documented in `.env.example`.
3. Run `php artisan migrate --no-interaction`.
4. Run `php artisan permissions:sync-matrix --no-interaction` so existing roles receive the new scoped permissions.
5. Run `npm run build` and clear/rebuild application caches for the deployment environment.

Without an API key, the workspace deliberately shows a setup-required state and does not fabricate content. Automated tests should mock `App\Services\ClassPilot\Contracts\AiProvider`; they never require live Gemini access.

The default model is `gemini-3.8-flash`. Override it only with a model currently available to the configured Gemini account.

## Data and safety boundaries

- Generated teacher material is stored as a draft. Approval is explicit and does not publish an assignment, exam, or answer key.
- Student practice attempts are separate from official results and never update marks.
- Family requests resolve the selected child through the existing parent relationship and accept only approved results whose term has been published.
- Prompts contain bounded curriculum excerpts and the minimum selected record data. Logs contain identifiers and error classes, not prompt or response bodies.
- Conversation retention is configured with `CLASS_PILOT_RETENTION_DAYS`; deployment should schedule a policy-approved cleanup command before automatic deletion is enabled.
- AI output is educational guidance, not an official school statement, human teacher, or counsellor.
