# Pulling filtered events with JavaScript

A worked example of reading events from a Mayo Events Manager site over its public
REST API and filtering them — here, events for the **Bergen** service body that are
tagged **Paid** on [nanj.org](https://www.nanj.org/events-and-activities/).

Everything below is plain `fetch` + DOM, no framework and no build step, so you can
paste it into a `<script>` tag on any page (or a browser console) and adapt it.

## TL;DR

```
GET https://www.nanj.org/wp-json/event-manager/v1/events?service_body=3&tags=paid
```

The tricky part is that `service_body` wants a **numeric BMLT id** (`3`), not a name
like "Bergen", while `tags` wants a **slug** (`paid`), not the label "Paid". The
example resolves both from human-readable names using the `/events/facets` endpoint,
so you configure names and never hardcode a fragile id.

## The endpoints

Both are public and read-only — no authentication, nonce, or cookies required.

| Endpoint | Purpose |
|---|---|
| `GET /wp-json/event-manager/v1/events` | The event list (supports the filters below). |
| `GET /wp-json/event-manager/v1/events/facets` | The distinct filter values present in the data: `service_bodies: [{ id, name }]`, `tags: [{ slug, name }]`, `categories`, `event_types`. Use it to map a name/label to the id/slug the events endpoint expects. |

### Query parameters used here

| Param | Value | Notes |
|---|---|---|
| `service_body` | numeric BMLT id(s), e.g. `3` | Comma-separated for several (`3,7`) = match any. **Not** a name. |
| `tags` | tag **slug**(s), e.g. `paid` | Comma-separated = match any. Prefix a slug with `-` to *exclude* it (`-paid`). **Not** the display label. |

Other handy params the endpoint accepts: `per_page`, `page`, `order` (`ASC`/`DESC`),
`event_type` (`Service` / `Activity` / `Celebration`, `-` prefix excludes),
`categories`, `start_date` + `end_date` (`YYYY-MM-DD` range).

## The example

```html
<style>
  #events {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #1a1a1a;
    max-width: 520px;
  }
  .mayo-heading { font-size: 1.25rem; margin: 0 0 1rem; }
  .mayo-heading span { color: #6b7280; font-weight: 400; }
  .mayo-events { list-style: none; margin: 0; padding: 0; display: grid; gap: 1rem; }
  .mayo-event {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    transition: box-shadow 0.15s ease;
  }
  .mayo-event:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
  .mayo-flyer-link { display: block; align-self: center; }
  .mayo-flyer {
    display: block;
    width: 100%;
    max-width: 440px;      /* ~5x the old 88px thumbnail */
    height: auto;
    border-radius: 8px;
    background: #f3f4f6;
    transition: opacity 0.15s ease;
  }
  .mayo-flyer-link:hover .mayo-flyer { opacity: 0.9; }
  .mayo-event__body { display: grid; gap: 0.2rem; }
  .mayo-event__title { font-size: 1.05rem; margin: 0; }
  .mayo-event__when { margin: 0; color: #2563eb; font-weight: 600; font-size: 0.9rem; }
  .mayo-event__where { margin: 0; color: #6b7280; font-size: 0.9rem; }
  .mayo-event__map { color: #2563eb; text-decoration: none; }
  .mayo-event__map:hover { text-decoration: underline; }
</style>

<div id="events">Loading events…</div>

<script>
(async () => {
  // --- Configuration — edit these -----------------------------------------
  const SOURCE = 'https://www.nanj.org';   // the Mayo-powered site to read from
  const SERVICE_BODY_NAME = 'Bergen';       // service body to show (a name, not an id)
  const TAG = 'Paid';                       // tag to filter by — change me anytime
  // ------------------------------------------------------------------------

  const API = `${SOURCE}/wp-json/event-manager/v1`;

  // Cross-origin, public data: no credentials/nonce needed for these GETs.
  const getJSON = async (url) => {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`${res.status} ${res.statusText} for ${url}`);
    return res.json();
  };

  // Case-insensitive lookup that tolerates minor wording differences
  // (e.g. config "Bergen Area" still matches a site that publishes "Bergen").
  const findByName = (list, name, key = 'name') => {
    const want = name.trim().toLowerCase();
    return (
      list.find((item) => (item[key] || '').toLowerCase() === want) ||
      list.find((item) => (item[key] || '').toLowerCase().includes(want)) ||
      null
    );
  };

  try {
    // 1. Resolve the human-readable names to the id / slug the API filters on.
    const facets = await getJSON(`${API}/events/facets`);

    const serviceBody = findByName(facets.service_bodies, SERVICE_BODY_NAME);
    if (!serviceBody) {
      throw new Error(
        `Service body "${SERVICE_BODY_NAME}" not found. Available: ` +
        facets.service_bodies.map((s) => s.name).join(', ')
      );
    }

    const tag = findByName(facets.tags, TAG);
    if (!tag) {
      throw new Error(
        `Tag "${TAG}" not found. Available: ` +
        facets.tags.map((t) => `${t.name} (${t.slug})`).join(', ')
      );
    }

    // 2. Fetch the filtered, upcoming events.
    const params = new URLSearchParams({
      service_body: serviceBody.id, // numeric BMLT id, e.g. "3"
      tags: tag.slug,               // slug, e.g. "paid"
      per_page: '50',
      order: 'ASC',                 // soonest first
    });
    const { events, pagination } = await getJSON(`${API}/events?${params}`);

    // 3. Render.
    const container = document.getElementById('events');
    if (!events.length) {
      container.textContent =
        `No "${TAG}" events found for ${serviceBody.name}.`;
      return;
    }

    const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'];

    // "2026-07-19" -> "Saturday, July 19, 2026". Split into parts rather than
    // new Date('2026-07-19'), which parses as UTC and can shift the weekday.
    const formatDate = (iso) => {
      if (!iso) return '';
      const [y, mo, d] = iso.split('-').map(Number);
      return `${WEEKDAYS[new Date(y, mo - 1, d).getDay()]}, ${MONTHS[mo - 1]} ${d}, ${y}`;
    };

    // "16:00" -> "4:00 PM". Times are stored as local wall-clock for the event's
    // timezone, so just reformat — no timezone conversion.
    const formatTime = (hhmm) => {
      if (!hhmm) return '';
      const [h, min] = hhmm.split(':').map(Number);
      const hour12 = h % 12 === 0 ? 12 : h % 12;
      return `${hour12}:${String(min).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
    };

    // Same day: "Saturday, July 19, 2026 · 11:00 AM – 4:00 PM".
    // Spanning days: "… Dec 31 → … Jan 2".
    const formatWhen = (m) => {
      const sameDay = !m.event_end_date || m.event_end_date === m.event_start_date;
      const startTime = formatTime(m.event_start_time);
      const endTime = formatTime(m.event_end_time);
      if (sameDay) {
        const times = [startTime, endTime].filter(Boolean).join(' – ');
        return [formatDate(m.event_start_date), times].filter(Boolean).join(' · ');
      }
      const start = [formatDate(m.event_start_date), startTime].filter(Boolean).join(' · ');
      const end = [formatDate(m.event_end_date), endTime].filter(Boolean).join(' · ');
      return `${start} → ${end}`;
    };

    // Google Maps search link for an address string.
    const mapsUrl = (query) =>
      `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;

    // Location as HTML: venue name, a clickable street address (opens Google
    // Maps in a new tab), then any extra details.
    const formatWhere = (m) => {
      const parts = [];
      if (m.location_name) parts.push(m.location_name);
      if (m.location_address) {
        // Query on name + address for a more reliable map pin.
        const query = [m.location_name, m.location_address].filter(Boolean).join(', ');
        parts.push(
          `<a class="mayo-event__map" href="${mapsUrl(query)}" target="_blank" rel="noopener">${m.location_address}</a>`
        );
      }
      if (m.location_details) parts.push(m.location_details);
      return parts.join(' · ');
    };

    container.innerHTML = `
      <h2 class="mayo-heading">${serviceBody.name} — ${tag.name} events <span>(${pagination.total})</span></h2>
      <ul class="mayo-events">
        ${events.map((event) => {
          const m = event.meta;
          const where = formatWhere(m);
          // featured_image is the event flyer; it can be null, so guard it.
          // Wrapped in a link so clicking opens the full-size flyer in a new tab.
          const flyer = event.featured_image
            ? `<a class="mayo-flyer-link" href="${event.featured_image}" target="_blank" rel="noopener">
                 <img class="mayo-flyer" src="${event.featured_image}" alt="${event.title.rendered} flyer" loading="lazy">
               </a>`
            : '';
          return `
            <li class="mayo-event">
              ${flyer}
              <div class="mayo-event__body">
                <h3 class="mayo-event__title">${event.title.rendered}</h3>
                <p class="mayo-event__when">${formatWhen(m)}</p>
                ${where ? `<p class="mayo-event__where">${where}</p>` : ''}
              </div>
            </li>`;
        }).join('')}
      </ul>`;
  } catch (err) {
    document.getElementById('events').textContent = `Could not load events: ${err.message}`;
    console.error(err);
  }
})();
</script>
```

## What comes back

`/events` returns `{ events, sources, pagination }`. Each event looks like:

```json
{
  "id": 123,
  "title": { "rendered": "BBQ Speaker Jam – Recovery on the Grill" },
  "link": "https://www.nanj.org/mayo/bbq-speaker-jam-recovery-on-the-grill/",
  "featured_image": "https://www.nanj.org/wp-content/uploads/…",
  "meta": {
    "event_start_date": "2026-07-19",
    "event_start_time": "11:00",
    "event_end_date": "2026-07-19",
    "timezone": "America/New_York",
    "event_type": "Activity",
    "service_body": "3",
    "location_name": "Van Saun Park",
    "location_address": "…"
  },
  "categories": [{ "id": 5, "name": "…", "slug": "…", "link": "…" }],
  "tags": [{ "id": 9, "name": "Paid", "slug": "paid", "link": "…" }]
}
```

`pagination` is `{ total, per_page, current_page, total_pages }` — increment `page`
to walk further if `total_pages > 1`.

## Changing the filters

- **Different tag:** change `TAG` (e.g. `'Out of State'`). It's resolved to a slug for
  you, so the label spelling from the site is all you need.
- **Different / additional service body:** change `SERVICE_BODY_NAME`. To match several
  ids, pass a comma-joined list as `service_body` (e.g. `'3,7'`).
- **Exclude a tag instead of requiring it:** pass the slug with a leading `-`
  (e.g. `tags: '-paid'` for everything *except* paid events).
- **Skip a filter entirely:** just omit that param from the `URLSearchParams`.

## Notes

- These GET endpoints are public, so a browser `fetch()` from another origin works —
  WordPress reflects the request `Origin` in its CORS response. Do **not** send
  `credentials`; none are needed for reads.
- The `/events/facets` values are derived from the events actually present, so a
  service body or tag only appears once at least one event uses it. If a lookup fails,
  the errors above print the exact names/slugs the site publishes.
