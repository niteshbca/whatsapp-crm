<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->unsignedBigInteger('whatsapp_account_id')->nullable()->after('company_id');
            $table->string('sender_number')->nullable()->after('whatsapp_account_id');

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('whatsapp_account_id')->references('id')->on('whatsapp_accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['whatsapp_account_id']);
            $table->dropColumn(['company_id', 'whatsapp_account_id', 'sender_number']);
        });
    }
};
