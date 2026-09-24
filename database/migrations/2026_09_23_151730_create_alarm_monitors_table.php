<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_monitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('alarm_rule_id')->constrained()->cascadeOnDelete();

            // The sensor being watched by the rule.
            $table->foreignId('sensor_id')->index()->constrained()->cascadeOnDelete();

            $table->string('status');
            $table->timestampTz('status_since');

            // When the value crossed the threshold, on the device clock itself.
            // Null when nothing is in flight.
            $table->timestampTz('pending_since')->nullable();

            // Readings up to here have been considered, never past our own clock.
            $table->timestampTz('evaluated_through')->nullable();

            $table->double('last_value')->nullable();

            // When the clock has to look at this monitor without a new reading.
            $table->timestampTz('next_check_at')->nullable()->index();

            $table->timestampsTz();

            $table->unique(['alarm_rule_id', 'sensor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_monitors');
    }
};
