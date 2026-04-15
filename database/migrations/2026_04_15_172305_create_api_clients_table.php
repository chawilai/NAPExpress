<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200); // human-readable label e.g. "ACTSE Clinic production"
            $table->string('client_id', 64)->unique();
            $table->string('client_secret_hash');
            $table->string('client_secret_prefix', 32); // first chars for display (e.g. "sk_live_abc...")
            $table->json('scopes')->nullable(); // future: limit what this client can do
            $table->string('allowed_ips', 500)->nullable(); // comma-separated IP whitelist
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
