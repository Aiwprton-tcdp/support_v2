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
        Schema::create('bx_crms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('acronym', 30)->unique();
            $table->string('domain', 100)->unique();
            // $table->string('app_domain', 100)->unique();
            // $table->unsignedInteger('marketplace_id');
            // $table->string('webhook_id', 50);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bx_crms');
    }
};
