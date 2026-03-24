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
        Schema::create('organizations', function (Blueprint $header) {
            $header->id();
            $header->string('name');
            $header->string('hcode')->nullable();
            $header->boolean('verified')->default(false);
            $header->string('subscription')->default('free');
            $header->boolean('api_enabled')->default(false);
            $header->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
