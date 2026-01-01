<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropForeign(['subscription_id']);

            $table->unsignedBigInteger('id', true)->change();

            $table->unsignedBigInteger('credit_card_id')->nullable()->change();
            $table->unsignedBigInteger('subscription_id')->nullable()->change();

            $table->decimal('amount', 15, 2)->change();
            $table->json('products')->nullable()->change();
            $table->json('refunds')->nullable()->change();
            $table->json('error')->nullable()->change();

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
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropForeign(['subscription_id']);

            $table->unsignedInteger('id', true)->change();
            $table->unsignedInteger('credit_card_id')->nullable()->change();
            $table->unsignedInteger('subscription_id')->nullable()->change();
            $table->double('amount')->change();
            $table->longText('products')->nullable()->change();
            $table->longText('refunds')->nullable()->change();
            $table->longText('error')->nullable()->change();

            $table->foreign('credit_card_id')->references('id')->on('credit_cards');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
        });
    }
};
