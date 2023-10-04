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
        Schema::create('resolved_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('old_ticket_id')->unique();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('manager_id');
            // $table->unsignedInteger('new_user_id');
            // $table->unsignedInteger('new_manager_id');
            // $table->unsignedInteger('crm_id')->constrained('bx_crms');
            $table->unsignedInteger('reason_id');
            // $table->unsignedInteger('user_id')->constrained('users', 'crm_id');
            // $table->unsignedInteger('manager_id')->constrained('users', 'crm_id');
            // $table->foreignId('reason_id')->constrained();
            $table->unsignedTinyInteger('weight');
            $table->enum('mark', [0,1,2,3]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resolved_tickets');
    }
};
