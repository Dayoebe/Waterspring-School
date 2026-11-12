# Open Learning

Open Learning is the public, curriculum-grounded learning experience at `/open-learning`. It uses the existing public website shell and existing ClassPilot AI provider binding. No account is required to select published curriculum, learn, ask questions, explore applications, complete assessments, or review results.

## Curriculum source and publication

The catalog resolves the configured Watersprings school, its current academic year, instructional classes, subjects, terms, and topics from existing academic records. A topic is public only when:

- its `SchemeTopic` belongs to a `SchemeOfWork` for the configured school;
- the scheme belongs to the school's current academic year;
- the selected class, subject, and semester all match the scheme; and
- the scheme has a non-null `published_at` value.

The feature does not create curriculum. If no qualifying content exists, visitors see a publication-empty state. Staff continue to manage and publish syllabi, schemes, and topics through the existing dashboard.

## AI teaching

`App\Services\OpenLearning\Tutor` reuses the existing `App\Services\ClassPilot\Contracts\AiProvider` binding. In production, set ClassPilot to Groq:

```dotenv
CLASS_PILOT_ENABLED=true
CLASS_PILOT_PROVIDER=groq
GROQ_API_KEY=
GROQ_MODEL=openai/gpt-oss-20b
GROQ_BASE_URL=https://api.groq.com/openai/v1
```

All provider requests are server-side. The tutor sends bounded published curriculum context, selected teaching style, the current section, and a limited recent conversation. Structured JSON responses are validated before they enter session state. AI and curriculum text are rendered as escaped text, not raw HTML.

Lessons are generated one section at a time. Beginner, Detailed, Practical, and Examination styles change the system instruction without resetting the session. Beyond the Classroom and contextual companion responses use the same selected topic.

## Guest sessions and saved progress

Guest activity is stored in Laravel's server-side session under `open_learning.active` and expires after the configured lifetime. It includes selection, lesson sections, conversation, Beyond the Classroom content, and assessment state. It is not inserted into permanent Open Learning tables.

Students, parents, and staff roles listed in `config/open-learning.php` can explicitly save a sanitized snapshot to `open_learning_records`. Every read/update is constrained to the authenticated `user_id` and protected by `OpenLearningRecordPolicy`. A claimed session is discarded if a different authenticated account later uses the same browser session.

## Assessments

Expected answers and marking guides stay in server-side session state and are removed from the Livewire view model until submission. Multiple choice, true/false, and fill-gap questions are marked deterministically in Laravel. Short answer, theory, and calculation responses are evaluated against the stored expected answer and marking guide through the configured AI provider. Scores are calculated server-side. A random submission token plus `submitted_at` prevents duplicate submission.

## Cost, safety, and privacy controls

- per-minute and per-session limits keyed by session and IP;
- maximum learner-message and conversation-history sizes;
- existing provider timeouts and retry policy;
- provider usage/failure records in `class_pilot_usage` using a one-way session hash;
- server-side curriculum ID re-resolution on every learning action;
- no exposure of private student records, provider keys, internal prompts, or unpublished curriculum;
- friendly failure states that retain completed session work.

Open Learning does not change registration policy, create accounts, alter official results, or let parents access a child's private learning record.

## Configuration

Set the public school explicitly in environments with more than one Watersprings school row:

```dotenv
OPEN_LEARNING_SCHOOL_ID=1
OPEN_LEARNING_SESSION_LIFETIME=180
OPEN_LEARNING_REQUESTS_PER_MINUTE=8
OPEN_LEARNING_REQUESTS_PER_SESSION=60
OPEN_LEARNING_MAX_MESSAGE_CHARACTERS=1200
OPEN_LEARNING_MAX_HISTORY_MESSAGES=12
```

If `OPEN_LEARNING_SCHOOL_ID` is absent, the resolver selects the first Watersprings school with both a current academic year and current semester. Explicit configuration is recommended for production.

## Deployment

1. Configure the public school and Groq variables.
2. Run `php artisan migrate --force`.
3. Clear cached configuration with `php artisan optimize:clear` (or rebuild deployment caches).
4. Build frontend assets with `npm run build`.
5. Publish at least one scheme with topics for the current academic year through the existing curriculum dashboard.

Automated tests must use a dedicated database named `testing` or ending in `_test`/`_testing`. They mock the AI provider and must never call Groq.
