<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            // The tenant key is a plain bigint. It is the cheapest possible
            // foreign key on every tenant table, and the RLS policy casts the
            // session variable to bigint rather than casting this column to
            // text, which is what keeps the index usable.
            $table->id();

            // The only public identifier. The numeric id never appears in a
            // URL or in a serialized response, so it is not enumerable.
            $table->string('slug', 32)->unique();

            $table->string('name');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
