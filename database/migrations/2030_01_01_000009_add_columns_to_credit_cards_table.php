<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
       Schema::table('credit_cards', function (Blueprint $table) {
           
           $table->string('last_four', 4)->after('number')->nullable();
           $table->string('association')->after('number')->nullable();
       });
    }

    public function down(): void

    {
        Schema::table('credit_cards', function (Blueprint $table) {

            $table->dropColumn('last_four');
            $table->dropColumn('association');
        });
    }
};
