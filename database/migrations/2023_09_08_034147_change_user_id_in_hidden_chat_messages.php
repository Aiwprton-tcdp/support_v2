<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hidden_chat_messages', function (Blueprint $table) {
            $table->unsignedInteger('new_user_id')->after('user_crm_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hidden_chat_messages', function (Blueprint $table) {
            $table->dropColumn('new_user_id');
        });
    }
};
