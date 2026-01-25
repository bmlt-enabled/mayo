#!/bin/bash
# Check for esc_url() usage in REST API contexts (should use esc_url_raw() instead)
# See issue #249 for context

ERRORS=0

# Check in includes/Rest directory
if grep -rn "esc_url(" includes/Rest/ 2>/dev/null; then
    echo "WARNING: Found esc_url() in includes/Rest/ - use esc_url_raw() for REST API contexts"
    ERRORS=1
fi

# Check in Announcement.php (has REST-consumed methods)
if grep -n "esc_url(" includes/Announcement.php 2>/dev/null | grep -v "esc_url_raw"; then
    echo "WARNING: Found esc_url() in includes/Announcement.php - verify it's not used for REST API data"
    ERRORS=1
fi

if [ $ERRORS -eq 0 ]; then
    echo "No esc_url() issues found in REST contexts"
fi

exit $ERRORS
