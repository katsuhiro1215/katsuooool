<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_starter_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('industry');
            $table->string('prefecture');
            $table->string('target_age_range')->nullable();
            $table->string('target_gender')->nullable();
            $table->string('purpose')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['industry', 'prefecture']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_starter_submissions');
    }
};
