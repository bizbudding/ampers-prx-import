# AMPERS PRX Import Plugin

A WordPress plugin for importing content from the PRX CMS API into WordPress. This plugin provides WP-CLI commands for managing PRX content imports and includes an automated cron job for regular content updates.

## Features

- **OAuth2 Authentication**: Secure authentication with PRX using OAuth2 client credentials flow
- **Automated Cron Jobs**: Scheduled imports every 3 hours to check for new stories
- **WP-CLI Integration**: Command-line tools for bulk imports, testing, and debugging
- **Advanced Custom Fields Integration**: Stores PRX metadata in custom fields
- **Media Import**: Automatically downloads and attaches featured images and audio files
- **Comprehensive Logging**: Detailed logging with WP-CLI console output support
- **Dry Run Mode**: Test imports without making changes

## Requirements

- WordPress 6.5 or higher
- PHP 8.2 or higher
- WP-CLI (for command-line functionality)
- Advanced Custom Fields (ACF) plugin
- WP-Crontrol plugin (for cron job management)

## Installation

1. Upload the plugin files to `/wp-content/plugins/ampers-prx-import/`
2. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your PRX API credentials (see Configuration section)

## Configuration

### PRX API Credentials

The plugin requires OAuth2 client credentials from PRX. These should be configured in your WordPress environment:

```php
// Add to wp-config.php or use environment variables
define( 'PRX_CLIENT_ID', 'your_client_id' );
define( 'PRX_CLIENT_SECRET', 'your_client_secret' );
```

### Account Configuration

The plugin is configured for the Ampers account (ID: 197472) by default. To change this, modify the `account_id()` function in `ampers-prx-import.php`.

## Usage

### Automated Cron Jobs

The plugin automatically sets up a cron job that runs every 3 hours to check for new stories. This can be managed through the WP-Crontrol plugin.

### WP-CLI Commands

#### Testing Authentication

```bash
# Test PRX API connection
wp ampers test-auth
```

#### Testing Story Retrieval

```bash
# Test fetching stories from PRX
wp ampers test-stories --limit=5
```

#### Listing PRX Accounts

```bash
# List available PRX accounts
wp ampers list-accounts
```

#### Importing Content

```bash
# Import stories from PRX (default: 10 stories, page 1)
wp ampers import-prx

# Import with custom parameters
wp ampers import-prx --account-id=197472 --per-page=25 --page=1

# Perform a dry run (no changes made)
wp ampers import-prx --per-page=10 --page=1 --dry-run

# Import from specific page
wp ampers import-prx --per-page=10 --page=2
```

#### Utility Commands

```bash
# Check meta fields on posts
wp ampers check-meta

# Delete removed ACF fields (cleanup)
wp ampers delete-removed-acf-fields
```

## How It Works

### Import Process

1. **Authentication**: Uses OAuth2 to authenticate with PRX API
2. **Story Retrieval**: Fetches stories from the specified PRX account
3. **Duplicate Detection**: Checks for existing posts using PRX ID
4. **Content Creation**: Creates or updates WordPress posts with:
   - Post title, content, and excerpt
   - Publication dates
   - Tags (including 'prx' tag)
   - Categories (based on series)
   - Station taxonomy
   - Featured images
   - Audio files
   - ACF custom fields

### Cron Job

The automated cron job:
- Runs every 3 hours
- Checks for new stories from the configured account
- Imports up to 50 stories per run
- Logs all activities

## API Reference

### Auth Class

Handles OAuth2 authentication with PRX API.

```php
$auth = new Ampers\PRXImport\Auth();
$result = $auth->test_connection();
```

### Import Class

Manages content import from PRX to WordPress.

```php
$import = new Ampers\PRXImport\Import( $auth, [ 'dry_run' => false ] );
$result = $import->import_story( $story_data );
```

### Cron Class

Manages automated import scheduling.

```php
$cron = new Ampers\PRXImport\Cron( [
    'account_id' => 197472,
    'interval_hours' => 3,
] );
```

### Logger Class

Provides consistent logging across the plugin.

```php
$logger = Ampers\PRXImport\Logger::get_instance();
$logger->info( 'Message' );
$logger->success( 'Success message' );
$logger->error( 'Error message' );
```

## Troubleshooting

### Common Issues

1. **Authentication Failed**: Check your PRX client credentials
2. **Missing ACF Fields**: Import the ACF configuration file
3. **Cron Job Not Running**: Check WP-Crontrol plugin and server cron setup
4. **Import Errors**: Use dry-run mode to test without making changes

### Debug Mode

Enable WordPress debug mode for detailed logging:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### Logging

The plugin provides comprehensive logging:
- **WP-CLI**: Direct console output with color coding
- **WordPress**: Error log integration
- **Ray**: Debug output when available

## Development

### Code Standards

- Use tabs for indentation (non-Laravel project)
- Follow WordPress coding standards
- Include proper PHPDoc comments
- Use proper error handling with `WP_Error`

### Adding Features

1. **New API Endpoints**: Extend the `Auth` class
2. **New Import Types**: Extend the `Import` class
3. **New CLI Commands**: Add methods to the `CLI` class
4. **Cron Modifications**: Update the `Cron` class

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
- Automated cron job integration
- WP-CLI command suite
- ACF field integration
- Media import functionality
- Comprehensive logging system
- Dry-run mode for testing
