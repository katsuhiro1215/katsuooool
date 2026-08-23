<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_events', function (Blueprint $table) {
            $table->index(['tool_slug', 'event_type', 'created_at'], 'tool_events_slug_type_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('tool_events', function (Blueprint $table) {
            $table->dropIndex('tool_events_slug_type_created_index');
        });
    }
};
