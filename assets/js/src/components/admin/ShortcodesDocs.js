const ShortcodesDocs = () => {
    return (
        <div className="wrap mayo-docs">
            <h1>Shortcodes</h1>

            <div className="card" style={{ marginBottom: '20px' }}>
                <h2>Table of Contents</h2>
                <ul style={{ listStyle: 'disc', marginLeft: '20px', lineHeight: '2' }}>
                    <li><a href="#event-list">[mayo_event_list] - Event List Shortcode</a></li>
                    <li><a href="#event-form">[mayo_event_form] - Event Submission Form Shortcode</a></li>
                    <li><a href="#announcement">[mayo_announcement] - Announcement Shortcode</a></li>
                    <li><a href="#announcement-form">[mayo_announcement_form] - Announcement Submission Form Shortcode</a></li>
                    <li><a href="#subscribe">[mayo_subscribe] - Email Subscription Form Shortcode</a></li>
                </ul>
            </div>

            <div className="card" id="event-list">
                <h2>Event List Shortcode</h2>
                <p>Use this shortcode to display a list of upcoming events:</p>
                <pre><code>[mayo_event_list]</code></pre>
                
                <h3>Optional Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Description</th>
                            <th>Default</th>
                            <th>Options</th>
                            <th>Overridable via Querystring?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>infinite_scroll</td>
                            <td>Enable automatic loading of more events as user scrolls down the page</td>
                            <td>true</td>
                            <td>true, false</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>autoexpand</td>
                            <td>Automatically expand all events when the page loads</td>
                            <td>false</td>
                            <td>true, false</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>categories</td>
                            <td>Filter by category slugs (comma-separated). Prefix a slug with a minus sign (-) to exclude it.</td>
                            <td>empty (all categories)</td>
                            <td>e.g., <pre>meetings,workshops</pre> or <pre>-meetings,-workshops</pre> (exclude meetings and workshops)</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>category_relation</td>
                            <td>How to match multiple categories: AND (must have all) or OR (must have any)</td>
                            <td>OR</td>
                            <td>AND, OR</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>tags</td>
                            <td>Filter by tag slugs (comma-separated). Prefix a slug with a minus sign (-) to exclude it.</td>
                            <td>empty (all tags)</td>
                            <td>e.g., <pre>featured,ticketed</pre> (include only featured or ticketed), <pre>featured+ticketed</pre> (is an and condition), or <pre>-featured,-ticketed</pre> (exclude featured and ticketed). For more information see the Wordpress documentation on <a target="_blank" href="https://developer.wordpress.org/reference/classes/wp_query/#tag-parameters">Tag Parameters</a>.</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>event_type</td>
                            <td>Filter by event type</td>
                            <td>empty (all types)</td>
                            <td>Service, Activity</td>
                            <td>Yes (only one event type at a time)</td>
                        </tr>
                        <tr>
                            <td>time_format</td>
                            <td>Format for displaying time</td>
                            <td>12hour</td>
                            <td>12hour, 24hour</td>
                            <td>No</td>
                        </tr>
                        <tr>
                            <td>per_page</td>
                            <td>Number of events to show per page</td>
                            <td>10</td>
                            <td>Any positive number</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>status</td>
                            <td>Shows events with a given status</td>
                            <td>publish</td>
                            <td>publish, pending</td>
                            <td>Yes (only one status at a time)</td>
                        </tr>
                        <tr>
                            <td>service_body</td>
                            <td>Filter events by service body IDs (comma-separated)</td>
                            <td>empty (all service bodies)</td>
                            <td>e.g., "1,2,3" (shows events from any of the specified service bodies)</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>source_ids</td>
                            <td>Filter events by source IDs (comma-separated)</td>
                            <td>empty (local events only)</td>
                            <td>e.g., "local,source_123,source_456"</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>archive</td>
                            <td>Show only past events that have completely ended (excluding current and future events)</td>
                            <td>false</td>
                            <td>true, false</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>order</td>
                            <td>Sort order for events by start date and time</td>
                            <td>ASC</td>
                            <td>ASC (earliest first), DESC (latest first)</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>timezone</td>
                            <td>Timezone to use for date filtering</td>
                            <td>Browser's timezone</td>
                            <td>Any valid IANA timezone (e.g., "America/New_York", "Europe/London")</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>view</td>
                            <td>Default view mode for displaying events</td>
                            <td>list</td>
                            <td>list, calendar</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Example with Parameters</h3>
                <pre><code>[mayo_event_list time_format="24hour" per_page="5" categories="meetings,workshops" tags="featured" event_type="Service" service_body="1,2,3" source_ids="local,source_123" archive="false" order="ASC" timezone="America/New_York" view="calendar"]</code></pre>

                <h3>Example with Category/Tag Exclusions</h3>
                <pre><code>[mayo_event_list categories="-announcements,-alerts" tags="-archived"]</code></pre>
                <p><em>Shows all events except those with the "announcements" or "alerts" categories, and excludes the "archived" tag.</em></p>

                <h3>Example with Querystring Overrides</h3>
                <pre><code>https://example.com/events?status=pending&categories=meetings,workshops&event_type=Service&service_body="1,2,3"&source_ids=local,source_123&archive=true&order=DESC&timezone=America/New_York&infinite_scroll=false&per_page=20&view=calendar</code></pre>
                
                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Prefix category or tag slugs with a minus sign (-) to exclude them from the event list</li>
                    <li>You can mix inclusions and exclusions: <code>categories="meetings,-cancelled"</code> shows meetings but not cancelled ones</li>
                    <li>The <code>view</code> parameter allows you to display events in either a list or calendar format. Users can toggle between views using the buttons in the header.</li>
                    <li>Local events are always included by default unless specifically excluded</li>
                    <li>To include only local events, use <code>source_ids="local"</code></li>
                    <li>To exclude local events, specify only external source IDs (e.g., <code>source_ids="source_123,source_456"</code>)</li>
                    <li>To include all events (local and external), leave source_ids empty</li>
                    <li>When <code>archive="true"</code>, only past events that have completely ended will be shown (excludes current and future events)</li>
                    <li>When <code>archive="false"</code> (default), only upcoming and ongoing events will be shown</li>
                    <li>Use <code>order="DESC"</code> with <code>archive="true"</code> to show most recent past events first</li>
                    <li>Use <code>order="ASC"</code> (default) to show events in chronological order (earliest first)</li>
                    <li>The <code>timezone</code> parameter ensures date filtering is accurate across different time zones</li>
                    <li>Events are loaded using infinite scroll automatically as the user scrolls down the page</li>
                </ul>
            </div>

            <div className="card" id="event-form">
                <h2>Event Submission Form Shortcode</h2>
                <p>
                    The Event Submission Form Shortcode allows users to submit new events to your site. The form includes fields for event name, type, start date, end date, and more.
                </p>
                <p>
                    When a new event is submitted, an email notification will be sent to the email addresses configured in the plugin settings. Multiple email addresses can be specified, separated by commas or semicolons.
                </p>
                <h3>Shortcode</h3>
                <code>[mayo_event_form]</code>
                
                <h3>Default Required Fields</h3>
                <p>The following fields are always required and cannot be overridden:</p>
                <ul>
                    <li>Event Name (event_name)</li>
                    <li>Event Type (event_type)</li>
                    <li>Service Body (service_body)</li>
                    <li>Email (email)</li>
                    <li>Start Date (event_start_date)</li>
                    <li>Start Time (event_start_time)</li>
                    <li>End Date (event_end_date)</li>
                    <li>End Time (event_end_time)</li>
                    <li>Timezone (timezone)</li>
                </ul>

                <h3>Optional Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Description</th>
                            <th>Default</th>
                            <th>Available Fields</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>additional_required_fields</td>
                            <td>Comma-separated list of additional fields that should be required</td>
                            <td>empty (no additional required fields)</td>
                            <td>
                                <ul>
                                    <li>description</li>
                                    <li>location_name</li>
                                    <li>location_address</li>
                                    <li>location_details</li>
                                    <li>flyer</li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td>categories</td>
                            <td>Comma-separated list of category slugs that should be available in the form. Prefix a category with a minus sign (-) to exclude it.</td>
                            <td>empty (all categories)</td>
                            <td>e.g., <pre>meetings,workshops</pre> to show only meetings and workshops, or <pre>-meetings,-workshops</pre> to show all categories except meetings and workshops</td>
                        </tr>
                        <tr>
                            <td>tags</td>
                            <td>Comma-separated list of tag slugs that should be available in the form. Prefix a tag with a minus sign (-) to exclude it. Tag slugs are always compared in lowercase.</td>
                            <td>empty (all tags)</td>
                            <td>e.g., <pre>featured,ticketed</pre> to show only featured and ticketed tags, or <pre>-featured,-ticketed</pre> to show all tags except featured and ticketed</td>
                        </tr>
                        <tr>
                            <td>default_service_bodies</td>
                            <td>Comma-separated list of service body IDs to restrict the form to specific service bodies. Perfect for multi-site setups where each site should only allow events for specific service bodies.</td>
                            <td>empty (all service bodies)</td>
                            <td>e.g., <pre>1,2,3</pre> to allow only service bodies 1, 2, and 3, or <pre>0</pre> for only Unaffiliated events. If only one service body is specified, the field will be hidden and auto-selected.</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Examples</h3>
                
                <h4>Standard Form with Additional Requirements</h4>
                <pre><code>[mayo_event_form additional_required_fields="flyer,location_name,location_address" categories="meetings,workshops" tags="featured,-ticketed"]</code></pre>
                
                <h4>Multi-Site Configuration - Restrict to Specific Service Bodies</h4>
                <pre><code>[mayo_event_form default_service_bodies="1,2,5" categories="meetings"]</code></pre>
                <p><em>Perfect for multi-site setups where each subsite should only allow events for specific service bodies.</em></p>
                
                <h4>Single Service Body (Auto-Hidden)</h4>
                <pre><code>[mayo_event_form default_service_bodies="3"]</code></pre>
                <p><em>When only one service body is specified, the service body field is hidden and automatically selected.</em></p>
                
                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Default required fields are always enforced</li>
                    <li>Additional required fields will be marked with an asterisk (*)</li>
                    <li>Form validation will ensure all required fields are filled</li>
                </ul>
            </div>

            <div className="card" id="announcement">
                <h2>Announcement Shortcode</h2>
                <p>Use this shortcode to display announcements as banners or modals. Announcements are managed separately from events and are useful for:</p>
                <ul className="ul-disc">
                    <li>Meeting closures or changes</li>
                    <li>Weather alerts</li>
                    <li>Breaking news or important updates</li>
                    <li>Promoting upcoming events (can be linked to events)</li>
                    <li>Post-event thank you messages</li>
                </ul>
                <pre><code>[mayo_announcement]</code></pre>

                <h3>How It Works</h3>
                <ul className="ul-disc">
                    <li>Announcements have their own display window (<code>display_start_date</code> and <code>display_end_date</code>)</li>
                    <li>Can optionally be linked to one or more events</li>
                    <li>Priority levels (low/normal/high/urgent) affect display order and styling</li>
                    <li><strong>Banner mode:</strong> Shows a fixed bar at the top of the viewport with carousel navigation for multiple announcements</li>
                    <li><strong>Modal mode:</strong> Shows a centered popup with a list of all matching announcements</li>
                    <li>When dismissed, announcements stay hidden for 24 hours but can be re-opened via a bell icon in the bottom-right corner</li>
                </ul>

                <h3>Optional Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Description</th>
                            <th>Default</th>
                            <th>Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>mode</td>
                            <td>Display mode for announcements</td>
                            <td>banner</td>
                            <td>banner (sticky top bar), modal (popup)</td>
                        </tr>
                        <tr>
                            <td>categories</td>
                            <td>Filter by category slugs (comma-separated)</td>
                            <td>empty (all categories)</td>
                            <td>e.g., <pre>announcements,alerts</pre></td>
                        </tr>
                        <tr>
                            <td>category_relation</td>
                            <td>How to match multiple categories: AND (must have all) or OR (must have any)</td>
                            <td>OR</td>
                            <td>AND, OR</td>
                        </tr>
                        <tr>
                            <td>tags</td>
                            <td>Filter by tag slugs (comma-separated)</td>
                            <td>empty (all tags)</td>
                            <td>e.g., <pre>urgent,featured</pre></td>
                        </tr>
                        <tr>
                            <td>priority</td>
                            <td>Filter by priority level</td>
                            <td>empty (all priorities)</td>
                            <td>low, normal, high, urgent</td>
                        </tr>
                        <tr>
                            <td>show_linked_events</td>
                            <td>Show linked event titles with the announcement</td>
                            <td>false</td>
                            <td>true, false</td>
                        </tr>
                        <tr>
                            <td>time_format</td>
                            <td>Format for displaying time</td>
                            <td>12hour</td>
                            <td>12hour, 24hour</td>
                        </tr>
                        <tr>
                            <td>background_color</td>
                            <td>Custom background color for the banner/modal header/bell icon</td>
                            <td>empty (uses CSS default)</td>
                            <td>Any hex color, e.g., <pre>#ff6600</pre></td>
                        </tr>
                        <tr>
                            <td>text_color</td>
                            <td>Custom text color for the banner/modal header/bell icon</td>
                            <td>empty (uses CSS default)</td>
                            <td>Any hex color, e.g., <pre>#ffffff</pre></td>
                        </tr>
                        <tr>
                            <td>orderby</td>
                            <td>Field to sort announcements by</td>
                            <td>date</td>
                            <td>date (display start date), title (alphabetical), created (post creation date)</td>
                        </tr>
                        <tr>
                            <td>order</td>
                            <td>Sort direction</td>
                            <td>DESC for date/created, ASC for title</td>
                            <td>ASC (ascending), DESC (descending)</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Examples</h3>

                <h4>Default Banner Mode</h4>
                <pre><code>[mayo_announcement]</code></pre>

                <h4>Modal Popup</h4>
                <pre><code>[mayo_announcement mode="modal"]</code></pre>

                <h4>Filter by Category</h4>
                <pre><code>[mayo_announcement categories="announcements,alerts"]</code></pre>

                <h4>Show Only Urgent Announcements</h4>
                <pre><code>[mayo_announcement priority="urgent"]</code></pre>

                <h4>Show Linked Events</h4>
                <pre><code>[mayo_announcement show_linked_events="true"]</code></pre>

                <h4>Combined Parameters</h4>
                <pre><code>[mayo_announcement mode="banner" categories="announcements" tags="urgent" priority="high" time_format="24hour"]</code></pre>

                <h4>Custom Colors</h4>
                <pre><code>[mayo_announcement background_color="#ff6600" text_color="#ffffff"]</code></pre>

                <h4>Red Alert Style</h4>
                <pre><code>[mayo_announcement background_color="#dc3545" text_color="#fff" priority="urgent"]</code></pre>

                <h4>Sort by Title (Alphabetical)</h4>
                <pre><code>[mayo_announcement orderby="title"]</code></pre>

                <h4>Sort by Created Date (Newest First)</h4>
                <pre><code>[mayo_announcement orderby="created" order="DESC"]</code></pre>

                <h3>Widget Usage</h3>
                <p>For site-wide announcements without editing templates, use the <strong>"Mayo Event Announcements"</strong> widget:</p>
                <ol>
                    <li>Go to <strong>Appearance → Widgets</strong></li>
                    <li>Add the "Mayo Event Announcements" widget to any widget area (footer recommended for site-wide display)</li>
                    <li>Configure the display mode, categories, tags, priority, and time format</li>
                </ol>

                <h3>Creating Announcements</h3>
                <ul className="ul-disc">
                    <li>Go to <strong>Mayo → Announcements → Add New</strong></li>
                    <li>Enter the announcement title and content</li>
                    <li>Set the <strong>Display Start Date</strong> to when you want the announcement to start showing</li>
                    <li>Set the <strong>Display End Date</strong> to when you want the announcement to stop showing</li>
                    <li>Choose a <strong>Priority Level</strong> (urgent announcements appear first)</li>
                    <li>Optionally <strong>link to events</strong> if this announcement relates to specific events</li>
                </ul>

                <h3>Linking Events</h3>
                <p>Announcements can be linked to events in two ways:</p>
                <ul className="ul-disc">
                    <li><strong>From the Announcement editor:</strong> Search and add events in the "Linked Events" panel</li>
                    <li><strong>From the Event editor:</strong> Click "Create Announcement for This Event" in the "Linked Announcements" panel</li>
                </ul>

                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Announcements have independent display windows from events</li>
                    <li>Use announcements to promote events before they start, or recap after they end</li>
                    <li>Priority levels: <strong>urgent</strong> (red), <strong>high</strong> (orange), <strong>normal</strong> (blue), <strong>low</strong> (gray)</li>
                    <li>Use categories or tags to control which announcements appear</li>
                    <li>Multiple announcements are shown as a carousel in banner mode, or as a list in modal mode</li>
                </ul>
            </div>

            <div className="card" id="announcement-form">
                <h2>Announcement Submission Form Shortcode</h2>
                <p>
                    The Announcement Submission Form Shortcode allows users to submit new announcements to your site. This works similarly to the event form but for announcements.
                </p>
                <p>
                    When a new announcement is submitted, an email notification will be sent to the email addresses configured in the plugin settings. The announcement will have a "pending" status until approved by an administrator.
                </p>
                <h3>Shortcode</h3>
                <code>[mayo_announcement_form]</code>

                <h3>Default Required Fields</h3>
                <p>The following fields are always required:</p>
                <ul className="ul-disc">
                    <li>Announcement Title (title)</li>
                    <li>Description (description)</li>
                    <li>Service Body (service_body)</li>
                    <li>Point of Contact Name (contact_name)</li>
                    <li>Point of Contact Email (email)</li>
                </ul>

                <h3>Optional Fields</h3>
                <ul className="ul-disc">
                    <li>Start Date (start_date) - When the announcement should start displaying</li>
                    <li>Start Time (start_time) - Time of day the announcement should start</li>
                    <li>End Date (end_date) - When the announcement should stop displaying</li>
                    <li>End Time (end_time) - Time of day the announcement should end</li>
                    <li>Image/Flyer (flyer) - An image attachment for the announcement</li>
                    <li>Categories - Standard WordPress categories</li>
                    <li>Tags - Standard WordPress tags</li>
                </ul>

                <h3>Optional Parameters</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Description</th>
                            <th>Default</th>
                            <th>Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>additional_required_fields</td>
                            <td>Comma-separated list of additional fields that should be required</td>
                            <td>empty (no additional required fields)</td>
                            <td>
                                <ul>
                                    <li>start_date</li>
                                    <li>start_time</li>
                                    <li>end_date</li>
                                    <li>end_time</li>
                                    <li>flyer</li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td>categories</td>
                            <td>Comma-separated list of category slugs that should be available in the form. Prefix a category with a minus sign (-) to exclude it.</td>
                            <td>empty (uses subscription settings)</td>
                            <td>e.g., <pre>announcements,alerts</pre> or <pre>-internal</pre></td>
                        </tr>
                        <tr>
                            <td>tags</td>
                            <td>Comma-separated list of tag slugs that should be available in the form. Prefix a tag with a minus sign (-) to exclude it.</td>
                            <td>empty (uses subscription settings)</td>
                            <td>e.g., <pre>urgent,featured</pre> or <pre>-archived</pre></td>
                        </tr>
                        <tr>
                            <td>default_service_bodies</td>
                            <td>Comma-separated list of service body IDs to restrict the form to specific service bodies.</td>
                            <td>empty (uses subscription settings)</td>
                            <td>e.g., <pre>1,2,3</pre> or <pre>0</pre> for Unaffiliated. If only one is specified, the field is hidden and auto-selected.</td>
                        </tr>
                        <tr>
                            <td>show_flyer</td>
                            <td>Show the image/flyer upload field</td>
                            <td>false</td>
                            <td>true, false</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Examples</h3>

                <h4>Basic Form</h4>
                <pre><code>[mayo_announcement_form]</code></pre>

                <h4>With Flyer Upload</h4>
                <pre><code>[mayo_announcement_form show_flyer="true"]</code></pre>

                <h4>With Required Dates</h4>
                <pre><code>[mayo_announcement_form additional_required_fields="start_date,end_date"]</code></pre>

                <h4>Restricted to Specific Service Bodies</h4>
                <pre><code>[mayo_announcement_form default_service_bodies="1,2,5"]</code></pre>

                <h4>Single Service Body (Auto-Hidden)</h4>
                <pre><code>[mayo_announcement_form default_service_bodies="3"]</code></pre>
                <p><em>When only one service body is specified, the field is hidden and automatically selected.</em></p>

                <h4>Filter Categories and Tags</h4>
                <pre><code>[mayo_announcement_form categories="announcements,alerts" tags="urgent,featured"]</code></pre>

                <h4>Full Configuration</h4>
                <pre><code>[mayo_announcement_form show_flyer="true" additional_required_fields="start_date,end_date,flyer" default_service_bodies="1,2" categories="announcements"]</code></pre>

                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Categories, tags, and service bodies are filtered by subscription settings configured in <strong>Mayo → Settings → Subscription Preferences</strong></li>
                    <li>Shortcode parameters further restrict the available options (intersection of both)</li>
                    <li>Contact name and email are private fields used for admin communication only</li>
                    <li>Submitted announcements are set to "pending" status and require admin approval</li>
                    <li>Admin receives email notification when new announcements are submitted</li>
                    <li>Start/end dates correspond to the announcement's display window</li>
                </ul>
            </div>

            <div className="card" id="subscribe">
                <h2>Email Subscription Form Shortcode</h2>
                <p>Use this shortcode to display an email subscription form. Users can subscribe to receive announcement notifications via email:</p>
                <pre><code>[mayo_subscribe]</code></pre>

                <h3>How It Works</h3>
                <ol style={{ marginLeft: '20px', lineHeight: '1.8' }}>
                    <li><strong>User enters email:</strong> A simple form with an email input field</li>
                    <li><strong>Confirmation email sent:</strong> User receives an email with a confirmation link (double opt-in)</li>
                    <li><strong>User confirms:</strong> Clicking the link activates their subscription</li>
                    <li><strong>Receive announcements:</strong> When announcements are published, subscribers get an email with the full content</li>
                    <li><strong>Easy unsubscribe:</strong> Each email includes a one-click unsubscribe link</li>
                </ol>

                <h3>Features</h3>
                <ul className="ul-disc">
                    <li><strong>Double opt-in:</strong> Confirmation email ensures valid addresses and prevents spam</li>
                    <li><strong>Full content delivery:</strong> Announcement emails include the complete content plus a link to view on site</li>
                    <li><strong>Token-based security:</strong> Unsubscribe links use cryptographically secure tokens (no login required)</li>
                    <li><strong>Spam folder reminder:</strong> Users are reminded to check spam/junk folders for the confirmation email</li>
                    <li><strong>Re-subscription support:</strong> Previously unsubscribed users can re-subscribe</li>
                </ul>

                <h3>Example</h3>
                <pre><code>[mayo_subscribe]</code></pre>

                <h3>Email Flow</h3>
                <h4>Confirmation Email</h4>
                <p>Sent immediately when a user subscribes:</p>
                <ul className="ul-disc">
                    <li>Subject: "Please confirm your subscription to [Site Name] announcements"</li>
                    <li>Contains a unique confirmation link</li>
                    <li>Includes note about checking spam folder</li>
                </ul>

                <h4>Announcement Email</h4>
                <p>Sent to all confirmed subscribers when an announcement is published:</p>
                <ul className="ul-disc">
                    <li>Subject: "[Site Name] [Announcement Title]"</li>
                    <li>Full announcement content in plain text</li>
                    <li>Link to view on site</li>
                    <li>One-click unsubscribe link</li>
                </ul>

                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Emails are sent using WordPress's <code>wp_mail()</code> function</li>
                    <li>The "From" address uses your WordPress email settings</li>
                    <li>Subscribers are stored in a custom database table (<code>wp_mayo_subscribers</code>)</li>
                    <li>Emails are only sent when announcements are first published (not on updates)</li>
                    <li>The preferences column is reserved for future filtering options</li>
                </ul>
            </div>
        </div>
    );
};

export default ShortcodesDocs; 