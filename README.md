# NestDecor — E-Commerce Project

## Local Setup

### Requirements
- XAMPP (PHP 8.1+ and MySQL/MariaDB with `mod_rewrite` and `AllowOverride All` enabled for `.htaccess`)
- Composer (for PHPUnit, used only for running tests)

### 1. Place the project
Copy this whole folder into `htdocs/nestdecor` inside your XAMPP installation.

### 2. Create the database
Open phpMyAdmin (or the MySQL CLI) and run, in this order:
```
database/schema.sql
database/seed.sql
```
This creates the `nestdecor` database with all tables, plus demo categories, products, variants, SKUs, and two test accounts:
- **Admin:** admin@nestdecor.com / admin123
- **Customer:** sara@nestdecor.com / customer123

### 3. Environment variables
The app reads database credentials from environment variables, with safe local defaults if none are set (see `config/db.php`):

| Variable | Default        | Purpose                      |
|----------|----------------|-------------------------------|
| DB_HOST  | 127.0.0.1      | MySQL host                    |
| DB_NAME  | nestdecor      | Database name                 |
| DB_USER  | root           | MySQL username                |
| DB_PASS  | (empty)        | MySQL password                |

A fresh XAMPP install needs no configuration at all — the defaults match XAMPP's default MySQL account. **Never commit real production credentials or a `.env` file with secrets to the repository.**

### 4. Run the app
Visit `http://127.0.0.1/nestdecor/admin/login.php` and log in with the admin account above.

### 5. Run the automated tests
```bash
composer install
mysql -u root -e "CREATE DATABASE IF NOT EXISTS nestdecor_test;"
mysql -u root < database/schema_test.sql
vendor/bin/phpunit
```
Tests run against a **separate** `nestdecor_test` database and roll back every change after each test, so they never touch your real seed data.