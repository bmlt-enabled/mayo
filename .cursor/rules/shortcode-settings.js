module.exports = {
    patterns: {
        shortcodeAttributes: {
            required: `
                // Define shortcode attributes with defaults
                $defaults = [
                    'additional_required_fields' => ''
                ];
                $atts = shortcode_atts($defaults, $atts);
            `,
            passToReact: `
                // Pass shortcode settings to React component
                wp_localize_script('mayo-public', $settings_key, [
                    'additionalRequiredFields' => $atts['additional_required_fields']
                ]);

                return sprintf(
                    '<div id="mayo-event-form" data-settings="%s"></div>',
                    esc_attr($settings_key)
                );
            `
        }
    },
    bestPractices: [
        {
            title: "Required Fields Management",
            description: "Default required fields should be defined in the React component and cannot be overridden. Additional required fields can be added via shortcode attributes.",
            example: `[mayo_event_form additional_required_fields="description,flyer"]`
        },
        {
            title: "Form Validation",
            description: "Always validate both default and additional required fields before form submission."
        }
    ]
}; 