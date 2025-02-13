<?php

namespace BmltEnabled\Mayo;

class PublicInterface {
    public static function init() {
        add_shortcode('mayo_event_form', [__CLASS__, 'render_event_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function render_event_form() {
        ob_start();
        ?>
        <form id="mayo-event-form">
            <input type="text" name="event_name" placeholder="Event Name" required>
            <input type="text" name="event_type" placeholder="Event Type" required>
            <input type="date" name="event_date" required>
            <input type="time" name="event_start_time" required>
            <input type="time" name="event_end_time" required>
            <textarea name="description" placeholder="Event Description"></textarea>
            <input type="url" name="flyer_url" placeholder="Flyer URL">
            <button type="submit">Submit Event</button>
        </form>
        <script>
        document.getElementById('mayo-event-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const data = Object.fromEntries(new FormData(form));
            
            try {
                const response = await fetch('/wp-json/event-manager/v1/submit-event', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    alert('Event submitted successfully! Awaiting approval.');
                    form.reset();
                } else {
                    alert('Error submitting event: ' + result.message);
                }
            } catch (error) {
                alert('Error submitting event');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public static function enqueue_scripts() {
        wp_enqueue_script(
            'event-manager-public',
            plugin_dir_url(__FILE__) . '../assets/js/dist/public.bundle.js',
            ['wp-element'],
            '1.0',
            true
        );
    }
}
