<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            // Its own primary key, not a composite one: role assignments and
            // tenant records reference the membership rather than the User.
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Restricted so that deleting a user cannot erase the memberships
            // that carry their history.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Access is revoked by flipping this, not by deleting the row, so
            // that the history of who did what inside the company survives.
            $table->boolean('active')->default(true);

            $table->timestampsTz();

            $table->unique(['company_id', 'user_id']);

            // Serves the central-context lookup of a user's companies. The
            // composite unique index above leads with company_id and cannot
            // answer a query filtered only by user_id.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
