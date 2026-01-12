<?php

namespace Iyzico\IyzipayLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PublishUpgradeMigrationCommand extends Command
{
    protected $signature = 'iyzipay:publish-upgrade';

    protected $description = 'Publishes the upgrade migration for upgrading from v1.x to v2.0';

    public function handle(): void
    {
        $this->line('');
        $this->info('Iyzipay Laravel - Upgrade Migration Publisher');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        // Detect installation state
        $state = $this->detectInstallationState();

        switch ($state) {
            case 'fresh':
                $this->info('Fresh installation detected.');
                $this->line('You are installing iyzipay-laravel for the first time.');
                $this->line('No upgrade migration needed - just run: php artisan migrate');
                $this->line('');
                return;

            case 'v2':
                $this->info('v2.0 installation detected.');
                $this->line('You are already on the latest version.');
                $this->line('No upgrade migration needed.');
                $this->line('');
                return;

            case 'v1':
                $this->warn('v1.x installation detected.');
                $this->line('Preparing to upgrade to v2.0...');
                $this->line('');
                break;

            default:
                $this->error('Unable to detect installation state.');
                $this->line('Please contact support or check documentation.');
                return;
        }

        // Publish the upgrade migration
        $this->publishUpgradeMigration();
    }

    /**
     * Detect the current installation state.
     */
    protected function detectInstallationState(): string
    {
        // Check if any iyzipay tables exist
        if (!Schema::hasTable('credit_cards')) {
            return 'fresh';
        }

        // Check for v2 marker columns (the 'type' column was added in v2)
        if (Schema::hasColumn('transactions', 'type')) {
            return 'v2';
        }

        // If tables exist but no v2 markers, it's v1
        return 'v1';
    }

    /**
     * Publish the upgrade migration with a current timestamp.
     */
    protected function publishUpgradeMigration(): void
    {
        try {
            // Generate timestamp
            $timestamp = date('Y_m_d_His');
            $filename = "{$timestamp}_upgrade_v1_to_v2.php";

            // Source and destination paths
            $source = __DIR__ . '/../../database/migrations/upgrade/2030_01_01_000001_upgrade_v1_to_v2.php';
            $destination = database_path("migrations/{$filename}");

            // Check if source exists
            if (!File::exists($source)) {
                $this->error('Upgrade migration source file not found!');
                $this->line("Expected location: {$source}");
                return;
            }

            // Copy the file
            File::copy($source, $destination);

            $this->line('');
            $this->info('✓ Upgrade migration published successfully!');
            $this->line('');
            $this->line("Migration file: database/migrations/{$filename}");
            $this->line('');

            // Display next steps with critical warning
            $this->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->warn('⚠  CRITICAL: BACKUP YOUR DATABASE FIRST!  ⚠');
            $this->warn('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line('');
            $this->info('Next steps:');
            $this->line('');
            $this->line('  1. Backup your database (CRITICAL!)');
            $this->line('  2. Run: php artisan migrate');
            $this->line('  3. Verify your data integrity');
            $this->line('  4. Test your payment functionality');
            $this->line('');
            $this->info('If something goes wrong:');
            $this->line('');
            $this->line('  • Rollback: php artisan migrate:rollback');
            $this->line('  • See UPGRADE.md for detailed instructions');
            $this->line('');

        } catch (\Exception $e) {
            $this->error('Failed to publish upgrade migration!');
            $this->line('');
            $this->error($e->getMessage());
            $this->line('');
        }
    }
}
