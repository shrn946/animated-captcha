# Animated CAPTCHA for Contact Form 7

A clean, modern, and animated CAPTCHA addon for Contact Form 7.

## Features
- Animated canvas-based CAPTCHA to deter bots.
- Server-side validation for security.
- AJAX-based refresh (manual or on validation failure).
- Customizable CAPTCHA length.
- Responsive and stylish design.
- Supports multiple forms on the same page.

## Installation
1. Upload the `captcha` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage
In your Contact Form 7 form editor, use the following tag:

`[animated_captcha your-captcha-name]`

### Options
- **size**: Change the length of the CAPTCHA word (default is 7).
  Example: `[animated_captcha your-captcha-name size:5]`
- **class**: Add custom CSS classes.
  Example: `[animated_captcha your-captcha-name class:my-custom-class]`
- **id**: Add a custom ID.
  Example: `[animated_captcha your-captcha-name id:my-captcha-id]`

### Example
`[animated_captcha captcha-123 size:6]`

## Requirements
- WordPress 5.0+
- Contact Form 7 5.0+
- jQuery (included with WordPress)
