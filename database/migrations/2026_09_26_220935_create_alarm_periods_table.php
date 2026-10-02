<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('sensor_id')->constrained()->restrictOnDelete();

            // Points to the monitor that watches the sensor.
            // Indexed to be easy to find when a monitor is deleted.
            $table->foreignId('alarm_monitor_id')->nullable()->index()->constrained()->nullOnDelete();

            // When the value crossed the threshold and when the alarm started.
            $table->timestampTz('breached_at');
            $table->timestampTz('started_at');

            // Null while the period is open.
            $table->timestampTz('ended_at')->nullable();
            $table->string('ended_reason')->nullable();

            // The value of the reading that met the duration.
            $table->double('value');

            // Snapshot of the alarm rule at the start of the period.
            $table->string('type');
            $table->string('label')->nullable();
            $table->string('direction')->nullable();
            $table->double('threshold')->nullable();
            $table->integer('trigger_after');
            $table->integer('clear_after');

            $table->timestampsTz();

            // Read paths by time: Periods of a company and periods of a sensor.
            $table->index(['company_id', 'started_at']);
            $table->index(['sensor_id', 'started_at']);
        });

        // Partial index since a monitor can have only one open period.
        // Statement since Blueprint does not have support (works on SQLite).
        DB::statement(
            'CREATE UNIQUE INDEX alarm_periods_open_unique ON alarm_periods (alarm_monitor_id) WHERE ended_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_periods');
    }
};
