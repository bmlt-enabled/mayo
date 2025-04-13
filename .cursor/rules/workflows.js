module.exports = {
    fileUploadImplementation: {
        name: "Implementing file uploads",
        steps: [
            {
                description: "Add file input handling in React component",
                pattern: "handleChange function with file type detection"
            },
            {
                description: "Use FormData for form submission",
                pattern: "FormData creation with file and field handling"
            },
            {
                description: "Process files in Rest.php bmltenabled_mayo_submit_event",
                pattern: "File upload handling after metadata processing"
            },
            {
                description: "Handle image attachments consistently",
                pattern: "Conditional processing based on mime type"
            },
            {
                description: "Update post meta for file URLs and IDs",
                pattern: "Store file references in post meta"
            }
        ]
    }
}; 