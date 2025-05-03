const ShortcodesDocs = () => {
    return (
        <div className="wrap mayo-docs">
            <h1>Shortcodes</h1>
            
            <div className="card">
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
                            <td>categories</td>
                            <td>Filter by category slugs (comma-separated)</td>
                            <td>empty (all categories)</td>
                            <td>e.g., <pre>meetings,workshops</pre> (is an or condition) or <pre>meetings+workshops</pre> (is an and condition).  For more information see the Wordpress documentation on <a target="_blank" href="https://developer.wordpress.org/reference/classes/wp_query/#category-parameters">Category Parameters</a>.</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>tags</td>
                            <td>Filter by tag slugs (comma-separated)</td>
                            <td>empty (all tags)</td>
                            <td>e.g., <pre>featured,ticketed</pre> (is an or condition) or <pre>featured+ticketed</pre> (is an and condition).  For more information see the Wordpress documentation on <a target="_blank" href="https://developer.wordpress.org/reference/classes/wp_query/#tag-parameters">Tag Parameters</a>.</td>
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
                            <td>No</td>
                        </tr>
                        <tr>
                            <td>show_pagination</td>
                            <td>Whether to show "Load More" button at bottom of list (infinite scroll is always enabled)</td>
                            <td>true</td>
                            <td>true, false</td>
                            <td>No</td>
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
                            <td>Show past events instead of only upcoming events</td>
                            <td>false</td>
                            <td>true, false</td>
                            <td>Yes</td>
                        </tr>
                        <tr>
                            <td>timezone</td>
                            <td>Timezone to use for date filtering</td>
                            <td>Browser's timezone</td>
                            <td>Any valid IANA timezone (e.g., "America/New_York", "Europe/London")</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Example with Parameters</h3>
                <pre><code>[mayo_event_list time_format="24hour" per_page="5" categories="meetings,workshops" tags="featured" event_type="Service" service_body="1,2,3" source_ids="local,source_123" archive="false" timezone="America/New_York"]</code></pre>

                <h3>Example with Querystring Overrides</h3>
                <pre><code>https://example.com/events?status=pending&categories=meetings,workshops&event_type=Service&service_body="1,2,3"&source_ids=local,source_123&archive=true&timezone=America/New_York</code></pre>
                
                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Local events are always included by default unless specifically excluded</li>
                    <li>To include only local events, use <code>source_ids="local"</code></li>
                    <li>To exclude local events, specify only external source IDs (e.g., <code>source_ids="source_123,source_456"</code>)</li>
                    <li>To include all events (local and external), leave source_ids empty</li>
                    <li>When <code>archive="true"</code>, both past and future events will be shown</li>
                    <li>When <code>archive="false"</code> (default), only upcoming events will be shown</li>
                    <li>The <code>timezone</code> parameter ensures date filtering is accurate across different time zones</li>
                    <li>Events are loaded using infinite scroll automatically as the user scrolls down the page</li>
                </ul>
            </div>

            <div className="card">
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
                    </tbody>
                </table>
                
                <h3>Example with Additional Required Fields</h3>
                <pre><code>[mayo_event_form additional_required_fields="flyer,location_name,location_address"]</code></pre>
                
                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Default required fields are always enforced</li>
                    <li>Additional required fields will be marked with an asterisk (*)</li>
                    <li>Form validation will ensure all required fields are filled</li>
                </ul>
            </div>
        </div>
    );
};

export default ShortcodesDocs; 