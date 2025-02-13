const ShortcodesDocs = () => {
    return (
        <div className="wrap mayo-docs">
            <h1>Mayo Event Manager Shortcodes</h1>
            
            <div className="card">
                <h2>Event Submission Form</h2>
                <p><code>[mayo_event_form]</code></p>
                <p>Displays a form that allows users to submit events for approval.</p>
                <h3>Usage:</h3>
                <pre><code>[mayo_event_form]</code></pre>
                <p>This shortcode has no additional parameters. Place it on any page where you want users to be able to submit events.</p>
            </div>

            <div className="card">
                <h2>Event List Display</h2>
                <p><code>[mayo_event_list]</code></p>
                <p>Displays a list of upcoming events in an accordion-style layout.</p>
                
                <h3>Parameters:</h3>
                <table className="widefat">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Values</th>
                            <th>Default</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>time_format</code></td>
                            <td><code>12hour</code> or <code>24hour</code></td>
                            <td><code>12hour</code></td>
                            <td>Controls how times are displayed (e.g., "2:30 PM" vs "14:30")</td>
                        </tr>
                    </tbody>
                </table>

                <h3>Examples:</h3>
                <pre><code># Default 12-hour time format
[mayo_event_list]

# Use 24-hour time format
[mayo_event_list time_format="24hour"]</code></pre>
            </div>

            <div className="card">
                <h2>Features</h2>
                <ul className="ul-disc">
                    <li>Events are automatically sorted by date</li>
                    <li>Past events are automatically filtered out</li>
                    <li>Expandable/collapsible event details</li>
                    <li>Location details with Google Maps integration</li>
                    <li>Event flyer image support</li>
                    <li>Mobile-responsive design</li>
                </ul>
            </div>
        </div>
    );
};

export default ShortcodesDocs; 