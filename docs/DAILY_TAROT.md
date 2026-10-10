# Daily tarot

## Where to use it

- Home: daily-card promotion inside the animated carousel, for guests and all account roles; there is no separate section below the hero.
- Reading page: `/daily-tarot`.
- Admin Settings → Tarot cards: `/admin/tarot-cards` (verified, active administrators only).
- Current library: 14 Violet Tides name-and-image drafts. The five original starters are archived; old readings remain unchanged. Client must complete and activate cards before new draws become available. Upright readings only; no zodiac or birthdate calculations. See `VIOLET_TIDES_AND_MEDIA.md`.

## Stored data

- `tarot_cards`: editable card name, category, keywords, meaning, guidance, reflection, image path, active status and archive flag. Inactive drafts may have blank text; activation requires complete content.
- `tarot_draws`: account ID or hashed anonymous browser identifier, selected card, date in Asia/Manila, timestamp, immutable reading snapshot and retry token.
- `tarot_readers`: stable per-reader database lock rows used to serialize claims and draws.
- Starter artwork: `public/images/tarot/*.png` retained for existing readings. Current supplied artwork: `public/images/tarot/violet-tides/*.png`.
- Uploaded artwork: `storage/app/public/tarot/`, exposed through `public/storage`. Use shared persistent storage in Forge release deployments.

Card replacement creates a new file; old files remain because previous readings may reference them. Deactivate cards rather than deleting them. Vue renders text as plain text, not HTML. Uploads accept JPG, PNG and WebP only, up to 5 MB and 4096px per side. Admin saves are audited.

## Draw rules

- Guests: one draw per Philippine calendar day, identified by an encrypted, HTTP-only, SameSite=Lax cookie with a one-year lifetime. Guest limits can be bypassed by clearing cookies or changing browsers; they are not identity verification.
- Any registered role: three draws per day, shared across devices. Suspended accounts are blocked by existing middleware. Email verification is required for admin management, not for this free feature.
- Guest draws transfer on registration or the first signed-in visit to the reading page. They count against the member's three. Draws already owned by another account never transfer. Claimed readings are hidden from the guest view after logout.
- Midnight Asia/Manila resets the allowance. Reload an open page to see the new day's results.
- Selection is server-side random. No same-day repeats while unseen active cards remain; if the active deck is exhausted, repeats are possible.
- The three card backs are a choice/reveal interaction, not a three-card spread. A choice consumes one draw. Merely shuffling does not.
- Reopening today's readings costs no draws. Records from earlier dates remain in the database; this first version displays today's readings only.
- Retries use a unique request token; database transactions and reader locks protect against double clicks and simultaneous requests.
- No credits are charged, no AI API is called, and no question text is collected.

## Local setup / Forge deployment

The local database was migrated and seeded as part of implementation. For a new environment, deploy the code and artwork, then run:

```sh
php artisan migrate --force
php artisan db:seed --class=TarotCardSeeder --force
php artisan storage:link
php artisan optimize:clear
npm run build
```

Run `storage:link` only if the deployment does not already provide the public storage link. Do not use `migrate:fresh` or run unrelated demo seeders. The tarot seeder is idempotent: existing cards and admin edits are never overwritten. No new environment variables or paid API credentials are required. Local admin edits and uploads do not automatically transfer to production.

Before public launch, have the client complete and approve the Violet Tides interpretations and finalize the existing draft privacy notice, including retention of anonymous draws. The UI describes these as automated reflections, not predictions or professional advice.

## Checks

```sh
php tests/TarotWorkflowCheck.php
php tests/AuthWorkflowCheck.php
php tests/ModerationCheck.php
npm run build
```

`tests/Feature/DailyTarotTest.php` additionally supplies PHPUnit HTTP/feature tests for environments with development dependencies installed. This checkout did not have PHPUnit installed, so these tests were not executed here; the standalone workflow checks were used instead. The standalone check creates temporary database/upload files, including concurrent worker requests, and removes its own fixtures afterwards.

## Artwork prompts

Generated with the built-in image-generation tool. Each card used the following shared prompt plus its individual subject below:

> Use case: stylized-concept. Asset: original tarot illustration for Intuition Island website, single portrait 2:3 card artwork. [SUBJECT] Cohesive elegant illustrated deck, painterly etched details, deep aubergine and midnight violet, muted lavender and warm antique gold highlights, fine celestial gold border inset from edges. Full-bleed artwork, centered subject, luxurious but calm spiritual reflection aesthetic. No text, no letters, no numbers, no watermark, no existing deck imitation. Opaque background.

- `the-fool.png`: The Fool: a clothed traveler with a small satchel and white flower stepping toward a sunlit mountain path, hopeful beginnings, safe poetic composition.
- `strength.png`: Strength: a serene fully clothed woman gently resting her hand on a calm lion amid flowers, compassionate courage.
- `the-star.png`: The Star: a luminous eight-point star reflected in a quiet pool, a fully clothed figure pouring water from a small vessel, hopeful renewal.
- `the-sun.png`: The Sun: a radiant golden sun above abundant sunflowers and a white horse in a peaceful garden, joyful warmth.
- `the-hermit.png`: The Hermit: a cloaked elderly traveler holding a glowing lantern on a mountain trail beneath stars, quiet reflection.
