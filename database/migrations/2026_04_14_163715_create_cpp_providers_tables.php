<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpp_providers', function (Blueprint $table) {
            $table->id();
            $table->string('hcode', 20)->unique();
            $table->text('name');
            $table->string('registration_type', 255)->nullable();
            $table->string('affiliation', 100)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('website', 500)->nullable();
            $table->string('service_plan_level', 100)->nullable();
            $table->text('operating_hours')->nullable();

            $table->string('address_no', 100)->nullable();
            $table->string('moo', 50)->nullable();
            $table->string('soi', 200)->nullable();
            $table->string('road', 200)->nullable();
            $table->string('subdistrict', 200)->nullable();
            $table->string('district', 200)->nullable();
            $table->string('province', 200)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('local_admin_area', 300)->nullable();

            $table->string('uc_phone', 100)->nullable();
            $table->string('quality_phone', 100)->nullable();
            $table->string('referral_phone', 100)->nullable();
            $table->string('uc_fax', 100)->nullable();
            $table->string('uc_email', 200)->nullable();
            $table->string('doc_email', 200)->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->date('cpp_last_updated')->nullable();
            $table->mediumText('raw_html')->nullable();
            $table->timestamp('scraped_at')->nullable();

            $table->timestamps();

            $table->index('province');
            $table->index('affiliation');
            $table->index('registration_type');
            $table->index('district');
        });

        Schema::create('cpp_provider_network_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpp_provider_id')->constrained('cpp_providers')->cascadeOnDelete();
            $table->string('type_code', 50);
            $table->text('type_name');
            $table->timestamps();

            $table->index('type_code');
            $table->index(['cpp_provider_id', 'type_code']);
        });

        Schema::create('cpp_provider_coordinators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cpp_provider_id')->constrained('cpp_providers')->cascadeOnDelete();
            $table->string('name', 300)->nullable();
            $table->string('email', 300)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('mobile', 100)->nullable();
            $table->string('fax', 100)->nullable();
            $table->text('department')->nullable();
            $table->timestamps();
        });

        Schema::create('cpp_scrape_queue', function (Blueprint $table) {
            $table->id();
            $table->string('hcode', 20)->unique();
            $table->string('status', 20)->default('pending');
            $table->string('phase', 20)->default('profile');
            $table->string('claimed_by', 50)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'phase']);
            $table->index('claimed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpp_scrape_queue');
        Schema::dropIfExists('cpp_provider_coordinators');
        Schema::dropIfExists('cpp_provider_network_types');
        Schema::dropIfExists('cpp_providers');
    }
};
