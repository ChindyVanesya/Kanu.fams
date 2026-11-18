<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('private_training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('training_date')->index();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('price_per_member')->default(125000);
            $table->string('status', 20)->default('open')->index();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('private_training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->timestampTz('attended_at')->nullable();
            $table->timestamps();
            $table->unique(['private_training_session_id', 'member_id']);
            $table->index(['member_id', 'private_training_session_id']);
        });

        Schema::create('private_training_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_training_participant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestampTz('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['private_training_participant_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('private_training_payments');
        Schema::dropIfExists('private_training_participants');
        Schema::dropIfExists('private_training_sessions');
    }
};
