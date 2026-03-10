# COROPOTI Calendar Management System

A comprehensive calendar management system built with Symfony for TESDA COROPOTI (Central Office Regional Office Provincial Office Training Institute) operations.

## Features

### 🗓️ Calendar Management
- **Public Calendar View** - Clean, responsive calendar interface
- **Event Spanning** - Multi-day events display across calendar days
- **Conflict Resolution** - Automatic detection and resolution of scheduling conflicts
- **Event Categories** - Color-coded events by office/cluster
- **Real-time Updates** - Dynamic event loading and filtering

### 👥 User Management
- **Role-based Access Control** - Admin, OSEC, EO, Division, Province roles
- **Profile Management** - User profiles with office assignments
- **Authentication System** - Secure login and registration

### 🏢 Office & Cluster Management
- **Office Hierarchy** - Clusters, Offices, and Divisions structure
- **Color Coding** - Visual organization by office colors
- **Directory Management** - Contact information and office details

### 📋 Event Features
- **Event Creation** - Rich event creation with attachments
- **Meeting Types** - Support for different meeting formats
- **Zoom Integration** - Meeting links and virtual event support
- **File Attachments** - Document uploads for events
- **Event Tags** - Categorization and filtering

### 🔧 Administrative Tools
- **Dashboard** - Comprehensive admin dashboard
- **Audit Logging** - Track system changes and user actions
- **Form Builder** - Dynamic form creation system
- **Security Monitoring** - System security and access control

## Technology Stack

- **Backend**: Symfony 7.x (PHP)
- **Database**: MySQL/MariaDB
- **Frontend**: Twig templates with Tailwind CSS
- **JavaScript**: Vanilla JS with Stimulus
- **Authentication**: Symfony Security Component
- **File Handling**: Symfony File Upload Component

## Installation

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL/MariaDB
- Node.js and npm (for asset compilation)

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/jdflores22/calendar.git
   cd calendar
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**
   ```bash
   npm install
   ```

4. **Configure environment**
   ```bash
   cp .env .env.local
   ```
   Edit `.env.local` with your database credentials:
   ```
   DATABASE_URL="mysql://username:password@127.0.0.1:3306/calendar_db"
   ```

5. **Create database and run migrations**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. **Load initial data (optional)**
   ```bash
   php bin/console app:seed-initial-data
   ```

7. **Build assets**
   ```bash
   npm run build
   ```

8. **Start the development server**
   ```bash
   symfony server:start
   ```
   Or use PHP built-in server:
   ```bash
   php -S localhost:8000 -t public/
   ```

## Usage

### Accessing the System

- **Public Calendar**: Visit the homepage to view the public calendar
- **Staff Login**: Click "Staff Login" to access administrative features
- **Dashboard**: After login, access the dashboard for management tools

### User Roles

- **Admin**: Full system access and management
- **OSEC**: Office-wide event management and oversight
- **EO (Executive Office)**: Office-specific event management
- **Division**: Division-level event management
- **Province**: Basic event creation and management

### Creating Events

1. Navigate to the calendar view
2. Click on a date or use "Create Event" button
3. Fill in event details:
   - Title and description
   - Date and time
   - Location (physical or virtual)
   - Meeting type and Zoom links
   - File attachments
   - Tags and categories

### Managing Conflicts

The system automatically detects scheduling conflicts:
- **Warning Display**: Shows conflicting events
- **Override Options**: Authorized users can proceed despite conflicts
- **Resolution Suggestions**: Alternative time slots

### Office Management

Administrators can:
- Create and manage office clusters
- Assign office colors for visual organization
- Manage divisions within offices
- Set up office hierarchies

## Configuration

### Environment Variables

Key environment variables in `.env.local`:

```env
# Database
DATABASE_URL="mysql://user:pass@host:port/dbname"

# App Environment
APP_ENV=prod
APP_SECRET=your-secret-key

# Mailer (optional)
MAILER_DSN=smtp://localhost

# File Upload Path
UPLOAD_PATH=public/uploads
```

### Security Configuration

The system includes:
- CSRF protection
- Rate limiting
- Input validation
- SQL injection prevention
- XSS protection

## API Endpoints

### Event Management
- `GET /api/events` - List events
- `POST /api/events` - Create event
- `PUT /api/events/{id}` - Update event
- `DELETE /api/events/{id}` - Delete event

### Conflict Detection
- `POST /api/events/check-conflicts` - Check for scheduling conflicts

### Office Management
- `GET /api/offices` - List offices
- `GET /api/offices/{id}/events` - Get office events

## Development

### Running Tests
```bash
php bin/phpunit
```

### Code Style
```bash
php-cs-fixer fix
```

### Database Migrations
```bash
# Create new migration
php bin/console make:migration

# Run migrations
php bin/console doctrine:migrations:migrate
```

### Asset Development
```bash
# Watch for changes
npm run watch

# Build for production
npm run build
```

## Deployment

### Production Setup

1. **Set environment to production**
   ```env
   APP_ENV=prod
   APP_DEBUG=false
   ```

2. **Optimize autoloader**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Clear and warm cache**
   ```bash
   php bin/console cache:clear --env=prod
   php bin/console cache:warmup --env=prod
   ```

4. **Build production assets**
   ```bash
   npm run build
   ```

5. **Set proper file permissions**
   ```bash
   chmod -R 755 var/
   chmod -R 755 public/uploads/
   ```

### Web Server Configuration

#### Apache
```apache
<VirtualHost *:80>
    ServerName calendar.example.com
    DocumentRoot /path/to/calendar/public
    
    <Directory /path/to/calendar/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name calendar.example.com;
    root /path/to/calendar/public;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }
}
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is proprietary software developed for TESDA COROPOTI operations.

## Support

For support and questions:
- Create an issue in the GitHub repository
- Contact the development team
- Check the documentation in the `/docs` folder (if available)

## Changelog

### Version 1.0.0
- Initial release with core calendar functionality
- User authentication and role management
- Office and cluster management
- Event creation and conflict resolution
- Public calendar interface
- Administrative dashboard

---

**TESDA COROPOTI Calendar Management System** - Streamlining schedule management across all TESDA offices and training institutes.