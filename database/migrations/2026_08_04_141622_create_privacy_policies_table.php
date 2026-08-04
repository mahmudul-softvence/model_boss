<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_policies', function (Blueprint $table) {
            $table->string('title')->default('Privacy Policy');
            $table->longText('content');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_policies');
    }
};
