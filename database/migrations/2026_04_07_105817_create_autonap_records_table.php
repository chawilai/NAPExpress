<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autonap_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('autonap_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq'); // ลำดับที่ใน job (1, 2, 3...)
            $table->string('uic', 20)->nullable();
            $table->string('pid_masked', 20)->nullable(); // xxxx1234
            $table->string('form_type', 10); // RR, VCT
            $table->boolean('success')->default(false);
            $table->string('nap_code', 50)->nullable(); // RR code or VCT code
            $table->string('nap_lab_code', 50)->nullable(); // ANTIHIV code
            $table->string('hiv_result', 20)->nullable(); // Positive, Negative, Inconclusive
            $table->string('comment')->nullable(); // error message or note
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autonap_records');
    }
};
