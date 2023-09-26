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
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('new_user_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('new_manager_id')->nullable()->after('new_user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('new_user_id');
            $table->dropColumn('new_manager_id');
        });
    }
};