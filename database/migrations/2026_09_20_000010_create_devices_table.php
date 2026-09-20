<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();

            // Name provided by the client, makes registration idempotent.
            $table->string('key');

            // Custom label used for display, defaults to the key.
            $table->string('label')->nullable();

            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
