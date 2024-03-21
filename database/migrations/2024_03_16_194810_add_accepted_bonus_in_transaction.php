<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean("accepted_bonus")->nullable();
        });
        Schema::table('deposits', function (Blueprint $table) {
            $table->boolean("accepted_bonus")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn("accepted_bonus");
        });
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn("accepted_bonus");
        });
    }
};
