# AMPERS PRX Import Plugin

A WordPress plugin for importing content from the PRX CMS API into WordPress. This plugin provides both WP-CLI commands and a web interface for managing PRX content imports.

## Features

- **OAuth2 Authentication**: Secure authentication with PRX using OAuth2 client credentials flow
- **Multi-Environment Support**: Works with both staging and production PRX environments
- **WP-CLI Integration**: Command-line tools for bulk imports and testing
- **Admin Interface**: WordPress admin settings page for configuration
- **Class-Based Architecture**: Clean, maintainable code structure

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WP-CLI (for command-line functionality)
- Advanced Custom Fields (ACF) plugin (for custom fields)

## Installation

1. Upload the plugin files to `/wp-content/plugins/ampers-prx-import/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your PRX API credentials in the admin settings

## Configuration

### Getting PRX API Credentials

You'll need to obtain OAuth2 client credentials from PRX:

1. **For Staging**: Visit https://id.staging.prx.tech/client_applications
2. **For Production**: Visit https://id.prx.org/client_applications

Note: OAuth applications must be manually created by PRX. Contact PRX support to request access.

### Setting Up Credentials

#### Option 1: WordPress Admin Interface

1. Go to **Settings > PRX Import** in your WordPress admin
2. Select your environment (staging or production)
3. Enter your Client ID and Client Secret
4. Save the settings

#### Option 2: WordPress Options (Programmatically)

```php
update_option( 'ampers_prx_client_id', 'your_client_id' );
update_option( 'ampers_prx_client_secret', 'your_client_secret' );
update_option( 'ampers_prx_environment', 'production' ); // or 'staging'
```

## Usage

### WP-CLI Commands

#### Testing Authentication

Test your PRX API connection:

```bash
# Test with credentials from WordPress options
wp ampers test-auth

# Test with specific credentials
wp ampers test-auth --environment=staging --client-id=your_id --client-secret=your_secret

# Test production environment
wp ampers test-auth --environment=production
```

#### Importing Content

Import stories from a PRX network:

```bash
# Import 10 stories from network 7 (Ampers)
wp ampers import-prx --network-id=7 --limit=10

# Import from staging with custom limit
wp ampers import-prx --environment=staging --network-id=7 --limit=5

# Import with specific credentials
wp ampers import-prx --client-id=your_id --client-secret=your_secret --limit=20
```

### Programmatic Usage

```php
// Initialize authentication
$auth = new Ampers\PRXImport\Auth( 'production', $client_id, $client_secret );

// Test connection
$result = $auth->test_connection();
if ( is_wp_error( $result ) ) {
    echo 'Authentication failed: ' . $result->get_error_message();
} else {
    echo 'Authentication successful!';
}

// Initialize import
$import = new Ampers\PRXImport\Import( $auth );

// Get stories from a network
$stories = $import->get_network_stories( 7, 1, 10 );

// Import stories
$results = $import->import_network_stories( 7, 10 );
```

## API Reference

### Auth Class

#### Constructor
```php
new Auth( string $environment, string $client_id, string $client_secret )
```

#### Methods
- `get_access_token()`: Get OAuth2 access token
- `test_connection()`: Test API connection
- `get_authorization()`: Get authorization information
- `make_request( string $endpoint, array $args )`: Make authenticated API request

### Import Class

#### Constructor
```php
new Import( Auth $auth )
```

#### Methods
- `get_network_stories( int $network_id, int $page, int $per_page )`: Get stories from network
- `get_story( int $story_id )`: Get specific story
- `get_network_series( int $network_id, int $page, int $per_page )`: Get series from network
- `get_series_stories( int $series_id, int $page, int $per_page )`: Get stories from series
- `import_network_stories( int $network_id, int $limit )`: Import stories from network
- `import_story( array $story_data )`: Import single story

## Testing

### Standalone Test Script

You can test the authentication system without WordPress:

1. Update the credentials in `test-auth.php`
2. Run: `php test-auth.php`

### WordPress Integration Test

Test within WordPress:

```bash
# Test authentication
wp ampers test-auth

# Test import (will show "not implemented" for now)
wp ampers import-prx --limit=1
```

## Development

### Project Structure

```
ampers-prx-import/
├── ampers-prx-import.php    # Main plugin file
├── classes/
│   ├── class-auth.php       # Authentication handler
│   ├── class-import.php     # Import functionality
│   └── class-cli.php        # WP-CLI commands
├── test-auth.php            # Standalone test script
└── README.md                # This file
```

### Adding New Features

1. **New API Endpoints**: Add methods to the `Auth` class
2. **New Import Types**: Extend the `Import` class
3. **New CLI Commands**: Add methods to the `CLI` class
4. **Admin Interface**: Extend the main plugin class

### Code Standards

- Use tabs for indentation (non-Laravel project)
- Follow WordPress coding standards
- Include proper PHPDoc comments
- Use proper error handling with `WP_Error`

## Troubleshooting

### Common Issues

1. **Authentication Failed**: Check your client ID and secret
2. **Environment Issues**: Ensure you're using the correct environment (staging vs production)
3. **API Limits**: PRX API has rate limits; implement proper delays if needed
4. **Missing Dependencies**: Ensure ACF plugin is installed for custom fields

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

## License

GPL-2.0-or-later

## Support

For issues related to:
- **Plugin functionality**: Check this README and WordPress error logs
- **PRX API access**: Contact PRX support
- **WordPress integration**: Standard WordPress support channels

## Changelog

### 2.0.0
- Complete rewrite with class-based architecture
- OAuth2 authentication support
- WP-CLI integration
- Admin settings interface
- Support for staging and production environments
