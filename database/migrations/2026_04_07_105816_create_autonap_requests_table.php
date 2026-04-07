<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autonap_requests', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 40)->unique();
            $table->string('site', 50)->index();
            $table->string('form_type', 10); // RR, VCT
            $table->string('method', 20)->default('ThaiID');
            $table->string('fy', 10)->nullable();
            $table->string('nap_user')->nullable(); // NAP login display name
            $table->string('nap_site')->nullable(); // NAP site/org name
            $table->integer('total')->default(0);
            $table->integer('success')->default(0);
            $table->integer('failed')->default(0);
            $table->string('status', 20)->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autonap_requests');
    }
};
