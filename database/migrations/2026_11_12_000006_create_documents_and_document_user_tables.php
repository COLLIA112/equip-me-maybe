<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('type'); // safety_certificate | inspection | manual
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });

        // Compliance tracking pivot.
        Schema::create('document_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps(); // needed by withTimestamps()
            $table->primary(['user_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_user');
        Schema::dropIfExists('documents');
    }
};
