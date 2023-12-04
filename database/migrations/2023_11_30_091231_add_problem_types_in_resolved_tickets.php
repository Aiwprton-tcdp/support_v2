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
            $table->boolean('incompetence')->default(false)->after('mark');
            $table->boolean('technical_problem')->default(false)->after('incompetence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resolved_tickets', function (Blueprint $table) {
            $table->dropColumn('incompetence');
            $table->dropColumn('technical_problem');
        });
    }
};
