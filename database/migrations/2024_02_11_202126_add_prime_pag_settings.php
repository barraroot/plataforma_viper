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
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('primepag_is_enable')->default(true);
        });

        Schema::table('gateways', function (Blueprint $table) {
            $table->string('primepag_client_id')->nullable();
            $table->string('primepag_client_secret')->nullable();
            $table->string('primepag_webhook')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('primepag_is_enable');
        });

        Schema::table('gateways', function (Blueprint $table) {
            $table->dropColumn('primepag_client_id');
            $table->dropColumn('primepag_client_secret');
            $table->dropColumn('primepag_webhook');
        });
    }
};
