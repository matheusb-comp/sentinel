<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The tenant is reached through this, so there is no company_id.
            $table->foreignId('device_id')->constrained()->restrictOnDelete();

            // The name the device uses for this sensor in every payload.
            $table->string('key');

            // What the device says this sensor is.
            $table->string('description')->nullable();

            // Custom label used for display, defaults to the key.
            $table->string('label')->nullable();

            // Optional type for the "value units"
            $table->string('unit')->nullable();

            // Seconds. Null means absence is not watched for this sensor.
            $table->integer('expected_interval')->nullable();

            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['device_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
