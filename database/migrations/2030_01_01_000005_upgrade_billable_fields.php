<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function getTableName(): string
    {
        $modelClass = config('iyzipay.billableModel', 'App\Models\User');
        return (new $modelClass)->getTable();
    }

    public function up(): void
    {
        Schema::table($this->getTableName(), function (Blueprint $table) {
            $table->json('bill_fields')->nullable()->change();
            $table->index('iyzipay_key');
        });
    }

    public function down(): void
    {
        Schema::table($this->getTableName(), function (Blueprint $table) {
            $table->longText('bill_fields')->nullable()->change();
            $table->dropIndex(['iyzipay_key']);
        });
    }
};
