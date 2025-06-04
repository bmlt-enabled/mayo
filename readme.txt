== Mayo Events Manager ==

Contributors: bmltenabled, radius314
Tags: events, bmlt, narcotics anonymous, na
Requires PHP: 8.2
Requires at least: 6.7
Tested up to: 6.8
Stable tag: 1.3.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Pronounced "my-oh".  Referring to the Spanish word "mayo" which means "May" in English.  Mayo Event Manager is a comprehensive WordPress plugin designed to facilitate the management of events with a focus on community engagement and administrative oversight. This plugin allows users to submit events for approval, supports recurring event schedules, and integrates seamlessly with WordPress's native features like featured images and custom post types. Mayo is meant to be simple, and does not provide all the functionality of a typical event management system.  It is designed to be used in conjunction with a BMLT server to provide a simple way to submit events for a specific service body.  Also we will not be implementing a calendar view, but rather a list view.  There are plenty of other plugins that provide calendar views, and you can make use of the calendar RSS to import the feeds you want.

1. Event Submission and Approval:
   - Users can submit events through a public-facing form.
   - Submitted events are saved as pending, requiring admin approval before they are published.
   - Admins receive email notifications upon new event submissions, ensuring timely review and approval.

2. Recurring Events:
   - Supports daily, weekly, and monthly recurring event patterns.
   - Users can specify recurrence intervals and end dates.
   - Recurring events are automatically generated based on the specified pattern.

3. Event Details and Customization:
   - Events can include detailed information such as event type, start and end dates, times, and location details.
   - Supports the use of WordPress's featured image functionality to visually represent events.
   - Allows categorization and tagging of events for better organization and filtering.

4. Service Body Integration:
   - Integrates with external service bodies, allowing users to associate events with specific service organizations.
   - Fetches and displays service body information dynamically.

5. Email Notifications:
   - Sends email notifications to the site admin when a new event is submitted.
   - Ensures that admins are promptly informed of new submissions for review.

6. REST API Integration:
   - Provides REST API endpoints for submitting events, retrieving event details, and managing settings.
   - Facilitates integration with other systems and custom front-end applications.

7. User-Friendly Interface:
   - Intuitive form interface for event submission, including validation for required fields like email.
   - Admin interface for managing events, including viewing, editing, and approving submissions.

== Additional Information == 

Mayo WordPress Plugin

A WordPress plugin for managing and displaying events from a BMLT root server.

CSS Documentation

The plugin includes documentation for the dynamic CSS classes used in the Event Card component. This helps developers understand and customize the appearance of events on their site.

Important Note About Styling

This plugin does not include a built-in CSS editor. To style the event cards, you need to use another plugin like "Simple Custom CSS" or add CSS to your theme's stylesheet. The documentation is provided to help you understand which CSS classes are available for styling.

Viewing the Documentation

The CSS documentation is available in the WordPress admin area under the Mayo menu. It includes:

* Dynamic classes generated based on event properties
* Examples of how to customize the appearance

Customization

You can customize the appearance of events by adding CSS rules to your theme or using a custom CSS plugin. The documentation provides examples of common customizations, such as:

* Styling specific categories or tags
* Customizing the appearance of different event types
* Adding styles for specific service bodies

Recommended CSS Plugins

* Simple Custom CSS (https://wordpress.org/plugins/simple-custom-css/)
* Custom CSS and JS (https://wordpress.org/plugins/custom-css-js/)
* Advanced Custom CSS (https://wordpress.org/plugins/advanced-custom-css/)

Development

Prerequisites

* Node.js (v14 or higher)
* npm (v6 or higher)

Installation

1. Clone the repository
2. Install dependencies:

   npm install

3. Build the plugin:

   npm run build

Development Workflow

1. Start the development server:

   npm run dev

2. Make changes to the code
3. Rebuild the plugin as needed

License

This project is licensed under the GPL v2 or later. 

== Technical Details ==

* Custom Post Type: Utilizes a custom post type `mayo_event` to store and manage events.
* JavaScript Components: Built with React and integrated into the WordPress admin and front-end using Gutenberg components.
* Email Handling: Uses WordPress's `wp_mail` function to send notifications.
* Data Storage: Stores event metadata using WordPress's post meta system, ensuring compatibility and scalability.

== Installation ==

1. Installation:
   - Upload the plugin files to the `/wp-content/plugins/mayo-event-manager` directory.
   - Activate the plugin through the 'Plugins' menu in WordPress.

2. Configuration:
   - Set up the BMLT root server URL in the plugin settings for service body integration.
   - Configure email settings to ensure notifications are sent correctly.

3. Usage:
   - Use the provided shortcode or block to display the event submission form on any page.
   - Manage submitted events from the WordPress admin dashboard, where you can approve, edit, or delete events.

== Changelog ==

= 1.3.5 =
* Handle virtual directory paths.
* Temporarily fix an issue with retrieving external sources by fetching 100 events. [#122]

= 1.3.4 =
* Change error messaging on event submission for when invalid file types are used. [#64] [#79]

= 1.3.3 =
* Removed the styling around service body in expanded view of Event List.

= 1.3.2 =
* Added autoexpand option to event list shortcode with querystring support [#119]

= 1.3.1 =
* Adding better documentation around infinite scroll and handling of querystring parameters
* Showing year in date badge as superscript

= 1.3.0 =
* Service bodies are shown in un-expanded cards on the display. [#113]
* Expand and collapse all button.
* Print capability.

= 1.2.11 =
* Fixed HTML encoding issues on displays. [#109]
* Fixed an issue where someone could upload a non-image file. [#111]

= 1.2.10 =
* Fixed another monthly recurrence bug.

= 1.2.9 =
* Fixed a few more recurrence issues [#104] [#105]

= 1.2.8 =
* Fixed recurring weekly event issue that would sometimes throw an error on admin entry

= 1.2.7 =
* Fixed an issue where the page would get stuck when using multiple shortcodes with external source IDs
* Fixes to /mayo archive page

= 1.2.6 =
* Added support for restricting categories and tags in the event submission form via shortcode parameters (`categories` and `tags`). [#98]
    * You can now include or exclude categories/tags using a minus sign (e.g., `categories="meetings,-workshops"`, `tags="featured,-ticketed"`).
    * Category and tag slugs are always compared in lowercase for consistency with WordPress URLs.
* Added server-side pagination to events API for better performance with large datasets [#91]
* Implemented infinite scroll on the event list for improved user experience [#95]
* Added server-side filtering to only show future events by default, with archive=true parameter option
* Added timezone support for accurate date filtering across different timezones
* Optimized recurring event generation to prevent timeout issues

= 1.2.5 =
* Fixed incorrect shortcode documentation in admin UI [#87]
* Fixed issue where not all categories and tags appeared in the event submission form [#88]
* Fixed timezone display issues in admin Events list showing incorrect dates and times [#89]
* Added timezone abbreviation display in admin Events list for better clarity

= 1.2.4 =
* Added recurring event pattern support to the event submission form [#27]

= 1.2.3 =
* Fixed HTML entities in category names showing incorrectly (e.g., "&" showing as "&amp;") in the event submission form [#84]

= 1.2.2 =
* Added contact name field and exposed email as well to the admin UI (fields are private and meant for point of contact) [#81]
* Added Service Body name into the Admin UI [#80]

= 1.2.1 =
* Removed PDF support which was unstable and inconsistent to maintain.
* Fixing external sources admin side, which was broken. [#76]

= 1.2.0 =
* Added the ability for external event pulling from other Mayo driven sites. [#4]
* Added the ability to filter on muliple service body ids [#70]
* Notification emails are now customizable. [#72]
* Set fixed size for PDFs [#73]

= 1.1.3 =
* Added CSS skinning capabilities and documentation. [#51]

= 1.1.2 =
* Added Unaffiliated option for Service Body selection. [#1]

= 1.1.1 =
* Switch to ICS format for Calendar Feed.
* Fix RSS icon which wasn't showing for non-logged in users.

= 1.1.0 =
* Added the ability to upload PDFs and display them.
* Added the ability to set other required fields on the event submission form.
* Added custom classes for tags, categories, service body and event type [#51].
* Calendar RSS link [#11]
* Fix to prevent insecure root servers [#50].

= 1.0.11 =
* Fix for root server settings not saving [#48].

= 1.0.10 =
* Added widget support [#10].
* Added text on the submission for to indicate what file types are allowed.
* Added the ability to show events with a given status [#31].
* Override some of the shortcode parameters via querystring [#32].
* Moved filtering to the REST API [#32].
* Fixed nonce issue [#10].
* Clicking flyer now opens in a new tab [#5].
* Re-occuring events now shows below the date in the gutenberg editor [#3].
* Service body name now shows all places [#2].
