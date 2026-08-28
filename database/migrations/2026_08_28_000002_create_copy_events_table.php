<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_events', function (Blueprint $table) {
            $table->id();
            $table->morphs('copyable');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['copyable_type', 'copyable_id', 'ip_hash'], 'copy_events_copyable_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_events');
    }
};
