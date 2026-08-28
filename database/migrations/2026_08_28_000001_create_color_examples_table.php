<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_examples', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['palette', 'gradient', 'scheme']);
            $table->string('title');
            $table->json('colors');
            $table->unsignedSmallInteger('angle')->nullable();
            $table->unsignedInteger('copy_count')->default(0);
            $table->timestamps();

            $table->index(['type', 'copy_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_examples');
    }
};
