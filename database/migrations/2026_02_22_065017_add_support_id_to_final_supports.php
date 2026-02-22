<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('final_supports', function (Blueprint $table) {
            $table->unsignedBigInteger('support_id')->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('final_supports', function (Blueprint $table) {
            $table->dropColumn('support_id');
        });
    }
};
