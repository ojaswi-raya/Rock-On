# RockOn — PHP version

> “Bored? Meet new people. Socialize!!”

The working web interface of **RockOn**. It is built from the specification, UML diagrams and Figma mockups in our *Web Application Design* report (Smriti G C, Karl, Ojaswi Rayamajhi · Software Architecture, fall 2026).

It is written in **plain PHP 8** with no framework and no Composer. The pages are rendered on the server, PHP sessions handle login, and a small vanilla JavaScript file adds the interactive parts. The data is kept in a JSON file, so there is **no database to set up**.

---

## Run it

**Option A — PHP's built-in server** (PHP 8.0 or newer):

```bash
cd rockon-php
php -S localhost:8000 router.php
```

Then open http://localhost:8000.

**Option B — XAMPP / MAMP / WAMP**

1. Copy the `rockon-php` folder into `htdocs/`.
2. Start Apache.
3. Open http://localhost/rockon-php/.

On first run the app creates `data/rockon.json` from the seed data. **The `data/` and `assets/uploads/` folders must be writable by PHP.** On macOS/Linux, run `chmod -R 775 data assets/uploads` if needed.

### Demo accounts

All demo accounts use the password **`rockon-demo-2026`**.

| Actor | Email | What to try |
|---|---|---|
| Member / Host | `maya@rockon.app` | Dashboard, accept requests, edit/cancel, rate the completed Street Art Tour |
| Member | `ojaswi@rockon.app` | Request to join, chat once accepted, leave, report, block |
| Administrator | `karl@rockon.app` | Report queue, remove listing, suspend account, categories, accounts |

A **demo bar** under the navigation lets you switch accounts in one click. **Reset demo data** restores the seed data.

Seed dates are set relative to *today*. Whenever you run it, there are upcoming plans, a completed activity whose 7-day rating window is still open, and an older one whose window has closed.

### Suggested walkthrough (report §4.4 navigation flow)

1. As a **guest**, open *Explore*, then *Sunrise Park Walk*, then **Log in to request to join**.
2. Log in as **Ojaswi**. You are brought back to the activity. Send the request. The chat stays locked.
3. Switch to **Maya**. The *Dashboard* shows the request with Ojaswi's reputation. **Accept**.
4. Switch back to **Ojaswi**. A notification says you're in, and the chat is now open.
5. As **Maya**, open *Rate participants* on *Street Art Tour* and rate Ojaswi. Ojaswi had already rated Maya, so **both ratings are released** and Maya's reputation updates.
6. As **Ojaswi**, report an activity. As **Karl**, open it in the queue and choose *Remove content*, then *Confirm removal*.

---

## Project structure

```
rockon-php/
├── index.php            Home (guests)
├── explore.php          Screen 1 — list + map, filters
├── login.php            Screen 2a
├── register.php         Screen 2b
├── dashboard.php        Screen 3 — Hosting / Joined, requests, notifications
├── activity-form.php    Screen 4 — create & edit (?id=…)
├── activity.php         Screen 5 — detail, join, chat (?id=…)
├── rate.php             Screen 6 — rate participants (?id=…)
├── profile.php          Screen 7 — public profile; ?id=me = your editor
├── community.php        Member directory
├── notifications.php    All notifications
├── about.php, 404.php
├── action.php           Handles small POST actions (join, accept, chat, report, block…)
├── admin/
│   ├── index.php        Screen 8 — report queue + moderation
│   ├── categories.php
│   └── accounts.php
├── includes/
│   ├── bootstrap.php    Sessions, CSRF, URL helpers, flash messages, auth guards
│   ├── store.php        JSON data store + service layer (one function per use case)
│   ├── rules.php        Domain rules (eligibility, reputation, capacity…), tagged FR/NFR
│   ├── seed.php         Demo data shaped like the class diagram
│   ├── components.php   Layout (actor-specific nav), avatar, dialogs, form helpers
│   ├── partials.php     Activity card, SVG map, join-request list
│   ├── dates.php, places.php
├── assets/
│   ├── css/styles.css   Design tokens from the Figma mockups
│   ├── js/app.js        Map zoom/pan, dialogs, live preview, menus (no libraries)
│   ├── img/             Photos and avatars cropped from our mockups
│   ├── fonts/           Outfit + Inter (self-hosted, works offline)
│   └── uploads/         Photos uploaded by users
├── data/                rockon.json (created on first run; not reachable from the web)
├── router.php           Only for `php -S`
└── .htaccess            Only for Apache
```

**Request flow.** Every page includes `includes/bootstrap.php`. Pages that change data use **Post/Redirect/Get**. A form posts to the page itself (login, register, create, rate, profile) or to `action.php`. The service function in `store.php` checks the rules and saves. The user is then redirected with a flash message, so refreshing never re-submits a form.

---

## Requirement coverage

| Req. | Where | Notes |
|---|---|---|
| FR-01 Register / log in, 18+ | `register.php`, `login.php` | 4-field sign-up, ≥12-char password, required 18+ checkbox, form keeps your input after an error; one generic login error that never says which field was wrong; Caps Lock hint |
| FR-02 Edit profile | `profile.php?id=me` | Photo upload, bio, interest chips, languages |
| FR-03 Public profile + reputation | `profile.php?id=…` | Linked from activities and join requests |
| FR-04 Create activity | `activity-form.php` | All fields, live preview, host checklist, photo upload, validation (end after start, future date, capacity includes host) |
| FR-05 List + map, filters | `explore.php` | Search, category, date and distance filters (saved in the URL); list ↔ map highlighting; zoom/pan; List/Map toggle on phones |
| FR-06 Join / request / leave | `activity.php` | Open joining confirms right away; host approval creates a pending request; withdraw or leave |
| FR-07 Accept / decline | Dashboard, activity page | Capacity is checked **on acceptance** |
| FR-08 Edit / cancel, notify | `activity-form.php?id=…`, cancel dialog | Changing time or place notifies participants; cancelling sends an optional note |
| FR-09 Group chat | `activity.php` | Locked until accepted; only accepted participants can read or send |
| FR-10 Categories incl. Weird & Fun | Create form, Explore, `admin/categories.php` | Admins can add, rename and delete categories |
| FR-11, FR-12 Rate participants | `rate.php` | 1–5 on punctuality, respect and fun (real radio buttons, so they work with the keyboard) plus a comment; you can't rate yourself |
| FR-13 Reputation + badge | `profile.php` | Calculated (not stored) mean of released ratings, rounded to 1 decimal; bars; badge |
| FR-14 Minimum reputation | Create form, join card | Members below it are told why they can't join |
| FR-15 Report user / activity | Profile, activity page | Reason plus details → admin queue |
| FR-16 Block | `profile.php` | Hides the blocked host's activities, stops them joining yours, declines their pending requests |
| FR-17 Moderation | `admin/` | Queue filters; remove / suspend / close with a confirmation step ("Keep reviewing"); reinstate |
| FR-18 Notifications | Nav counter, dashboard, `notifications.php` | Requests, acceptances, changes, cancellations, rating prompts, released ratings, moderation |
| NFR-01 Security | `bootstrap.php`, `store.php` | `password_hash`/`password_verify`, CSRF token on every POST, session ID regenerated on login, HttpOnly + SameSite cookies, all output escaped with `h()`, `data/` and `includes/` blocked from the web, uploads checked with `getimagesize` and never executed. HTTPS is a hosting setting. |
| NFR-02 7-day window, attendees only | `rules.php → rating_eligibility()` | |
| NFR-03 Hidden until mutual | `store.php → svc_submit_rating()` | `isReleased` turns true for both ratings when the second arrives |
| NFR-04 Map performance | `partials.php → map_view()` | Plain SVG pins; pins in the same area are fanned out |
| NFR-05 Create in < 2 min | Create form | One screen, sensible defaults |
| NFR-06 Phone / tablet / desktop | `styles.css` | Breakpoints at 1000px and 760px; hamburger nav |
| NFR-07 WCAG 2.1 AA basics | Throughout | Skip link, visible focus, labelled inputs, `aria-invalid` + error text, focus moves to the first error, keyboard map pins, native `<dialog>`, alt text, reduced motion |
| NFR-09 Daily backup | — | The whole state is one file (`data/rockon.json`), so backing it up is a one-line cron job |

**Without JavaScript** you can still browse, filter, log in, register, join, accept, chat, rate and edit. JavaScript is only needed for the confirmation dialogs (cancel, leave, block, report) and for map zoom and pop-ups.

## How the code maps to the class diagram

- `User` (abstract) is a record with `kind` = `member` or `admin`.
- Host is a **role, not a class**. `Activity.hostId` points to a Member, and the host also has a `Participation`, so "11 / 16 going" includes the host.
- `Participation.status` goes from `pending` to `accepted` (or `declined` / `left`). `attended` is worked out once the activity has ended.
- `ActivityStatus.completed` is worked out from the end time. `cancelled` is stored.
- `reputationScore` is never stored. Seed members carry a `history` summary that stands in for ratings released before the demo.
- `Rating.isReleased` stays false until the reverse rating exists.

## Moving to MySQL

All reads go through `db()` and the lookup functions in `rules.php`. All writes go through the `svc_*` functions in `store.php`. To use MySQL, rewrite those functions with PDO queries (one table per class in the diagram). The pages stay the same.
