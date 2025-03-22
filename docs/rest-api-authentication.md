# REST API Authentication in Mayo Events Manager

This document explains how authentication works in the Mayo Events Manager REST API and how to troubleshoot common issues.

## API Endpoints

The plugin registers several REST API endpoints under the `event-manager/v1` namespace:

- `GET /settings` - Get plugin settings
- `POST /settings` - Update plugin settings (requires `manage_options` capability)
- `GET /events` - Get all events
- `GET /events/{id}` - Get a specific event
- `POST /events` - Create a new event (requires `edit_posts` capability)
- `PUT /events/{id}` - Update an event (requires `edit_posts` capability)
- `DELETE /events/{id}` - Delete an event (requires admin or being the author)

## Authentication Methods

The plugin uses WordPress's built-in REST API authentication mechanisms:

1. **Cookie Authentication** - For logged-in users
2. **Nonce Authentication** - For CSRF protection

### Nonce Implementation

WordPress requires a valid nonce to be sent with REST API requests to prevent CSRF attacks. In the Mayo Events Manager, this is handled in the `util.js` file:

```javascript
export const apiFetch = async (endpoint, options = {}) => {
  const baseUrl = '/wp-json/event-manager/v1';
  const url = `${baseUrl}${endpoint}`;
  
  const defaultOptions = {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': window.mayoApiSettings?.nonce || window.wpApiSettings?.nonce || ''
    }
  };
  
  const fetchOptions = { ...defaultOptions, ...options };
  
  // Rest of the function...
};
```

For this to work, the nonce must be properly localized in the Admin.php file:

```php
wp_localize_script(
    'mayo-admin',
    'mayoApiSettings',
    [
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'namespace' => Rest::ROUTE_BASE
    ]
);
```

## Troubleshooting 401 Errors

If you're getting a 401 Unauthorized error when making requests to the REST API, check the following:

1. **User Capabilities**
   - For `/settings` endpoint: Ensure the user has the `manage_options` capability (typically administrators)
   - For event endpoints: Ensure the user has at least the `edit_posts` capability

2. **Nonce Issues**
   - Check browser console for JavaScript errors
   - Verify that `mayoApiSettings.nonce` is being correctly output to the page
   - Check network requests to confirm the `X-WP-Nonce` header is being sent

3. **Cookie Authentication**
   - Ensure the user is logged in
   - Check that `credentials: 'same-origin'` is set in fetch options

4. **Debugging Tips**
   - Add more detailed error logging to the apiFetch function:
   ```javascript
   if (!response.ok) {
     const errorText = await response.text();
     console.error(`API error (${response.status}): ${errorText}`);
     // More debugging information
     throw new Error(`API error: ${response.status} ${response.statusText}`);
   }
   ```

5. **Common Solutions**
   - Reload the admin page to get a fresh nonce
   - Log out and log back in to refresh your WordPress session
   - Clear browser cache and cookies

## Implementation Reference

### PHP Side (Rest.php)

```php
register_rest_route(
    self::ROUTE_BASE, '/settings', [
        'methods'  => \WP_REST_Server::EDITABLE,
        'callback' => [__CLASS__, 'updateSettings'],
        'args' => [
            'rootserver' => [
                'required' => true,
                'validate_callback' => function ($param) {
                    return is_string($param);
                }
            ]
        ],
        'permission_callback' => function () {
            // Only users who can manage options (admins) can update settings
            return current_user_can('manage_options');
        }
    ]
);
```

### JavaScript Side (util.js)

```javascript
export const apiFetch = async (endpoint, options = {}) => {
  const baseUrl = '/wp-json/event-manager/v1';
  const url = `${baseUrl}${endpoint}`;
  
  const defaultOptions = {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': window.mayoApiSettings?.nonce || window.wpApiSettings?.nonce || ''
    }
  };
  
  const fetchOptions = { ...defaultOptions, ...options };
  
  try {
    const response = await fetch(url, fetchOptions);
    
    if (!response.ok) {
      throw new Error(`API error: ${response.status} ${response.statusText}`);
    }
    
    return await response.json();
  } catch (error) {
    console.error('API fetch error:', error);
    throw error;
  }
};
``` 