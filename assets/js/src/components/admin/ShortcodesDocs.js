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
                            <td>Whether to show pagination controls</td>
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
                            <td>Shows events for a given service body</td>
                            <td>empty (all service bodies)</td>
                            <td>e.g., "1,2,3"</td>
                            <td>Yes</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Example with Parameters</h3>
                <pre><code>[mayo_event_list time_format="24hour" per_page="5" categories="meetings,workshops" tags="featured" event_type="Service"]</code></pre>

                <h3>Example with Querystring Overrides</h3>
                <pre><code>https://example.com/events?status=pending&categories=meetings,workshops&event_type=Service</code></pre>
            </div>

            <div className="card">
                <h2>Event Submission Form Shortcode</h2>
                <p>Use this shortcode to display a form that allows users to submit events:</p>
                <pre><code>[mayo_event_form]</code></pre>
                
                <h3>Features</h3>
                <ul className="ul-disc">
                    <li>Event name and type selection</li>
                    <li>Date and time selection</li>
                    <li>Event description with rich text editor</li>
                    <li>Event flyer upload</li>
                    <li>Location details (name, address, additional info)</li>
                    <li>Category and tag selection</li>
                    <li>Recurring event patterns</li>
                </ul>

                <h3>Notes</h3>
                <ul className="ul-disc">
                    <li>Submitted events are saved as pending and require admin approval</li>
                    <li>Required fields are marked with an asterisk (*)</li>
                    <li>Images are automatically processed and stored in the media library</li>
                    <li>Form includes built-in validation and error handling</li>
                </ul>
            </div>
        </div>
    );
};

export default ShortcodesDocs; 