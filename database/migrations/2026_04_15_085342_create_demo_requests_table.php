<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('org_name', 300);
            $table->string('province', 100)->nullable();
            $table->json('work_types');
            $table->unsignedInteger('cases_per_month')->nullable();
            $table->string('contact_name', 200);
            $table->string('contact_phone', 50);
            $table->string('contact_email', 200)->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
