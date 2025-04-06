# Mayo WordPress Plugin

A WordPress plugin for managing and displaying events from a BMLT root server.

## CSS Documentation

The plugin includes documentation for the dynamic CSS classes used in the Event Card component. This helps developers understand and customize the appearance of events on their site.

### Important Note About Styling

**This plugin does not include a built-in CSS editor.** To style the event cards, you need to use another plugin like "Simple Custom CSS" or add CSS to your theme's stylesheet. The documentation is provided to help you understand which CSS classes are available for styling.

### Viewing the Documentation

The CSS documentation is available in the WordPress admin area under the Mayo menu. It includes:

- Dynamic classes generated based on event properties
- Examples of how to customize the appearance

### Customization

You can customize the appearance of events by adding CSS rules to your theme or using a custom CSS plugin. The documentation provides examples of common customizations, such as:

- Styling specific categories or tags
- Customizing the appearance of different event types
- Adding styles for specific service bodies

#### Recommended CSS Plugins

- [Simple Custom CSS](https://wordpress.org/plugins/simple-custom-css/)
- [Custom CSS and JS](https://wordpress.org/plugins/custom-css-js/)
- [Advanced Custom CSS](https://wordpress.org/plugins/advanced-custom-css/)

## Development

### Prerequisites

- Node.js (v14 or higher)
- npm (v6 or higher)

### Installation

1. Clone the repository
2. Install dependencies:

   ```bash
   npm install
   ```

3. Build the plugin:

   ```bash
   npm run build
   ```

### Development Workflow

1. Start the development server:

   ```bash
   npm run dev
   ```

2. Make changes to the code
3. Rebuild the plugin as needed

## License

This project is licensed under the GPL v2 or later. 