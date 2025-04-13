module.exports = {
    patterns: {
        frontend: {
            // Form handling pattern for file uploads
            formDataCreation: `
                const data = new FormData();
                if (formData.flyer instanceof File) {
                    data.append('flyer', formData.flyer);
                }
                Object.keys(formData).forEach(key => {
                    if (formData[key] != null && formData[key] !== '' && !(formData[key] instanceof File)) {
                        data.append(key, formData[key]);
                    }
                });
            `,
        },
        backend: {
            // WordPress file upload handling
            fileProcessing: `
                // Process files after metadata
                if (strpos($uploaded_file['type'], 'image/') === 0) {
                    set_post_thumbnail($post_id, $attachment_id);
                }
            `
        }
    },
    relationships: {
        components: [
            {
                source: "includes/Rest.php",
                target: "assets/js/src/components/public/EventForm.js",
                description: "Form submission and file upload handling"
            },
            {
                source: "includes/Rest.php",
                target: "assets/js/src/components/public/cards/EventCard.js",
                description: "Image display in event cards"
            }
        ]
    }
}; 