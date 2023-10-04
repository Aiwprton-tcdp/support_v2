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
        Schema::table('resolved_tickets', function (Blueprint $table) {
            $table->unsignedInteger('crm_id')->default(1)->after('new_manager_id')->constrained('bx_crms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resolved_tickets', function (Blueprint $table) {
            $table->dropColumn('crm_id');
        });
    }
};