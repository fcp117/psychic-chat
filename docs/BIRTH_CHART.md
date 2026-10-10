# Birth Chart Studio (List 7)

## Availability

Free for verified, active registered clients, Spiritual Coaches and admins. Guests go through login. No minute deduction, purchase, subscription or AI API call. Find it in the account menu (including mobile) under **Your birth chart**, or through Moonoscope. Admin Settings has a **Birth charts** section.

`BIRTH_CHART_ENABLED=true` is the default. False hides member links and blocks direct report access. Admin editing remains available while the public generator is disabled. Independent of `FORECAST_ENABLED`.

Access is centralized in `App/Services/BirthChartAccess.php`. A later paid version needs a separate approved product, server-verified payment entitlement and server-side protected report delivery; changing a label or front-end check is not paid access enforcement. Current browser calculation is deliberately free.

## Reference and interpretation provenance

Client sources: `C:/Git/psychic-chat-reference/Sample Birth Chart.eml` and its `Sample-Birth-Chart-PDF.pdf`. The sample supplies a branded wheel, Sun/Moon/Rising, 14 positions, whole-sign houses, elements/modes, aspects, closing blessing, session invitation and reflection disclaimer. The email body adds no technical requirements.

The sample contains only one Cancer Sun / Aries Moon interpretation sentence. Its two clauses are seeded as separate entries. There are 36 editable entries (12 signs for Sun, Moon and Rising), not a fabricated complete client-approved reading library. Missing text is clearly marked on screen/PDF. Admins edit plain text, confirm editorial approval, and save. Blank entries are omitted. Versions prevent overwriting another editor's changes and edits are audited. Existing PDFs do not change. No AI interpretation provider is enabled or required.

## Calculation contract

Astronomy Engine 2.1.19: tropical geocentric longitude, true equinox of date. Sun and planets use apparent GeoVector/Ecliptic; Moon uses EclipticGeoMoon. Lunar nodes use the osculating orbital-plane intersection in the true ecliptic frame (not the mean node). Ascendant is the eastern horizon intersection using latitude, longitude and sidereal time; Midheaven uses obliquity and local sidereal time. Whole-sign houses follow the Rising sign. Retrograde is the sign of longitude change across a two-hour centered interval. Elements and modes count the ten planets, excluding nodes/angles.

The reference does not specify aspect orbs. Explicit convention: conjunction/opposition/trine 8 degrees, square/sextile 6 degrees; exclude nodes and angle-to-angle aspects, sort by orb. These yield the sample's 19 aspects. Small rounding differences can arise from approximate city-center coordinates.

The sample's 1990-07-15 10:30 Manila uses historical UTC+9, producing 01:30 UTC. Do not force today's UTC+8 onto historical birthdays. Browser Intl/IANA rules handle historical offsets. Nonexistent clock times are rejected; repeated times require choosing earlier/later. Valid dates start at 1900; future dates are rejected. Latitude is restricted to between -89 and 89 degrees; locations near the poles need specialist review.

Unknown time: noon-reference planetary signs only, Moon possibilities across the full local date, no definite Rising/Midheaven, houses, aspects, element/mode totals or personalized prose. Degree and retrograde displays are omitted. No misleading exact chart wheel is shown.

## Privacy and exports

Only the existing account birthday/name and approved generic interpretations are supplied by the server. Submitted time, city, coordinates and resulting chart remain in page memory: no birth-report database record, localStorage, external geocoding or AI request. Users explicitly confirm the notice and birthplace coordinates. Approximate city shortcuts are optional; manual coordinates/time zones are supported. Reloading discards the chart. Downloaded PDFs contain personal details and are the user's responsibility.

The PDF renderer loads only when Download is selected and uses a locally bundled Noto Sans font, no CDN. The on-screen UI supports Unicode; PDF names/cities/interpretations currently require characters supported by the Latin font. Unsupported glyphs produce an explicit error instead of a damaged PDF. Keep font, pdf-lib, fontkit and Astronomy Engine licenses with dependencies.

Known-time sample: four-page PDF. Long prose continues onto additional pages. Unknown-time report omits the empty aspects page. The page and PDF share a reference-style wheel with original vector zodiac/planet symbols, whole-sign house numbers, degree ticks, AC/MC axes, degrees, retrograde marks and colored aspects. Exact coordinates stay anchored to tick marks; crowded symbol labels are displaced with leader lines. Increasing longitude runs counterclockwise from the Ascendant at the left, matching the reference. A clearly labelled unknown-time notice replaces the wheel when birth time is missing. The session invitation links to the same site's bookings page.

## Local installation and deployment

Install lockfile dependencies (`npm ci`), run `php artisan migrate`, then `npm run build`. Clear cached configuration after environment changes and restart any long-running PHP process. The local SQLite database was backed up before this migration. This work does not deploy to Forge or modify live payment settings.

## Verification

- `node tests/BirthChartCheck.mjs [pdf-output-directory]`: exact sample positions/houses, retrogrades, elements/modes, aspect count, historical offsets, unknown time, input rejection, PDF samples.
- `php tests/BirthChartWorkflowCheck.php`: isolated SQLite migration, seeded content, free access for all three roles, own birthday, unchanged account balance, guest/suspension/role protection, editorial approval, optimistic locking, audit and feature flag.
- Existing Moonoscope calculation/workflow checks remain passing.
- Production build passes. PDF sample/unknown/long-text pages rendered and visually reviewed. Local isolated browser: login protection, menu entry, reference chart generation, PDF download and admin save confirmed. No real user accounts or reports were created by UI testing.
