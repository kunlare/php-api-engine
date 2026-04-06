# Get Started — Pre-Packagist Installation Guide

> **Temporary file**: Remove this after publishing to Packagist.

---

## Table of Contents

- [Local Installation with XAMPP](#local-installation-with-xampp)
  - [Mac](#xampp-on-mac)
  - [Linux](#xampp-on-linux)
  - [Windows](#xampp-on-windows)
- [Local Installation with PHP Built-in Server](#php-built-in-server-no-xampp)
- [Remote Deployment on SiteGround](#remote-deployment-on-siteground)
  - [Manual Upload (FTP/SFTP)](#option-a-manual-upload)
  - [CI/CD with GitHub Actions](#option-b-cicd-with-github-actions-ftp-deploy)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

- **PHP >= 8.1** with extensions `pdo`, `pdo_mysql`, `json`
- **Composer** ([https://getcomposer.org](https://getcomposer.org))
- **MySQL 5.7+** or **MariaDB 10.3+**

Verify your setup:

```bash
php -v          # Must show 8.1+
composer -V     # Must be installed
php -m | grep pdo   # Must show pdo and pdo_mysql
```

---

## Local Installation with XAMPP

### XAMPP on Mac

```bash
# 1. Go to XAMPP's htdocs
cd /Applications/XAMPP/xamppfiles/htdocs

# 2. Create project
mkdir my-api && cd my-api

# 3. Initialize composer
composer init --name="myapp/api" --type="project" --no-interaction

# 4. Create database
/Applications/XAMPP/xamppfiles/bin/mysql -u root -e "CREATE DATABASE crud_api_db"
```

### XAMPP on Linux

```bash
# 1. Go to XAMPP's htdocs
cd /opt/lampp/htdocs

# 2. Create project
mkdir my-api && cd my-api

# 3. Initialize composer
composer init --name="myapp/api" --type="project" --no-interaction

# 4. Create database
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE crud_api_db"
```

### XAMPP on Windows

```cmd
REM 1. Go to XAMPP's htdocs
cd C:\xampp\htdocs

REM 2. Create project
mkdir my-api && cd my-api

REM 3. Initialize composer
composer init --name="myapp/api" --type="project" --no-interaction

REM 4. Create database via phpMyAdmin or:
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE crud_api_db"
```

### Common Steps (All Platforms)

After creating the project directory and database, follow these steps:

#### Step 1: Configure composer.json

Replace your `composer.json` with:

```json
{
    "name": "myapp/api",
    "type": "project",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/lemmartikun/php-crud-api.git"
        }
    ],
    "require": {
        "kunlare/php-crud-api": "dev-main"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

**Alternative — install from a local folder** (if you cloned the repo on your machine):

```json
{
    "name": "myapp/api",
    "type": "project",
    "repositories": [
        {
            "type": "path",
            "url": "/full/path/to/your/php-crud-api"
        }
    ],
    "require": {
        "kunlare/php-crud-api": "*"
    }
}
```

#### Step 2: Install dependencies

```bash
composer install
```

#### Step 3: Create the .env file

```bash
cp vendor/kunlare/php-crud-api/examples/.env.example .env
```

Edit `.env` with your database credentials:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=crud_api_db
DB_USER=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

API_VERSION=v1
API_BASE_PATH=/api
API_DEBUG=true

AUTH_METHOD=jwt
JWT_SECRET=change-me-to-a-random-string-at-least-32-characters-long
JWT_EXPIRATION=3600

AUTO_SETUP=true
FIRST_USER_IS_ADMIN=true
ENABLE_CORS=true
ALLOWED_ORIGINS=*
MIN_PASSWORD_LENGTH=8
REQUIRE_SPECIAL_CHARS=true
```

> **Tip**: Generate a random JWT secret: `php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"`

#### Step 4: Create index.php

Create `index.php` in your project root:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Kunlare\PhpCrudApi\Api\Router;
use Kunlare\PhpCrudApi\Bootstrap;
use Kunlare\PhpCrudApi\Config\Config;
use Kunlare\PhpCrudApi\Database\Connection;

$config = new Config(__DIR__);
$db = Connection::getInstance($config);

if ($config->getBool('AUTO_SETUP', true)) {
    (new Bootstrap($db, $config))->run();
}

(new Router($db, $config))->handleRequest();
```

#### Step 5: Create .htaccess (for Apache/XAMPP)

Create `.htaccess` in your project root:

```apache
RewriteEngine On

# Redirect everything to index.php (except existing files)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

#### Step 6: Create admin user

```bash
php vendor/bin/setup.php
```

Follow the prompts to create your admin account.

#### Step 7: Access

If running under XAMPP Apache:

- **Admin Panel**: http://localhost/my-api/admin
- **API**: http://localhost/my-api/api/v1/auth/login

> **Important**: When running in a subdirectory (e.g. `/my-api/`), update `.env`:
> ```env
> API_BASE_PATH=/my-api/api
> ```
> And update the admin panel path check — or use a VirtualHost to serve from root.

---

## PHP Built-in Server (No XAMPP)

If you just want to test quickly without XAMPP:

```bash
# From your project directory (where index.php is)
mkdir my-api && cd my-api

# ... follow Steps 1-6 above ...

# Start the server
php -S localhost:8000 index.php
```

Access:

- **Admin Panel**: http://localhost:8000/admin
- **API**: http://localhost:8000/api/v1/auth/login

No `.htaccess` needed — the built-in server routes everything through `index.php` automatically.

---

## Remote Deployment on SiteGround

### Option A: Manual Upload

#### 1. Build locally

```bash
cd my-api
composer install --no-dev --optimize-autoloader
```

#### 2. Prepare .env for production

Create or edit `.env` with SiteGround's MySQL credentials
(find them in **Site Tools > MySQL**):

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_siteground_db
DB_USER=your_siteground_user
DB_PASSWORD=your_siteground_password
DB_CHARSET=utf8mb4

API_VERSION=v1
API_BASE_PATH=/api
API_DEBUG=false

AUTH_METHOD=jwt
JWT_SECRET=generate-a-long-random-secret-unique-to-production
JWT_EXPIRATION=3600
JWT_REFRESH_EXPIRATION=604800

AUTO_SETUP=true
FIRST_USER_IS_ADMIN=true
ENABLE_CORS=true
ALLOWED_ORIGINS=https://yourdomain.com
MIN_PASSWORD_LENGTH=8
REQUIRE_SPECIAL_CHARS=true
```

#### 3. Create the database

In SiteGround **Site Tools > MySQL > Databases**:
- Create a new database
- Create a new user
- Grant the user access to the database

#### 4. Upload files

Upload via SiteGround's **File Manager** or **SFTP** to `public_html/`:

```
public_html/
├── vendor/          ← entire vendor folder
├── .env             ← production config
├── .htaccess        ← rewrite rules
└── index.php        ← entry point
```

**Do NOT upload**: `.git/`, `tests/`, `examples/`, `phpunit.xml`, `GETSTARTED.md`

#### 5. Create admin user via SSH

Enable SSH in SiteGround **Site Tools > Developers > SSH Keys**, then:

```bash
ssh your-user@your-server
cd ~/public_html
php vendor/bin/setup.php
```

Or bootstrap without SSH — register the first admin via API:

```bash
curl -X POST https://yourdomain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","email":"you@email.com","password":"YourP@ssw0rd!"}'
```

#### 6. Access

- **Admin Panel**: https://yourdomain.com/admin
- **API**: https://yourdomain.com/api/v1/...

---

### Option B: CI/CD with GitHub Actions (FTP Deploy)

Automate deployments on every push to `main`.

#### 1. Create the workflow file

In your project repo, create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to SiteGround

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction

      - name: Create .env for production
        run: |
          cat > .env << 'ENVEOF'
          DB_HOST=${{ secrets.DB_HOST }}
          DB_PORT=3306
          DB_NAME=${{ secrets.DB_NAME }}
          DB_USER=${{ secrets.DB_USER }}
          DB_PASSWORD=${{ secrets.DB_PASSWORD }}
          DB_CHARSET=utf8mb4
          API_VERSION=v1
          API_BASE_PATH=/api
          API_DEBUG=false
          AUTH_METHOD=jwt
          JWT_SECRET=${{ secrets.JWT_SECRET }}
          JWT_ALGORITHM=HS256
          JWT_EXPIRATION=3600
          JWT_REFRESH_EXPIRATION=604800
          AUTO_SETUP=true
          FIRST_USER_IS_ADMIN=true
          ENABLE_CORS=true
          ALLOWED_ORIGINS=${{ secrets.ALLOWED_ORIGINS }}
          ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
          ALLOWED_HEADERS=Content-Type,Authorization,X-API-Key
          MIN_PASSWORD_LENGTH=8
          REQUIRE_SPECIAL_CHARS=true
          ENVEOF

      - name: Create .htaccess
        run: |
          cat > .htaccess << 'HTEOF'
          RewriteEngine On
          RewriteCond %{REQUEST_FILENAME} !-f
          RewriteCond %{REQUEST_FILENAME} !-d
          RewriteRule ^ index.php [QSA,L]
          HTEOF

      - name: Create index.php
        run: |
          cat > index.php << 'PHPEOF'
          <?php
          require_once __DIR__ . '/vendor/autoload.php';
          use Kunlare\PhpCrudApi\Api\Router;
          use Kunlare\PhpCrudApi\Bootstrap;
          use Kunlare\PhpCrudApi\Config\Config;
          use Kunlare\PhpCrudApi\Database\Connection;
          $config = new Config(__DIR__);
          $db = Connection::getInstance($config);
          if ($config->getBool('AUTO_SETUP', true)) {
              (new Bootstrap($db, $config))->run();
          }
          (new Router($db, $config))->handleRequest();
          PHPEOF

      - name: Deploy via FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_HOST }}
          username: ${{ secrets.FTP_USER }}
          password: ${{ secrets.FTP_PASSWORD }}
          server-dir: /public_html/
          exclude: |
            **/.git*
            **/.git*/**
            **/tests/**
            **/examples/**
            **/bin/**
            phpunit.xml
            GETSTARTED.md
            CHANGELOG.md
```

#### 2. Configure GitHub Secrets

Go to your repo **Settings > Secrets and variables > Actions** and add:

| Secret | Example Value | Where to Find |
|--------|---------------|---------------|
| `FTP_HOST` | `ftp.yourdomain.com` | SiteGround Site Tools > FTP Accounts |
| `FTP_USER` | `user@yourdomain.com` | SiteGround Site Tools > FTP Accounts |
| `FTP_PASSWORD` | `your_ftp_password` | SiteGround Site Tools > FTP Accounts |
| `DB_HOST` | `localhost` | SiteGround Site Tools > MySQL |
| `DB_NAME` | `dbname` | SiteGround Site Tools > MySQL > Databases |
| `DB_USER` | `dbuser` | SiteGround Site Tools > MySQL > Users |
| `DB_PASSWORD` | `dbpassword` | Set when creating the user |
| `JWT_SECRET` | `a1b2c3d4...` (32+ chars) | Generate: `php -r "echo bin2hex(random_bytes(32));"` |
| `ALLOWED_ORIGINS` | `https://yourdomain.com` | Your domain(s), comma-separated |

#### 3. Push and deploy

```bash
git add . && git commit -m "Initial deploy" && git push origin main
```

The workflow runs automatically. Check **Actions** tab in GitHub for progress.

#### 4. First-time admin setup

After the first deploy, create the admin via SSH or API:

```bash
# Via API (no SSH needed)
curl -X POST https://yourdomain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","email":"you@email.com","password":"YourP@ssw0rd!"}'
```

Then open https://yourdomain.com/admin and log in.

---

## Troubleshooting

### "Class not found" errors

```bash
composer dump-autoload
```

### "500 Internal Server Error" on Apache

Make sure `mod_rewrite` is enabled:

```bash
# Linux/Mac XAMPP
sudo a2enmod rewrite

# Or check httpd.conf — find this line and ensure it's uncommented:
# LoadModule rewrite_module modules/mod_rewrite.so
```

Also check that `AllowOverride All` is set for your directory in Apache config.

### "Connection refused" or "Access denied" on database

- Verify `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD` in `.env`
- Make sure MySQL is running
- Make sure the database exists: `mysql -u root -e "SHOW DATABASES"`

### "404 Not Found" for API routes

- Check `.htaccess` exists and `mod_rewrite` is active
- If using PHP built-in server, make sure you pass `index.php`: `php -S localhost:8000 index.php`

### Admin panel shows blank page

- Open browser DevTools (F12) > Console for JavaScript errors
- Check that `API_BASE_PATH` in `.env` matches your actual URL structure
- If running in a subdirectory, update `API_BASE_PATH` accordingly

### SiteGround-specific issues

- PHP version: Set to 8.1+ in **Site Tools > Developers > PHP Manager**
- File permissions: `index.php` and `.htaccess` should be `644`, directories `755`
- `.env` security: SiteGround blocks `.env` access from web by default (good), but if not, add to `.htaccess`:
  ```apache
  <Files .env>
      Order allow,deny
      Deny from all
  </Files>
  ```

### Debug mode

Set `API_DEBUG=true` in `.env` to see detailed error messages. **Always set to `false` in production.**

---

## Quick Reference

| Action | Command / URL |
|--------|---------------|
| Install deps | `composer install` |
| Install prod deps | `composer install --no-dev --optimize-autoloader` |
| Run setup | `php vendor/bin/setup.php` |
| Start dev server | `php -S localhost:8000 index.php` |
| Run tests | `composer test` |
| Admin panel | `http://localhost:8000/admin` |
| API base | `http://localhost:8000/api/v1/` |
| Login | `POST /api/v1/auth/login` |
| Register first admin | `POST /api/v1/auth/register` |
