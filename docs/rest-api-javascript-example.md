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

    // "2026-07-19 11:00 – 16:00" for a same-day event; spans the dates when the
    // event runs across days ("2026-12-31 12:00 – 2027-01-02 13:00").
    const formatWhen = (m) => {
      const start = [m.event_start_date, m.event_start_time].filter(Boolean).join(' ');
      let end = '';
      if (m.event_end_date && m.event_end_date !== m.event_start_date) {
        end = [m.event_end_date, m.event_end_time].filter(Boolean).join(' ');
      } else if (m.event_end_time) {
        end = m.event_end_time; // same day — just the closing time
      }
      return end ? `${start} – ${end}` : start;
    };

    // Full location: venue name, street address, then any extra details.
    const formatWhere = (m) =>
      [m.location_name, m.location_address, m.location_details]
        .filter(Boolean)
        .join(' · ');

    container.innerHTML = `
      <h2>${serviceBody.name} — ${tag.name} events (${pagination.total})</h2>
      <ul>
        ${events.map((event) => {
          const m = event.meta;
          // title comes back as { rendered: '...' }.
          return `<li>
            <strong>${event.title.rendered}</strong><br>
            <small>
              When: ${formatWhen(m)}<br>
              Where: ${formatWhere(m) || 'Location TBD'}
            </small>
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
