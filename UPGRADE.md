# Upgrading to v2.0

## Overview

Version 2.0 introduces a major refactor of the migration system. All 11 migrations have been consolidated into a single, clean migration for fresh installations, while maintaining full backward compatibility for existing users through an intelligent upgrade migration.

## For Fresh Installations

If you're installing iyzipay-laravel for the first time, no special action is needed! Just follow the standard installation process:

```bash
composer require istanbay/iyzipay-laravel
php artisan migrate
```

The package will automatically create all required tables with the latest v2 schema.

---

## For v1.x Users (Upgrading)

If you're upgrading from v1.x, follow these steps carefully:

### Critical: Backup First

**ALWAYS backup your database before upgrading!** This cannot be stressed enough.

```bash
# Example: MySQL backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 1: Update Composer Package

Update to v2.0 using Composer:

```bash
composer require istanbay/iyzipay-laravel:^2.0
```

### Step 2: Publish Upgrade Migration

Run the upgrade command to publish the intelligent upgrade migration:

```bash
php artisan iyzipay:publish-upgrade
```

### Step 3: Run Migrations

Execute the upgrade migration:

```bash
php artisan migrate
```

The upgrade migration will intelligently detect your current schema and apply only the necessary changes.
