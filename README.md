# Maui Garden Tour Map

An interactive web application allowing users to place pins on a map of Maui and submit location information with email confirmation.

## Features

- **Interactive Google Map** centered on Maui with standard controls (zoom, map type, fullscreen)
- **Pin Placement** - Click anywhere to place a draggable pin
- **Submission Form** - Name, address, email (required), picture upload, description
- **Email Confirmation** - Pins only appear after email verification
- **Admin Dashboard** - Password-protected management interface with:
  - Sortable table of all submissions
  - Pagination
  - Record detail view with map preview
  - Delete functionality
  - CSV export

## Requirements

- PHP 8.1+ (enforced by `composer.json`)
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Google Maps API key
- SMTP email credentials

## Installation

### 1. Clone/Download the project

```bash
cd /var/www/html
git clone <repository-url> gardentour
cd gardentour
```

### 2. Install dependencies

```bash
composer install
```

### 3. Create the database

```bash
mysql -u root -p < schema.sql
```

Or run the SQL manually from `schema.sql`.

### 4. Configure environment

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` with your settings:

```env
# Database
DB_HOST=localhost
DB_NAME=garden_tour
DB_USER=your_db_user
DB_PASS=your_db_password

# Google Maps API Key
# Get one at: https://console.cloud.google.com/google/maps-apis
GOOGLE_MAPS_API_KEY=your_api_key

# SMTP Email
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=Maui Garden Tour

# Admin credentials
ADMIN_USER=admin
ADMIN_PASS_HASH=<generated_hash>
ADMIN_EMAIL=admin@yourdomain.com

# Application
SITE_URL=https://yourdomain.com
TOKEN_EXPIRY_HOURS=24
MAX_IMAGE_SIZE_MB=5
```

### 5. Generate admin password hash

```bash
php -r "echo password_hash('your_secure_password', PASSWORD_DEFAULT) . PHP_EOL;"
```

Copy the output to `ADMIN_PASS_HASH` in your `.env` file.

### 6. Set permissions

```bash
chmod 755 uploads
chown www-data:www-data uploads
```

### 7. Configure web server

For Apache, ensure `.htaccess` is enabled. The included `.htaccess` file handles security.

For Nginx, add appropriate rules to protect sensitive files.

## Usage

### Public Users

1. Visit the main page
2. Click on the map to place a pin
3. Fill in the form (email required)
4. Submit and check email for confirmation link
5. Click the link to make the pin visible

### Administrators

1. Visit `/admin/`
2. Log in with your credentials
3. View, sort, and manage submissions
4. Export data as CSV
5. Delete unwanted submissions

## File Structure

```
gardentour/
├── index.php           # Main map page
├── confirm.php         # Email confirmation handler
├── envloader.php       # Environment variable loader
├── schema.sql          # Database schema
├── composer.json       # PHP dependencies
├── phpunit.xml         # PHPUnit configuration
├── .env.example        # Configuration template
├── .htaccess           # Apache security rules
├── admin/
│   └── index.php       # Admin dashboard
├── api/
│   ├── submit.php      # Handle new submissions
│   ├── pins.php        # Get confirmed pins
│   ├── update.php      # Update a submission (matching email cookie)
│   ├── delete.php      # Delete a submission (matching email cookie)
│   ├── admin-image.php # Image removal/replacement (admin)
│   └── reconnect.php   # Re-establish email-cookie ownership of existing pins
├── includes/
│   ├── db.php          # Database functions
│   ├── image.php       # Image validation/storage helpers
│   └── mailer.php      # Email functions
├── tests/
│   ├── bootstrap.php   # PHPUnit bootstrap
│   └── SmokeTest.php   # Syntax + structural smoke tests
├── uploads/            # User-uploaded images
├── css/
│   └── style.css       # Styles
└── js/
    └── map.js          # Map interactions
```

## Tests

```bash
composer test        # or: vendor/bin/phpunit
```

`tests/SmokeTest.php` runs `php -l` over every project PHP file and checks the shared escaping helper. It needs no database or network access. PHPUnit configuration lives in `phpunit.xml`, with `tests/bootstrap.php` as the bootstrap.

## Security

- CSRF protection on all forms
- SQL injection prevention via prepared statements
- XSS prevention via output escaping
- File upload validation (type, size)
- Session-based admin authentication
- Sensitive files protected via `.htaccess`

## Future Enhancements

- Selective field visibility (access control)
- Multiple admin users with roles
- Rate limiting
- Image optimization/resizing
- Map clustering for many pins

## License

MIT License
