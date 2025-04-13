== Mayo Events Manager ==

Contributors: bmltenabled, radius314
Tags: events, bmlt, narcotics anonymous, na
Requires PHP: 8.2
Requires at least: 6.7
Tested up to: 6.7
Stable tag: 1.2.0

License: MIT
License URI: https://opensource.org/licenses/MIT

### Description

Pronounced "my-oh".  Referring to the Spanish word "mayo" which means "May" in English.

Mayo Event Manager is a comprehensive WordPress plugin designed to facilitate the management of events with a focus on community engagement and administrative oversight. This plugin allows users to submit events for approval, supports recurring event schedules, and integrates seamlessly with WordPress's native features like featured images and custom post types.

Mayo is meant to be simple, and does not provide all the functionality of a typical event management system.  It is designed to be used in conjunction with a BMLT server to provide a simple way to submit events for a specific service body.  Also we will not be implementing a calendar view, but rather a list view.  There are plenty of other plugins that provide calendar views, and you can make use of the calendar RSS to import the feeds you want.

### Key Features

1. **Event Submission and Approval**:
   - Users can submit events through a public-facing form.
   - Submitted events are saved as pending, requiring admin approval before they are published.
   - Admins receive email notifications upon new event submissions, ensuring timely review and approval.

2. **Recurring Events**:
   - Supports daily, weekly, and monthly recurring event patterns.
   - Users can specify recurrence intervals and end dates.
   - Recurring events are automatically generated based on the specified pattern.

3. **Event Details and Customization**:
   - Events can include detailed information such as event type, start and end dates, times, and location details.
   - Supports the use of WordPress's featured image functionality to visually represent events.
   - Allows categorization and tagging of events for better organization and filtering.

4. **Service Body Integration**:
   - Integrates with external service bodies, allowing users to associate events with specific service organizations.
   - Fetches and displays service body information dynamically.

5. **Email Notifications**:
   - Sends email notifications to the site admin when a new event is submitted.
   - Ensures that admins are promptly informed of new submissions for review.

6. **REST API Integration**:
   - Provides REST API endpoints for submitting events, retrieving event details, and managing settings.
   - Facilitates integration with other systems and custom front-end applications.

7. **User-Friendly Interface**:
   - Intuitive form interface for event submission, including validation for required fields like email.
   - Admin interface for managing events, including viewing, editing, and approving submissions.

### Technical Details

- **Custom Post Type**: Utilizes a custom post type `mayo_event` to store and manage events.
- **JavaScript Components**: Built with React and integrated into the WordPress admin and front-end using Gutenberg components.
- **Email Handling**: Uses WordPress's `wp_mail` function to send notifications.
- **Data Storage**: Stores event metadata using WordPress's post meta system, ensuring compatibility and scalability.

### Installation and Usage

1. **Installation**:
   - Upload the plugin files to the `/wp-content/plugins/mayo-event-manager` directory.
   - Activate the plugin through the 'Plugins' menu in WordPress.

2. **Configuration**:
   - Set up the BMLT root server URL in the plugin settings for service body integration.
   - Configure email settings to ensure notifications are sent correctly.

3. **Usage**:
   - Use the provided shortcode or block to display the event submission form on any page.
   - Manage submitted events from the WordPress admin dashboard, where you can approve, edit, or delete events.

### Future Enhancements

- **Additional Notification Options**: Expand notification capabilities to include SMS or push notifications.
- **Advanced Filtering**: Implement more advanced filtering and search options for events.
- **User Roles and Permissions**: Enhance role-based access control for event management.
