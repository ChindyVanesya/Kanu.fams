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
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedInteger('contribution_amount')->default(2000);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('fee_due_amount')->default(0);
        });

        Schema::create('registration_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('amount');
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'year']);
        });

        Schema::create('attendance_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['attendance_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_fee_payments');
        Schema::dropIfExists('registration_fee_payments');
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('fee_due_amount');
        });
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn('contribution_amount');
        });
    }
};
