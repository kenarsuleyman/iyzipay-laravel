<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unsignedBigInteger('id', true)->change();
                $table->decimal('next_charge_amount', 15, 2)->default(0)->change();
                $table->json('plan')->change();
                $table->string('currency', 3)->default('TRY')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unsignedInteger('id', true)->change();
                $table->double('next_charge_amount')->default(0)->change();
                $table->longText('plan')->change();
                $table->string('currency')->default('try')->change();
            });
        }
    }
};
