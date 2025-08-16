# Submitter Notifications

This document describes the submitter notification functionality implemented in Mayo Events Manager version 1.3.11 to address issue #135.

## Overview

When an event is approved and published by an administrator, the plugin can now automatically send an email notification to the person who submitted the event. This provides valuable feedback to event submitters, letting them know that their event has been approved and is now live on the website.

## How It Works

1. **Event Submission**: When a user submits an event through the event submission form, the event is saved with a "pending" status and the submitter's email address is stored in the event metadata.

2. **Admin Approval**: When an administrator changes the event status from "pending" to "publish" in the WordPress admin, the plugin detects this status transition.

3. **Email Notification**: If submitter notifications are enabled, the plugin automatically sends a comprehensive email to the event submitter with details about their published event.

## Email Content

The submitter notification email includes:

### Basic Event Details
- **Event Name**: The title of the event
- **Event Type**: The type/category of the event
- **Service Body**: The service body name (resolved from BMLT server)
- **Start Date**: The event start date
- **Start Time**: The event start time
- **End Date**: The event end date
- **End Time**: The event end time
- **Timezone**: The timezone for the event

### Location Information
- **Location Name**: The name of the venue/location
- **Location Address**: The physical address
- **Location Details**: Additional location information

### Event Classification
- **Categories**: WordPress categories assigned to the event
- **Tags**: WordPress tags assigned to the event

### Recurring Pattern Information
For recurring events, the email includes details about the recurrence pattern:
- **Daily**: "Daily (every X days)" if interval > 1
- **Weekly**: "Weekly (every X weeks) on [days]" with specific weekdays
- **Monthly**: "Monthly (every X months) on day [date]" or "on [weekday]"

### Event Description
- **Description**: The full event description/content

### Links
- **View your event**: Direct link to the published event page

## Configuration

Submitter notifications are automatically enabled and cannot be disabled. When an event is published, a notification email is always sent to the event submitter.

### Requirements
- **BMLT Root Server**: Required for fetching service body names
- **Valid Email**: The event must have a valid submitter email address

## Example Email

```
Subject: Your event has been published: Weekly Support Meeting

Hello John Doe,

Great news! Your event has been approved and published on our website.

Event Details:
Event Name: Weekly Support Meeting
Event Type: Meeting
Service Body: Test Service Body
Start Date: 2024-01-15
Start Time: 19:00
End Date: 2024-01-15
End Time: 20:00
Timezone: America/New_York

Location:
  Community Center
  123 Main St, Anytown, ST 12345
  Room 101

Categories: Support Groups
Tags: weekly, support, open

Recurring Pattern: Weekly on Monday, Wednesday until 2024-12-31

Description:
This is a weekly support meeting for community members. 
All are welcome to attend.

View your event: https://example.com/mayo/weekly-support-meeting/

Thank you for submitting your event!

Best regards,
Your Website Name
```

## Technical Implementation

The submitter notification functionality is implemented using WordPress hooks:

### WordPress Hook
- **`transition_post_status`**: Detects when post status changes from "pending" to "publish"

### Key Functions
- **`handle_event_status_transition()`**: Main handler for post status transitions
- **`send_event_published_notification()`**: Sends the notification email



## Error Handling

The system includes comprehensive error handling:

- **Missing Email**: If no valid email is found, an error is logged and no notification is sent
- **Email Send Failure**: If `wp_mail()` fails, an error is logged
- **Invalid Post Type**: Only processes `mayo_event` post types
- **Invalid Status Transition**: Only processes "pending" to "publish" transitions

## Testing

A comprehensive test suite has been created in `tests/integration/test-submitter-notifications.php` to verify:

- ✅ Notifications are sent when events are published
- ✅ Notifications are not sent for other post types
- ✅ Notifications are not sent for other status transitions
- ✅ Notifications are not sent without email addresses
- ✅ Recurring pattern information is included correctly

## Backward Compatibility

This enhancement is fully backward compatible:

- ✅ Existing installations will work without changes
- ✅ Submitter notifications are always enabled
- ✅ No changes required to existing event submission forms

## Privacy Considerations

- **Email Storage**: Submitter emails are stored in post meta and are private
- **Email Usage**: Emails are only used for notifications about the specific event
- **No Marketing**: The system does not send marketing emails or newsletters
- **Opt-out**: Administrators can disable notifications globally

## Troubleshooting

### Common Issues

1. **No emails being sent**
   - Verify the event has a valid email address
   - Check WordPress email configuration
   - Review error logs for email send failures

2. **Missing service body names**
   - Ensure BMLT root server is configured
   - Check network connectivity to BMLT server
   - Verify service body IDs are correct

3. **Incomplete email content**
   - Ensure all required event metadata is present
   - Check for missing location, category, or tag information

### Debug Information

Enable WordPress debug logging to see detailed information about:
- Email send attempts
- Service body resolution
- Status transition detection
- Error conditions 