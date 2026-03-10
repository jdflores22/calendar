# TESDA Calendar Task Management System

A production-ready web application built on Symfony 8+ framework that provides a centralized scheduling platform for all TESDA offices.

## Requirements

- PHP 8.3+
- MySQL 8.0+
- Node.js 18+
- Composer
- npm

## Installation

1. Clone the repository
2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install Node.js dependencies:
   ```bash
   npm install
   ```

4. Configure your database in `.env.local`:
   ```
   DATABASE_URL="mysql://username:password@127.0.0.1:3306/tesda_calendar?serverVersion=8.0.32&charset=utf8mb4"
   ```

5. Create the database:
   ```bash
   php bin/console doctrine:database:create
   ```

6. Build assets:
   ```bash
   npm run build
   ```

7. Start the development server:
   ```bash
   php -S localhost:8000 -t public
   ```

## Development

- Build assets for development: `npm run dev`
- Watch for changes: `npm run watch`
- Build for production: `npm run build`

## Features

- **Shared Global Calendar**: All users can view all organizational events
- **Role-Based Access Control**: Permissions based on organizational hierarchy
- **Conflict Resolution**: Hierarchical override capabilities
- **Responsive Design**: Built with Tailwind CSS
- **Security First**: Comprehensive protection against web vulnerabilities

## Architecture

- **Framework**: Symfony 8+
- **Database**: MySQL with Doctrine ORM
- **Frontend**: Twig templates with Tailwind CSS
- **Assets**: Webpack Encore
- **Security**: Argon2id password hashing