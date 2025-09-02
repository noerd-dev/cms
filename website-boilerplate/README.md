# CMS Website Boilerplate

This module provides a complete website boilerplate for the CMS system with its own dedicated CSS and JavaScript assets.

## Assets

### CSS (`resources/css/website.css`)
- **Tailwind CSS 4**: Uses the new `@import "tailwindcss"` syntax
- **Component Scoping**: Only includes styles from website boilerplate templates
- **Custom Components**: Includes website-specific component classes
- **Build Output**: Compiled to `public/build/assets/website-*.css`

### JavaScript (`resources/js/website.js`)
- **Mobile Navigation**: Enhanced mobile menu with ARIA support
- **Smooth Scrolling**: Automatic smooth scroll for anchor links
- **Image Lazy Loading**: Fallback for browsers without native support
- **reCAPTCHA Integration**: Callback handling for contact forms
- **Build Output**: Compiled to `public/build/assets/website-*.js`

## Build Configuration

The assets are automatically built when running:
```bash
npm run build
npm run dev
```

Both files are configured in `vite.config.js`:
```javascript
input: [
    'app-modules/cms/website-boilerplate/resources/css/website.css',
    'app-modules/cms/website-boilerplate/resources/js/website.js',
]
```

## Templates

The website boilerplate uses its own layout template:
- **Layout**: `resources/views/components/layouts/weblayout.blade.php`
- **Page Template**: `resources/views/page.blade.php`
- **Element Components**: `resources/views/livewire/elements/*.blade.php`

## CSS Classes

The website.css provides these utility classes:

### Navigation
- `.nav-link` - Base navigation link styles
- `.nav-link--active` - Active navigation state
- `.nav-link--inactive` - Inactive navigation state

### Buttons
- `.btn-primary` - Primary button styling
- `.btn-secondary` - Secondary button styling

### Forms
- `.form-input` - Input field styling
- `.form-label` - Label styling
- `.form-error` - Error message styling

### Layout
- `.content-section` - Content section padding
- `.content-container` - Container with max-width

## Development

When developing the website boilerplate:

1. **CSS Changes**: Edit `resources/css/website.css`
2. **JS Changes**: Edit `resources/js/website.js`
3. **Build**: Run `npm run build` or `npm run dev`
4. **Templates**: The layout automatically loads the built assets

## Independence

This module is completely independent from the main application's CSS/JS:
- ✅ No dependency on `resources/css/app.css`
- ✅ No dependency on `resources/js/app.js`
- ✅ Own Tailwind configuration and scoping
- ✅ Separate build output