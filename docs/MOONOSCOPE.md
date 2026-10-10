# Moonoscope (List 6)

## Content provenance

- `C:/Git/psychic-chat-reference/Moonoscope_by_Intuition_Island.pdf`: introduction, Why the Moon explanation, how readings are born, and disclaimer. Public copy is transcribed from this document. Interface instructions/privacy explanations are implementation copy, not additional client-supplied readings.
- `Moonoscope_Full_Instructions.pdf`: Appendix A calculation design, sections 4–6 finder/houses/writer rules, section 7 examples, section 8 editorial checklist. `Moonoscope Writeup for website and pdf for creation.eml` carries the supplied materials; its plain body has no additional substantive copy.
- Only three template messages were supplied, as illustrative examples (mapped to houses 5, 6 and 11). Nine entries deliberately remain blank. No invented complete library and no readings published by installation. Client/admin must supply or approve remaining text.

## Entry points and behavior

- `/forecast` now serves Moonoscope publicly for guests and all account roles. Original forecast articles remain in a paginated archive, with their existing admin editor. Existing route names remain compatible.
- `FORECAST_ENABLED=false` hides navigation and rejects direct public/admin Moonoscope routes. The saved setting is not changed by installation.
- Admin Settings → Moonoscope: Manual (default), Templates or AI-assisted. Mode changes affect only new dates. Calculate/prepare a date, edit 12 messages, preview, check the editorial checklist, then publish. Save draft never changes the published snapshot. No automatic publishing or scheduled AI charges.
- One record per Manila date; future approved sets appear on their date. No stale previous-day reading shown as today's. The 6 AM timestamp is the astronomical reference, not a mandatory publishing time.
- Draft generation refuses to overwrite existing dates. Version checks reject stale editor writes. All admin changes audited. Daily sets and original articles paginated.
- The admin can copy the exact calculated prompt for external manual drafting as the reference suggests. This sends nothing automatically.

## Calculations and privacy

Astronomy Engine (MIT, Don Cross) is a production npm dependency; keep its bundled LICENSE on deployment. Browser and server use the same module. Geocentric tropical ecliptic longitude, true equinox of date; houses counted from natal Moon sign per reference formula. Daily phase uses the reference's eight angular sectors, not exact phase-event timing.

The finder runs locally in the browser and reuses only the signed-in visitor's birthdate. Time/city/time zone and result are not saved or sent to the server or AI. No new personal fields added. Recognized city shortcuts suggest zones; users confirm the IANA zone. Unknown cities use manual zone selection; there is no third-party geocoder.

Historical IANA rules come from browser/Node Intl. Nonexistent clock times are rejected; repeated clock times show both possibilities. Unknown birth times check the full local day, display possible signs on transition days, and do not expose a misleading noon degree. Ingress times are approximate; imprecise birth times near boundaries can change results. Supported dates 1900–2100 (admin daily sets through 2100-12-30). Calculations do not establish predictive scientific validity.

## AI configuration

`MOONOSCOPE_NODE_BINARY=node` must resolve for the PHP service account (or use an absolute Node executable path).
`MOONOSCOPE_ANTHROPIC_KEY` and `MOONOSCOPE_ANTHROPIC_MODEL` enable Claude drafting. Do not put credentials in Vue or admin props. No provider/model selected or secret copied from other features. Manual/templates need no AI key. API mode remains selectable but generation is disabled until configured.

Only calculated date, Moon information, house themes and fixed writing rules are sent to `https://api.anthropic.com/v1/messages`. Admin explicitly authorizes generation and provider charges. No automatic retries/charges on failure; no raw provider errors exposed. JSON must contain all 12 ordered signs, distinct text, at most 35 whitespace-delimited words and one/two sentences before publication. Positivity, originality and no promises require human review; semantic compliance cannot be guaranteed by length checks.

## Deployment

Back up the database; install dependencies (`npm ci`, retaining production `astronomy-engine` on the server), run `php artisan migrate`, build assets, and ensure Node is available to PHP. Usual Laravel config cache refresh applies after environment changes. No Forge deployment performed by this task. Local upgrade saved a pre-migration SQLite backup in `storage/app/pre-moonoscope-*.sqlite`.

## Verification

- `node tests/MoonoscopeCheck.mjs`: reference examples (Aries 17.14°, 2026-10-07 Leo→Virgo 10:52 Manila), house mapping, unknown times, DST gaps/folds, invalid input.
- `php tests/MoonoscopeWorkflowCheck.php`: isolated SQLite, visibility, own-birthdate prefill, admin restrictions, mode preservation, no overwrite, publishing snapshot/version protections, word/sign/duplicate checks, mocked provider success/failure and no birth-detail transmission. No live AI calls.

Dependency documentation: https://github.com/cosinekitty/astronomy/tree/master/source/js ; provider request format: https://platform.claude.com/docs/en/api/messages/create .
