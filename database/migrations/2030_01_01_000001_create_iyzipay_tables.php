<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        // Add billable fields to the user (or configured billable) table
        Schema::table($this->getBillableTableName(), function (Blueprint $table) {
            $table->json('bill_fields')->nullable();
            $table->string('iyzipay_key')->nullable()->index();
        });

        // Create credit_cards table
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->string('billable_id')->index();
            $table->string('alias', 100);
            $table->string('number', 10);
            $table->string('token');
            $table->string('bank')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->string('association')->nullable();
            $table->unique(['billable_id', 'token']);
            $table->timestamps();
            $table->softDeletes();
        });

        // Create subscriptions table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('billable_id')->index();
            $table->decimal('next_charge_amount', 15, 2)->default(0);
            $table->string('currency')->default('TRY');
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->json('plan');
            $table->timestamps();
            $table->softDeletes();
        });

        // Create transactions table
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('charge')->index();
            $table->string('billable_id')->index();
            $table->unsignedBigInteger('credit_card_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TRY');
            $table->unsignedTinyInteger('installment')->default(1);
            $table->json('products')->nullable();
            $table->string('iyzipay_key')->nullable();
            $table->string('iyzipay_transaction_id')->nullable();
            $table->json('refunds')->nullable();
            $table->string('status')->nullable();
            $table->json('error')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys with null on delete
            $table->foreign('credit_card_id')
                ->references('id')
                ->on('credit_cards')
                ->nullOnDelete();

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('credit_cards');

        Schema::table($this->getBillableTableName(), function (Blueprint $table) {
            $table->dropColumn(['bill_fields', 'iyzipay_key']);
        });
    }
};
