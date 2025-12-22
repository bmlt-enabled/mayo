const ApiDocs = () => {
    const baseUrl = window.location.origin;

    return (
        <div className="wrap mayo-docs">
            <h1>API Documentation</h1>

            <div className="card">
                <h2>Overview</h2>
                <p>The Mayo Events Manager provides a REST API for programmatic access to events and announcements. All endpoints use the namespace <code>event-manager/v1</code>.</p>
                <p><strong>Base URL:</strong> <code>{baseUrl}/wp-json/event-manager/v1</code></p>
            </div>

            <div className="card">
                <h2>Table of Contents</h2>
                <h3>Event Endpoints</h3>
                <ul className="ul-disc">
                    <li><a href="#get-events">GET /events</a> - List events with filtering</li>
                    <li><a href="#get-event-slug">GET /event/{'{slug}'}</a> - Get event by slug</li>
                    <li><a href="#get-event-id">GET /events/{'{id}'}</a> - Get event by ID</li>
                    <li><a href="#search-events">GET /events/search</a> - Search events</li>
                    <li><a href="#post-submit-event">POST /submit-event</a> - Submit a new event</li>
                </ul>
                <h3>Announcement Endpoints</h3>
                <ul className="ul-disc">
                    <li><a href="#get-announcements">GET /announcements</a> - List announcements</li>
                    <li><a href="#get-announcement-id">GET /announcement/{'{id}'}</a> - Get announcement by ID</li>
                </ul>
                <h3>Settings Endpoints</h3>
                <ul className="ul-disc">
                    <li><a href="#get-settings">GET /settings</a> - Get plugin settings</li>
                    <li><a href="#post-settings">POST /settings</a> - Update plugin settings</li>
                </ul>
                <h3>Reference</h3>
                <ul className="ul-disc">
                    <li><a href="#event-meta-fields">Event Meta Fields</a></li>
                    <li><a href="#announcement-response">Announcement Response Object</a></li>
                    <li><a href="#wordpress-rest-api">WordPress REST API</a></li>
                    <li><a href="#usage-examples">Usage Examples</a></li>
                </ul>
            </div>

            <div className="card" id="get-events">
                <h2>GET /events</h2>
                <p>Retrieve a list of events with optional filtering and pagination.</p>

                <h3>Endpoint</h3>
                <pre><code>GET {baseUrl}/wp-json/event-manager/v1/events</code></pre>

                <h3>Query Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Default</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>page</code></td>
                            <td>integer</td>
                            <td>1</td>
                            <td>Page number for pagination</td>
                        </tr>
                        <tr>
                            <td><code>per_page</code></td>
                            <td>integer</td>
                            <td>10</td>
                            <td>Number of events per page</td>
                        </tr>
                        <tr>
                            <td><code>status</code></td>
                            <td>string</td>
                            <td>publish</td>
                            <td>Post status filter (<code>publish</code>, <code>pending</code>)</td>
                        </tr>
                        <tr>
                            <td><code>event_type</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Filter by event type (e.g., <code>Service</code>, <code>Activity</code>)</td>
                        </tr>
                        <tr>
                            <td><code>service_body</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Comma-separated service body IDs (e.g., <code>1,2,3</code>)</td>
                        </tr>
                        <tr>
                            <td><code>categories</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Comma-separated category slugs. Prefix with <code>-</code> to exclude (e.g., <code>meetings,-cancelled</code>)</td>
                        </tr>
                        <tr>
                            <td><code>tags</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Comma-separated tag slugs. Prefix with <code>-</code> to exclude</td>
                        </tr>
                        <tr>
                            <td><code>archive</code></td>
                            <td>boolean</td>
                            <td>false</td>
                            <td>When <code>true</code>, returns only past events</td>
                        </tr>
                        <tr>
                            <td><code>order</code></td>
                            <td>string</td>
                            <td>ASC</td>
                            <td>Sort order: <code>ASC</code> (earliest first) or <code>DESC</code> (latest first)</td>
                        </tr>
                        <tr>
                            <td><code>timezone</code></td>
                            <td>string</td>
                            <td>Server timezone</td>
                            <td>IANA timezone for date filtering (e.g., <code>America/New_York</code>)</td>
                        </tr>
                        <tr>
                            <td><code>start_date</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Filter events starting from this date (YYYY-MM-DD format)</td>
                        </tr>
                        <tr>
                            <td><code>end_date</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Filter events ending before this date (YYYY-MM-DD format)</td>
                        </tr>
                        <tr>
                            <td><code>source_ids</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Comma-separated source IDs. Use <code>local</code> for local events, or external source IDs</td>
                        </tr>
                        <tr>
                            <td><code>relation</code></td>
                            <td>string</td>
                            <td>AND</td>
                            <td>Relation between meta query conditions (<code>AND</code> or <code>OR</code>)</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Example Request</h3>
                <pre><code>{`GET ${baseUrl}/wp-json/event-manager/v1/events?per_page=5&categories=meetings&order=ASC`}</code></pre>

                <h3>Example Response</h3>
                <pre><code>{`{
    "events": [
        {
            "id": 123,
            "title": {
                "rendered": "Monthly Meeting"
            },
            "content": {
                "rendered": "<p>Event description...</p>"
            },
            "link": "${baseUrl}/mayo/monthly-meeting/",
            "meta": {
                "event_start_date": "2024-03-15",
                "event_end_date": "2024-03-15",
                "event_start_time": "19:00:00",
                "event_end_time": "21:00:00",
                "timezone": "America/New_York",
                "event_type": "Service",
                "service_body": "1",
                "location_name": "Community Center",
                "location_address": "123 Main St",
                "location_details": "Room 101"
            },
            "featured_image": "${baseUrl}/wp-content/uploads/2024/03/flyer.jpg",
            "categories": [
                {
                    "id": 5,
                    "name": "Meetings",
                    "slug": "meetings",
                    "link": "${baseUrl}/category/meetings/"
                }
            ],
            "tags": [],
            "source": {
                "id": "local",
                "name": "Local Events",
                "url": "${baseUrl}"
            }
        }
    ],
    "pagination": {
        "total": 25,
        "per_page": 5,
        "current_page": 1,
        "total_pages": 5
    }
}`}</code></pre>
            </div>

            <div className="card" id="get-event-slug">
                <h2>GET /event/{'{slug}'}</h2>
                <p>Retrieve details for a single event by its slug.</p>

                <h3>Endpoint</h3>
                <pre><code>GET {baseUrl}/wp-json/event-manager/v1/event/{'{slug}'}</code></pre>

                <h3>Path Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>slug</code></td>
                            <td>string</td>
                            <td>The event's URL slug</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Example Request</h3>
                <pre><code>{`GET ${baseUrl}/wp-json/event-manager/v1/event/monthly-meeting`}</code></pre>

                <h3>Response</h3>
                <p>Returns a single event object (same structure as events in the list response).</p>

                <h3>Error Response</h3>
                <pre><code>{`{
    "code": "no_event",
    "message": "Event not found",
    "data": {
        "status": 404
    }
}`}</code></pre>
            </div>

            <div className="card" id="get-event-id">
                <h2>GET /events/{'{id}'}</h2>
                <p>Retrieve a single event by its post ID.</p>

                <h3>Endpoint</h3>
                <pre><code>GET {baseUrl}/wp-json/event-manager/v1/events/{'{id}'}</code></pre>

                <h3>Path Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>id</code></td>
                            <td>integer</td>
                            <td>The event's post ID</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Example Request</h3>
                <pre><code>{`GET ${baseUrl}/wp-json/event-manager/v1/events/123`}</code></pre>

                <h3>Example Response</h3>
                <pre><code>{`{
    "id": 123,
    "title": "Monthly Meeting",
    "start_date": "2024-03-15",
    "end_date": "2024-03-15",
    "start_time": "19:00:00",
    "end_time": "21:00:00",
    "permalink": "${baseUrl}/mayo/monthly-meeting/",
    "edit_link": "${baseUrl}/wp-admin/post.php?post=123&action=edit"
}`}</code></pre>

                <h3>Error Response</h3>
                <pre><code>{`{
    "code": "not_found",
    "message": "Event not found",
    "data": {
        "status": 404
    }
}`}</code></pre>
            </div>

            <div className="card" id="search-events">
                <h2>GET /events/search</h2>
                <p>Search events by title. Useful for finding events to link to announcements.</p>

                <h3>Endpoint</h3>
                <pre><code>GET {baseUrl}/wp-json/event-manager/v1/events/search</code></pre>

                <h3>Query Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Default</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>search</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Search term to match against event titles</td>
                        </tr>
                        <tr>
                            <td><code>limit</code></td>
                            <td>integer</td>
                            <td>20</td>
                            <td>Maximum number of results to return</td>
                        </tr>
                        <tr>
                            <td><code>include</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Comma-separated event IDs to fetch specific events</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Example Request</h3>
                <pre><code>{`GET ${baseUrl}/wp-json/event-manager/v1/events/search?search=meeting&limit=10`}</code></pre>

                <h3>Example Response</h3>
                <pre><code>{`{
    "events": [
        {
            "id": 123,
            "title": "Monthly Meeting",
            "start_date": "2024-03-15",
            "permalink": "${baseUrl}/mayo/monthly-meeting/",
            "edit_link": "${baseUrl}/wp-admin/post.php?post=123&action=edit"
        },
        {
            "id": 456,
            "title": "Regional Meeting",
            "start_date": "2024-04-01",
            "permalink": "${baseUrl}/mayo/regional-meeting/",
            "edit_link": "${baseUrl}/wp-admin/post.php?post=456&action=edit"
        }
    ]
}`}</code></pre>
            </div>

            <div className="card" id="post-submit-event">
                <h2>POST /submit-event</h2>
                <p>Submit a new event. Events are created with <code>pending</code> status and require admin approval.</p>

                <h3>Endpoint</h3>
                <pre><code>POST {baseUrl}/wp-json/event-manager/v1/submit-event</code></pre>

                <h3>Authentication</h3>
                <p>This endpoint is publicly accessible (no authentication required).</p>

                <h3>Request Body (multipart/form-data)</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>event_name</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>Event title</td>
                        </tr>
                        <tr>
                            <td><code>event_type</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>Event type (e.g., <code>Service</code>, <code>Activity</code>)</td>
                        </tr>
                        <tr>
                            <td><code>service_body</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>Service body ID (use <code>0</code> for Unaffiliated)</td>
                        </tr>
                        <tr>
                            <td><code>email</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>Contact email address</td>
                        </tr>
                        <tr>
                            <td><code>contact_name</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>Contact person's name</td>
                        </tr>
                        <tr>
                            <td><code>event_start_date</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>Start date (YYYY-MM-DD format)</td>
                        </tr>
                        <tr>
                            <td><code>event_end_date</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>End date (YYYY-MM-DD format)</td>
                        </tr>
                        <tr>
                            <td><code>event_start_time</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>Start time (HH:MM:SS format)</td>
                        </tr>
                        <tr>
                            <td><code>event_end_time</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>End time (HH:MM:SS format)</td>
                        </tr>
                        <tr>
                            <td><code>timezone</code></td>
                            <td>string</td>
                            <td>Yes</td>
                            <td>IANA timezone (e.g., <code>America/New_York</code>)</td>
                        </tr>
                        <tr>
                            <td><code>description</code></td>
                            <td>string</td>
                            <td>No</td>
                            <td>Event description (HTML allowed)</td>
                        </tr>
                        <tr>
                            <td><code>location_name</code></td>
                            <td>string</td>
                            <td>No</td>
                            <td>Venue name</td>
                        </tr>
                        <tr>
                            <td><code>location_address</code></td>
                            <td>string</td>
                            <td>No</td>
                            <td>Street address</td>
                        </tr>
                        <tr>
                            <td><code>location_details</code></td>
                            <td>string</td>
                            <td>No</td>
                            <td>Additional location info (room number, etc.)</td>
                        </tr>
                        <tr>
                            <td><code>categories</code></td>
                            <td>string</td>
                            <td>No</td>
                            <td>Comma-separated category IDs</td>
                        </tr>
                        <tr>
                            <td><code>tags</code></td>
                            <td>string</td>
                            <td>No</td>
                            <td>Comma-separated tag names or IDs</td>
                        </tr>
                        <tr>
                            <td><code>recurring_pattern</code></td>
                            <td>JSON string</td>
                            <td>No</td>
                            <td>Recurring event configuration (see below)</td>
                        </tr>
                        <tr>
                            <td><code>flyer</code></td>
                            <td>file</td>
                            <td>No</td>
                            <td>Event flyer image (set as featured image)</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Recurring Pattern Object</h3>
                <pre><code>{`{
    "type": "weekly",           // "none", "daily", "weekly", "monthly"
    "interval": 1,              // Repeat every N days/weeks/months
    "weekdays": [1, 3, 5],      // For weekly: days of week (0=Sun, 6=Sat)
    "monthlyType": "date",      // For monthly: "date" or "weekday"
    "monthlyDate": 15,          // For monthly by date: day of month
    "monthlyWeekday": "2,4",    // For monthly by weekday: "week,day" (e.g., "2,4" = 2nd Thursday)
    "endDate": "2024-12-31"     // When recurring pattern ends
}`}</code></pre>

                <h3>Example Response</h3>
                <pre><code>{`{
    "id": 456,
    "title": {
        "rendered": "New Community Event"
    },
    "content": {
        "rendered": "<p>Event description...</p>"
    },
    "link": "${baseUrl}/mayo/new-community-event/",
    "meta": {
        "event_start_date": "2024-04-01",
        "event_end_date": "2024-04-01",
        "event_start_time": "14:00:00",
        "event_end_time": "16:00:00",
        "timezone": "America/Los_Angeles",
        "event_type": "Activity",
        "service_body": "5",
        "location_name": "Park Pavilion",
        "location_address": "456 Oak Ave",
        "location_details": ""
    },
    "categories": [],
    "tags": []
}`}</code></pre>
            </div>

            <div className="card" id="get-announcements">
                <h2>GET /announcements</h2>
                <p>Retrieve a list of announcements with optional filtering. By default, only returns announcements within their active display window.</p>

                <h3>Endpoint</h3>
                <pre><code>GET {baseUrl}/wp-json/event-manager/v1/announcements</code></pre>

                <h3>Query Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Default</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>priority</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Filter by priority level: <code>low</code>, <code>normal</code>, <code>high</code>, <code>urgent</code></td>
                        </tr>
                        <tr>
                            <td><code>categories</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Comma-separated category slugs</td>
                        </tr>
                        <tr>
                            <td><code>tags</code></td>
                            <td>string</td>
                            <td>empty</td>
                            <td>Comma-separated tag slugs</td>
                        </tr>
                        <tr>
                            <td><code>linked_event</code></td>
                            <td>integer</td>
                            <td>empty</td>
                            <td>Filter by linked event ID</td>
                        </tr>
                        <tr>
                            <td><code>active</code></td>
                            <td>string</td>
                            <td>true</td>
                            <td>Set to <code>false</code> to include announcements outside their display window</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Example Request</h3>
                <pre><code>{`GET ${baseUrl}/wp-json/event-manager/v1/announcements?priority=urgent`}</code></pre>

                <h3>Example Response</h3>
                <pre><code>{`{
    "announcements": [
        {
            "id": 789,
            "title": "Weather Closure Notice",
            "content": "<p>Due to severe weather...</p>",
            "excerpt": "Due to severe weather...",
            "link": "${baseUrl}/announcement/weather-closure/",
            "display_start_date": "2024-03-14",
            "display_end_date": "2024-03-16",
            "priority": "urgent",
            "linked_events": [
                {
                    "id": 123,
                    "title": "Monthly Meeting",
                    "permalink": "${baseUrl}/mayo/monthly-meeting/",
                    "start_date": "2024-03-15"
                }
            ],
            "featured_image": null,
            "categories": [],
            "tags": []
        }
    ],
    "total": 1
}`}</code></pre>
            </div>

            <div className="card" id="get-announcement-id">
                <h2>GET /announcement/{'{id}'}</h2>
                <p>Retrieve a single announcement by its post ID.</p>

                <h3>Endpoint</h3>
                <pre><code>GET {baseUrl}/wp-json/event-manager/v1/announcement/{'{id}'}</code></pre>

                <h3>Path Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>id</code></td>
                            <td>integer</td>
                            <td>The announcement's post ID</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Example Request</h3>
                <pre><code>{`GET ${baseUrl}/wp-json/event-manager/v1/announcement/789`}</code></pre>

                <h3>Response</h3>
                <p>Returns a single announcement object (same structure as announcements in the list response).</p>

                <h3>Error Response</h3>
                <pre><code>{`{
    "code": "not_found",
    "message": "Announcement not found",
    "data": {
        "status": 404
    }
}`}</code></pre>
            </div>

            <div className="card" id="get-settings">
                <h2>GET /settings</h2>
                <p>Retrieve plugin settings. This endpoint is publicly accessible for frontend configuration.</p>

                <h3>Endpoint</h3>
                <pre><code>GET {baseUrl}/wp-json/event-manager/v1/settings</code></pre>

                <h3>Example Response</h3>
                <pre><code>{`{
    "bmlt_root_server": "https://bmlt.example.org/main_server",
    "notification_email": "events@example.org",
    "default_service_bodies": "1,2,3",
    "external_sources": [
        {
            "id": "source_abc123",
            "url": "https://other-site.org",
            "name": "Other Site Events",
            "event_type": "",
            "service_body": "",
            "categories": "",
            "tags": "",
            "enabled": true
        }
    ]
}`}</code></pre>
            </div>

            <div className="card" id="post-settings">
                <h2>POST /settings</h2>
                <p>Update plugin settings. <strong>Requires administrator authentication.</strong></p>

                <h3>Endpoint</h3>
                <pre><code>POST {baseUrl}/wp-json/event-manager/v1/settings</code></pre>

                <h3>Authentication</h3>
                <p>Requires a logged-in user with <code>manage_options</code> capability (administrator).</p>
                <p>Include the WordPress REST API nonce in your request:</p>
                <pre><code>{`// Using wp-api-fetch (recommended)
import apiFetch from '@wordpress/api-fetch';
apiFetch({ path: '/event-manager/v1/settings', method: 'POST', data: {...} });

// Using fetch with nonce
fetch('${baseUrl}/wp-json/event-manager/v1/settings', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({...})
});`}</code></pre>

                <h3>Request Body (JSON)</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>bmlt_root_server</code></td>
                            <td>string</td>
                            <td>BMLT root server URL</td>
                        </tr>
                        <tr>
                            <td><code>notification_email</code></td>
                            <td>string</td>
                            <td>Email address(es) for event submission notifications</td>
                        </tr>
                        <tr>
                            <td><code>default_service_bodies</code></td>
                            <td>string</td>
                            <td>Comma-separated service body IDs to restrict submissions</td>
                        </tr>
                        <tr>
                            <td><code>external_sources</code></td>
                            <td>array</td>
                            <td>Array of external source configurations</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Example Response</h3>
                <pre><code>{`{
    "success": true,
    "settings": {
        "bmlt_root_server": "https://bmlt.example.org/main_server",
        "notification_email": "events@example.org",
        "default_service_bodies": "1,2,3",
        "external_sources": []
    }
}`}</code></pre>

                <h3>Error Response (Unauthorized)</h3>
                <pre><code>{`{
    "code": "rest_forbidden",
    "message": "Sorry, you are not allowed to update settings.",
    "data": {
        "status": 401
    }
}`}</code></pre>
            </div>

            <div className="card" id="event-meta-fields">
                <h2>Event Meta Fields</h2>
                <p>Events use the following meta fields, which are exposed via the WordPress REST API for the <code>mayo_event</code> post type:</p>

                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Meta Key</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>event_type</code></td>
                            <td>string</td>
                            <td>Event type (Service, Activity, etc.)</td>
                        </tr>
                        <tr>
                            <td><code>service_body</code></td>
                            <td>string</td>
                            <td>Service body ID</td>
                        </tr>
                        <tr>
                            <td><code>event_start_date</code></td>
                            <td>string</td>
                            <td>Start date (YYYY-MM-DD)</td>
                        </tr>
                        <tr>
                            <td><code>event_end_date</code></td>
                            <td>string</td>
                            <td>End date (YYYY-MM-DD)</td>
                        </tr>
                        <tr>
                            <td><code>event_start_time</code></td>
                            <td>string</td>
                            <td>Start time (HH:MM:SS)</td>
                        </tr>
                        <tr>
                            <td><code>event_end_time</code></td>
                            <td>string</td>
                            <td>End time (HH:MM:SS)</td>
                        </tr>
                        <tr>
                            <td><code>timezone</code></td>
                            <td>string</td>
                            <td>IANA timezone identifier</td>
                        </tr>
                        <tr>
                            <td><code>location_name</code></td>
                            <td>string</td>
                            <td>Venue name</td>
                        </tr>
                        <tr>
                            <td><code>location_address</code></td>
                            <td>string</td>
                            <td>Street address</td>
                        </tr>
                        <tr>
                            <td><code>location_details</code></td>
                            <td>string</td>
                            <td>Additional location information</td>
                        </tr>
                        <tr>
                            <td><code>contact_name</code></td>
                            <td>string</td>
                            <td>Contact person's name</td>
                        </tr>
                        <tr>
                            <td><code>email</code></td>
                            <td>string</td>
                            <td>Contact email address</td>
                        </tr>
                        <tr>
                            <td><code>recurring_pattern</code></td>
                            <td>object</td>
                            <td>Recurring event configuration</td>
                        </tr>
                        <tr>
                            <td><code>skipped_occurrences</code></td>
                            <td>array</td>
                            <td>Array of skipped dates for recurring events</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div className="card" id="announcement-response">
                <h2>Announcement Response Object</h2>
                <p>Announcements returned from the API include the following fields:</p>

                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>id</code></td>
                            <td>integer</td>
                            <td>The announcement's post ID</td>
                        </tr>
                        <tr>
                            <td><code>title</code></td>
                            <td>string</td>
                            <td>Announcement title</td>
                        </tr>
                        <tr>
                            <td><code>content</code></td>
                            <td>string</td>
                            <td>Full announcement content (HTML)</td>
                        </tr>
                        <tr>
                            <td><code>excerpt</code></td>
                            <td>string</td>
                            <td>Short excerpt of the announcement</td>
                        </tr>
                        <tr>
                            <td><code>link</code></td>
                            <td>string</td>
                            <td>Permalink to the announcement</td>
                        </tr>
                        <tr>
                            <td><code>edit_link</code></td>
                            <td>string</td>
                            <td>Admin edit URL for the announcement</td>
                        </tr>
                        <tr>
                            <td><code>display_start_date</code></td>
                            <td>string</td>
                            <td>When the announcement starts showing (YYYY-MM-DD)</td>
                        </tr>
                        <tr>
                            <td><code>display_end_date</code></td>
                            <td>string</td>
                            <td>When the announcement stops showing (YYYY-MM-DD)</td>
                        </tr>
                        <tr>
                            <td><code>priority</code></td>
                            <td>string</td>
                            <td>Priority level: <code>low</code>, <code>normal</code>, <code>high</code>, or <code>urgent</code></td>
                        </tr>
                        <tr>
                            <td><code>linked_events</code></td>
                            <td>array</td>
                            <td>Array of linked event objects with <code>id</code>, <code>title</code>, <code>permalink</code>, <code>start_date</code></td>
                        </tr>
                        <tr>
                            <td><code>is_active</code></td>
                            <td>boolean</td>
                            <td>Whether the announcement is currently within its display window</td>
                        </tr>
                        <tr>
                            <td><code>featured_image</code></td>
                            <td>string|null</td>
                            <td>URL of the featured image, if set</td>
                        </tr>
                        <tr>
                            <td><code>categories</code></td>
                            <td>array</td>
                            <td>Array of category objects</td>
                        </tr>
                        <tr>
                            <td><code>tags</code></td>
                            <td>array</td>
                            <td>Array of tag objects</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div className="card" id="wordpress-rest-api">
                <h2>WordPress REST API</h2>
                <p>Events are also accessible via the standard WordPress REST API at <code>/wp-json/wp/v2/mayo_event</code>.</p>

                <h3>Example</h3>
                <pre><code>{`GET ${baseUrl}/wp-json/wp/v2/mayo_event?per_page=10&status=publish`}</code></pre>

                <p>This provides access to all standard WordPress post fields plus the registered meta fields above.</p>
            </div>

            <div className="card" id="usage-examples">
                <h2>Usage Examples</h2>

                <h3>JavaScript (Fetch API)</h3>
                <pre><code>{`// Get upcoming events
fetch('${baseUrl}/wp-json/event-manager/v1/events?per_page=10')
    .then(response => response.json())
    .then(data => {
        console.log('Events:', data.events);
        console.log('Total:', data.pagination.total);
    });

// Get events for a specific service body
fetch('${baseUrl}/wp-json/event-manager/v1/events?service_body=1,2')
    .then(response => response.json())
    .then(data => console.log(data));

// Get past events (archive)
fetch('${baseUrl}/wp-json/event-manager/v1/events?archive=true&order=DESC')
    .then(response => response.json())
    .then(data => console.log(data));

// Get active announcements
fetch('${baseUrl}/wp-json/event-manager/v1/announcements')
    .then(response => response.json())
    .then(data => console.log(data.announcements));

// Get urgent announcements only
fetch('${baseUrl}/wp-json/event-manager/v1/announcements?priority=urgent')
    .then(response => response.json())
    .then(data => console.log(data));`}</code></pre>

                <h3>PHP (WordPress)</h3>
                <pre><code>{`// Get events using wp_remote_get
$response = wp_remote_get('${baseUrl}/wp-json/event-manager/v1/events?per_page=5');

if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    foreach ($data['events'] as $event) {
        echo $event['title']['rendered'] . '<br>';
    }
}`}</code></pre>

                <h3>cURL</h3>
                <pre><code>{`# Get events
curl "${baseUrl}/wp-json/event-manager/v1/events?per_page=5"

# Get a single event
curl "${baseUrl}/wp-json/event-manager/v1/event/monthly-meeting"

# Get settings
curl "${baseUrl}/wp-json/event-manager/v1/settings"

# Get announcements
curl "${baseUrl}/wp-json/event-manager/v1/announcements"

# Get announcements for a specific event (use linked_event filter)
curl "${baseUrl}/wp-json/event-manager/v1/announcements?linked_event=123"`}</code></pre>
            </div>
        </div>
    );
};

export default ApiDocs;
