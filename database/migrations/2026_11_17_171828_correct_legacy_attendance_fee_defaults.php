<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('attendances')->update(['fee_due_amount' => 0]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('fee_due_amount')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedInteger('fee_due_amount')->default(2000)->change();
        });
    }
};
