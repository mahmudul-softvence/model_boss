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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('address')->nullable()->after('phone_number');
            $table->string('zip_code')->nullable()->after('address');
            $table->string('state')->nullable()->after('zip_code');
        });

        DB::table('users')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $name = preg_replace('/\s+/', ' ', trim((string) $user->name));

                    if ($name === '') {
                        continue;
                    }

                    $parts = explode(' ', $name);
                    $firstName = array_shift($parts);
                    $lastName = count($parts) > 0 ? array_pop($parts) : null;
                    $middleName = count($parts) > 0 ? implode(' ', $parts) : null;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'first_name' => $firstName,
                            'middle_name' => $middleName,
                            'last_name' => $lastName,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'address',
                'zip_code',
                'state',
            ]);
        });
    }
};
