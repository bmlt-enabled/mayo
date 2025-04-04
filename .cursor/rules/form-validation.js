module.exports = {
    patterns: {
        requiredFields: {
            defaultFields: `
                // Define default required fields that cannot be overridden
                const defaultRequiredFields = [
                    'event_name',
                    'event_type',
                    'service_body',
                    'email',
                    'event_start_date',
                    'event_start_time',
                    'event_end_time',
                    'event_end_date',
                    'timezone'
                ];
            `,
            additionalFields: `
                // Get additional required fields from settings
                const additionalRequiredFields = settings.additionalRequiredFields ? 
                    settings.additionalRequiredFields.split(',').map(field => field.trim()) : 
                    [];

                // Combine both arrays for all required fields
                const allRequiredFields = [...defaultRequiredFields, ...additionalRequiredFields];
            `,
            fieldValidation: `
                // Check if a field is required
                const isFieldRequired = (fieldName) => {
                    return allRequiredFields.includes(fieldName);
                };

                // Usage in JSX
                <label htmlFor="fieldName">
                    Field Label {isFieldRequired('fieldName') && '*'}
                </label>
                <input
                    required={isFieldRequired('fieldName')}
                    // ... other props
                />
            `
        },
        fileUpload: {
            hiddenInput: `
                // Proper way to hide file input while maintaining accessibility
                .mayo-file-input {
                    width: 0.1px;
                    height: 0.1px;
                    opacity: 0;
                    overflow: hidden;
                    position: absolute;
                    z-index: -1;
                }
            `
        }
    },
    relationships: {
        shortcodeToComponent: [
            {
                source: "includes/Frontend.php",
                target: "assets/js/src/components/public/EventForm.js",
                description: "Shortcode attributes are passed as settings to the React component"
            }
        ]
    },
    commonPatterns: {
        formValidation: `
            // Form validation with required fields
            const missingFields = allRequiredFields.filter(field => {
                if (field === 'flyer') {
                    return !formData[field];
                }
                return !formData[field];
            });

            if (missingFields.length > 0) {
                throw new Error(\`Please fill in all required fields: \${missingFields.join(', ')}\`);
            }
        `
    }
}; 