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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->constrained('users', 'crm_id');
            $table->unsignedInteger('manager_id')->constrained('users', 'crm_id');
            // $table->unsignedInteger('new_user_id')->constrained('users');
            // $table->unsignedInteger('new_manager_id')->constrained('users');
            // $table->unsignedInteger('crm_id')->constrained('bx_crms');
            $table->foreignId('reason_id')->constrained();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->boolean('active')->default(true);
            $table->string('anydesk', 13)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
