# Lists 8 and 9: Violet Tides and company media

## Catalogue replacement

The 14 supplied PNGs were copied without editing from `C:/Git/psychic-chat-reference/Part 5VIoletTides/` into `public/images/tarot/violet-tides/`. The ZIP duplicates this same set and is not imported again. Names follow the printed labels: I–X of Tides, Page, Knight, Queen and King of Tides. No Cups mapping or meanings are invented. Category, keywords, meaning, guidance and reflection are blank. All 14 start inactive.

Migration `2026_10_10_160000_replace_starter_tarot_with_violet_tides` archives the five original starter rows and leaves their images and immutable draw snapshots intact. They disappear from the normal admin catalogue and cannot be reactivated through its endpoint. A database backup is the recovery route; no old rows or images are deleted. Subsequent `TarotCardSeeder` runs only add missing Violet Tides drafts and never overwrite edits or reintroduce starters.

Admins may save incomplete inactive drafts. Activation requires name, artwork, category/suit, keywords, meaning, guidance and reflection. Public draw queries also exclude archived/incomplete rows. Until the client completes and activates a card, the existing deck-preparation message is shown and failed attempts consume no draw. Existing daily readings remain readable. Limits remain 1 guest / 3 registered per Manila day, with existing claim, retry, concurrency and animation behavior.

## Shared company links

One footer component is mounted in the shared Inertia wrapper for all account roles and guests, including authentication screens. Home's redundant copyright footer was removed. About has an Etsy call-to-action explaining that external shop purchases are separate from site minutes. Client-supplied links are centralized in `resources/js/lib/company-links.mjs`. External links open a new tab with `noopener noreferrer`; no embedded feeds, trackers, remote images or scripts are added.

Source: `Social Media links and Etsy shop to promote and highlightand link.eml`. Etsy was reachable during review; Facebook/Instagram/Pinterest could not be inspected by the research tool, so the supplied URLs are preserved rather than guessed.

## Music

Source `Ringing sound, music for the site.eml`, attachment `Intuition Calling.mp3`, copied intact to `public/audio/intuition-calling.mp3`. Client-supplied credit: **Intuition Calling — © Lynn Lyric**. Shown on Home and Daily Tarot for guests and every signed-in role. Starts paused, `preload=none`, no looping or auto-resume; play/pause, seek, volume and elapsed/duration controls. Stops on leaving the page. Playback errors are visible and do not block the rest of the page. Mobile devices may use their hardware volume control. No music on chat, payment or admin pages. Existing coach request beep/mute functionality is untouched.

The source does not supply lyrics or a transcript. If the track includes spoken/sung material, obtain an approved transcript/lyrics for an accessible text alternative. Attribution follows the supplied email, not a new ownership/licensing determination.

## Promotional-media shortlist — pending approval, not installed

Source collection: https://www.etsy.com/shop/IntuitionIsland

1. Shop banner, exact discovered asset: https://i.etsystatic.com/21743933/r/isbl/b9e11f/86767033/isbl_1680x420.86767033_q5c98jmy.jpg — possible About/Etsy promotion. Ask client to confirm current branding and provide the original file.
2. Rainbow's shop-owner portrait, exact discovered thumbnail: https://i.etsystatic.com/iusa/98bb65/117056001/iusa_75x75.117056001_ur1e.jpg?version=0 — possible approved biography portrait; request a higher-resolution original. Do not enlarge the 75px thumbnail.

These image URLs were listed by the shop page but the research tool could not fetch their image contents. They are source-identified candidates, not visually approved assets. No candidate was downloaded, hotlinked into the site or published. No specific video could be verified from the blocked social pages; client should supply a post URL and original file. Do not import testimonials, private chat screenshots or customer identities.

## Benchmark boundaries

Reviewed public reference pages: https://www.keen.com/ , https://www.kasamba.com/ , https://www.onedivinesource.com/ , https://starzpsychics.com/ . Retain the site's own design; use compact grouped navigation, clear action/status labels and separation of free reflections from paid coach services. No benchmark pricing, guarantees, branding, testimonials or private screenshot content copied. Existing chat/notes features remain unchanged.

## Deploy and verify

Back up the database, deploy code and public assets, run `php artisan migrate --force`, then `npm run build`. The migration performs the catalogue replacement; no repeated manual import needed. Retain the old public/images/tarot files for saved readings. No new environment variables, paid providers or network services.

Tests: `php tests/VioletTidesCheck.php` and `php tests/TarotWorkflowCheck.php`. The latter now uses a separate five-card test fixture alongside the 14 drafts so concurrency and reading behavior stay covered without activating blank production cards.
