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
        Schema::table('user_social_accounts', function (Blueprint $table) {
            $table->unique(['provider', 'provider_id'], 'unique_provider_provider_id');
            $table->unique(['user_id', 'provider'], 'unique_user_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_social_accounts', function (Blueprint $table) {
            $table->dropUnique('unique_provider_provider_id');
            $table->dropUnique('unique_user_provider');
        });
    }
};
