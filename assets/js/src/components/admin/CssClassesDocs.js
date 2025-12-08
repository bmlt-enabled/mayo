import { useState } from '@wordpress/element';

const CssClassesDocs = () => {
    const [lastUpdated] = useState(new Date().toLocaleString());

    // Hardcoded dynamic class information
    const dynamicClasses = [
        {
            pattern: 'mayo-event-category-{category-slug}',
            baseName: 'mayo-event-category',
            description: 'Class for event categories'
        },
        {
            pattern: 'mayo-event-tag-{tag-slug}',
            baseName: 'mayo-event-tag',
            description: 'Class for event tags'
        },
        {
            pattern: 'mayo-event-type-{event-type-slug}',
            baseName: 'mayo-event-type',
            description: 'Class for event types'
        },
        {
            pattern: 'mayo-event-service-body-{service-body-slug}',
            baseName: 'mayo-event-service-body',
            description: 'Class for service bodies'
        }
    ];

    return (
        <div className="wrap mayo-docs">
            <h1>CSS Classes Documentation</h1>
            
            <div className="card">
                <div className="mayo-docs-header">
                    <h2>Dynamic CSS Classes</h2>
                    <div className="mayo-docs-meta">
                        <span className="mayo-docs-last-updated">Last updated: {lastUpdated}</span>
                    </div>
                </div>
                
                <div className="notice notice-warning">
                    <p><strong>Important:</strong> This plugin does not include a built-in CSS editor. To style the event cards, you need to use another plugin like "Simple Custom CSS" or add CSS to your theme's stylesheet.</p>
                </div>
                
                <p>This documentation explains the dynamic CSS classes used in the Event Card and Calendar View components. These classes are generated based on event properties and can be used to style specific events.</p>
                
                <div className="mayo-tab-content">
                    <h3>Dynamic Classes</h3>
                    <p>These classes are dynamically generated based on event properties:</p>
                    <table className="widefat">
                        <thead>
                            <tr>
                                <th>Class Pattern</th>
                                <th>Based On</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            {dynamicClasses.map((dynamicClass, index) => {
                                // Determine what the class is based on
                                let basedOn = '';
                                let example = '';
                                
                                if (dynamicClass.baseName.includes('category')) {
                                    basedOn = 'Event categories';
                                    example = 'mayo-event-category-meetings';
                                } else if (dynamicClass.baseName.includes('tag')) {
                                    basedOn = 'Event tags';
                                    example = 'mayo-event-tag-featured';
                                } else if (dynamicClass.baseName.includes('type')) {
                                    basedOn = 'Event type';
                                    example = 'mayo-event-type-service';
                                } else if (dynamicClass.baseName.includes('service-body')) {
                                    basedOn = 'Service body';
                                    example = 'mayo-event-service-body-district-1';
                                }
                                
                                return (
                                    <tr key={index}>
                                        <td><code>{dynamicClass.pattern}</code></td>
                                        <td>{basedOn}</td>
                                        <td><code>{example}</code></td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    
                    <h4>Special Characters Handling</h4>
                    <p>Special characters and emojis in category, tag, event type, and service body names are converted to Unicode codes to ensure valid CSS class names:</p>
                    <pre><code>// Example: "Meeting 🎉" becomes "mayo-event-category-meeting-u127881"</code></pre>
                    
                    <h4>Small Variants</h4>
                    <p>Categories and tags in the event brief section use small variants:</p>
                    <ul>
                        <li><code>mayo-event-category-small</code></li>
                        <li><code>mayo-event-tag-small</code></li>
                    </ul>
                    
                    <h4>How to Add Custom CSS</h4>
                    <p>To style the event cards, you need to add CSS to your theme or use a custom CSS plugin. Here are some recommended plugins:</p>
                    <ul>
                        <li><a href="https://wordpress.org/plugins/simple-custom-css/" target="_blank" rel="noopener noreferrer">Simple Custom CSS</a></li>
                        <li><a href="https://wordpress.org/plugins/custom-css-js/" target="_blank" rel="noopener noreferrer">Custom CSS and JS</a></li>
                        <li><a href="https://wordpress.org/plugins/advanced-custom-css/" target="_blank" rel="noopener noreferrer">Advanced Custom CSS</a></li>
                    </ul>
                    
                    <h4>List View vs Calendar View Styling</h4>
                    <p>The same dynamic classes are applied to both list view and calendar view events. This means your existing styles will automatically work in both views:</p>
                    <pre><code>{`
/* This styles events in BOTH list and calendar views */
.mayo-event-category-meetings {
    border-left: 4px solid #4a90e2;
}
                    `}</code></pre>
                    <p>If you want <strong>different</strong> styling for each view, use the base element class as a prefix:</p>
                    <ul>
                        <li><code>.mayo-event-card</code> - List view event cards</li>
                        <li><code>.mayo-calendar-event</code> - Calendar view event pills</li>
                    </ul>
                    <pre><code>{`
/* List view only */
.mayo-event-card.mayo-event-category-meetings {
    border-left: 4px solid #4a90e2;
}

/* Calendar view only */
.mayo-calendar-event.mayo-event-category-meetings {
    background-color: #e3f2fd;
    border-left: 3px solid #1976d2;
}
                    `}</code></pre>

                    <h4>Customization Example</h4>
                    <p>Once you have a CSS editor, you can add rules like these:</p>
                    <pre><code>{`
/* Style events with the "meetings" category (both views) */
.mayo-event-category-meetings {
    border-left: 4px solid #4a90e2;
}

/* Style events with the "workshops" category (both views) */
.mayo-event-category-workshops {
    border-left: 4px solid #e2844a;
}

/* Style service events (both views) */
.mayo-event-type-service {
    background-color: #f0f7ff;
}

/* Style events from a specific service body (both views) */
.mayo-event-service-body-district-1 {
    border-top: 2px solid #4a90e2;
}

/* Calendar view only: different background for activity events */
.mayo-calendar-event.mayo-event-type-activity {
    background-color: #fff3e0;
}
                    `}</code></pre>
                </div>
            </div>
        </div>
    );
};

export default CssClassesDocs; 