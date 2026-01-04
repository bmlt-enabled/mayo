# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the Mayo Events Manager WordPress plugin - a comprehensive event management system for WordPress. The plugin allows users to submit events through frontend forms, manage them through WordPress admin, and display them via shortcodes. It includes support for recurring events, service body categorization (BMLT integration), and automated email notifications.

## Key Development Commands

### Build & Development
- `npm run build` - Build production JavaScript bundles
- `npm run dev` - Build development bundles with watch mode
- `composer install` - Install PHP dependencies

### Testing
- `composer test` - Run all PHPUnit tests
- `composer test:integration` - Run integration tests only

### Code Quality
- `composer lint` - Run PHP CodeSniffer for linting PHP files
- `composer lint:fix` - Auto-fix PHP CodeSniffer issues

## Architecture Overview

### Core Components Structure
- **Main Plugin File**: `mayo-events-manager.php` - Plugin initialization, post type registration, template loading
- **Admin Class**: `includes/Admin.php` - Admin interface, post type registration, meta fields, custom columns
- **Frontend Class**: `includes/Frontend.php` - Shortcode handlers, frontend script enqueuing
- **Rest Class**: `includes/Rest.php` - REST API endpoints for event submission and retrieval
- **CalendarFeed Class**: `includes/CalendarFeed.php` - Calendar feed functionality

### Post Type & Meta Structure
The plugin creates a `mayo_event` custom post type with extensive metadata:
- Basic event info: `event_type`, `event_start_date`, `event_end_date`, `event_start_time`, `event_end_time`, `timezone`
- Location: `location_name`, `location_address`, `location_details`
- Contact: `contact_name`, `email`
- BMLT integration: `service_body` (for Narcotics Anonymous service body categorization)
- Recurring events: `recurring_pattern` (complex object), `skipped_occurrences` (array)

### Frontend Integration
- **JavaScript Bundles**: Built with webpack, separate admin/public bundles
- **React Components**: Uses WordPress components and React for admin interface
- **Shortcodes**: `[mayo_event_form]` for submissions, `[mayo_event_list]` for display
- **Templates**: Custom post type templates in `templates/` directory

### JavaScript Architecture
- Entry points: `assets/js/src/admin.js`, `assets/js/src/public.js`
- Webpack configuration handles React/WordPress component externals
- Scripts enqueue conditionally based on shortcode presence and post type contexts

### REST API Endpoints
Namespace: `event-manager/v1`
- `POST /submit-event` - Public event submission
- `GET /events` - Event listing with filtering
- `GET /settings` - Plugin settings retrieval
- `POST /settings` - Plugin settings update (admin only)
- `GET /event/{slug}` - Individual event details

### Testing Structure
- PHPUnit with WordPress test framework
- Integration tests in `tests/integration/`
- Test configuration in `phpunit.xml`
- Uses `wp-phpunit/wp-phpunit` for WordPress-specific testing

### Key Features
- **Recurring Events**: Complex recurring pattern system with skip functionality
- **Email Notifications**: Auto-notifications when events are published from pending status
- **BMLT Integration**: Service body management for Narcotics Anonymous events
- **Timezone Support**: Full timezone handling for events
- **Image Handling**: Featured image support for event flyers
- **Copy Functionality**: Admin can copy existing events
- **Categories & Tags**: Standard WordPress taxonomy support

### Development Notes
- Uses PSR-4 autoloading with namespace `BmltEnabled\Mayo\`
- Minimum PHP version: 8.2
- WordPress Coding Standards compliance via PHPCS
- All user inputs are properly sanitized for security
- AJAX nonce verification for admin actions

## Release Notes Automation

**IMPORTANT**: When you add new features, fix bugs, or make significant changes, you MUST update the changelog in `readme.txt`.

### Changelog Format
The changelog follows WordPress plugin standards in `readme.txt`:
```
= X.X.X =
* Description of change or fix.
* Another change description.
```

### When to Update Changelog
Update the changelog whenever you:
- Fix bugs (especially user-facing issues)
- Add new features or functionality  
- Make breaking changes
- Improve performance significantly
- Update dependencies or requirements

### How to Update
1. **Add a new version section** at the top of the changelog (after `== Changelog ==`)
2. **Increment version number** appropriately:
   - Patch (X.X.1): Bug fixes, minor improvements
   - Minor (X.1.0): New features, non-breaking changes
   - Major (1.X.0): Breaking changes, major rewrites
3. **Write clear, user-focused descriptions**:
   - Start with action words (Fixed, Added, Improved, etc.)
   - Describe the user impact, not technical details
   - Reference GitHub issue numbers when applicable: `[#123]`

### Examples
```
= 1.4.3 =
* Fixed monthly recurring events bug where "last day of month" events would show repeatedly on the same day instead of advancing to next month.

= 1.4.4 =
* Added new timezone picker for better event scheduling.
* Improved performance of recurring event calculations.
* Fixed email notifications not sending for pending events.
```

### Version Consistency
Also update the version number in:
- `mayo-events-manager.php` (line 23: `MAYO_VERSION` constant)
- `readme.txt` (line 8: `Stable tag`)
- `package.json` (line 3: `version`)