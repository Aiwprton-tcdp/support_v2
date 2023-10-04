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
        Schema::create('hidden_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->unsignedInteger('user_crm_id');
            // $table->unsignedInteger('new_user_id');
            $table->unsignedInteger('ticket_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hidden_chat_messages');
    }
};
