<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_events', function (Blueprint $table) {
            $table->id();
            $table->string('tool_slug')->index();
            $table->string('event_type');
            $table->unsignedInteger('file_count')->nullable();
            $table->unsignedBigInteger('input_bytes')->nullable();
            $table->unsignedBigInteger('output_bytes')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_events');
    }
};
