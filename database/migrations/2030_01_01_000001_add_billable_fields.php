<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Helper to get the dynamic table name.
     */
    protected function getTableName(): string
    {
        $modelClass = config('iyzipay.billableModel', 'App\Models\User');
        return (new $modelClass)->getTable();
    }

    public function up(): void
    {
        Schema::table($this->getTableName(), function (Blueprint $table) {
            $table->longText('bill_fields')->nullable();
            $table->string('iyzipay_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->getTableName(), function (Blueprint $table) {
            $table->dropColumn(['bill_fields', 'iyzipay_key']);
        });
    }
};
