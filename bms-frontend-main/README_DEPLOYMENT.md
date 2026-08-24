# Deployment Configuration for SPA Routing

This application is a Single Page Application (SPA) built with React and Vite. When deployed, you need to configure your server to redirect all routes to `index.html` to prevent 404 errors when refreshing pages.

## The Problem

When you refresh a page on a route like `/employee-management/dashboard`, the server tries to find a file at that path. Since it doesn't exist, you get a 404 error.

## Solutions by Server Type

### 1. Apache Server

If you're using Apache, copy the `.htaccess` file from the `public` folder to your `dist` folder after building:

```bash
npm run build
cp public/.htaccess dist/.htaccess
```

The `.htaccess` file is already configured to handle SPA routing.

### 2. Nginx Server

If you're using Nginx, use the `nginx.conf.example` as a reference. Update the paths and domain name, then copy it to your Nginx configuration:

```bash
# Copy the example config
cp nginx.conf.example /etc/nginx/sites-available/your-site

# Create symlink
ln -s /etc/nginx/sites-available/your-site /etc/nginx/sites-enabled/

# Test and reload
nginx -t
sudo systemctl reload nginx
```

### 3. Netlify

If deploying to Netlify, the `_redirects` file in the `public` folder will be automatically used. No additional configuration needed.

### 4. Vercel

If deploying to Vercel, the `vercel.json` file is already configured. No additional configuration needed.

### 5. Other Static Hosting Services

For other services (GitHub Pages, AWS S3, etc.), you'll need to configure URL rewriting according to their documentation. The general rule is: **redirect all routes to `/index.html`**.

## Testing Locally

To test the production build locally:

```bash
npm run build
npm run preview
```

The preview server is configured to handle SPA routing correctly.

## Development

The development server (`npm run dev`) already handles SPA routing correctly, so you won't see this issue during development.

