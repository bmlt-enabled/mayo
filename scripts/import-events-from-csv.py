#!/usr/bin/env python3
"""
Mayo Events Manager - CSV Import Script

This script imports events from a CSV file into Mayo Events Manager
via the REST API.

Usage:
    python3 import-events-from-csv.py <csv-file> <wordpress-url>

Example:
    python3 import-events-from-csv.py events.csv https://example.com

Options:
    --dry-run       Validate CSV and show what would be imported
    --delay=N       Delay N seconds between requests (default: 0.5)

CSV Format:
    The CSV should have a header row with the following columns:
    - event_name (required): The title of the event
    - description: Event description/content
    - event_type: Type of event (e.g., "convention", "workshop")
    - event_start_date (required): Start date in YYYY-MM-DD format
    - event_end_date: End date in YYYY-MM-DD format
    - event_start_time: Start time in HH:MM format (24-hour)
    - event_end_time: End time in HH:MM format (24-hour)
    - timezone: Timezone (e.g., "America/New_York")
    - location_name: Name of the venue
    - location_address: Street address
    - location_details: Additional location info
    - contact_name: Contact person's name
    - email: Contact email address
    - service_body: Service body ID (for BMLT integration)
    - categories: Comma-separated category IDs
    - tags: Comma-separated tags

Notes:
    - Events are submitted as "pending" status and need admin approval
    - No authentication required (public submission endpoint)
"""

import csv
import json
import re
import sys
import time
import urllib.request
import urllib.error
from argparse import ArgumentParser
from pathlib import Path


# ANSI colors for terminal output
class Colors:
    RED = '\033[0;31m'
    GREEN = '\033[0;32m'
    YELLOW = '\033[1;33m'
    NC = '\033[0m'  # No Color


def validate_date(date_str):
    """Validate date format (YYYY-MM-DD)."""
    return bool(re.match(r'^\d{4}-\d{2}-\d{2}$', date_str))


def validate_time(time_str):
    """Validate time format (HH:MM or HH:MM:SS)."""
    return bool(re.match(r'^\d{2}:\d{2}(:\d{2})?$', time_str))


def validate_email(email):
    """Basic email validation."""
    return bool(re.match(r'^[^\s@]+@[^\s@]+\.[^\s@]+$', email))


def validate_event(event, row_num):
    """Validate an event dictionary. Returns list of error messages."""
    errors = []

    if not event.get('event_name'):
        errors.append(f"Row {row_num}: Missing required field 'event_name'")

    start_date = event.get('event_start_date', '')
    if not start_date:
        errors.append(f"Row {row_num}: Missing required field 'event_start_date'")
    elif not validate_date(start_date):
        errors.append(f"Row {row_num}: Invalid date format '{start_date}'. Use YYYY-MM-DD")

    end_date = event.get('event_end_date', '')
    if end_date and not validate_date(end_date):
        errors.append(f"Row {row_num}: Invalid date format '{end_date}' for event_end_date. Use YYYY-MM-DD")

    start_time = event.get('event_start_time', '')
    if start_time and not validate_time(start_time):
        errors.append(f"Row {row_num}: Invalid time format '{start_time}'. Use HH:MM")

    end_time = event.get('event_end_time', '')
    if end_time and not validate_time(end_time):
        errors.append(f"Row {row_num}: Invalid time format '{end_time}'. Use HH:MM")

    email = event.get('email', '')
    if email and not validate_email(email):
        errors.append(f"Row {row_num}: Invalid email format '{email}'")

    return errors


def submit_event(event, wp_url):
    """Submit an event to the WordPress REST API."""
    api_url = f"{wp_url.rstrip('/')}/wp-json/event-manager/v1/submit-event"

    # Build payload with defaults
    payload = {
        'event_name': event.get('event_name', ''),
        'description': event.get('description', ''),
        'event_type': event.get('event_type', ''),
        'event_start_date': event.get('event_start_date', ''),
        'event_end_date': event.get('event_end_date') or event.get('event_start_date', ''),
        'event_start_time': event.get('event_start_time') or '00:00',
        'event_end_time': event.get('event_end_time') or '23:59',
        'timezone': event.get('timezone') or 'America/New_York',
        'location_name': event.get('location_name', ''),
        'location_address': event.get('location_address', ''),
        'location_details': event.get('location_details', ''),
        'contact_name': event.get('contact_name', ''),
        'email': event.get('email', ''),
        'service_body': event.get('service_body') or '0',
        'categories': event.get('categories', ''),
        'tags': event.get('tags', ''),
    }

    data = json.dumps(payload).encode('utf-8')

    request = urllib.request.Request(
        api_url,
        data=data,
        headers={'Content-Type': 'application/json'},
        method='POST'
    )

    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            result = json.loads(response.read().decode('utf-8'))
            return True, result
    except urllib.error.HTTPError as e:
        error_body = e.read().decode('utf-8')
        return False, f"HTTP {e.code}: {error_body}"
    except urllib.error.URLError as e:
        return False, f"Connection error: {e.reason}"
    except Exception as e:
        return False, str(e)


def read_csv(csv_path):
    """Read and parse CSV file."""
    events = []

    with open(csv_path, 'r', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)

        # Normalize header names (strip whitespace, lowercase for matching)
        if reader.fieldnames:
            reader.fieldnames = [name.strip() for name in reader.fieldnames]

        for row in reader:
            # Strip whitespace from all values
            event = {k: v.strip() if v else '' for k, v in row.items()}
            events.append(event)

    return events


def main():
    parser = ArgumentParser(
        description='Import events from CSV into Mayo Events Manager'
    )
    parser.add_argument('csv_file', help='Path to the CSV file')
    parser.add_argument('wordpress_url', help='WordPress site URL')
    parser.add_argument('--dry-run', action='store_true',
                        help='Validate CSV without importing')
    parser.add_argument('--delay', type=float, default=0.5,
                        help='Delay between requests in seconds (default: 0.5)')

    args = parser.parse_args()

    # Validate CSV file exists
    csv_path = Path(args.csv_file)
    if not csv_path.exists():
        print(f"{Colors.RED}Error: CSV file not found: {args.csv_file}{Colors.NC}")
        sys.exit(1)

    print("Mayo Events Manager - CSV Import")
    print("=================================")
    print(f"CSV File: {args.csv_file}")
    print(f"WordPress URL: {args.wordpress_url}")
    print()

    # Read CSV
    try:
        events = read_csv(csv_path)
    except Exception as e:
        print(f"{Colors.RED}Error reading CSV: {e}{Colors.NC}")
        sys.exit(1)

    print(f"Found {len(events)} events in CSV")
    print()

    if not events:
        print("No events to import.")
        sys.exit(0)

    # Validate all events
    print("Validating events...")
    all_errors = []

    for i, event in enumerate(events, start=2):  # +2 for header row and 1-based index
        errors = validate_event(event, i)
        all_errors.extend(errors)

    if all_errors:
        print(f"\n{Colors.RED}Validation errors found:{Colors.NC}")
        for error in all_errors:
            print(f"  - {error}")
        print("\nPlease fix the errors and try again.")
        sys.exit(1)

    print(f"{Colors.GREEN}All events validated successfully!{Colors.NC}")
    print()

    if args.dry_run:
        print(f"{Colors.YELLOW}DRY RUN MODE - No events will be imported{Colors.NC}")
        print()
        print("Events that would be imported:")
        print("-" * 40)
        for i, event in enumerate(events, start=1):
            print(f"  {i}. {event['event_name']} ({event['event_start_date']})")
        sys.exit(0)

    # Import events
    print(f"Importing events to: {args.wordpress_url}")
    print(f"Delay between requests: {args.delay}s")
    print()

    success_count = 0
    fail_count = 0
    failed_events = []

    for i, event in enumerate(events, start=1):
        event_name = event['event_name']
        print(f"[{i}/{len(events)}] Importing: {event_name}... ", end='', flush=True)

        success, result = submit_event(event, args.wordpress_url)

        if success:
            event_id = result.get('id', 'unknown')
            print(f"{Colors.GREEN}✓{Colors.NC} (ID: {event_id})")
            success_count += 1
        else:
            print(f"{Colors.RED}✗{Colors.NC}")
            print(f"    Error: {result}")
            fail_count += 1
            failed_events.append({'name': event_name, 'error': result})

        # Delay between requests
        if i < len(events):
            time.sleep(args.delay)

    # Print summary
    print()
    print("=" * 50)
    print("IMPORT SUMMARY")
    print("=" * 50)
    print(f"Total events: {len(events)}")
    print(f"Successful: {Colors.GREEN}{success_count}{Colors.NC}")
    print(f"Failed: {Colors.RED}{fail_count}{Colors.NC}")

    if failed_events:
        print()
        print("Failed imports:")
        for item in failed_events:
            print(f"  - {item['name']}: {item['error']}")

    if success_count > 0:
        print()
        print(f"{Colors.YELLOW}Note: Events are imported with 'pending' status.{Colors.NC}")
        print("An admin needs to review and publish them in WordPress.")


if __name__ == '__main__':
    main()
