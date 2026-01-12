<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Iyzico\IyzipayLaravel\Enums\TransactionStatus;

return new class extends Migration
{
    /**
     * Helper to get the dynamic billable table name.
     */
    protected function getBillableTableName(): string
    {
        $modelClass = config('iyzipay.billableModel', 'App\Models\User');
        return (new $modelClass)->getTable();
    }

    public function up(): void
    {
        // Safety check: If credit_cards table doesn't exist, this is a fresh install
        if (!Schema::hasTable('credit_cards')) {
            echo "Fresh installation detected. Skipping upgrade migration.\n";
            return;
        }

        // Check if already upgraded (detect v2 marker column)
        if (Schema::hasColumn('transactions', 'type')) {
            echo "Already on v2.0. Skipping upgrade migration.\n";
            return;
        }

        echo "Upgrading iyzipay-laravel from v1.x to v2.0...\n";

        // ===== UPGRADE BILLABLE TABLE =====
        $billableTable = $this->getBillableTableName();

        if (Schema::hasTable($billableTable)) {
            Schema::table($billableTable, function (Blueprint $table) {
                // Convert bill_fields from longText to json
                $table->json('bill_fields')->nullable()->change();

                // Add index to iyzipay_key if not exists
                if (!$this->hasIndex($this->getBillableTableName(), 'iyzipay_key')) {
                    $table->index('iyzipay_key');
                }
            });
            echo "Upgraded billable table\n";
        }

        // ===== UPGRADE CREDIT_CARDS TABLE =====
        Schema::table('credit_cards', function (Blueprint $table) {
            // Upgrade ID to bigint
            $table->id()->change();

            // Add new columns if they don't exist
            if (!Schema::hasColumn('credit_cards', 'last_four')) {
                $table->string('last_four', 4)->nullable()->after('bank');
            }

            if (!Schema::hasColumn('credit_cards', 'association')) {
                $table->string('association')->nullable()->after('last_four');
            }
        });
        echo "Upgraded credit_cards table\n";

        // ===== UPGRADE SUBSCRIPTIONS TABLE =====
        Schema::table('subscriptions', function (Blueprint $table) {
            // Upgrade ID to bigint
            $table->id()->change();

            // Change amount from double to decimal
            $table->decimal('next_charge_amount', 15, 2)->default(0)->change();

            // Convert plan from longText to json
            $table->json('plan')->change();
        });
        echo "Upgraded subscriptions table\n";

        // ===== UPGRADE TRANSACTIONS TABLE =====

        // Step 1: Drop foreign keys before changing column types
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropForeign(['subscription_id']);
        });

        // Step 2: Change column types and add new columns
        Schema::table('transactions', function (Blueprint $table) {
            // Upgrade ID to bigint
            $table->id()->change();

            // Upgrade foreign key columns to bigint
            $table->unsignedBigInteger('credit_card_id')->nullable()->change();
            $table->unsignedBigInteger('subscription_id')->nullable()->change();

            // Change amount from double to decimal
            $table->decimal('amount', 15, 2)->change();

            // Convert data columns from longText to json
            $table->json('products')->nullable()->change();
            $table->json('refunds')->nullable()->change();
            $table->json('error')->nullable()->change();

            // Add new v2 columns
            if (!Schema::hasColumn('transactions', 'type')) {
                $table->string('type')->default('charge')->index()->after('id');
            }

            if (!Schema::hasColumn('transactions', 'installment')) {
                $table->unsignedTinyInteger('installment')->default(1)->after('currency');
            }

            if (!Schema::hasColumn('transactions', 'iyzipay_transaction_id')) {
                $table->string('iyzipay_transaction_id')->nullable()->after('iyzipay_key');
            }

            if (!Schema::hasColumn('transactions', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('voided_at');
            }
        });

        // Step 3: Migrate status from boolean to string enum with data preservation
        // First, change column type to string
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status')->nullable()->change();
        });

        // Then migrate the data
        DB::table('transactions')
            ->where('status', '1')
            ->orWhere('status', 1)
            ->update(['status' => TransactionStatus::SUCCESS->value]);

        DB::table('transactions')
            ->where(function ($query) {
                $query->where('status', '0')
                    ->orWhere('status', 0)
                    ->orWhereNull('status');
            })
            ->whereNull('voided_at')
            ->update(['status' => TransactionStatus::FAILED->value]);

        DB::table('transactions')
            ->whereNotNull('voided_at')
            ->update(['status' => TransactionStatus::VOIDED->value]);

        // Step 4: Re-add foreign keys with proper cascades
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('credit_card_id')
                ->references('id')
                ->on('credit_cards')
                ->nullOnDelete();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->nullOnDelete();
        });

        echo "Upgraded transactions table\n";
        echo "\n Upgrade completed successfully!\n";
    }

    public function down(): void
    {
        echo "Rolling back v2.0 to v1.x...\n";

        if (!Schema::hasTable('credit_cards')) {
            return;
        }

        // ===== ROLLBACK TRANSACTIONS TABLE =====

        // Step 1: Drop foreign keys
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropForeign(['subscription_id']);
        });

        // Step 2: Migrate status enum back to boolean
        DB::table('transactions')
            ->where('status', TransactionStatus::SUCCESS->value)
            ->update(['status' => '1']);

        DB::table('transactions')
            ->where('status', '!=', '1')
            ->update(['status' => '0']);

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('status')->nullable()->change();
        });

        // Step 3: Remove v2 columns and revert types
        Schema::table('transactions', function (Blueprint $table) {
            // Remove v2 columns
            if (Schema::hasColumn('transactions', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('transactions', 'installment')) {
                $table->dropColumn('installment');
            }
            if (Schema::hasColumn('transactions', 'iyzipay_transaction_id')) {
                $table->dropColumn('iyzipay_transaction_id');
            }
            if (Schema::hasColumn('transactions', 'refunded_at')) {
                $table->dropColumn('refunded_at');
            }

            // Revert column types
            $table->unsignedInteger('id', true)->change();
            $table->unsignedInteger('credit_card_id')->nullable()->change();
            $table->unsignedInteger('subscription_id')->nullable()->change();
            $table->double('amount')->change();
            $table->longText('products')->nullable()->change();
            $table->longText('refunds')->nullable()->change();
            $table->longText('error')->nullable()->change();
        });

        // Step 4: Re-add foreign keys
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('credit_card_id')->references('id')->on('credit_cards');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
        });

        // ===== ROLLBACK SUBSCRIPTIONS TABLE =====
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('id', true)->change();
            $table->double('next_charge_amount')->default(0)->change();
            $table->longText('plan')->change();
        });

        // ===== ROLLBACK CREDIT_CARDS TABLE =====
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->unsignedInteger('id', true)->change();

            if (Schema::hasColumn('credit_cards', 'last_four')) {
                $table->dropColumn('last_four');
            }
            if (Schema::hasColumn('credit_cards', 'association')) {
                $table->dropColumn('association');
            }
        });

        // ===== ROLLBACK BILLABLE TABLE =====
        $billableTable = $this->getBillableTableName();

        if (Schema::hasTable($billableTable)) {
            Schema::table($billableTable, function (Blueprint $table) {
                $table->longText('bill_fields')->nullable()->change();

                if ($this->hasIndex($this->getBillableTableName(), 'iyzipay_key')) {
                    $table->dropIndex(['iyzipay_key']);
                }
            });
        }

        echo "Rollback completed.\n";
    }

    /**
     * Check if a table has a specific index.
     */
    protected function hasIndex(string $table, string $column): bool
    {
        $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Column_name = ?", [$column]);
        return count($indexes) > 0;
    }
};
