# iyzipay (by iyzico) Integration for Laravel

This package is under development. All contributions are welcomed!

You can sign up for an iyzico account at [iyzico.com](https://www.iyzico.com).

## Documentation

See the [wiki](https://github.com/iyzico/iyzipay-laravel/wiki) for full documentation, examples, operational details and other information. 

# Installation

You can check all required steps for installing the api from [installation wiki page](https://github.com/iyzico/iyzipay-laravel/wiki/Installation)

## Upgrading from v1.x

If you're upgrading from version 1.x to 2.0, please follow these steps:

1. **Backup your database** (CRITICAL!)
2. Update the package: `composer require istanbay/iyzipay-laravel:^2.0`
3. Publish the upgrade migration: `php artisan iyzipay:publish-upgrade`
4. Run migrations: `php artisan migrate`
5. Verify your data and test functionality

For detailed upgrade instructions, troubleshooting, and rollback procedures, see **[UPGRADE.md](UPGRADE.md)**.

## Roadmap
* Bill Storage ✓
* Card Storage ✓
    * Add Credit Card ✓
    * Remove Credit Card ✓
* Single Charges ✓
    * Collect Charges ✓
    * Void Charges ✓
    * Refund Charges
* Subscriptions
    * Creating Plans ✓
    * Creating Subscriptions ✓ 
    * Canceling Subscriptions ✓
* Documentation

## Author

Originally developed by Mehmet Aydin Bahadir (mehmet.aydin.bahadir@gmail.com). 
Now officially maintained by iyzico.
