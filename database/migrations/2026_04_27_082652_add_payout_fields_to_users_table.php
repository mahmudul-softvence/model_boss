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
        Schema::table('users', function (Blueprint $table) {
            $table->string('paypal_email')->nullable()->after('stripe_onboarding_complete');
            $table->string('bitpay_wallet')->nullable()->after('paypal_email');
            $table->string('moncash_phone')->nullable()->after('bitpay_wallet');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['paypal_email', 'bitpay_wallet', 'moncash_phone']);
        });
    }
};
