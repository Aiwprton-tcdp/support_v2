<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bx_crms', function (Blueprint $table) {
            $table->string('app_domain', 100)->after('domain')->nullable();
            $table->unsignedInteger('marketplace_id')->after('app_domain')->nullable();
            $table->string('webhook_id', 50)->after('marketplace_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bx_crms', function (Blueprint $table) {
            $table->dropColumn('app_domain');
            $table->dropColumn('marketplace_id');
            $table->dropColumn('webhook_id');
        });
    }
};