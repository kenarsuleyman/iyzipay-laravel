<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->unsignedInteger('id', true)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('credit_card_id')
                ->references('id')
                ->on('credit_cards');
        });
    }
};
