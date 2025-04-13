module.exports = {
    fileUploads: [
        {
            issue: "Form submission with file uploads not working",
            solution: "Ensure files are handled consistently in both frontend and backend. Use FormData for file uploads and process all form fields before handling attachments in Rest.php.",
            files: [
                "includes/Rest.php",
                "assets/js/src/components/public/EventForm.js"
            ]
        },
    ]
}; 