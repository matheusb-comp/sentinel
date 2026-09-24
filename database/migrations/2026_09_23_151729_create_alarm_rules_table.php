<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // A rule names no sensor: it is the configuration, and an
            // alarm_monitors row is what applies it to one.
            $table->foreignId('company_id')->index()->constrained()->restrictOnDelete();

            // Optional custom label used for display.
            $table->string('label')->nullable();

            $table->string('type');

            // Null on a rule that watches silence instead of a value.
            $table->string('direction')->nullable();
            $table->double('threshold')->nullable();

            // Seconds to wait before triggering an alarm after threshold cross.
            $table->integer('trigger_after');
            $table->integer('clear_after')->default(0);

            // Seconds. Null falls back to config('alarms.max_reading_age').
            $table->integer('max_reading_age')->nullable();

            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_rules');
    }
};
