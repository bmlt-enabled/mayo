# Enhanced Email Notifications

This document describes the enhanced email notification functionality implemented in Mayo Events Manager version 1.3.10 to address issue #134.

## Overview

When a new event is submitted through the event submission form, an email notification is sent to the configured email addresses. The email now includes comprehensive details about the submitted event to provide administrators with all the information they need to review and approve the event.

## Email Content

The enhanced email notification includes the following information:

### Basic Event Details
- **Event Name**: The title of the event
- **Event Type**: The type/category of the event
- **Service Body**: The service body name and ID (e.g., "Test Service Body (ID: 1)")
- **Start Date**: The event start date
- **Start Time**: The event start time
- **End Date**: The event end date
- **End Time**: The event end time
- **Timezone**: The timezone for the event

### Contact Information
- **Contact Name**: The name of the person submitting the event
- **Contact Email**: The email address of the person submitting the event

### Location Information
- **Location Name**: The name of the venue/location
- **Location Address**: The physical address
- **Location Details**: Additional location information (room number, directions, etc.)

### Event Classification
- **Categories**: WordPress categories assigned to the event
- **Tags**: WordPress tags assigned to the event

### Recurring Pattern Information
For recurring events, the email includes details about the recurrence pattern:
- **Daily**: "Daily (every X days)" if interval > 1
- **Weekly**: "Weekly (every X weeks) on [days]" with specific weekdays
- **Monthly**: "Monthly (every X months) on day [date]" or "on [weekday]"

### File Attachments
- **Attachments**: List of uploaded files (flyers, images, etc.)

### Event Description
- **Description**: The full event description/content

### Administrative Link
- **View the event**: Direct link to the WordPress admin page for reviewing and editing the event

## Configuration

Email notifications are configured in the WordPress admin under **Mayo > Settings**. You can specify:

- **Notification Email**: Email addresses to receive notifications (multiple addresses can be separated by commas or semicolons)
- **BMLT Root Server**: Required for fetching service body names

If no notification email is configured, notifications will be sent to the WordPress admin email address.

## Example Email

```
Subject: New Event Submission: Weekly Support Meeting

A new event has been submitted:

Event Name: Weekly Support Meeting
Event Type: Meeting
Service Body: Test Service Body (ID: 1)
Start Date: 2024-01-15
Start Time: 19:00
End Date: 2024-01-15
End Time: 20:00
Timezone: America/New_York
Contact Name: John Doe
Contact Email: john@example.com

Recurring Pattern: Weekly on Monday, Wednesday until 2024-12-31

Location:
  Name: Community Center
  Address: 123 Main St, Anytown, ST 12345
  Details: Room 101

Categories: Support Groups
Tags: weekly, support, open

Attachments: meeting-flyer.jpg

Description:
This is a weekly support meeting for community members. 
All are welcome to attend.

View the event: https://example.com/wp-admin/post.php?post=123&action=edit
```

## Technical Implementation

The enhanced email functionality is implemented in the `send_event_submission_email()` method in `includes/Rest.php`. Key features:

- **Service Body Resolution**: Fetches service body names from the BMLT root server
- **Recurring Pattern Formatting**: Converts JSON recurring pattern data into human-readable text
- **File Attachment Detection**: Scans `$_FILES` array to identify uploaded files
- **Comprehensive Data**: Includes all available event metadata in the email

## Testing

A comprehensive test suite has been created in `tests/integration/test-email-notifications.php` to verify that all email content is correctly formatted and includes all required information.

## Backward Compatibility

This enhancement is fully backward compatible. Existing installations will continue to work, and the enhanced emails will provide more detailed information without requiring any configuration changes. 