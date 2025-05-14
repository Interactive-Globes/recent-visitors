# Interactive Globes Addon - Recent Visitors

A WordPress plugin that displays recent visitor locations on an Interactive Globe. It tracks and visualizes your website visitors' locations in real-time, showing where your audience is coming from around the world. This is an Addon to Interactive Globes Pro. It will not work as a standalone.


## Features

- Real-time visitor location tracking
- Interactive globe visualization
- Configurable time frames for visitor display
- Customizable visitor message
- IP geolocation with proxy support
- Location data caching system

## Requirements

- WordPress 5.0 or higher
- Interactive Globes Pro plugin 
https://wpinteractiveglobes.com/get-pro/

## Installation

1. Upload the plugin files in your WordPress dashboard Plugins > Add New > Upload.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure the plugin settings in your Interactive Globe settings, in the markers section.


## Configuration

The plugin can be configured through the Interactive Globe settings:

- Enable/disable visitor tracking
- Set custom time frames (in minutes)
- Customize the visitor message using placeholders:
  - `{count}` - Number of recent visitors
  - `{country_count}` - Number of different countries

## Frequently Asked Questions

### How does the plugin track visitors?
The plugin uses IP geolocation to determine visitor locations. It supports proxy detection and handles various IP header formats to ensure accurate location tracking.

### How long are visitor locations stored?
Visitor locations are stored for 24 hours by default. The display time frame can be configured in the globe settings.

### Can I customize the visitor message?
Yes, you can customize the message that displays the number of recent visitors and countries. Use {count} for visitor count and {country_count} for the number of different countries.

## Changelog

### 1.1.1
- Initial release with visitor tracking and globe visualization features
- Support for custom time frames
- Configurable visitor message
- IP geolocation with proxy support
- Caching system for location data

## Credits

Developed by the Interactive Globes Team
