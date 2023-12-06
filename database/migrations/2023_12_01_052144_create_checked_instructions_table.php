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
        Schema::create('checked_instructions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('instruction_id')->constrained('instructions')->nullOnDelete();
            // $table->foreignId('instruction_id')->constrained();
            $table->unsignedInteger('ticket_id')->constrained('tickets')->nullOnDelete();
            // $table->foreignId('ticket_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checked_instructions');
    }
};
