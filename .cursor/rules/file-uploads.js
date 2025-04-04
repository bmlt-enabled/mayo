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
            // File input handling pattern
            fileInputHandling: `
                const handleChange = (e) => {
                    const { name, value, files } = e.target;
                    if (files && files[0]) {
                        const file = files[0];
                        setUploadType(file.type === 'application/pdf' ? 'pdf' : 'image');
                        setFormData(prev => ({
                            ...prev,
                            flyer: file
                        }));
                    }
                };
            `
        },
        backend: {
            // WordPress file upload handling
            fileProcessing: `
                // Process files after metadata
                if ($uploaded_file['type'] === 'application/pdf') {
                    update_post_meta($post_id, 'event_pdf_url', $uploaded_file['url']);
                    update_post_meta($post_id, 'event_pdf_id', $attachment_id);
                } 
                elseif (strpos($uploaded_file['type'], 'image/') === 0) {
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
                description: "PDF and image display in event cards"
            }
        ]
    }
}; 